<?php

namespace App\Filament\Resources\WorkSessions\RelationManagers;

use App\Models\User;
use App\Models\EmailTemplate;
use App\Models\EmailEvent;
use App\Services\MagicLinkService;
use App\Services\EmailService;
use App\Services\EmailVariableService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Felhasználók';

    protected static ?string $modelLabel = 'felhasználó';

    protected static ?string $pluralModelLabel = 'felhasználók';

    public function form(Schema $schema): Schema
    {
        return \App\Filament\Schemas\UserForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(fn ($record) => "{$record->name} ({$record->email})"),

                Tables\Columns\TextColumn::make('role')
                    ->label('Szerepkör')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        User::ROLE_SUPER_ADMIN => 'danger',
                        User::ROLE_PHOTO_ADMIN => 'warning',
                        User::ROLE_CUSTOMER => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        User::ROLE_SUPER_ADMIN => 'Super Admin',
                        User::ROLE_PHOTO_ADMIN => 'Fotós Admin',
                        User::ROLE_CUSTOMER => 'Megrendelő',
                        default => ucfirst((string) $state),
                    }),

                Tables\Columns\TextColumn::make('tabloProgress.current_step')
                    ->label('Lépés')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'claiming' => 'Kiválasztás',
                        'registration' => 'Regisztráció',
                        'retouch' => 'Retusálás',
                        'tablo' => 'Tabló',
                        'completed' => 'Befejezve',
                        default => '-',
                    })
                    ->color(fn ($state) => match($state) {
                        'claiming' => 'warning',
                        'registration' => 'info',
                        'retouch' => 'primary',
                        'tablo' => 'success',
                        'completed' => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->default('-'),

                Tables\Columns\TextColumn::make('selected_photos_count')
                    ->label('Képek')
                    ->badge()
                    ->color('info')
                    ->state(function (User $record) {
                        $progress = $record->tabloProgress;
                        if (!$progress || !$progress->steps_data) {
                            return 0;
                        }
                        $stepsData = $progress->steps_data;
                        return count($stepsData['claimed_photo_ids'] ?? []);
                    })
                    ->default(0),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Szerepkör')
                    ->options([
                        User::ROLE_SUPER_ADMIN => 'Super Admin',
                        User::ROLE_PHOTO_ADMIN => 'Fotós Adminisztrátor',
                        User::ROLE_CUSTOMER => 'Megrendelő',
                    ]),
            ])
            ->headerActions([
                Action::make('create_user')
                    ->label('Új felhasználó létrehozása')
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading('Új felhasználó létrehozása')
                    ->modalWidth('lg')
                    ->visible(function () {
                        $workSession = $this->getOwnerRecord();
                        $parent = $workSession->parentWorkSession ?? $workSession;
                        return !$parent->is_tablo_mode;
                    })
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Név')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'users', column: 'email'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(255),
                    ])
                    ->action(function (array $data) {
                        $workSession = $this->getOwnerRecord();

                        // Create user with random password
                        $password = Str::random(16);
                        $user = User::create([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'phone' => $data['phone'] ?? null,
                            'password' => bcrypt($password),
                            'password_set' => false,
                        ]);

                        // Assign tablo role
                        $user->assignRole(User::ROLE_TABLO);

                        // Attach user to work session
                        $workSession->users()->attach($user->id);

                        // Generate magic link tied to work session
                        $magicLinkService = app(MagicLinkService::class);
                        $magicLink = $magicLinkService->generateForWorkSession($user, $workSession, 30);

                        // Send email with magic link
                        $this->sendMagicLinkEmail($user, $workSession, $magicLink['url']);

                        \Filament\Notifications\Notification::make()
                            ->title('Felhasználó létrehozva és magic link elküldve')
                            ->success()
                            ->send();
                    }),

                Action::make('attach_users')
                    ->label('Meglévő felhasználó hozzáadása')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Felhasználó hozzáadása')
                    ->visible(function () {
                        $workSession = $this->getOwnerRecord();
                        $parent = $workSession->parentWorkSession ?? $workSession;
                        return !$parent->is_tablo_mode;
                    })
                    ->form([
                        Forms\Components\Select::make('user_ids')
                            ->label('Felhasználók')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                // Get users created within last 10 minutes
                                $recentCutoff = now()->subMinutes(10);

                                $users = User::query()
                                    ->orderByRaw("CASE WHEN created_at >= ? THEN 0 ELSE 1 END", [$recentCutoff])
                                    ->orderBy('created_at', 'desc')
                                    ->get();

                                return $users->mapWithKeys(fn ($user) => [$user->id => "{$user->name} ({$user->email})"]);
                            })
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $workSession = $this->getOwnerRecord();
                        $workSession->users()->attach($data['user_ids']);

                        \Filament\Notifications\Notification::make()
                            ->title('Felhasználók hozzáadva')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('send_magic_link')
                        ->label('Belépési link küldése')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->modalHeading('Belépési link küldése')
                        ->modalDescription(fn ($record) => "Belépési link küldése a következő felhasználónak: {$record->name} ({$record->email})")
                        ->form(function () {
                            $workSession = $this->getOwnerRecord();

                            return [
                                Forms\Components\Checkbox::make('include_code')
                                    ->label('Csatoljuk a 6-jegyű belépési kódot')
                                    ->helperText(function () use ($workSession) {
                                        if ($workSession->digit_code) {
                                            return "Kód: {$workSession->digit_code} - Ezzel mások is hozzáférhetnek az albumhoz";
                                        }
                                        return 'Nincs aktív belépési kód';
                                    })
                                    ->default(false)
                                    ->disabled(fn () => !$workSession->digit_code || !$workSession->digit_code_enabled),
                            ];
                        })
                        ->action(function (User $record, array $data) {
                            $workSession = $this->getOwnerRecord();
                            $includeCode = $data['include_code'] ?? false;
                            $this->sendMagicLinkEmailForWorkSession($record, $workSession, $includeCode);

                            \Filament\Notifications\Notification::make()
                                ->title('Magic link elküldve')
                                ->success()
                                ->send();
                        }),
                    DetachAction::make()
                        ->label('Eltávolítás'),
                ])
                    ->label('Műveletek')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('send_magic_links')
                        ->label('Magic link küldése')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->modalHeading('Magic linkek küldése')
                        ->modalDescription(fn (Collection $records) => "Magic link küldése {$records->count()} felhasználónak")
                        ->form(function () {
                            $workSession = $this->getOwnerRecord();

                            return [
                                Forms\Components\Checkbox::make('include_code')
                                    ->label('Csatoljuk a 6-jegyű belépési kódot')
                                    ->helperText(function () use ($workSession) {
                                        if ($workSession->digit_code) {
                                            return "Kód: {$workSession->digit_code} - Ezzel mások is hozzáférhetnek az albumhoz";
                                        }
                                        return 'Nincs aktív belépési kód';
                                    })
                                    ->default(false)
                                    ->disabled(fn () => !$workSession->digit_code || !$workSession->digit_code_enabled),
                            ];
                        })
                        ->action(function (Collection $records, array $data) {
                            $workSession = $this->getOwnerRecord();
                            $includeCode = $data['include_code'] ?? false;
                            $count = 0;

                            foreach ($records as $record) {
                                $this->sendMagicLinkEmailForWorkSession($record, $workSession, $includeCode);
                                $count++;
                            }

                            \Filament\Notifications\Notification::make()
                                ->title("{$count} magic link elküldve")
                                ->success()
                                ->send();
                        }),
                    DetachBulkAction::make()
                        ->label('Kijelöltek eltávolítása'),
                ]),
            ]);
    }

    /**
     * Send magic link email to user (for new user creation)
     */
    protected function sendMagicLinkEmail(User $user, $workSession, string $magicLinkUrl): void
    {
        // Find active email event for work_session_created
        $emailEvent = EmailEvent::where('event_type', 'work_session_created')
            ->where('is_active', true)
            ->first();

        if (!$emailEvent || !$emailEvent->emailTemplate) {
            \Illuminate\Support\Facades\Log::warning('No active email template found for work_session_created event');
            return;
        }

        $template = $emailEvent->emailTemplate;

        // Prepare variables for email
        $variableService = app(EmailVariableService::class);
        $variables = $variableService->resolveVariables(
            user: $user,
            workSession: $workSession,
            authData: [
                'magic_link' => $magicLinkUrl,
            ]
        );

        // Send email
        $emailService = app(EmailService::class);
        $emailService->sendFromTemplate(
            template: $template,
            recipientEmail: $user->email,
            variables: $variables,
            recipientUser: $user,
            eventType: 'work_session_created'
        );
    }

    /**
     * Send magic link email for existing user in work session
     */
    protected function sendMagicLinkEmailForWorkSession(User $user, $workSession, bool $includeCode = false): void
    {
        // Generate magic link tied to work session
        $magicLinkService = app(MagicLinkService::class);
        $magicLink = $magicLinkService->generateForWorkSession($user, $workSession, 30);

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
            workSession: $workSession,
            authData: [
                'magic_link' => $magicLink['url'],
                'include_code' => $includeCode,
                'digit_code' => $workSession->digit_code ?? '',
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
