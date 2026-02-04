<?php

namespace App\Filament\Resources\TabloPersonResource\Tables;

use App\Enums\TabloPersonType;
use App\Filament\Resources\TabloProjectResource;
use App\Models\TabloProject;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TabloPersonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager loading N+1 elkerülésére
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['project.school']))
            ->columns([
                ImageColumn::make('photo_thumb_url')
                    ->label('')
                    ->circular()
                    ->size(30)
                    ->width(36)
                    ->alignCenter()
                    ->defaultImageUrl(asset('images/placeholder-person.svg')),

                TextColumn::make('name')
                    ->label('Név')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('project.school.name')
                    ->label('Iskola')
                    ->searchable()
                    ->sortable()
                    ->visible(false),

                TextColumn::make('project.class_name')
                    ->label('Osztály')
                    ->toggleable()
                    ->visible(fn ($livewire) => $livewire->activeTab !== 'students'),

                TextColumn::make('project.class_year')
                    ->label('Évfolyam')
                    ->toggleable(),

                TextColumn::make('note')
                    ->label('Megjegyzés')
                    ->limit(30)
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('position')
                    ->label('Pozíció')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Group::make('project.school.name')
                    ->label('Iskola')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false),
                Group::make('project.class_name')
                    ->label('Osztály')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false),
                Group::make('tablo_project_id')
                    ->label('Iskola és Osztály')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn ($record) => ($record->project?->school?->name ?? '') . ' ' . ($record->project?->class_name ?? '')),
                Group::make('type')
                    ->label('Típus')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn ($record) => TabloPersonType::tryFrom($record->type)?->pluralLabel() ?? 'Ismeretlenek'),
            ])
            ->defaultSort('name')
            ->striped()
            ->filters([
                SelectFilter::make('tablo_project_id')
                    ->label('Projekt')
                    ->options(fn () => TabloProject::query()
                        ->with('school:id,name')
                        ->select('id', 'class_name', 'school_id')
                        ->get()
                        ->mapWithKeys(fn ($p) => [
                            $p->id => ($p->school?->name ?? '') . ' - ' . $p->class_name,
                        ])
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('goToProject')
                        ->label('Ugrás a projekthez')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn ($record) => TabloProjectResource::getUrl('edit', ['record' => $record->tablo_project_id]))
                        ->openUrlInNewTab(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Műveletek'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nincs személy')
            ->emptyStateDescription('Nincsenek személyek ebben a listában.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
