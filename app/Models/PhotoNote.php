<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotoNote extends Model
{
    protected $fillable = [
        'photo_id',
        'user_id',
        'text',
    ];
}
