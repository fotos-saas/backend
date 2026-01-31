<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminUserResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

/**
 * Resource for managing admin users with Spatie Permission roles.
 */
class AdminUserResource extends BaseResource
{

    protected static ?string $model = User::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCog;

    protected static ?string $navigationLabel = 'Admin Felhasználók';

    protected static ?string $modelLabel = 'Admin Felhasználó';

    protected static ?string $pluralModelLabel = 'Admin Felhasználók';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Platform Beállítások';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Alapadatok')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Név')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefonszám')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\Select::make('roles')
                            ->label('Szerepkörök')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->preload()
                            ->required()
                            ->live()
                            ->helperText('Admin szerepkörök kezelése'),

                        Forms\Components\Select::make('tablo_partner_id')
                            ->label('Partner')
                            ->relationship('tabloPartner', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(function ($get) {
                                $selectedRoleIds = $get('roles') ?? [];
                                if (empty($selectedRoleIds)) {
                                    return false;
                                }
                                $tabloRoleId = \Spatie\Permission\Models\Role::where('name', 'tablo')->first()?->id;

                                return in_array($tabloRoleId, $selectedRoleIds);
                            })
                            ->helperText('Melyik partnerhez tartozik ez a felhasználó'),

                        Forms\Components\TextInput::make('password')
                            ->label('Jelszó')
                            ->password()
                            ->dehydrateStateUsing(function ($state, $record) {
                                if (filled($state)) {
                                    return Hash::make($state);
                                } elseif (! $record) {
                                    return Hash::make(static::generateRandomPassword());
                                }

                                return null;
                            })
                            ->dehydrated(fn ($state, $record) => filled($state) || ! $record)
                            ->maxLength(255)
                            ->helperText('Hagyd üresen automatikus jelszó generálásához. Szerkesztéskor üresen hagyva nem változik.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Szerepkörök')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'super_admin' => 'danger',
                        'photo_admin' => 'warning',
                        'tablo' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'super_admin' => 'Super Admin',
                        'photo_admin' => 'Fotós Admin',
                        'tablo' => 'Tablo',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('tabloPartner.name')
                    ->label('Partner')
                    ->badge()
                    ->color('success')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Regisztrálva')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Szerepkör')
                    ->relationship('roles', 'name'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn ($record) => $record->hasRole('super_admin')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->deselectRecordsAfterCompletion()
                        ->action(function ($records) {
                            $filteredRecords = $records->filter(fn ($record) => ! $record->hasRole('super_admin'));

                            if ($filteredRecords->count() !== $records->count()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Figyelmeztetés')
                                    ->body('Super Admin felhasználók nem törölhetők.')
                                    ->warning()
                                    ->send();
                            }

                            $filteredRecords->each(fn ($record) => $record->delete());
                        }),
                ]),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $query->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['super_admin', 'photo_admin', 'tablo']);
                });
            })
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminUsers::route('/'),
            'create' => Pages\CreateAdminUser::route('/create'),
            'edit' => Pages\EditAdminUser::route('/{record}/edit'),
        ];
    }

    /**
     * Generate a random password for new users.
     *
     * @return string Random password
     */
    protected static function generateRandomPassword(): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';

        for ($i = 0; $i < 12; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $password;
    }
}
