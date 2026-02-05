<?php

namespace App\Notifications;

use App\Models\BugReport;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BugReportNotification extends Notification
{
    use Queueable;

    public function __construct(
        public BugReport $bugReport,
        public string $type,
        public User $actor
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $data = [
            'type' => $this->type,
            'bug_report_id' => $this->bugReport->id,
            'bug_report_title' => $this->bugReport->title,
            'actor_name' => $this->actor->name,
        ];

        if ($this->type === 'bug_report_status_changed') {
            $data['new_status'] = $this->bugReport->status;
            $data['status_label'] = BugReport::getStatuses()[$this->bugReport->status] ?? $this->bugReport->status;
            $data['message'] = "A \"{$this->bugReport->title}\" hibajelentés státusza megváltozott: {$data['status_label']}";
        }

        if ($this->type === 'bug_report_comment') {
            $data['message'] = "{$this->actor->name} hozzászólt a \"{$this->bugReport->title}\" hibajelentéshez.";
        }

        return $data;
    }
}
