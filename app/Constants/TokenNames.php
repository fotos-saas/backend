<?php

namespace App\Constants;

final class TokenNames
{
    public const TABLO_AUTH = 'tablo-auth-token';
    public const TABLO_SHARE = 'tablo-share-token';
    public const TABLO_PREVIEW = 'tablo-preview-token';
    public const GUEST_ACCESS = 'guest-access';
    public const DEV_TABLO = 'dev-tablo-token';
    public const PARTNER_AUTH = 'partner-auth-token';
    public const AUTH = 'auth-token';
    public const MAGIC_LINK = 'magic-link-login';
    public const QR_REGISTRATION = 'qr-registration';
    public const CLIENT_AUTH = 'client-auth-token';

    /** Teljes hozzáférést biztosító tokenek (kódos belépés + QR regisztráció) */
    public const FULL_ACCESS_TOKENS = [
        self::TABLO_AUTH,
        self::QR_REGISTRATION,
    ];
}
