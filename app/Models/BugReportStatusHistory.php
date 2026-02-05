<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BugReportStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'bug_report_status_history';

    protected $fillable = [
        'bug_report_id',
        'changed_by',
        'old_status',
        'new_status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function bugReport(): BelongsTo
    {
        return $this->belongsTo(BugReport::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
