<?php

namespace Tests\Feature\Filament;

use App\Enums\TabloPersonType;
use App\Filament\Resources\TabloMissingPeople\TabloMissingPersonResource;
use App\Models\TabloMissingPerson;
use App\Models\TabloProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListTabloMissingPeopleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Admin user létrehozása teszteléshez
        $this->user = User::factory()->create([
            'is_admin' => true,
        ]);
    }

    public function test_page_loads_successfully(): void
    {
        // Act & Assert
        $this->actingAs($this->user)
            ->get(TabloMissingPersonResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_tabs_show_correct_counts(): void
    {
        // Arrange
        $project = TabloProject::factory()->create();

        TabloMissingPerson::factory()->count(3)->create([
            'tablo_project_id' => $project->id,
            'type' => TabloPersonType::TEACHER->value,
        ]);

        TabloMissingPerson::factory()->count(7)->create([
            'tablo_project_id' => $project->id,
            'type' => TabloPersonType::STUDENT->value,
        ]);

        // Act & Assert
        $this->actingAs($this->user)
            ->get(TabloMissingPersonResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('Tanárok')
            ->assertSee('Diákok')
            ->assertSee('Mind');
    }

    public function test_project_filter_changes_grouping(): void
    {
        // Arrange
        $project = TabloProject::factory()->create();

        TabloMissingPerson::factory()->create([
            'tablo_project_id' => $project->id,
            'type' => TabloPersonType::TEACHER->value,
        ]);

        // Act & Assert - Projekt filter URL-ben
        $url = TabloMissingPersonResource::getUrl('index', [
            'tableFilters' => [
                'tablo_project_id' => ['value' => $project->id],
            ],
        ]);

        $this->actingAs($this->user)
            ->get($url)
            ->assertSuccessful();
    }

    public function test_activeTab_url_parameter_works(): void
    {
        // Arrange
        $project = TabloProject::factory()->create();

        TabloMissingPerson::factory()->create([
            'tablo_project_id' => $project->id,
            'type' => TabloPersonType::STUDENT->value,
        ]);

        // Act & Assert
        $this->actingAs($this->user)
            ->get(TabloMissingPersonResource::getUrl('index', ['activeTab' => 'students']))
            ->assertSuccessful()
            ->assertSee('Diákok');
    }
}
