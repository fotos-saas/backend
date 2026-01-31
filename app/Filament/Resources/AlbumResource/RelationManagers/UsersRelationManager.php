<?php

namespace App\Filament\Resources\AlbumResource\RelationManagers;

use App\Filament\ActionGroups\UserImportActionGroup;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'class';

    protected static ?string $title = 'Megrendelők';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Override to use User model instead of the relationship
     * Only show users who have assigned photos in this album
     */
    protected function getTableQuery(): Builder
    {
        $album = $this->getOwnerRecord();

        // Get users who have assigned photos in this album
        $query = User::query()
            ->where('role', User::ROLE_CUSTOMER)
            ->whereHas('photos', function ($photoQuery) use ($album) {
                $photoQuery->where('album_id', $album->id);
            });

        // Sort new users to the top
        $newUserId = session('new_user_id');
        if ($newUserId) {
            $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$newUserId])
                ->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }

    public function form(Schema $schema): Schema
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
                    ->helperText('Ha az email cím már létezik, a meglévő felhasználó lesz hozzácsatolva az albumhoz.'),

                Forms\Components\TextInput::make('password')
                    ->label('Jelszó')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->minLength(6)
                    ->helperText('Hagyd üresen automatikus jelszó generálásához. Szerkesztéskor üresen hagyva nem változik.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-envelope')
                    ->copyable(),

                Tables\Columns\TextColumn::make('photos_count')
                    ->label('Képek száma')
                    ->getStateUsing(function ($record) {
                        $album = $this->getOwnerRecord();

                        return $album->photos()
                            ->where('assigned_user_id', $record->id)
                            ->count();
                    })
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
            ])
            ->filters([
                //
            ])
            ->recordClasses(function ($record) {
                // Highlight new users created within the last 10 seconds
                $createdAt = $record->created_at;
                $tenSecondsAgo = now()->subSeconds(10);

                if ($createdAt && $createdAt->isAfter($tenSecondsAgo)) {
                    return 'fi-ta-row-new';
                }

                return null;
            })
            ->headerActions([
                Action::make('create_user')
                    ->label('Új megrendelő')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Új megrendelő létrehozása')
                    ->schema($this->form(new Schema)->getComponents())
                    ->action(function (array $data) {
                        $album = $this->getOwnerRecord();

                        // Check if user already exists
                        $existingUser = User::where('email', $data['email'])->first();

                        if ($existingUser) {
                            // User exists, attach to album
                            static::attachUserToAlbum($existingUser, $album);

                            // Store user ID in session for highlighting
                            session()->put('new_user_id', $existingUser->id);

                            \Filament\Notifications\Notification::make()
                                ->title('Megrendelő hozzácsatolva')
                                ->body("A meglévő felhasználó ({$existingUser->name}) sikeresen hozzácsatolva az albumhoz.")
                                ->success()
                                ->send();
                        } else {
                            // Create new user
                            // Set class_id from album if available
                            if ($album->class_id) {
                                $data['class_id'] = $album->class_id;
                            }

                            // Set role to customer
                            $data['role'] = User::ROLE_CUSTOMER;

                            // Hash password or generate random one
                            if (isset($data['password']) && filled($data['password'])) {
                                $data['password'] = Hash::make($data['password']);
                            } else {
                                $data['password'] = Hash::make(static::generateRandomPassword());
                            }

                            $user = User::create($data);

                            // Attach new user to album
                            static::attachUserToAlbum($user, $album);

                            // Store new user ID in session for highlighting
                            session()->put('new_user_id', $user->id);

                            \Filament\Notifications\Notification::make()
                                ->title('Megrendelő létrehozva')
                                ->body('Új megrendelő sikeresen létrehozva és hozzácsatolva az albumhoz.')
                                ->success()
                                ->send();
                        }
                    }),

                UserImportActionGroup::make(
                    allowClassSelection: false,
                    fixedClassId: $this->getOwnerRecord()->class_id,
                    albumId: $this->getOwnerRecord()->id,
                ),

                Action::make('detachAll')
                    ->label('Összes hozzárendelés törlése')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Összes megrendelő hozzárendelésének törlése')
                    ->modalDescription('Ez az albumban lévő összes kép hozzárendelését törli (a képek és felhasználók megmaradnak, csak a kapcsolat szűnik meg).')
                    ->modalSubmitActionLabel('Igen, törlöm')
                    ->action(function () {
                        $album = $this->getOwnerRecord();
                        $count = $album->photos()->whereNotNull('assigned_user_id')->count();

                        // Set all assigned_user_id to NULL for this album
                        $album->photos()->update(['assigned_user_id' => null]);

                        \Filament\Notifications\Notification::make()
                            ->title('Hozzárendelések törölve')
                            ->body("{$count} kép hozzárendelése törölve.")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('view_photos')
                        ->label('Képek megtekintése')
                        ->icon('heroicon-o-photo')
                        ->url(function ($record) {
                            $album = $this->getOwnerRecord();
                            $baseUrl = route('filament.admin.resources.photos.index');

                            $query = http_build_query([
                                'filters' => [
                                    'album_id' => [
                                        'value' => $album->id,
                                    ],
                                    'assigned_user_id' => [
                                        'values' => [$record->id],
                                    ],
                                ],
                            ]);

                            return "{$baseUrl}?{$query}";
                        })
                        ->openUrlInNewTab(),

                    Action::make('edit_user')
                        ->label('Szerkesztés')
                        ->icon('heroicon-o-pencil')
                        ->modalHeading('Megrendelő szerkesztése')
                        ->fillForm(fn ($record) => [
                            'name' => $record->name,
                            'email' => $record->email,
                        ])
                        ->schema($this->form(new Schema)->getComponents())
                        ->action(function (array $data, $record) {
                            if (isset($data['password']) && filled($data['password'])) {
                                $data['password'] = Hash::make($data['password']);
                            } else {
                                unset($data['password']);
                            }

                            $record->update($data);

                            \Filament\Notifications\Notification::make()
                                ->title('Megrendelő frissítve')
                                ->success()
                                ->send();
                        }),

                    Action::make('detach')
                        ->label('Hozzárendelés törlése')
                        ->icon('heroicon-o-x-mark')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Hozzárendelés törlése')
                        ->modalDescription(fn ($record) => "Biztosan törölni szeretnéd {$record->name} összes kép hozzárendelését ebből az albumból? A felhasználó és a képek megmaradnak, csak a kapcsolat szűnik meg.")
                        ->modalSubmitActionLabel('Igen, törlöm')
                        ->action(function ($record) {
                            $album = $this->getOwnerRecord();
                            $count = $album->photos()
                                ->where('assigned_user_id', $record->id)
                                ->count();

                            // Set assigned_user_id to NULL for this user's photos in this album
                            $album->photos()
                                ->where('assigned_user_id', $record->id)
                                ->update(['assigned_user_id' => null]);

                            \Filament\Notifications\Notification::make()
                                ->title('Hozzárendelés törölve')
                                ->body("{$count} kép hozzárendelése törölve {$record->name} felhasználótól.")
                                ->success()
                                ->send();
                        }),

                    Action::make('delete_user')
                        ->label('Törlés')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Megrendelő törlése')
                        ->modalDescription('Figyelem! Ez törli a felhasználót a rendszerből, minden hozzárendelésével és megrendelésével együtt!')
                        ->action(function ($record) {
                            $record->delete();

                            \Filament\Notifications\Notification::make()
                                ->title('Megrendelő törölve')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('Műveletek')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('detachBulk')
                        ->label('Hozzárendelések törlése')
                        ->icon('heroicon-o-x-mark')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Kijelölt hozzárendelések törlése')
                        ->modalDescription('A kijelölt felhasználók összes kép hozzárendelése törlődik ebből az albumból. A felhasználók és képek megmaradnak.')
                        ->action(function ($records) {
                            $album = $this->getOwnerRecord();
                            $userIds = $records->pluck('id')->toArray();
                            $count = $album->photos()
                                ->whereIn('assigned_user_id', $userIds)
                                ->count();

                            $album->photos()
                                ->whereIn('assigned_user_id', $userIds)
                                ->update(['assigned_user_id' => null]);

                            \Filament\Notifications\Notification::make()
                                ->title('Hozzárendelések törölve')
                                ->body("{$count} kép hozzárendelése törölve.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('deleteBulk')
                        ->label('Törlés')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Megrendelők törlése')
                        ->modalDescription('Figyelem! Ez törli a kijelölt felhasználókat a rendszerből, minden adatukkal együtt!')
                        ->action(function ($records) {
                            $count = $records->count();

                            foreach ($records as $record) {
                                $record->delete();
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Megrendelők törölve')
                                ->body("{$count} megrendelő törölve.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->poll('10s');
    }

    /**
     * Attach a user to the current album by assigning at least one photo.
     *
     * @param  User  $user  User instance
     * @param  \App\Models\Album  $album  Album instance
     */
    protected static function attachUserToAlbum(User $user, \App\Models\Album $album): void
    {
        // Check if user already has photos in this album
        $existingPhoto = $album->photos()
            ->where('assigned_user_id', $user->id)
            ->first();

        // If user doesn't have any photos in this album yet, assign one unassigned photo
        if (! $existingPhoto) {
            $unassignedPhoto = $album->photos()
                ->whereNull('assigned_user_id')
                ->first();

            if ($unassignedPhoto) {
                $unassignedPhoto->update(['assigned_user_id' => $user->id]);
            }

            // Update user's created_at to current time for highlighting
            $user->created_at = now();
            $user->save();
        }

        // Update user's class if they don't have one and album has a class
        if (! $user->class_id && $album->class_id) {
            $user->update(['class_id' => $album->class_id]);
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
