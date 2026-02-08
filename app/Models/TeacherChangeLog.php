<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherChangeLog extends Model
{
    use HasFactory;

    protected $table = 'teacher_change_log';

    public $timestamps = false;

    protected $fillable = [
        'teacher_id',
        'user_id',
        'change_type',
        'old_value',
        'new_value',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(TeacherArchive::class, 'teacher_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
