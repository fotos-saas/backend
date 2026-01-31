<?php

namespace App\Filament\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->label('Név')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(table: 'users', column: 'email', ignoreRecord: true),

                Forms\Components\TextInput::make('phone')
                    ->label('Telefon')
                    ->tel()
                    ->maxLength(255),

                Forms\Components\TextInput::make('password')
                    ->label('Jelszó')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn ($context) => $context === 'create')
                    ->default(fn ($context) => $context === 'create' ? Str::random(16) : null)
                    ->helperText('Új felhasználónál automatikusan generálódik random jelszó. A felhasználó magic link-kel belépve változtathatja meg.'),

                Forms\Components\Hidden::make('password_set')
                    ->default(false)
                    ->dehydrated(true),
            ]);
    }
}
