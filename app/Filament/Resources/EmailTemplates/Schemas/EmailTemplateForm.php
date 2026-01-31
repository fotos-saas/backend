<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use App\Services\EmailVariableService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Alapadatok')
                    ->columnSpan(['lg' => 2])
                    ->components([
                        TextInput::make('name')
                            ->label('Azonosító kulcs')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(150)
                            ->helperText('Pl.: welcome_email vagy order_confirmation.'),
                        TextInput::make('subject')
                            ->label('Email tárgya')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Használhatsz változókat: {user_name}, {site_name}, stb.'),
                        RichEditor::make('body')
                            ->label('Email tartalom')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'h2', 'h3', 'bulletList', 'orderedList',
                                'link', 'blockquote', 'codeBlock',
                                'undo', 'redo',
                            ])
                            ->helperText('Használhatsz HTML-t és változókat is a tartalomban.'),
                        Grid::make(3)
                            ->components([
                                Toggle::make('is_active')
                                    ->label('Aktív sablon')
                                    ->default(true)
                                    ->inline(false),
                                Select::make('smtp_account_id')
                                    ->label('SMTP Fiók')
                                    ->relationship('smtpAccount', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Alapértelmezett: környezet szerinti aktív SMTP'),
                                Select::make('priority')
                                    ->label('Prioritás')
                                    ->options([
                                        'low' => 'Alacsony',
                                        'normal' => 'Normál',
                                        'high' => 'Magas',
                                        'critical' => 'Kritikus',
                                    ])
                                    ->default('normal')
                                    ->required(),
                            ]),
                    ]),
                Section::make('Elérhető változók')
                    ->columnSpan(['lg' => 1])
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        Placeholder::make('variables_hint')
                            ->label('Használható helyettesítők')
                            ->content(static function () {
                                $service = app(EmailVariableService::class);
                                $groups = $service->getAvailableVariables();

                                $html = '<div class="space-y-6">';
                                foreach ($groups as $group => $variables) {
                                    $html .= '<div class="border border-gray-200 rounded-lg p-4">';
                                    $html .= '<h4 class="font-semibold text-sm uppercase tracking-wide text-gray-600 mb-3">'.__(match ($group) {
                                        'user' => 'Felhasználó adatai',
                                        'album' => 'Album adatai',
                                        'order' => 'Megrendelés adatai',
                                        default => 'Általános',
                                    }).'</h4>';
                                    $html .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-2">';
                                    foreach ($variables as $key => $label) {
                                        $html .= '<div class="bg-gray-50 border border-gray-200 rounded px-3 py-2">';
                                        $html .= '<code class="text-sm font-mono text-gray-700">{'.e($key).'}</code>';
                                        $html .= '<div class="text-xs text-gray-500 mt-1">'.e($label).'</div>';
                                        $html .= '</div>';
                                    }
                                    $html .= '</div></div>';
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ]),
            ])
            ->columns(3);
    }
}
