<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Student\BulkUploadStudentPhotosAction;
use App\Http\Controllers\Api\Partner\Traits\BulkPhotoTrait;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Models\StudentArchive;

class PartnerStudentBulkPhotoController extends Controller
{
    use PartnerAuthTrait, BulkPhotoTrait;

    protected function getArchiveModelClass(): string
    {
        return StudentArchive::class;
    }

    protected function getBulkUploadAction(): object
    {
        return new BulkUploadStudentPhotosAction();
    }
}
