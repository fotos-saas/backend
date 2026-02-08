<?php

declare(strict_types=1);

namespace App\Actions\Partner;

use App\Models\TabloContact;
use App\Services\Search\SearchService;

/**
 * Kapcsolattartók exportálása vCard 3.0 formátumba.
 *
 * iPhone/Android/Outlook kompatibilis.
 * Fonetikus becenév mezőbe a projekt rövidnév + osztálynév kerül.
 */
class ExportContactsVcardAction
{
    public function execute(int $partnerId, ?string $search = null): string
    {
        $contacts = $this->getContacts($partnerId, $search);

        $vcards = [];

        foreach ($contacts as $contact) {
            $vcards[] = $this->buildVcard($contact);
        }

        return implode("\r\n", $vcards);
    }

    private function buildVcard(TabloContact $contact): string
    {
        $nameParts = $this->splitName($contact->name);
        $lastName = $this->escapeVcard($nameParts['last']);
        $firstName = $this->escapeVcard($nameParts['first']);

        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:' . $this->escapeVcard($contact->name),
            "N:{$lastName};{$firstName};;;",
        ];

        if ($contact->phone) {
            $lines[] = 'TEL;TYPE=CELL:' . $contact->phone;
        }

        if ($contact->email) {
            $lines[] = 'EMAIL:' . $contact->email;
        }

        $projects = $contact->projects;
        $projectLabels = $projects->map(function ($project) {
            $schoolName = $project->school?->name ?? '';
            $className = $project->class_name ?? '';

            if ($schoolName && $className) {
                return "{$schoolName} - {$className}";
            }

            return $schoolName ?: $className ?: $project->display_name;
        })->filter()->values();

        if ($projectLabels->isNotEmpty()) {
            $lines[] = 'X-PHONETIC-LAST-NAME:' . $this->escapeVcard($projectLabels->first());

            if ($projectLabels->count() > 1) {
                $lines[] = 'NOTE:Projektek: ' . $this->escapeVcard($projectLabels->implode(', '));
            }
        }

        if ($contact->note) {
            $notePrefix = $projectLabels->count() > 1 ? '' : 'NOTE:';
            if ($notePrefix) {
                $lines[] = 'NOTE:' . $this->escapeVcard($contact->note);
            } else {
                // NOTE already added with projects, append the personal note
                $lastNoteIndex = array_key_last(array_filter($lines, fn ($l) => str_starts_with($l, 'NOTE:')));
                if ($lastNoteIndex !== null) {
                    $lines[$lastNoteIndex] .= '\\n' . $this->escapeVcard($contact->note);
                }
            }
        }

        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines);
    }

    /**
     * Magyar névsorrendben: Vezetéknév Keresztnév → split
     */
    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);

        return [
            'last' => $parts[0] ?? '',
            'first' => $parts[1] ?? '',
        ];
    }

    private function escapeVcard(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(',', '\\,', $value);
        $value = str_replace(';', '\\;', $value);
        $value = str_replace("\n", '\\n', $value);
        $value = str_replace("\r", '', $value);

        return $value;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, TabloContact>
     */
    private function getContacts(int $partnerId, ?string $search): \Illuminate\Database\Eloquent\Collection
    {
        $query = TabloContact::where('partner_id', $partnerId)
            ->with(['projects.school']);

        if ($search) {
            $query = app(SearchService::class)->apply($query, $search, [
                'columns' => ['name', 'email', 'phone'],
            ]);
        }

        return $query->orderBy('name')->get();
    }
}
