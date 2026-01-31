<?php

namespace App\Filament\Actions;

use App\Events\UserCreatedWithCredentials;
use App\Models\SchoolClass;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ImportUsersFromCsvAction
{
    /**
     * Create a new import users from CSV action.
     *
     * @param  string  $label  Action label
     * @param  string  $icon  Action icon
     */
    public static function make(?int $fixedClassId = null, ?int $albumId = null, string $label = 'CSV importálás', string $icon = 'heroicon-o-document-text'): Action
    {
        $formComponents = [
            FileUpload::make('csv_file')
                ->label('CSV fájl')
                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                ->maxSize(5120) // 5MB
                ->helperText('Formátum: név,email (minden sorban egy felhasználó, első sor opcionálisan fejléc lehet)')
                ->required()
                ->disk('local')
                ->directory('temp-uploads')
                ->columnSpanFull(),
            Hidden::make('album_id')
                ->default($albumId),
        ];

        if ($fixedClassId !== null) {
            $formComponents[] = Hidden::make('default_class_id')
                ->default($fixedClassId);
        } else {
            $formComponents[] = Select::make('default_class_id')
                ->label('Alapértelmezett osztály (opcionális)')
                ->options(SchoolClass::orderBy('label')->pluck('label', 'id'))
                ->searchable()
                ->nullable();
        }

        return Action::make('importUsersFromCsv')
            ->label($label)
            ->icon($icon)
            ->color('info')
            ->form($formComponents)
            ->action(function (array $data) {
                return static::processImport($data);
            });
    }

    /**
     * Process the CSV import of users.
     *
     * @param  array  $data  Form data containing csv_file and default_class_id
     */
    protected static function processImport(array $data): void
    {
        $csvFile = $data['csv_file'];
        $defaultClassId = $data['default_class_id'] ?? null;
        $albumId = $data['album_id'] ?? null;

        $filePath = Storage::disk('local')->path($csvFile);

        if (! file_exists($filePath)) {
            Notification::make()
                ->title('Hiba')
                ->body('A fájl nem található.')
                ->danger()
                ->send();

            return;
        }

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        $handle = fopen($filePath, 'r');
        $lineNumber = 0;
        $isFirstLine = true;

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            $lineNumber++;

            // Skip first line if it looks like a header
            if ($isFirstLine) {
                $isFirstLine = false;
                // Check if first line is header (contains "név" or "name" or "email")
                $firstCell = strtolower($row[0] ?? '');
                if (in_array($firstCell, ['név', 'name', 'nev']) || strpos($firstCell, 'email') !== false) {
                    continue;
                }
            }

            try {
                $result = static::processRow($row, $defaultClassId, $albumId, $lineNumber);
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $errors[] = $result['error'];
                }
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "Sor $lineNumber: ".$e->getMessage();
            }
        }

        fclose($handle);

        // Clean up the uploaded file
        Storage::disk('local')->delete($csvFile);

        // Show success notification
        if ($successCount > 0) {
            Notification::make()
                ->title('Import sikeres')
                ->body("$successCount felhasználó sikeresen létrehozva.")
                ->success()
                ->send();
        }

        // Show error notification if there were errors
        if ($errorCount > 0) {
            $errorMessage = "$errorCount hiba történt:\n".implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $errorMessage .= "\n... és ".(count($errors) - 5).' további hiba.';
            }

            Notification::make()
                ->title('Import hibák')
                ->body($errorMessage)
                ->danger()
                ->send();
        }
    }

    /**
     * Process a single CSV row of user data.
     *
     * @param  array  $row  The CSV row to process
     * @param  int|null  $defaultClassId  Default class ID for the user
     * @param  int|null  $albumId  Album ID to attach existing users to
     * @param  int  $lineNumber  Line number for error reporting
     * @return array Result with success status and error message if applicable
     */
    protected static function processRow(array $row, ?int $defaultClassId, ?int $albumId, int $lineNumber): array
    {
        if (count($row) < 2) {
            return [
                'success' => false,
                'error' => "Sor $lineNumber: Hiányzó adatok. Minimum 2 oszlop szükséges (név, email).",
            ];
        }

        $name = trim($row[0]);
        $email = trim($row[1]);

        // Validate name
        if (empty($name)) {
            return [
                'success' => false,
                'error' => "Sor $lineNumber: A név nem lehet üres.",
            ];
        }

        // Validate email
        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error' => "Sor $lineNumber: Érvénytelen email cím: $email",
            ];
        }

        // Check if user already exists
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            static::attachUserToAlbum($existingUser, $albumId, $defaultClassId);

            return ['success' => true, 'attached_existing' => true];
        }

        // Generate random password
        $password = static::generateRandomPassword();

        // Create user
        try {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => User::ROLE_CUSTOMER,
                'class_id' => $defaultClassId,
            ]);

            static::attachUserToAlbum($user, $albumId, $defaultClassId);

            // Send credentials email
            event(new UserCreatedWithCredentials($user, $password, null));

            return ['success' => true];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Sor $lineNumber: Hiba a felhasználó létrehozásakor: ".$e->getMessage(),
            ];
        }
    }

    /**
     * Attach a user to the given album by assigning at least one photo.
     *
     * @param  User  $user  User instance
     * @param  int|null  $albumId  Album ID where the user should be attached
     * @param  int|null  $defaultClassId  Default class ID used if the user has no class
     */
    protected static function attachUserToAlbum(User $user, ?int $albumId, ?int $defaultClassId): void
    {
        if (! $albumId) {
            return;
        }

        // Check if user already has photos in this album
        $existingPhoto = \App\Models\Photo::where('album_id', $albumId)
            ->where('assigned_user_id', $user->id)
            ->first();

        // If user doesn't have any photos in this album yet, assign one unassigned photo
        if (! $existingPhoto) {
            $unassignedPhoto = \App\Models\Photo::where('album_id', $albumId)
                ->whereNull('assigned_user_id')
                ->first();

            if ($unassignedPhoto) {
                $unassignedPhoto->update(['assigned_user_id' => $user->id]);
            }
        }

        // Update user's class if they don't have one and we have a default
        if (! $user->class_id && $defaultClassId) {
            $user->update(['class_id' => $defaultClassId]);
        }
    }

    /**
     * Generate a random password for new users.
     *
     * @return string Random password
     */
    protected static function generateRandomPassword(): string
    {
        // Generate a random password with letters and numbers
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';

        for ($i = 0; $i < 12; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $password;
    }
}
