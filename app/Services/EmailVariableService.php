<?php

namespace App\Services;

use App\Models\Album;
use App\Models\EmailVariable;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkSession;
use Illuminate\Support\Carbon;

class EmailVariableService
{
    public function __construct(protected BrandingService $branding) {}

    public function getAvailableVariables(): array
    {
        return [
            'user' => [
                'user_name' => 'Felhasználó neve',
                'user_email' => 'Felhasználó email címe',
                'user_phone' => 'Felhasználó telefonszáma',
                'user_class' => 'Felhasználó osztály megnevezése',
            ],
            'album' => [
                'album_title' => 'Album címe',
                'album_class' => 'Albumhoz tartozó osztály',
                'album_photo_count' => 'Albumhoz tartozó képek száma',
            ],
            'order' => [
                'order_id' => 'Megrendelés azonosítója',
                'order_number' => 'Megrendelés szám',
                'order_total' => 'Megrendelés végösszege',
                'order_status' => 'Megrendelés státusza',
                'order_items_count' => 'Megrendelés tételeinek száma',
                'payment_method' => 'Fizetési mód',
                'shipping_method' => 'Szállítási mód',
                'tracking_number' => 'Szállítási követő szám',
            ],
            'work_session' => [
                'access_code' => 'Belépési kód (6 jegyű)',
                'work_session_url' => 'WorkSession belépési URL',
                'expires_at' => 'Lejárati idő',
            ],
            'auth' => [
                'password' => 'Generált jelszó',
                'reset_link' => 'Jelszó visszaállítási link',
                'magic_link' => 'Magic belépési link (egyszer használatos, lejáró)',
                'digit_code' => '6-jegyű munkamenet belépési kód',
                'digit_code_section' => 'Digit kód szekció (HTML blokk, csak ha van kód)',
                'quick_link' => 'Gyors link URL (auto-fill kóddal)',
                'quick_link_section' => 'Gyors link szekció (HTML blokk)',
            ],
            'tablo' => [
                'parent_session_name' => 'Szülő munkamenet neve',
                'child_session_name' => 'Felhasználó saját munkamenet neve',
                'child_album_title' => 'Felhasználó saját albumának címe',
                'tablo_photo_url' => 'Kiválasztott tablókép URL-je (teljes méret)',
                'tablo_photo_thumb_url' => 'Kiválasztott tablókép thumbnail URL-je (300x300)',
                'tablo_photo_id' => 'Kiválasztott tablókép azonosítója',
                'magic_link_worksession' => 'Magic link a WorkSession-höz (6 hónap érvényesség)',
                'work_session_name' => 'Munkamenet neve',
                'completion_date' => 'Véglegesítés dátuma',
                'max_retouch_photos' => 'Maximum retusálható képek száma',
                'removed_count' => 'Eltávolított képek száma (konfliktus)',
                'removed_photo_ids' => 'Eltávolított képek azonosítói (vesszővel elválasztva)',
                'winner_user_name' => 'Nyertes felhasználó neve (aki előbb véglegesített)',
            ],
            'general' => [
                'site_name' => 'Weboldal neve',
                'site_url' => 'Weboldal URL címe',
                'partner_email' => 'Partner e-mail címe',
                'partner_phone' => 'Partner telefonszáma',
                'partner_address' => 'Partner címe',
                'partner_tax_number' => 'Partner adószáma',
                'partner_landing_page' => 'Landing oldal URL',
                'current_date' => 'Mai dátum',
                'current_year' => 'Aktuális év',
            ],
        ];
    }

