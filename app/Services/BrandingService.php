<?php

namespace App\Services;

use App\Models\PartnerSetting;

class BrandingService
{
    protected ?PartnerSetting $cachedSetting = null;

    public function getName(): string
    {
        return $this->getActiveSetting()?->name ?? config('app.name');
    }

    public function getSlogan(): ?string
    {
        return $this->getActiveSetting()?->slogan;
    }

    public function getLogoPath(): ?string
    {
        return $this->getActiveSetting()?->logo;
    }

    public function getLogoUrl(): ?string
    {
        $logoPath = $this->getLogoPath();

        if (! $logoPath) {
            return null;
        }

        return asset('storage/'.ltrim($logoPath, '/'));
    }

    public function getFaviconPath(): ?string
    {
        return $this->getActiveSetting()?->favicon;
    }

    public function getFaviconUrl(): ?string
    {
        $faviconPath = $this->getFaviconPath();

        if (! $faviconPath) {
            return null;
        }

        return asset('storage/'.ltrim($faviconPath, '/'));
    }

    public function getBrandColor(): ?string
    {
        return $this->getActiveSetting()?->brand_color;
    }

    public function getEmail(): ?string
    {
        return $this->getActiveSetting()?->email;
    }

    public function getPhone(): ?string
    {
        return $this->getActiveSetting()?->phone;
    }

    public function getAddress(): ?string
    {
        return $this->getActiveSetting()?->address;
    }

    public function getTaxNumber(): ?string
    {
        return $this->getActiveSetting()?->tax_number;
    }

    public function getWebsite(): ?string
    {
        return $this->getActiveSetting()?->website;
    }

    public function getLandingPageUrl(): ?string
    {
        return $this->getActiveSetting()?->landing_page_url;
    }

    public function getInstagramUrl(): ?string
    {
        return $this->getActiveSetting()?->instagram_url;
    }

    public function getFacebookUrl(): ?string
    {
        return $this->getActiveSetting()?->facebook_url;
    }

    protected function getActiveSetting(): ?PartnerSetting
    {
        if (! $this->cachedSetting) {
            try {
                $this->cachedSetting = PartnerSetting::query()
                    ->where('is_active', true)
                    ->latest('updated_at')
                    ->first();
            } catch (\Exception $e) {
                // Table might not exist yet (during migration)
                return null;
            }
        }

        return $this->cachedSetting;
    }
}
