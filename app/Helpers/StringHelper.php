<?php

declare(strict_types=1);

namespace App\Helpers;

class StringHelper
{
    /**
     * Szöveg rövidítése a közepéből.
     *
     * Megtartja az elejét és a végét, a közepet „…"-lal helyettesíti.
     * Szó határon vág (nem töri meg a szavakat).
     *
     * Példa: "Szegedi Radnóti Miklós Kísérleti Gimnázium 12.A 2025/2026"
     *       → "Szegedi Radnóti…12.A 2025/2026" (maxLength=30)
     *
     * @param  string  $text     A rövidítendő szöveg
     * @param  int     $maxLength Maximum karakter (default: 40)
     * @param  string  $ellipsis Rövidítés jel (default: '…')
     */
    public static function abbreviateMiddle(
        string $text,
        int $maxLength = 40,
        string $ellipsis = '…',
    ): string {
        $text = trim($text);

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $ellipsisLen = mb_strlen($ellipsis);
        $available = $maxLength - $ellipsisLen;

        // Eleje kb. 60%, vége kb. 40% (a vége fontosabb: osztály + év)
        $headLen = (int) ceil($available * 0.6);
        $tailLen = $available - $headLen;

        $head = mb_substr($text, 0, $headLen);
        $tail = mb_substr($text, -$tailLen);

        // Szó határon vágjuk az elejét (utolsó szóköz)
        $lastSpace = mb_strrpos($head, ' ');
        if ($lastSpace !== false && $lastSpace > 3) {
            $head = mb_substr($head, 0, $lastSpace);
        }

        // Szó határon vágjuk a végét (első szóköz)
        $firstSpace = mb_strpos($tail, ' ');
        if ($firstSpace !== false && $firstSpace < mb_strlen($tail) - 3) {
            $tail = mb_substr($tail, $firstSpace + 1);
        }

        return trim($head) . $ellipsis . trim($tail);
    }

    /**
     * Projekt rövid neve fájlnév/ZIP célra.
     *
     * Formátum: "{rövidített név} ({id})"
     * Pl.: "Szegedi…12.A 2025-2026 (454)"
     */
    public static function projectShortName(
        string $projectName,
        int $projectId,
        int $maxLength = 50,
    ): string {
        // Fájlnévhez nem megengedett karakterek cseréje
        $name = preg_replace('/[\/\\\\:*?"<>|]/', '_', $projectName) ?? $projectName;
        // Slash a tanévben: 2025/2026 → 2025-2026
        $name = str_replace('/', '-', $name);

        $suffix = " ({$projectId})";
        $nameMaxLen = $maxLength - mb_strlen($suffix);

        $abbreviated = self::abbreviateMiddle($name, $nameMaxLen);

        return trim($abbreviated . $suffix);
    }
}
