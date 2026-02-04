<?php

namespace App\Filament\Resources\TabloPersonResource\Pages;

use App\Enums\TabloPersonType;
use App\Filament\Actions\PhotoUploadAction;
use App\Filament\Pages\MissingSummaryPage;
use App\Filament\Resources\TabloPersonResource\TabloPersonResource;
use App\Services\TabloPersonService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListTabloPersons extends ListRecords
{
    protected static string $resource = TabloPersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('summary')
                ->label('Összesítő')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->url(MissingSummaryPage::getUrl()),

            PhotoUploadAction::make(),
        ];
    }

    /**
     * Projekt filter értékének kiolvasása (DRY helper)
     */
    protected function getProjectFilter(): ?int
    {
        $value = $this->tableFilters['tablo_project_id']['value']
            ?? request()->input('tableFilters.tablo_project_id.value');

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Service instance getter (lazy load)
     */
    protected function getService(): TabloPersonService
    {
        return app(TabloPersonService::class);
    }

    public function getTabs(): array
    {
        $projectFilter = $this->getProjectFilter();
        $counts = $this->getService()->getTabCounts($projectFilter);

        return [
            'teachers' => Tab::make(TabloPersonType::TEACHER->pluralLabel())
                ->icon(TabloPersonType::TEACHER->icon())
                ->badgeColor(TabloPersonType::TEACHER->badgeColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', TabloPersonType::TEACHER->value))
                ->badge($counts['teachers']),

            'students' => Tab::make(TabloPersonType::STUDENT->pluralLabel())
                ->icon(TabloPersonType::STUDENT->icon())
                ->badgeColor(TabloPersonType::STUDENT->badgeColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', TabloPersonType::STUDENT->value))
                ->badge($counts['students']),

            'all' => Tab::make('Mind')
                ->icon('heroicon-o-list-bullet')
                ->badge($counts['all']),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        $tabFromUrl = request()->query('activeTab');

        if ($tabFromUrl && in_array($tabFromUrl, ['teachers', 'students', 'all'])) {
            return $tabFromUrl;
        }

        return 'teachers';
    }

    /**
     * Első betöltéskor beállítjuk a csoportosítást és filter-t
     */
    public function mount(): void
    {
        parent::mount();

        $projectFilter = request()->input('tableFilters.tablo_project_id.value');

        if ($projectFilter && is_numeric($projectFilter)) {
            // Projekt filter aktív: beállítjuk explicit
            $this->tableFilters['tablo_project_id']['value'] = (int) $projectFilter;
            $this->activeTab = 'all';
            $this->tableGrouping = $this->getService()->getGroupingForTab(null, (int) $projectFilter);
        } else {
            // Tab-függő csoportosítás
            $this->tableGrouping = $this->getService()->getGroupingForTab($this->activeTab, null);
        }
    }

    /**
     * Tab váltáskor csoportosítás frissítése
     */
    public function updatedActiveTab(): void
    {
        $this->tableGrouping = $this->getService()->getGroupingForTab(
            $this->activeTab,
            $this->getProjectFilter()
        );

        parent::updatedActiveTab();
    }

    public function table(Table $table): Table
    {
        $table = parent::table($table);

        $projectFilter = $this->getProjectFilter();

        if ($projectFilter) {
            // Projekt szűrésnél: csak Típus csoportosítás engedélyezett
            $table->groups([
                Group::make('type')
                    ->label('Típus')
                    ->collapsible(false)
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn ($record) => $this->getService()->getTypeLabel($record->type)),
            ]);
        }

        return $table;
    }
}
