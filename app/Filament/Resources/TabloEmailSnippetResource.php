<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TabloEmailSnippetResource\Pages;
use App\Models\TabloEmailSnippet;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class TabloEmailSnippetResource extends BaseResource
{

    protected static ?string $model = TabloEmailSnippet::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Email sablonok';

    protected static ?string $modelLabel = 'Email sablon';

    protected static ?string $pluralModelLabel = 'Email sablonok';

    protected static string|UnitEnum|null $navigationGroup = 'Tabló';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'tablo/email-snippets';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Alapadatok')
                    ->icon('heroicon-o-document-text')
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Név')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, $record) {
                                if (!$record) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Egyedi azonosító (automatikusan generálódik)'),

                        Forms\Components\TextInput::make('subject')
                            ->label('Tárgy')
                            ->maxLength(255)
                            ->helperText('Használható: {nev}, {osztaly}, {iskola}, {ev}'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sorrend')
                            ->numeric()
                            ->default(0)
                            ->helperText('Kisebb szám = előrébb jelenik meg'),
                    ])
                    ->columns(2),

                Section::make('Tartalom')
                    ->icon('heroicon-o-pencil-square')
                    ->components([
                        Forms\Components\RichEditor::make('content')
                            ->label('Email szöveg')
                            ->required()
                            ->helperText('Használható változók: {nev}, {osztaly}, {iskola}, {ev}')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'h2',
                                'h3',
                                'bulletList',
                                'orderedList',
                                'link',
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('placeholders_help')
                            ->label('Elérhető változók')
                            ->content(function () {
                                $placeholders = TabloEmailSnippet::getAvailablePlaceholders();
                                $html = '<div class="text-sm space-y-1">';
                                foreach ($placeholders as $key => $description) {
                                    $html .= "<div><code class=\"bg-gray-100 px-1 rounded\">{$key}</code> - {$description}</div>";
                                }
                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Beállítások')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->components([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktív')
                            ->helperText('Inaktív sablonok nem jelennek meg a választható listában')
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Kiemelt')
                            ->helperText('A kiemelt sablonok előre kerülnek a választóban')
                            ->default(false),
                    ])
                    ->columns(2),

                Section::make('Automatizálás')
                    ->icon('heroicon-o-clock')
                    ->description('Automatikus email küldés beállításai (későbbi fejlesztés)')
                    ->components([
                        Forms\Components\Toggle::make('is_auto_enabled')
                            ->label('Automatikus küldés engedélyezve')
                            ->helperText('Ha be van kapcsolva, a rendszer automatikusan használhatja ezt a sablont')
                            ->default(false)
                            ->live(),

                        Forms\Components\Select::make('auto_trigger')
                            ->label('Trigger típus')
                            ->options([
                                'no_reply_days' => 'Nincs válasz X napja',
                                'status_change' => 'Státusz változás',
                                'deadline' => 'Határidő közeleg',
                            ])
                            ->visible(fn (callable $get) => $get('is_auto_enabled'))
                            ->helperText('Mikor küldődjön automatikusan'),

                        Forms\Components\KeyValue::make('auto_trigger_config')
                            ->label('Trigger konfiguráció')
                            ->keyLabel('Beállítás')
                            ->valueLabel('Érték')
                            ->visible(fn (callable $get) => $get('is_auto_enabled'))
                            ->helperText('Pl.: days = 7'),

                        Forms\Components\Placeholder::make('auto_last_run_at_display')
                            ->label('Utolsó automatikus futás')
                            ->content(fn ($record) => $record?->auto_last_run_at?->format('Y-m-d H:i') ?? 'Még nem futott')
                            ->visible(fn (callable $get) => $get('is_auto_enabled')),
                    ])
                    ->collapsed()
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('50px'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Sablon')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->subject),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktív')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Kiemelt')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_auto_enabled')
                    ->label('Auto')
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn ($record) => $record->is_auto_enabled ? 'Automatikus küldés engedélyezve' : 'Manuális'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Módosítva')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktív')
                    ->placeholder('Mind')
                    ->trueLabel('Aktív')
                    ->falseLabel('Inaktív'),

                Tables\Filters\TernaryFilter::make('is_auto_enabled')
                    ->label('Automatikus')
                    ->placeholder('Mind')
                    ->trueLabel('Automatikus')
                    ->falseLabel('Manuális'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Nincs email sablon')
            ->emptyStateDescription('Hozz létre sablonokat a gyors email küldéshez.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTabloEmailSnippets::route('/'),
            'create' => Pages\CreateTabloEmailSnippet::route('/create'),
            'edit' => Pages\EditTabloEmailSnippet::route('/{record}/edit'),
        ];
    }
}
