<?php

namespace App\Filament\Resources\QueueManagementResource\Pages;

use App\Filament\Resources\QueueManagementResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Page to list and manage queue jobs.
 */
class ListQueueJobs extends ListRecords
{
    protected static string $resource = QueueManagementResource::class;

    /**
     * Get the key name for table records.
     */
    public function getTableRecordKey(mixed $record): string
    {
        return $record['__key'] ?? $record['key'];
    }

    /**
     * Get table records from Redis and failed_jobs table.
     */
    public function getTableRecords(): EloquentCollection
    {
        $pendingJobs = $this->getPendingJobs();
        $failedJobs = $this->getFailedJobs();

        $data = collect($pendingJobs)->merge($failedJobs);

        return new EloquentCollection($data);
    }

    /**
     * Get pending jobs from Redis.
     */
    protected function getPendingJobs(): array
    {
        try {
            $redis = Redis::connection();
            $queues = ['queues:face-recognition', 'queues:default'];
            $jobs = [];

            foreach ($queues as $queueKey) {
                $queueName = str_replace('queues:', '', $queueKey);
                $length = $redis->llen($queueKey);

                for ($i = 0; $i < min($length, 100); $i++) {
                    $job = $redis->lindex($queueKey, $i);
                    if ($job) {
                        $decoded = json_decode($job, true);
                        $jobId = uniqid('pending_');
                        $jobs[] = [
                            '__key' => $jobId,
                            'key' => $jobId,
                            'id' => $jobId,
                            'queue' => $queueName,
                            'status' => 'pending',
                            'payload' => $decoded,
                            'created_at' => now()->subMinutes($length - $i),
                            'attempts' => 0,
                        ];
                    }
                }
            }

            return $jobs;
        } catch (\Exception $e) {
            \Log::error('Failed to fetch pending jobs from Redis', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Get failed jobs from database.
     */
    protected function getFailedJobs(): array
    {
        return DB::table('failed_jobs')
            ->latest('failed_at')
            ->limit(50)
            ->get()
            ->map(fn ($job) => [
                '__key' => 'failed_'.$job->id,
                'key' => 'failed_'.$job->id,
                'id' => 'failed_'.$job->id,
                'failed_jobs_id' => $job->id,
                'queue' => $job->queue ?? 'default',
                'status' => 'failed',
                'payload' => json_decode($job->payload, true),
                'raw_payload' => $job->payload,
                'exception' => $job->exception,
                'failed_at' => $job->failed_at,
                'created_at' => $job->failed_at,
                'attempts' => 3,
            ])
            ->toArray();
    }
}
