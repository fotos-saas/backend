<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Events\UserCreatedWithCredentials;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $generatedPassword = null;

    protected function getRedirectUrl(): string
    {
        session()->put('new_user_id', $this->record->getKey());

        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // If password is empty, generate a random one and store it
        if (empty($data['password'])) {
            $this->generatedPassword = $this->generateRandomPassword();
            $data['password'] = Hash::make($this->generatedPassword);
        } elseif (! Hash::needsRehash($data['password'])) {
            // Password is already hashed (from form dehydrateStateUsing)
            // We can't send email with hashed password, so we skip email in this case
            $this->generatedPassword = null;
        } else {
            // Password is plain text, store it before hashing
            $this->generatedPassword = $data['password'];
            $data['password'] = Hash::make($data['password']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Send credentials email only if we have the plain text password
        if ($this->generatedPassword) {
            event(new UserCreatedWithCredentials($this->record, $this->generatedPassword));
        }

        // Ensure the new user always has the 'customer' role within this resource
        $this->record->syncRoles(['customer']);
    }

    /**
     * Generate a random password for new users.
     */
    protected function generateRandomPassword(): string
    {
        return Str::random(12);
    }
}