    public function resolveVariables(
        ?User $user = null,
        ?Album $album = null,
        ?Order $order = null,
        ?WorkSession $workSession = null,
        ?array $authData = null
    ): array {
        $variables = [];

        if ($user) {
            $variables = array_merge($variables, $this->resolveUserVariables($user));
        }

        if ($album) {
            $variables = array_merge($variables, $this->resolveAlbumVariables($album));
        }

        if ($order) {
            $variables = array_merge($variables, $this->resolveOrderVariables($order));
            $variables = array_merge($variables, $this->resolveOrderExtendedVariables($order));
        }

        if ($workSession) {
            $variables = array_merge($variables, $this->resolveWorkSessionVariables($workSession));
        }

        if ($authData) {
            $variables = array_merge($variables, $this->resolveAuthVariables($authData));
        }

        $variables = array_merge($variables, $this->resolveGeneralVariables());

        // Merge database variables
        $databaseVariables = $this->getDatabaseVariables();
        $variables = array_merge($databaseVariables, $variables);

        // Resolve recursively
        return $this->resolveVariablesRecursively($variables);
    }

    public function replaceVariables(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            $content = str_replace("{{$key}}", (string) $value, $content);
        }

        return $content;
    }

    protected function resolveUserVariables(User $user): array
    {
        return [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_phone' => $user->phone ?? '',
            'user_class' => $user->class?->label ?? '',
        ];
    }

    protected function resolveAlbumVariables(Album $album): array
    {
        return [
            'album_title' => $album->title,
            'album_class' => $album->class?->label ?? '',
            'album_photo_count' => $album->photos()->count(),
        ];
    }

    protected function resolveOrderVariables(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number ?? '',
            'order_total' => number_format($order->total_gross_huf, 0, ',', ' ').' Ft',
            'order_status' => $this->translateOrderStatus($order->status),
            'order_items_count' => $order->items()->count(),
        ];
    }

    /**
     * Resolve extended order variables (payment, shipping, tracking)
     */
    protected function resolveOrderExtendedVariables(Order $order): array
    {
        return [
            'payment_method' => $order->paymentMethod?->name ?? 'N/A',
            'shipping_method' => $order->shippingMethod?->name ?? 'N/A',
            'shipping_cost' => number_format($order->shipping_cost_huf, 0, ',', ' ').' Ft',
            'cod_fee' => number_format($order->cod_fee_huf, 0, ',', ' ').' Ft',
            'grand_total' => number_format($order->getGrandTotal(), 0, ',', ' ').' Ft',
            'tracking_number' => $order->tracking_number ?? 'Még nem elérhető',
            'package_point' => $order->packagePoint?->name ?? 'N/A',
            'shipping_address' => $order->shipping_address ?? 'N/A',
        ];
    }

    /**
     * Resolve WorkSession variables (access code, url, expiry)
     */
    protected function resolveWorkSessionVariables(WorkSession $workSession): array
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        return [
            'access_code' => $workSession->access_code ?? '',
            'work_session_url' => $frontendUrl.'/auth/code?code='.$workSession->access_code,
            'expires_at' => $workSession->expires_at ? $workSession->expires_at->format('Y-m-d H:i') : 'N/A',
        ];
    }

    /**
     * Resolve authentication variables (password, reset link, magic link)
     */
    protected function resolveAuthVariables(array $authData): array
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        $variables = [
            'password' => $authData['password'] ?? '',
            'reset_link' => $authData['reset_link'] ?? '',
            'magic_link' => $authData['magic_link'] ?? '',
            'digit_code' => $authData['digit_code'] ?? '',
            'work_session_name' => $authData['work_session_name'] ?? '',
            'quick_link' => '',
            'digit_code_section' => '',
            'quick_link_section' => '',
            'removed_count' => $authData['removed_count'] ?? '',
            'removed_photo_ids' => isset($authData['removed_photo_ids']) ? implode(', ', $authData['removed_photo_ids']) : '',
            'winner_user_name' => $authData['winner_user_name'] ?? '',
        ];

        // Generate composite HTML sections if digit_code is present and should be included
        if (!empty($authData['digit_code']) && ($authData['include_code'] ?? false)) {
            $digitCode = $authData['digit_code'];
            $quickLinkUrl = $frontendUrl . '/auth/verify?code=' . $digitCode . '&focus=true';

            $variables['quick_link'] = $quickLinkUrl;

            // Digit code section with styled HTML
            $variables['digit_code_section'] = '
                <div style="margin: 24px 0; padding: 20px; background-color: #f3f4f6; border-radius: 8px; border-left: 4px solid #1e7b34;">
                    <h3 style="margin: 0 0 12px 0; color: #1f2933; font-size: 18px;">Megosztható belépési kód</h3>
                    <p style="margin: 0 0 12px 0; color: #52606d; font-size: 14px;">
                        Oszd meg ezt a 6-jegyű kódot azokkal, akiknek hozzáférést szeretnél adni az albumhoz:
                    </p>
                    <div style="font-size: 32px; font-weight: bold; font-family: monospace; color: #1e7b34; letter-spacing: 4px; text-align: center; padding: 16px; background: white; border-radius: 6px; border: 2px dashed #1e7b34;">
                        ' . htmlspecialchars($digitCode) . '
                    </div>
                </div>
            ';

            // Quick link section
            $variables['quick_link_section'] = '
                <div style="margin: 24px 0; padding: 16px; background-color: #eff6ff; border-radius: 8px;">
                    <p style="margin: 0 0 8px 0; color: #1f2933; font-size: 14px; font-weight: 600;">
                        Gyors belépés ezzel a linkkel:
                    </p>
                    <p style="margin: 0; font-size: 13px; color: #52606d;">
                        Ez a link automatikusan kitölti a belépési kódot a weboldalon:
                    </p>
                    <p style="margin: 12px 0 0 0;">
                        <a href="' . htmlspecialchars($quickLinkUrl) . '" style="color: #1e7b34; word-break: break-all; font-size: 13px;">
                            ' . htmlspecialchars($quickLinkUrl) . '
                        </a>
                    </p>
                </div>
            ';
        }

        return $variables;
    }

    /**
     * Translate order status to Hungarian
     */
    protected function translateOrderStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'Függőben',
            'payment_pending' => 'Fizetésre vár',
            'paid' => 'Fizetve',
            'processing' => 'Feldolgozás alatt',
            'in_production' => 'Gyártás alatt',
            'shipped' => 'Elküldve',
            'delivered' => 'Kézbesítve',
            'completed' => 'Teljesítve',
            'cancelled' => 'Törölve',
            'refunded' => 'Visszatérítve',
            default => $status,
        };
    }

    protected function resolveGeneralVariables(): array
    {
        $now = Carbon::now();

        return [
            'site_name' => $this->branding->getName(),
            'site_url' => $this->branding->getWebsite() ?? config('app.frontend_url') ?? config('app.url'),
            'partner_email' => $this->branding->getEmail() ?? config('mail.from.address'),
            'partner_phone' => $this->branding->getPhone() ?? '',
            'partner_address' => $this->branding->getAddress() ?? '',
            'partner_tax_number' => $this->branding->getTaxNumber() ?? '',
            'partner_landing_page' => $this->branding->getLandingPageUrl() ?? ($this->branding->getWebsite() ?? config('app.url')),
            'current_date' => $now->format('Y-m-d'),
            'current_year' => $now->format('Y'),
        ];
    }

    /**
     * Get all active variables from database
     */
    public function getDatabaseVariables(): array
    {
        return EmailVariable::active()
            ->byPriority()
            ->get()
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Resolve variables recursively (supports {key} inside values)
     */
    public function resolveVariablesRecursively(array $variables, ?int $maxDepth = null): array
    {
        $maxDepth = $maxDepth ?? config('email-system.variable_recursion_depth', 5);

        for ($i = 0; $i < $maxDepth; $i++) {
            $hasUnresolved = false;

            foreach ($variables as $key => $value) {
                if (! is_string($value)) {
                    continue;
                }

                $original = $value;
                $value = $this->replaceVariables($value, $variables);
                $variables[$key] = $value;

                if ($original !== $value) {
                    $hasUnresolved = true;
                }
            }

            if (! $hasUnresolved) {
                break;
            }
        }

        return $variables;
    }
}
