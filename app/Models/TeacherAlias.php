<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAlias extends Model
{
    use HasFactory;

    protected $table = 'teacher_aliases';

    protected $fillable = [
        'teacher_id',
        'alias_name',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(TeacherArchive::class, 'teacher_id');
    }
}
