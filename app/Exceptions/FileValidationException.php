<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

class FileValidationException extends InvalidArgumentException
{
    public static function tooLarge(float $maxMB): self
    {
        return new self("A fájl túl nagy (max {$maxMB}MB).");
    }

    public static function invalidMimeType(): self
    {
        return new self('Nem engedélyezett fájltípus.');
    }

    public static function notAnImage(): self
    {
        return new self('A fájl nem érvényes kép.');
    }

    public static function invalidExtension(): self
    {
        return new self('Nem engedélyezett kiterjesztés.');
    }

    public static function magicBytesMismatch(): self
    {
        return new self('A fájl tartalma nem egyezik a kiterjesztéssel!');
    }
}
