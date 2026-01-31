<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TabloOrderAnalysisResource\Pages;
use App\Jobs\ProcessOrderEmailJob;
use App\Models\TabloOrderAnalysis;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TabloOrderAnalysisResource extends BaseResource
{

    protected static ?string $model = TabloOrderAnalysis::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Megrendelés elemzések';

    protected static ?string $modelLabel = 'Megrendelés elemzés';

    protected static ?string $pluralModelLabel = 'Megrendelés elemzések';

    protected static string|UnitEnum|null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'tablo-megrendeles-elemzesek';

    /**
     * Badge a navigation-ben a feldolgozás alatt lévők számával.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = TabloOrderAnalysis::whereIn('status', ['pending', 'processing'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Státusz')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Várakozik',
                        'processing' => 'Feldolgozás...',
                        'completed' => 'Kész',
                        'failed' => 'Hiba',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('school_name')
                    ->label('Iskola')
                    ->searchable()
                    ->weight('bold')
                    ->wrap()
                    ->formatStateUsing(fn (TabloOrderAnalysis $record): string =>
                        $record->school_name . ($record->class_name ? ' - ' . $record->class_name : '')
                    )
                    ->description(fn (TabloOrderAnalysis $record): ?string =>
                        $record->tags ? implode(' · ', $record->tags) : null
                    )
                    ->action(
                        Action::make('viewSummary')
                            ->modalHeading(fn (TabloOrderAnalysis $record): string =>
                                $record->school_name . ' - ' . ($record->class_name ?? '')
                            )
                            ->modalWidth('md')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Bezárás')
                            ->infolist(fn (TabloOrderAnalysis $record): array => [
                                Section::make('AI Összefoglaló')
                                    ->icon('heroicon-o-sparkles')
                                    ->schema([
                                        TextEntry::make('ai_summary')
                                            ->label('')
                                            ->state($record->ai_summary ?? 'Nincs összefoglaló')
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible(false),
                                Section::make('Címkék')
                                    ->icon('heroicon-o-tag')
                                    ->schema([
                                        TextEntry::make('tags')
                                            ->label('')
                                            ->state(fn () => $record->tags ? implode(', ', $record->tags) : 'Nincs címke')
                                            ->badge()
                                            ->color('primary'),
                                    ])
                                    ->collapsible(false)
                                    ->hidden(fn () => empty($record->tags)),
                            ])
                            ->visible(fn (TabloOrderAnalysis $record): bool => $record->isCompleted())
                    ),

                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Kapcsolattartó')
                    ->icon(fn (TabloOrderAnalysis $record) =>
                        $record->contact_phone || $record->contact_email ? 'heroicon-m-user' : null
                    )
                    ->iconPosition('after')
                    ->color('primary')
                    ->action(
                        Action::make('viewContact')
                            ->modalHeading('Kapcsolattartó')
                            ->modalWidth('md')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Bezárás')
                            ->infolist(fn (TabloOrderAnalysis $record): array => [
                                Section::make($record->contact_name ?? 'Kapcsolattartó')
                                    ->schema([
                                        TextEntry::make('contact_name_entry')
                                            ->label('Név')
                                            ->state($record->contact_name)
                                            ->copyable()
                                            ->hidden(! $record->contact_name),
                                        TextEntry::make('contact_email_entry')
                                            ->label('Email')
                                            ->state($record->contact_email)
                                            ->copyable()
                                            ->icon('heroicon-m-envelope')
                                            ->hidden(! $record->contact_email),
                                        TextEntry::make('contact_phone_entry')
                                            ->label('Telefon')
                                            ->state($record->contact_phone)
                                            ->copyable()
                                            ->icon('heroicon-m-phone')
                                            ->hidden(! $record->contact_phone),
                                    ])
                                    ->columns(1)
                                    ->compact(),
                            ])
                            ->visible(fn (TabloOrderAnalysis $record): bool =>
                                (bool) ($record->contact_name || $record->contact_email || $record->contact_phone)
                            )
                    ),

                Tables\Columns\TextColumn::make('persons_summary')
                    ->label('Létszám')
                    ->state(fn (TabloOrderAnalysis $record): string =>
                        "D: {$record->student_count} / T: {$record->teacher_count}"
                    )
                    ->badge()
                    ->color(fn (TabloOrderAnalysis $record) =>
                        ($record->student_count > 0 || $record->teacher_count > 0) ? 'info' : 'gray'
                    ),

                Tables\Columns\IconColumn::make('warnings_indicator')
                    ->label('!')
                    ->state(fn (TabloOrderAnalysis $record): bool => $record->hasWarnings())
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->action(
                        Action::make('showWarnings')
                            ->modalHeading('Figyelmeztetések')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Bezárás')
                            ->modalContent(fn (TabloOrderAnalysis $record): \Illuminate\Contracts\View\View =>
                                view('filament.resources.tablo-order-analysis.warnings-modal', [
                                    'warnings' => $record->warnings ?? [],
                                ])
                            )
                            ->visible(fn (TabloOrderAnalysis $record): bool => $record->hasWarnings())
                    ),

                Tables\Columns\TextColumn::make('projectEmail.email_date')
                    ->label('Dátum')
                    ->date('m.d')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Státusz')
                    ->options([
                        'pending' => 'Várakozik',
                        'processing' => 'Feldolgozás alatt',
                        'completed' => 'Kész',
                        'failed' => 'Hiba',
                    ]),

                Tables\Filters\TernaryFilter::make('has_project')
                    ->label('Kapcsolt projekt')
                    ->placeholder('Mind')
                    ->trueLabel('Van')
                    ->falseLabel('Nincs')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('tablo_project_id'),
                        false: fn (Builder $query) => $query->whereNull('tablo_project_id'),
                    ),
            ])
            ->actions([
                // PDF letöltés
                Action::make('downloadPdf')
                    ->label('')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->tooltip('PDF letöltése')
                    ->url(fn (TabloOrderAnalysis $record): ?string =>
                        $record->pdf_path ? route('order-pdf.download', $record) : null
                    )
                    ->openUrlInNewTab()
                    ->visible(fn (TabloOrderAnalysis $record): bool => (bool) $record->pdf_path),

                // Elemzés megtekintése
                Action::make('viewAnalysis')
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->tooltip('Elemzés megtekintése')
                    ->modalHeading('Megrendelés elemzés')
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Bezárás')
                    ->modalContent(fn (TabloOrderAnalysis $record): \Illuminate\Contracts\View\View =>
                        view('filament.resources.tablo-order-analysis.view-modal', [
                            'analysis' => $record,
                        ])
                    )
                    ->visible(fn (TabloOrderAnalysis $record): bool => $record->isCompleted()),

                // Újrafeldolgozás
                Action::make('reprocess')
                    ->label('')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->tooltip('Újrafeldolgozás')
                    ->requiresConfirmation()
                    ->modalHeading('Újrafeldolgozás')
                    ->modalDescription('Biztosan újra szeretnéd futtatni az AI elemzést?')
                    ->action(function (TabloOrderAnalysis $record): void {
                        $record->update([
                            'status' => 'pending',
                            'error_message' => null,
                        ]);

                        if ($record->project_email_id) {
                            ProcessOrderEmailJob::dispatch($record->project_email_id);
                        }

                        Notification::make()
                            ->title('Újrafeldolgozás elindítva')
                            ->success()
                            ->send();
                    }),

                // Projekt link
                Action::make('goToProject')
                    ->label('')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('success')
                    ->tooltip('Projekt megnyitása')
                    ->url(fn (TabloOrderAnalysis $record): ?string =>
                        $record->tablo_project_id
                            ? TabloProjectResource::getUrl('edit', ['record' => $record->tablo_project_id])
                            : null
                    )
                    ->visible(fn (TabloOrderAnalysis $record): bool => (bool) $record->tablo_project_id),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Nincs megrendelés elemzés')
            ->emptyStateDescription('A beérkező megrendelőlapok automatikusan feldolgozásra kerülnek.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTabloOrderAnalyses::route('/'),
        ];
    }
}
