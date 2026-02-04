<?php

namespace App\Http\Controllers\Api\Partner\Traits;

use App\Models\TabloProject;

/**
 * Common authentication and authorization methods for Partner controllers.
 */
trait PartnerAuthTrait
{
    /**
     * Get the authenticated user's partner ID or fail with 403.
     */
    protected function getPartnerIdOrFail(): int
    {
        $partnerId = auth()->user()->tablo_partner_id;

        if (!$partnerId) {
            abort(403, 'Nincs partnerhez rendelve');
        }

        return $partnerId;
    }

    /**
     * Get a project that belongs to the user's partner.
     */
    protected function getProjectForPartner(int $projectId): TabloProject
    {
        return TabloProject::where('id', $projectId)
            ->where('partner_id', $this->getPartnerIdOrFail())
            ->firstOrFail();
    }
}
