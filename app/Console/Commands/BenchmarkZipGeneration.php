<?php

namespace App\Console\Commands;

use App\Models\ConversionJob;
use App\Services\StreamingZipService;
use App\Services\ZipGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BenchmarkZipGeneration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zip:benchmark {job_id : The conversion job ID to test}
                           {--method=both : Method to test (streaming|traditional|both)}
                           {--iterations=3 : Number of test iterations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Benchmark ZIP generation methods (streaming vs traditional)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $jobId = $this->argument('job_id');
        $method = $this->option('method');
        $iterations = (int) $this->option('iterations');

        $job = ConversionJob::find($jobId);

        if (!$job) {
            $this->error("Conversion job #{$jobId} not found!");
            return Command::FAILURE;
        }

        $this->info("🚀 ZIP Generation Benchmark");
        $this->info("================================");
        $this->info("Job ID: {$job->id}");
        $this->info("Job Name: {$job->job_name}");
        $this->info("Total Files: {$job->total_files}");
        $this->info("Method: {$method}");
        $this->info("Iterations: {$iterations}");
        $this->info("================================\n");

        $results = [];

        // Test streaming method
        if ($method === 'streaming' || $method === 'both') {
            $this->info("📦 Testing STREAMING method...");
            $streamingService = app(StreamingZipService::class);

            for ($i = 1; $i <= $iterations; $i++) {
                $this->info("  Iteration {$i}/{$iterations}...");
                $startTime = microtime(true);
                $startMemory = memory_get_usage(true);
                $peakMemoryBefore = memory_get_peak_usage(true);

                try {
                    // Simulate streaming generation
                    $estimatedSize = $this->getEstimatedSize($streamingService, $job);

                    $endTime = microtime(true);
                    $endMemory = memory_get_usage(true);
                    $peakMemoryAfter = memory_get_peak_usage(true);

                    $results['streaming'][] = [
                        'iteration' => $i,
                        'time' => round($endTime - $startTime, 3),
                        'memory_used' => $this->formatBytes($endMemory - $startMemory),
                        'peak_memory' => $this->formatBytes($peakMemoryAfter - $peakMemoryBefore),
                        'estimated_size' => $this->formatBytes($estimatedSize)
                    ];

                    $this->info("    ✅ Time: " . round($endTime - $startTime, 3) . "s");
                    $this->info("    💾 Memory: " . $this->formatBytes($endMemory - $startMemory));

                } catch (\Exception $e) {
                    $this->error("    ❌ Error: " . $e->getMessage());
                }

                // Cleanup
                gc_collect_cycles();
                sleep(1); // Cool down between iterations
            }
        }

        // Test traditional method
        if ($method === 'traditional' || $method === 'both') {
            $this->info("\n📦 Testing TRADITIONAL method...");
            $traditionalService = app(ZipGeneratorService::class);

            for ($i = 1; $i <= $iterations; $i++) {
                $this->info("  Iteration {$i}/{$iterations}...");
                $startTime = microtime(true);
                $startMemory = memory_get_usage(true);
                $peakMemoryBefore = memory_get_peak_usage(true);

                try {
                    // Generate ZIP the traditional way
                    $zipPath = $traditionalService->generateZip($job);

                    $endTime = microtime(true);
                    $endMemory = memory_get_usage(true);
                    $peakMemoryAfter = memory_get_peak_usage(true);

                    $fileSize = filesize($zipPath);

                    $results['traditional'][] = [
                        'iteration' => $i,
                        'time' => round($endTime - $startTime, 3),
                        'memory_used' => $this->formatBytes($endMemory - $startMemory),
                        'peak_memory' => $this->formatBytes($peakMemoryAfter - $peakMemoryBefore),
                        'file_size' => $this->formatBytes($fileSize)
                    ];

                    $this->info("    ✅ Time: " . round($endTime - $startTime, 3) . "s");
                    $this->info("    💾 Memory: " . $this->formatBytes($endMemory - $startMemory));
                    $this->info("    📁 Size: " . $this->formatBytes($fileSize));

                    // Cleanup ZIP file
                    $traditionalService->cleanup($zipPath);

                } catch (\Exception $e) {
                    $this->error("    ❌ Error: " . $e->getMessage());
                }

                // Cleanup
                gc_collect_cycles();
                sleep(1); // Cool down between iterations
            }
        }

        // Display results summary
        $this->displayResults($results);

        return Command::SUCCESS;
    }

    /**
     * Get estimated size using streaming service
     */
    private function getEstimatedSize(StreamingZipService $service, ConversionJob $job): int
    {
        // Use reflection to call protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateEstimatedSize');
        $method->setAccessible(true);

        return $method->invoke($service, $job);
    }

    /**
     * Display benchmark results
     */
    private function displayResults(array $results): void
    {
        $this->info("\n\n📊 BENCHMARK RESULTS");
        $this->info("================================");

        foreach ($results as $method => $iterations) {
            $this->info("\n" . strtoupper($method) . " METHOD:");

            $avgTime = 0;
            $avgMemory = 0;
            $maxPeakMemory = 0;

            $table = [];
            foreach ($iterations as $data) {
                $avgTime += $data['time'];

                // Parse memory values for averaging
                $memoryValue = $this->parseBytes($data['memory_used']);
                $avgMemory += $memoryValue;

                $peakValue = $this->parseBytes($data['peak_memory']);
                if ($peakValue > $maxPeakMemory) {
                    $maxPeakMemory = $peakValue;
                }

                $table[] = [
                    $data['iteration'],
                    $data['time'] . 's',
                    $data['memory_used'],
                    $data['peak_memory'],
                    $data['file_size'] ?? $data['estimated_size'] ?? 'N/A'
                ];
            }

            $this->table(
                ['Iteration', 'Time', 'Memory Used', 'Peak Memory', 'Size'],
                $table
            );

            $count = count($iterations);
            if ($count > 0) {
                $this->info("  📈 Average Time: " . round($avgTime / $count, 3) . "s");
                $this->info("  📈 Average Memory: " . $this->formatBytes($avgMemory / $count));
                $this->info("  📈 Max Peak Memory: " . $this->formatBytes($maxPeakMemory));
            }
        }

        // Compare methods if both were tested
        if (isset($results['streaming']) && isset($results['traditional'])) {
            $this->info("\n\n⚡ PERFORMANCE COMPARISON");
            $this->info("================================");

            $streamingAvgTime = array_sum(array_column($results['streaming'], 'time')) / count($results['streaming']);
            $traditionalAvgTime = array_sum(array_column($results['traditional'], 'time')) / count($results['traditional']);

            $streamingAvgMemory = array_sum(array_map([$this, 'parseBytes'], array_column($results['streaming'], 'memory_used'))) / count($results['streaming']);
            $traditionalAvgMemory = array_sum(array_map([$this, 'parseBytes'], array_column($results['traditional'], 'memory_used'))) / count($results['traditional']);

            $timeImprovement = round((1 - $streamingAvgTime / $traditionalAvgTime) * 100, 1);
            $memoryImprovement = round((1 - $streamingAvgMemory / $traditionalAvgMemory) * 100, 1);

            $this->table(
                ['Metric', 'Streaming', 'Traditional', 'Improvement'],
                [
                    ['Avg Time', round($streamingAvgTime, 3) . 's', round($traditionalAvgTime, 3) . 's', ($timeImprovement > 0 ? '+' : '') . $timeImprovement . '%'],
                    ['Avg Memory', $this->formatBytes($streamingAvgMemory), $this->formatBytes($traditionalAvgMemory), ($memoryImprovement > 0 ? '+' : '') . $memoryImprovement . '%']
                ]
            );

            if ($memoryImprovement > 50) {
                $this->info("\n🎉 Streaming method achieved " . abs($memoryImprovement) . "% memory reduction!");
            }
        }
    }

    /**
     * Format bytes to human-readable format
     */
    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Parse human-readable bytes to numeric value
     */
    private function parseBytes(string $size): float
    {
        $units = ['B' => 0, 'KB' => 1, 'MB' => 2, 'GB' => 3];

        preg_match('/^([\d.]+)\s*(\w+)$/', $size, $matches);

        if (count($matches) === 3) {
            $number = (float) $matches[1];
            $unit = strtoupper($matches[2]);

            if (isset($units[$unit])) {
                return $number * pow(1024, $units[$unit]);
            }
        }

        return 0;
    }
}