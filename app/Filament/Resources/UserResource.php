<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\PhotosRelationManager;
use App\Models\EmailEvent;
use App\Models\User;
use App\Services\EmailService;
use App\Services\EmailVariableService;
use App\Services\MagicLinkService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserResource extends BaseResource
{

    protected static ?string $model = User::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Felhasználók';

    protected static ?string $modelLabel = 'Felhasználó';

    protected static ?string $pluralModelLabel = 'Felhasználók';

    protected static ?int $navigationSort = 4;

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

                        Forms\Components\Select::make('class_id')
                            ->label('Osztály')
                            ->relationship('class', 'label')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\TextInput::make('password')
                            ->label('Jelszó')
                            ->password()
                            ->dehydrateStateUsing(function ($state, $record) {
                                if (filled($state)) {
                                    return Hash::make($state);
                                } elseif (! $record) {
                                    // New record without password - generate random
                                    return Hash::make(static::generateRandomPassword());
                                }

                                // Edit without password - don't change
                                return null;
                            })
                            ->dehydrated(fn ($state, $record) => filled($state) || ! $record)
                            ->maxLength(255)
                            ->helperText('Hagyd üresen automatikus jelszó generálásához. Szerkesztéskor üresen hagyva nem változik.'),
                    ])
                    ->columns(2),

                Section::make('Cím')
                    ->description('Felhasználó postázási címe')
                    ->components([
                        Forms\Components\Textarea::make('address.line1')
                            ->label('Cím')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('address.zip')
                            ->label('Irányítószám')
                            ->maxLength(10),

                        Forms\Components\TextInput::make('address.city')
                            ->label('Város')
                            ->maxLength(100),

                        Forms\Components\Select::make('address.country')
                            ->label('Ország')
                            ->options([
                                'HU' => 'Magyarország',
                                'AT' => 'Ausztria',
                                'SK' => 'Szlovákia',
                                'RO' => 'Románia',
                                'HR' => 'Horvátország',
                                'SI' => 'Szlovénia',
                                'RS' => 'Szerbia',
                            ])
                            ->default('HU'),
                    ])
                    ->columns(2),

                Section::make('Statisztikák')
                    ->components([
                        Forms\Components\Placeholder::make('photos_count')
                            ->label('Hozzárendelt képek')
                            ->content(fn ($record) => $record ? $record->photos()->count() : 0),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Regisztrálva')
                            ->content(fn ($record) => $record ? $record->created_at->format('Y-m-d H:i') : '-'),
                    ])
                    ->columns(2)
                    ->hidden(fn ($record) => $record === null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Név')
                    ->description(function (User $record): string {
                        $parts = [];

                        // Email cím
                        if ($record->email) {
                            $parts[] = $record->email;
                        }

                        // Telefonszám
                        if ($record->phone) {
                            $parts[] = $record->phone;
                        }

                        return implode(' · ', $parts);
                    })
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tabloRegistration.schoolClass.school')
                    ->label('Iskola')
                    ->formatStateUsing(function ($record) {
                        $registration = $record->tabloRegistration;
                        if (!$registration || !$registration->schoolClass) {
                            return null;
                        }
                        return $registration->schoolClass->school;
                    })
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('tabloRegistration.schoolClass.label')
                    ->label('Osztály')
                    ->formatStateUsing(function ($record) {
                        $registration = $record->tabloRegistration;
                        if (!$registration || !$registration->schoolClass) {
                            return null;
                        }
                        return $registration->schoolClass->label;
                    })
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('photos_stats')
                    ->label('Képek')
                    ->badge()
                    ->state(function (User $record): string {
                        $progress = $record->tabloProgress;
                        if (!$progress) {
                            return '0 / 0 / 0';
                        }

                        $stepsData = $progress->steps_data ?? [];

                        $claimed = count($stepsData['claimed_photo_ids'] ?? []);
                        $retouch = count($stepsData['retouch_photo_ids'] ?? []);
                        $tablo = isset($stepsData['tablo_photo_id']) ? 1 : 0;

                        $badge = "{$claimed} / {$retouch} / {$tablo}";

                        // Add "Befejezve" if completed
                        if ($progress->current_step === 'completed') {
                            $badge .= ' / Befejezve';
                        }

                        return $badge;
                    })
                    ->color(function (User $record): string {
                        $progress = $record->tabloProgress;
                        if (!$progress) {
                            return 'gray';
                        }

                        $stepsData = $progress->steps_data ?? [];

                        $claimed = count($stepsData['claimed_photo_ids'] ?? []);
                        $retouch = count($stepsData['retouch_photo_ids'] ?? []);
                        $tablo = isset($stepsData['tablo_photo_id']) ? 1 : 0;

                        // Priority sorrend:
                        // 1. Zöld - ha van Tábló
                        if ($tablo > 0) {
                            return 'success'; // Zöld
                        }

                        // 2. Halvány lila - ha van Retusált, de nincs Tábló
                        if ($retouch > 0) {
                            return 'info'; // Halvány lila/kék
                        }

                        // 3. Sárga - ha csak Kiválasztva van
                        if ($claimed > 0) {
                            return 'warning'; // Sárga
                        }

                        // 4. Szürke - ha nincs semmi
                        return 'gray';
                    })
                    ->tooltip(function (User $record): string {
                        $progress = $record->tabloProgress;
                        if (!$progress) {
                            return 'Nincs adat';
                        }

                        $stepsData = $progress->steps_data ?? [];

                        // Kiválasztva
                        $claimedCount = count($stepsData['claimed_photo_ids'] ?? []);

                        // Retusált
                        $retouchCount = count($stepsData['retouch_photo_ids'] ?? []);

                        // Tábló
                        $tabloCount = isset($stepsData['tablo_photo_id']) ? 1 : 0;

                        return "Kiválasztva: {$claimedCount}\nRetusált: {$retouchCount}\nTábló: {$tabloCount}";
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount('photos')
                            ->orderBy('photos_count', $direction);
                    }),

                Tables\Columns\TextColumn::make('tabloProgress.current_step')
                    ->label('Befejezett')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'completed' ? 'Igen' : 'Nem')
                    ->color(fn ($state) => $state === 'completed' ? 'success' : 'gray')
                    ->sortable()
                    ->default('Nem')
                    ->visible(false),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Regisztrálva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordClasses(function ($record) {
                // Check if record was created within the last 10 seconds
                $createdAt = $record->created_at;
                $tenSecondsAgo = now()->subSeconds(10);

                if ($createdAt && $createdAt->isAfter($tenSecondsAgo)) {
                    return 'fi-ta-row-new';
                }

                return null;
            })
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Szerepkör')
                    ->relationship('roles', 'name'),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('send_magic_link')
                        ->label('Belépési link küldése')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Belépési link küldése')
                        ->modalDescription(fn ($record) => "Belépési link küldése a következő felhasználónak: {$record->name} ({$record->email})")
                        ->action(function (User $record) {
                            static::sendMagicLinkToUser($record);

                            Notification::make()
                                ->title('Magic link elküldve')
                                ->success()
                                ->send();
                        }),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->label('Műveletek')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('send_magic_links')
                        ->label('Belépési link küldése')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Magic linkek küldése')
                        ->modalDescription(fn (Collection $records) => "Belépési link küldése {$records->count()} felhasználónak")
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                static::sendMagicLinkToUser($record);
                                $count++;
                            }

                            Notification::make()
                                ->title("{$count} magic link elküldve")
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(function ($query) {
                $newUserId = session('new_user_id');

                // Only show customer users (exclude guest users)
                $query->whereHas('roles', function ($q) {
                    $q->where('name', 'customer');
                });

                // Eager load relationships to avoid N+1 queries
                $query->with(['tabloRegistration.schoolClass', 'tabloProgress']);

                if ($newUserId) {
                    return $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$newUserId])
                        ->orderBy('created_at', 'desc');
                }

                return $query->orderBy('created_at', 'desc');
            })
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            PhotosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
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

    /**
     * Send magic link to a user
     */
    protected static function sendMagicLinkToUser(User $user): void
    {
        // Generate magic link (24 hours validity)
        $magicLinkService = app(MagicLinkService::class);
        $magicLink = $magicLinkService->generate($user, 24);

        // Find active email event for user_magic_login
        $emailEvent = EmailEvent::where('event_type', 'user_magic_login')
            ->where('is_active', true)
            ->first();

        if (!$emailEvent || !$emailEvent->emailTemplate) {
            \Illuminate\Support\Facades\Log::warning('No active email template found for user_magic_login event');
            return;
        }

        $template = $emailEvent->emailTemplate;

        // Prepare variables for email
        $variableService = app(EmailVariableService::class);
        $variables = $variableService->resolveVariables(
            user: $user,
            authData: [
                'magic_link' => $magicLink['url'],
            ]
        );

        // Send email
        $emailService = app(EmailService::class);
        $emailService->sendFromTemplate(
            template: $template,
            recipientEmail: $user->email,
            variables: $variables,
            recipientUser: $user,
            eventType: 'user_magic_login'
        );
    }
}
