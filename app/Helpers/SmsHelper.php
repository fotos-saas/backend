<?php

namespace App\Helpers;

use App\Models\TabloContact;
use App\Models\TabloProject;

class SmsHelper
{
    /**
     * SMS aláírás - egységes mindenhol
     */
    public const SIGNATURE = 'Udv: tablokiraly';

    /**
     * Generál egy rövid SMS szöveget (ékezet nélkül, 160 kar alatt)
     * Formátum: "Kedves {keresztnév}! Megkaptad az emailt a tablo elkeszitesevel kapcsolatban? Udv: tablokiraly"
     */
    public static function generateShortMessage(string $firstName): string
    {
        return "Kedves {$firstName}! Megkaptad az emailt a tablo elkeszitesevel kapcsolatban? " . self::SIGNATURE;
    }

    /**
     * Generál SMS linket telefonszámmal és üzenettel
     */
    public static function generateSmsLink(string $phone, string $message): string
    {
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        $encodedMessage = rawurlencode($message);

        return "sms:{$cleanPhone}&body={$encodedMessage}";
    }

    /**
     * Generál SMS linket a kontakt és projekt alapján (rövid verzió)
     */
    public static function generateSmsLinkForContact(TabloContact $contact, ?TabloProject $project = null): string
    {
        $phone = $contact->phone ?? '';
        $fullName = $contact->name ?? '';

        $firstName = self::extractFirstName($fullName);

        $message = self::generateShortMessage($firstName);

        return self::generateSmsLink($phone, $message);
    }

    /**
     * Generál SMS linket teljes névből (TabloOutreachResource-hoz)
     */
    public static function generateSmsLinkFromName(string $phone, string $fullName): string
    {
        $firstName = self::extractFirstName($fullName);

        $message = self::generateShortMessage($firstName);

        return self::generateSmsLink($phone, $message);
    }

    /**
     * Keresztnév kinyerése a teljes névből
     * Megpróbálja használni a ClaudeService-t, ha elérhető,
     * különben egyszerű heurisztikát használ (utolsó szó = keresztnév magyar neveknél)
     */
    public static function extractFirstName(string $fullName): string
    {
        // Próbáljuk meg a ClaudeService-t használni
        try {
            if (class_exists(\App\Services\ClaudeService::class)) {
                $claudeService = app(\App\Services\ClaudeService::class);
                return $claudeService->extractFirstName($fullName);
            }
        } catch (\Throwable $e) {
            // Ha bármilyen hiba van (pl. nincs Anthropic csomag), használjuk a fallback-et
        }

        // Fallback: magyar neveknél az utolsó szó általában a keresztnév
        $parts = preg_split('/\s+/', trim($fullName));
        if (count($parts) > 0) {
            return end($parts);
        }

        return $fullName;
    }
}
