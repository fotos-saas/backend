<?php

namespace Tests\Unit\Services;

use App\Enums\TabloPersonType;
use App\Models\TabloPerson;
use App\Models\TabloProject;
use App\Services\TabloPersonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TabloPersonServiceTest extends TestCase
{
    use RefreshDatabase;

    private TabloPersonService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TabloPersonService;
        Cache::flush();
    }

    public function test_getTabCounts_returns_correct_counts(): void
    {
        // Arrange
        $project = TabloProject::factory()->create();

        TabloPerson::factory()->count(3)->create([
            'tablo_project_id' => $project->id,
            'type' => TabloPersonType::TEACHER->value,
        ]);

        TabloPerson::factory()->count(5)->create([
            'tablo_project_id' => $project->id,
            'type' => TabloPersonType::STUDENT->value,
        ]);

        // Act
        $counts = $this->service->getTabCounts();

        // Assert
        $this->assertEquals(3, $counts['teachers']);
        $this->assertEquals(5, $counts['students']);
        $this->assertEquals(8, $counts['all']);
    }

    public function test_getTabCounts_filters_by_project(): void
    {
        // Arrange
        $project1 = TabloProject::factory()->create();
        $project2 = TabloProject::factory()->create();

        TabloPerson::factory()->count(2)->create([
            'tablo_project_id' => $project1->id,
            'type' => TabloPersonType::TEACHER->value,
        ]);

        TabloPerson::factory()->count(4)->create([
            'tablo_project_id' => $project2->id,
            'type' => TabloPersonType::STUDENT->value,
        ]);

        // Act
        $countsProject1 = $this->service->getTabCounts($project1->id);
        $countsProject2 = $this->service->getTabCounts($project2->id);

        // Assert
        $this->assertEquals(2, $countsProject1['teachers']);
        $this->assertEquals(0, $countsProject1['students']);
        $this->assertEquals(2, $countsProject1['all']);

        $this->assertEquals(0, $countsProject2['teachers']);
        $this->assertEquals(4, $countsProject2['students']);
        $this->assertEquals(4, $countsProject2['all']);
    }

    public function test_getTabCounts_uses_cache(): void
    {
        // Arrange
        $project = TabloProject::factory()->create();

        TabloPerson::factory()->create([
            'tablo_project_id' => $project->id,
            'type' => TabloPersonType::TEACHER->value,
        ]);

        // Act - első hívás
        $this->service->getTabCounts();

        // Új rekord hozzáadása cache után
        TabloPerson::factory()->create([
            'tablo_project_id' => $project->id,
            'type' => TabloPersonType::TEACHER->value,
        ]);

        // Assert - cache-ből jön, így még 1-et mutat
        $cachedCounts = $this->service->getTabCounts();
        $this->assertEquals(1, $cachedCounts['teachers']);
    }

    public function test_clearCountCache_invalidates_cache(): void
    {
        // Arrange
        $project = TabloProject::factory()->create();

        TabloPerson::factory()->create([
            'tablo_project_id' => $project->id,
            'type' => TabloPersonType::TEACHER->value,
        ]);

        // Act - cache feltöltés
        $this->service->getTabCounts();

        // Új rekord hozzáadása
        TabloPerson::factory()->create([
            'tablo_project_id' => $project->id,
            'type' => TabloPersonType::TEACHER->value,
        ]);

        // Cache törlése
        $this->service->clearCountCache();

        // Assert - friss adat
        $freshCounts = $this->service->getTabCounts();
        $this->assertEquals(2, $freshCounts['teachers']);
    }

    public function test_getGroupingForTab_returns_correct_grouping(): void
    {
        // Assert - projekt filter nélkül
        $this->assertEquals('project.school.name:asc', $this->service->getGroupingForTab('teachers', null));
        $this->assertEquals('tablo_project_id:asc', $this->service->getGroupingForTab('students', null));
        $this->assertNull($this->service->getGroupingForTab('all', null));

        // Assert - projekt filter esetén mindig type
        $this->assertEquals('type:asc', $this->service->getGroupingForTab('teachers', 1));
        $this->assertEquals('type:asc', $this->service->getGroupingForTab('students', 1));
        $this->assertEquals('type:asc', $this->service->getGroupingForTab('all', 1));
    }

    public function test_getTypeLabel_returns_correct_labels(): void
    {
        $this->assertEquals('Tanár', $this->service->getTypeLabel('teacher'));
        $this->assertEquals('Diák', $this->service->getTypeLabel('student'));
        $this->assertEquals('Ismeretlen', $this->service->getTypeLabel('unknown'));
    }

    public function test_getTypePluralLabel_returns_correct_labels(): void
    {
        $this->assertEquals('Tanárok', $this->service->getTypePluralLabel('teacher'));
        $this->assertEquals('Diákok', $this->service->getTypePluralLabel('student'));
        $this->assertEquals('Ismeretlenek', $this->service->getTypePluralLabel('unknown'));
    }
}
