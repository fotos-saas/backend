<?php

namespace App\Events;

use App\Models\Photo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhotoUploaded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Photo $photo) {}
}
