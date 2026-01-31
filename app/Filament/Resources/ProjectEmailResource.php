<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectEmailResource\Pages;
use App\Models\ProjectEmail;
use App\Models\TabloProject;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Infolists;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class ProjectEmailResource extends BaseResource
{

    protected static ?string $model = ProjectEmail::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Emailek';

    protected static ?string $modelLabel = 'Email';

    protected static ?string $pluralModelLabel = 'Emailek';

    protected static string | UnitEnum | null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::needsReply()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('direction')
                    ->label('')
                    ->icon(fn (string $state): string => match ($state) {
                        'inbound' => 'heroicon-o-arrow-down-tray',
                        'outbound' => 'heroicon-o-arrow-up-tray',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'inbound' => 'info',
                        'outbound' => 'success',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'inbound' => 'Bejövő',
                        'outbound' => 'Kimenő',
                    }),

                Tables\Columns\TextColumn::make('email_date')
                    ->label('Dátum')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('from_display')
                    ->label('Feladó')
                    ->state(fn (ProjectEmail $record) => $record->from_name ?: $record->from_email)
                    ->description(fn (ProjectEmail $record) => $record->from_name ? $record->from_email : null)
                    ->searchable(['from_email', 'from_name']),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Tárgy')
                    ->searchable()
                    ->limit(50)
                    ->weight(fn (ProjectEmail $record) => $record->is_read ? 'normal' : 'bold')
                    ->description(fn (ProjectEmail $record) => \Illuminate\Support\Str::limit($record->body_preview, 80)),

                Tables\Columns\TextColumn::make('project.display_name')
                    ->label('Projekt')
                    ->placeholder('Nincs hozzárendelve')
                    ->badge()
                    ->color(fn (ProjectEmail $record) => $record->tablo_project_id ? 'success' : 'gray'),

                Tables\Columns\IconColumn::make('needs_reply')
                    ->label('Válasz')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->tooltip(fn (ProjectEmail $record) => $record->needs_reply && ! $record->is_replied
                        ? 'Válaszra vár'
                        : ($record->is_replied ? 'Megválaszolva' : 'Nem igényel választ')),

                Tables\Columns\IconColumn::make('has_attachments')
                    ->label('')
                    ->state(fn (ProjectEmail $record) => $record->hasAttachments())
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('')
                    ->trueColor('gray'),
            ])
            ->defaultSort('email_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('direction')
                    ->label('Irány')
                    ->options([
                        'inbound' => 'Bejövő',
                        'outbound' => 'Kimenő',
                    ]),

                Tables\Filters\TernaryFilter::make('needs_reply')
                    ->label('Válaszra vár')
                    ->queries(
                        true: fn (Builder $query) => $query->where('needs_reply', true)->where('is_replied', false),
                        false: fn (Builder $query) => $query->where(fn ($q) => $q->where('needs_reply', false)->orWhere('is_replied', true)),
                    ),

                Tables\Filters\TernaryFilter::make('has_project')
                    ->label('Van projekt')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('tablo_project_id'),
                        false: fn (Builder $query) => $query->whereNull('tablo_project_id'),
                    ),

                Tables\Filters\SelectFilter::make('tablo_project_id')
                    ->label('Projekt')
                    ->relationship('project', 'id')
                    ->getOptionLabelFromRecordUsing(fn (TabloProject $record) => $record->display_name)
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('view')
                    ->label('Megtekintés')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (ProjectEmail $record) => $record->subject)
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Bezárás')
                    ->infolist(fn (ProjectEmail $record) => [
                        Section::make('Email részletek')
                            ->schema([
                                Infolists\Components\TextEntry::make('from_email')
                                    ->label('Feladó')
                                    ->state(fn () => $record->from_name ? "{$record->from_name} <{$record->from_email}>" : $record->from_email)
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('to_email')
                                    ->label('Címzett')
                                    ->state(fn () => $record->to_name ? "{$record->to_name} <{$record->to_email}>" : $record->to_email)
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('email_date')
                                    ->label('Dátum')
                                    ->dateTime('Y-m-d H:i:s'),
                                Infolists\Components\TextEntry::make('direction')
                                    ->label('Irány')
                                    ->badge()
                                    ->color(fn (string $state) => $state === 'inbound' ? 'info' : 'success')
                                    ->formatStateUsing(fn (string $state) => $state === 'inbound' ? 'Bejövő' : 'Kimenő'),
                            ])
                            ->columns(2),
                        Section::make('Üzenet')
                            ->schema([
                                Infolists\Components\TextEntry::make('clean_body')
                                    ->hiddenLabel()
                                    ->html()
                                    ->state(fn () => new HtmlString($record->clean_body_html ?: nl2br(e($record->clean_body_text ?? 'Nincs tartalom')))),
                            ]),
                        Section::make('Teljes email (idézetekkel)')
                            ->schema([
                                Infolists\Components\TextEntry::make('full_body')
                                    ->hiddenLabel()
                                    ->html()
                                    ->state(fn () => new HtmlString($record->body_html ?: nl2br(e($record->body_text ?? 'Nincs tartalom')))),
                            ])
                            ->collapsed()
                            ->collapsible(),
                        Section::make('Csatolmányok')
                            ->schema([
                                Infolists\Components\TextEntry::make('attachments')
                                    ->label('')
                                    ->state(function () use ($record) {
                                        if (! $record->attachments) {
                                            return 'Nincs csatolmány';
                                        }

                                        return collect($record->attachments)
                                            ->map(fn ($a) => "{$a['name']} ({$a['mime_type']})")
                                            ->join(', ');
                                    }),
                            ])
                            ->visible(fn () => $record->hasAttachments()),
                    ])
                    ->after(function (ProjectEmail $record) {
                        if (! $record->is_read) {
                            $record->update(['is_read' => true]);
                        }
                    }),

                Action::make('assignProject')
                    ->label('Projekt')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('tablo_project_id')
                            ->label('Projekt kiválasztása')
                            ->options(fn () => TabloProject::with('school')
                                ->get()
                                ->mapWithKeys(fn ($p) => [$p->id => $p->display_name])
                            )
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (ProjectEmail $record, array $data) {
                        $record->update(['tablo_project_id' => $data['tablo_project_id']]);
                    }),

                Action::make('markReplied')
                    ->label('Megválaszolva')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ProjectEmail $record) => $record->needs_reply && ! $record->is_replied)
                    ->requiresConfirmation()
                    ->action(fn (ProjectEmail $record) => $record->update(['is_replied' => true])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('assignProjectBulk')
                        ->label('Projekthez rendelés')
                        ->icon('heroicon-o-link')
                        ->form([
                            Forms\Components\Select::make('tablo_project_id')
                                ->label('Projekt kiválasztása')
                                ->options(fn () => TabloProject::with('school')
                                    ->get()
                                    ->mapWithKeys(fn ($p) => [$p->id => $p->display_name])
                                )
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each->update(['tablo_project_id' => $data['tablo_project_id']]);
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('markRepliedBulk')
                        ->label('Megválaszoltnak jelölés')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['is_replied' => true]))
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectEmails::route('/'),
        ];
    }
}
