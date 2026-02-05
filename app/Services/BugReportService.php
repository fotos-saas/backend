<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BugReport;
use App\Models\BugReportAttachment;
use App\Models\BugReportComment;
use App\Models\BugReportStatusHistory;
use App\Models\User;
use App\Notifications\BugReportNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BugReportService
{
    /**
     * Új hibajelentés létrehozása mellékletekkel.
     */
    public function createReport(User $reporter, array $data, array $files = []): BugReport
    {
        return DB::transaction(function () use ($reporter, $data, $files) {
            $report = BugReport::create([
                'reporter_id' => $reporter->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => BugReport::STATUS_NEW,
                'priority' => $data['priority'] ?? BugReport::PRIORITY_MEDIUM,
            ]);

            // Státusz napló - létrehozás
            BugReportStatusHistory::create([
                'bug_report_id' => $report->id,
                'changed_by' => $reporter->id,
                'old_status' => null,
                'new_status' => BugReport::STATUS_NEW,
            ]);

            // Mellékletek feltöltése
            if (!empty($files)) {
                $this->attachFiles($report, $files);
            }

            return $report->load(['attachments', 'reporter']);
        });
    }

    /**
     * Státusz módosítás + értesítés.
     */
    public function updateStatus(BugReport $report, string $newStatus, User $changedBy, ?string $note = null): BugReport
    {
        $oldStatus = $report->status;

        $report->update(['status' => $newStatus]);

        BugReportStatusHistory::create([
            'bug_report_id' => $report->id,
            'changed_by' => $changedBy->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
        ]);

        // Bejelentő értesítése
        if ($report->reporter_id !== $changedBy->id) {
            $report->reporter->notify(new BugReportNotification(
                $report,
                'bug_report_status_changed',
                $changedBy
            ));
        }

        return $report->fresh();
    }

    /**
     * Prioritás módosítás.
     */
    public function updatePriority(BugReport $report, string $priority): BugReport
    {
        $report->update(['priority' => $priority]);

        return $report->fresh();
    }

    /**
     * Komment hozzáadása + értesítés.
     */
    public function addComment(BugReport $report, User $author, string $content, bool $isInternal = false): BugReportComment
    {
        $comment = BugReportComment::create([
            'bug_report_id' => $report->id,
            'author_id' => $author->id,
            'content' => $content,
            'is_internal' => $isInternal,
        ]);

        // Ha admin kommentelt (nem belső), értesítjük a bejelentőt
        if (!$isInternal && $report->reporter_id !== $author->id) {
            $report->reporter->notify(new BugReportNotification(
                $report,
                'bug_report_comment',
                $author
            ));
        }

        return $comment->load('author');
    }

    /**
     * Olvasatlan hibajelentések száma (SuperAdmin).
     */
    public function getUnreadCount(): int
    {
        return BugReport::unread()->count();
    }

    /**
     * Képmellékletek feltöltése.
     */
    private function attachFiles(BugReport $report, array $files): void
    {
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $storagePath = "bug-reports/{$report->id}/{$filename}";

            Storage::disk('public')->putFileAs(
                "bug-reports/{$report->id}",
                $file,
                $filename
            );

            // Kép méret lekérdezés
            $width = null;
            $height = null;
            if (str_starts_with($file->getMimeType(), 'image/')) {
                $dimensions = getimagesize($file->getPathname());
                if ($dimensions) {
                    [$width, $height] = $dimensions;
                }
            }

            BugReportAttachment::create([
                'bug_report_id' => $report->id,
                'filename' => $filename,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'storage_path' => $storagePath,
                'width' => $width,
                'height' => $height,
            ]);
        }
    }
}
