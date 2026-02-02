<?php

namespace App\DTOs;

use Illuminate\Http\Request;
use InvalidArgumentException;

readonly class CreateDiscountData
{
    public function __construct(
        public int $partnerId,
        public int $percent,
        public ?int $durationMonths,
        public ?string $note,
        public int $createdBy,
    ) {
        if ($percent < 1 || $percent > 99) {
            throw new InvalidArgumentException('Percent must be 1-99');
        }

        if ($durationMonths !== null && ($durationMonths < 1 || $durationMonths > 120)) {
            throw new InvalidArgumentException('Duration months must be 1-120');
        }
    }

    public static function fromRequest(Request $request, int $partnerId): self
    {
        $note = $request->string('note')->toString();

        return new self(
            partnerId: $partnerId,
            percent: $request->integer('percent'),
            durationMonths: $request->integer('duration_months') ?: null,
            note: $note ? strip_tags($note) : null,
            createdBy: $request->user()->id,
        );
    }
}
