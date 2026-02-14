<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Partner;

use App\Actions\Teacher\BulkUploadTeacherPhotosAction;
use App\Http\Controllers\Api\Partner\Traits\BulkPhotoTrait;
use App\Http\Controllers\Api\Partner\Traits\PartnerAuthTrait;
use App\Http\Controllers\Controller;
use App\Models\TeacherArchive;

class PartnerTeacherBulkPhotoController extends Controller
{
    use PartnerAuthTrait, BulkPhotoTrait;

    protected function getArchiveModelClass(): string
    {
        return TeacherArchive::class;
    }

    protected function getBulkUploadAction(): object
    {
        return new BulkUploadTeacherPhotosAction();
    }
}
