<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAlias extends Model
{
    use HasFactory;

    protected $table = 'student_aliases';

    protected $fillable = [
        'student_id',
        'alias_name',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentArchive::class, 'student_id');
    }
}
