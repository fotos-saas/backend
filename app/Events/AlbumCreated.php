<?php

namespace App\Events;

use App\Models\Album;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlbumCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Album $album) {}
}
