<?php

namespace App\Filament\Schemas;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Schemas\Components\Section;

/**
 * Reusable Filament form section for 6-digit access code.
 *
 * Supports both:
 * - New style (access_code, access_code_enabled, access_code_expires_at)
 * - Legacy style (digit_code, digit_code_enabled, digit_code_expires_at)
 */
class AccessCodeSection
{
    /**
     * Create access code section for Filament forms.
     *
     * @param  string  $modelClass  Model class (e.g., \App\Models\TabloProject::class)
     * @param  string  $tableName  Database table name for unique validation
     * @param  int  $defaultDays  Default expiration days (default: 30)
     * @param  bool  $useLegacyFields  Use digit_code_* instead of access_code_* (default: false)
     */
    public static function make(
        string $modelClass,
        string $tableName,
        int $defaultDays = 30,
        bool $useLegacyFields = false
    ): Section {
        // Field names based on legacy mode
        $enabledField = $useLegacyFields ? 'digit_code_enabled' : 'access_code_enabled';
        $codeField = $useLegacyFields ? 'digit_code' : 'access_code';
        $expiresField = $useLegacyFields ? 'digit_code_expires_at' : 'access_code_expires_at';

        return Section::make('6 számjegyű kód')
            ->description('Belépési kód hozzáférés beállítása')
            ->schema([
                Forms\Components\Toggle::make($enabledField)
                    ->label('6 számjegyű kód engedélyezése')
                    ->helperText('Automatikusan generálódik bekapcsoláskor')
                    ->default(true)
                    ->live()
                    ->afterStateUpdated(function ($state, $set, $get) use ($modelClass, $defaultDays, $codeField, $expiresField) {
                        if ($state) {
                            // Ha bekapcsolják ÉS nincs még kód, generáljunk
                            if (empty($get($codeField))) {
                                $instance = new $modelClass;
                                $set($codeField, $instance->generateAccessCode());
                            }

                            // Ha nincs lejárat, állítsuk be X napra
                            if (empty($get($expiresField))) {
                                $set($expiresField, now()->addDays($defaultDays));
                            }
                        }
                    }),

                Forms\Components\TextInput::make($codeField)
                    ->label('Belépési Kód')
                    ->length(6)
                    ->numeric()
                    ->nullable()
                    ->unique(
                        table: $tableName,
                        column: $codeField,
                        ignoreRecord: false,
                        modifyRuleUsing: fn ($rule, $record) => $rule
                            ->where(function ($query) use ($record, $tableName) {
                                // Soft delete support if table has deleted_at
                                if (\Schema::hasColumn($tableName, 'deleted_at')) {
                                    $query->whereNull('deleted_at');
                                }
                                if ($record) {
                                    $query->where('id', '!=', $record->id);
                                }
                            })
                    )
                    ->validationMessages([
                        'unique' => 'Ez a kód már használatban van. Kattints a Generálás gombra új kódért.',
                    ])
                    ->visible(fn ($get) => $get($enabledField))
                    ->helperText('6 számjegyű kód. Kattints a Generálás gombra új egyedi kódért.')
                    ->suffixAction(
                        Action::make('generateCode')
                            ->icon('heroicon-o-arrow-path')
                            ->tooltip('Új kód generálása')
                            ->action(function ($set) use ($modelClass, $codeField) {
                                $instance = new $modelClass;
                                $set($codeField, $instance->generateAccessCode());
                            })
                    ),

                Forms\Components\DateTimePicker::make($expiresField)
                    ->label('Kód lejárati ideje')
                    ->visible(fn ($get) => $get($enabledField))
                    ->helperText("Ha üresen hagyod, {$defaultDays} napig érvényes"),
            ])
            ->columns(2);
    }
}
