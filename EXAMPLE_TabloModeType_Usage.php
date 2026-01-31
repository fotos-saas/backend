<?php

/**
 * PÉLDA: TabloModeType használata Filament 4 Resource-ban
 *
 * Ez a fájl NEM része a működő kódnak, csak példa!
 */

use App\Enums\TabloModeType;
use Filament\Forms;
use Filament\Schemas\Schema;

// ============================================
// PÉLDA 1: Radio gombok (ajánlott vizuális módhoz)
// ============================================
public static function form(Schema $schema): Schema
{
    return $schema->components([
        Forms\Components\Radio::make('mode_type')
            ->label('Tábló Mód Típusa')
            ->options(TabloModeType::class) // Enum class-t közvetlenül használhatod!
            ->descriptions([
                'fixed' => TabloModeType::FIXED->description(),
                'flexible' => TabloModeType::FLEXIBLE->description(),
                'packages' => TabloModeType::PACKAGES->description(),
            ])
            ->required()
            ->inline(false) // Egymás alatt jelennek meg
            ->default(TabloModeType::FIXED),
    ]);
}

// ============================================
// PÉLDA 2: Select dropdown (kompakt megjelenés)
// ============================================
public static function form(Schema $schema): Schema
{
    return $schema->components([
        Forms\Components\Select::make('mode_type')
            ->label('Tábló Mód Típusa')
            ->options(TabloModeType::class) // Enum class használata
            ->required()
            ->native(false) // Filament stílusú dropdown
            ->searchable(false) // 3 opció esetén nincs szükség keresésre
            ->helperText('Válaszd ki, milyen módon működjön a tábló rendszer')
            ->default(TabloModeType::FIXED),
    ]);
}

// ============================================
// PÉLDA 3: Radio ikonokkal (advanced)
// ============================================
public static function form(Schema $schema): Schema
{
    return $schema->components([
        Forms\Components\Radio::make('mode_type')
            ->label('Tábló Mód Típusa')
            ->options([
                'fixed' => TabloModeType::FIXED->getLabel(),
                'flexible' => TabloModeType::FLEXIBLE->getLabel(),
                'packages' => TabloModeType::PACKAGES->getLabel(),
            ])
            ->descriptions([
                'fixed' => TabloModeType::FIXED->description(),
                'flexible' => TabloModeType::FLEXIBLE->description(),
                'packages' => TabloModeType::PACKAGES->description(),
            ])
            ->required()
            ->inline(false)
            ->default(TabloModeType::FIXED->value),

        // Dinamikus mező (csak FIXED mód esetén jelenik meg)
        Forms\Components\TextInput::make('fixed_image_count')
            ->label('Fix képszám')
            ->numeric()
            ->minValue(1)
            ->maxValue(1000)
            ->required()
            ->visible(fn ($get) => $get('mode_type') === TabloModeType::FIXED->value),

        // Dinamikus mező (csak FLEXIBLE mód esetén)
        Forms\Components\TextInput::make('flexible_free_limit')
            ->label('Ingyenes limit')
            ->numeric()
            ->minValue(0)
            ->required()
            ->visible(fn ($get) => $get('mode_type') === TabloModeType::FLEXIBLE->value),
    ]);
}

// ============================================
// PÉLDA 4: Model-ben casting
// ============================================
class TabloMode extends Model
{
    protected $fillable = [
        'mode_type',
        'fixed_image_count',
        'flexible_free_limit',
    ];

    protected $casts = [
        'mode_type' => TabloModeType::class, // Automatikus enum casting
    ];

    /**
     * Accessor példa.
     */
    public function getModeTypeIconAttribute(): string
    {
        return $this->mode_type->icon();
    }

    /**
     * Scope példa.
     */
    public function scopeFixedMode($query)
    {
        return $query->where('mode_type', TabloModeType::FIXED);
    }
}

// ============================================
// PÉLDA 5: Használat Controller-ben
// ============================================
class TabloModeController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'mode_type' => ['required', Rule::enum(TabloModeType::class)],
            'fixed_image_count' => ['required_if:mode_type,fixed', 'integer', 'min:1'],
        ]);

        $mode = TabloMode::create([
            'mode_type' => TabloModeType::from($validated['mode_type']),
            'fixed_image_count' => $validated['fixed_image_count'] ?? null,
        ]);

        return response()->json($mode);
    }

    public function index()
    {
        // Enum alapú szűrés
        $fixedModes = TabloMode::where('mode_type', TabloModeType::FIXED)->get();

        // Vagy scope-pal
        $fixedModes = TabloMode::fixedMode()->get();

        return response()->json($fixedModes);
    }
}

// ============================================
// PÉLDA 6: Filament Table Column-ban
// ============================================
use Filament\Tables;

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('mode_type')
                ->label('Típus')
                ->badge() // Badge-ként jelenítjük meg
                ->color(fn (TabloModeType $state): string => match ($state) {
                    TabloModeType::FIXED => 'danger',
                    TabloModeType::FLEXIBLE => 'success',
                    TabloModeType::PACKAGES => 'primary',
                })
                ->icon(fn (TabloModeType $state): string => $state->icon())
                ->sortable()
                ->searchable(),
        ]);
}

// ============================================
// PÉLDA 7: Migration-ban használat
// ============================================
Schema::create('tablo_modes', function (Blueprint $table) {
    $table->id();
    $table->enum('mode_type', TabloModeType::toArray())
        ->default(TabloModeType::FIXED->value);
    $table->unsignedInteger('fixed_image_count')->nullable();
    $table->unsignedInteger('flexible_free_limit')->nullable();
    $table->timestamps();
});
