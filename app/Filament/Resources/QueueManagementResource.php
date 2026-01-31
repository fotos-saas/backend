<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QueueManagementResource\Pages;
use BackedEnum;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Resource for managing queue jobs.
 */
class QueueManagementResource extends BaseResource
{

    protected static function getPermissionKey(): string
    {
        return 'queue-management';
    }

    protected static ?string $model = null;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Queue Kezelés';

    protected static ?string $modelLabel = 'Job';

    protected static ?string $pluralModelLabel = 'Queue Job-ok';

    protected static ?int $navigationSort = 100;

    /**
     * Override to return a dummy query builder.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'failed_jobs';
        };

        return $model->newQuery()->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordAction(null)
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('queue')
                    ->label('Queue')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('payload.displayName')
                    ->label('Job típus')
                    ->formatStateUsing(fn ($state) => class_basename($state ?? 'Unknown'))
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Státusz')
                    ->color(fn ($state) => match ($state) {
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending' => 'Várakozik',
                        'failed' => 'Sikertelen',
                        default => 'Ismeretlen',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('attempts')
                    ->label('Próbálkozások')
                    ->badge()
                    ->color(fn ($state) => $state > 2 ? 'danger' : ($state > 0 ? 'warning' : 'success')),
            ])
            ->headerActions([
                Actions\Action::make('clear_default')
                    ->label('Default Queue Kiürítése')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function () {
                        Artisan::call('queue:clear', ['connection' => 'redis', '--queue' => 'default']);
                        Notification::make()->title('Default queue kiürítve')->success()->send();
                    }),

                Actions\Action::make('clear_face')
                    ->label('Face Recognition Queue Kiürítése')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function () {
                        Artisan::call('queue:clear', ['connection' => 'redis', '--queue' => 'face-recognition']);
                        Notification::make()->title('Face-recognition queue kiürítve')->success()->send();
                    }),

                Actions\Action::make('clear_all')
                    ->label('MINDEN Queue Kiürítése')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Összes queue törlése')
                    ->modalDescription('Biztosan törölni szeretnéd az ÖSSZES queue-t?')
                    ->action(function () {
                        Artisan::call('queue:clear', ['connection' => 'redis', '--queue' => 'default']);
                        Artisan::call('queue:clear', ['connection' => 'redis', '--queue' => 'face-recognition']);
                        DB::table('failed_jobs')->truncate();
                        Notification::make()->title('Összes queue törölve')->success()->send();
                    }),

                Actions\Action::make('clear_failed')
                    ->label('Sikertelen Job-ok Törlése')
                    ->icon('heroicon-o-x-mark')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function () {
                        $count = DB::table('failed_jobs')->count();
                        DB::table('failed_jobs')->truncate();
                        Notification::make()->title("$count sikertelen job törölve")->success()->send();
                    }),
            ])
            ->actions([
                Actions\Action::make('retry')
                    ->label('Újra')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record['status'] === 'failed')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        if (isset($record['failed_jobs_id'])) {
                            DB::table('failed_jobs')->where('id', $record['failed_jobs_id'])->delete();
                            Notification::make()->title('Job újraindítva')->success()->send();
                        }
                    }),

                Actions\Action::make('delete')
                    ->label('Törlés')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record['status'] === 'failed')
                    ->action(function ($record) {
                        if (isset($record['failed_jobs_id'])) {
                            DB::table('failed_jobs')->where('id', $record['failed_jobs_id'])->delete();
                            Notification::make()->title('Job törölve')->success()->send();
                        }
                    }),
            ])
            ->poll('10s')
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQueueJobs::route('/'),
        ];
    }
}
