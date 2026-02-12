<?php

namespace App\Filament\Resources\TabloProjectResource\RelationManagers;

use App\Enums\NoteStatus;
use App\Helpers\QueryHelper;
use App\Filament\Resources\TabloProjectResource;
use App\Models\ProjectEmail;
use App\Models\SmtpAccount;
use App\Models\TabloEmailSnippet;
use App\Models\TabloNote;
use App\Models\TabloProject;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

class EmailsRelationManager extends RelationManager
{
    protected static string $relationship = 'emails';

    protected static ?string $title = 'Emailek';

    protected static ?string $modelLabel = 'Email';

    protected static ?string $pluralModelLabel = 'Emailek';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-envelope';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return TabloProjectResource::canAccessRelation('emails');
    }

    /**
     * Livewire action: megjegyzés státuszának módosítása
     */
    public function markNoteStatus(int $noteId, string $status): void
    {
        $note = TabloNote::find($noteId);
        if ($note && $note->tablo_project_id === $this->getOwnerRecord()->id) {
            $note->update([
                'status' => NoteStatus::from($status),
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
            ]);

            Notification::make()
                ->title('Megjegyzés státusza frissítve')
                ->success()
                ->send();
        }
    }

    /**
     * Megjegyzések lista HTML renderelése az email modalhoz
     */
    private function renderNotesListHtml(): HtmlString
    {
        /** @var TabloProject $project */
        $project = $this->getOwnerRecord();
        $notes = $project->notes()
            ->whereIn('status', [NoteStatus::New, NoteStatus::InProgress])
            ->orderByDesc('created_at')
            ->get();

        if ($notes->isEmpty()) {
            return new HtmlString(
                '<div style="text-align: center; color: #9ca3af; padding: 40px 20px; font-size: 14px;">'.
                '<div style="font-size: 32px; margin-bottom: 8px;">📝</div>'.
                'Nincs aktív megjegyzés'.
                '</div>'
            );
        }

        $html = '<div style="max-height: 450px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; padding-right: 4px;">';

        foreach ($notes as $note) {
            $statusBg = match ($note->status) {
                NoteStatus::New => '#fef3c7',
                NoteStatus::InProgress => '#dbeafe',
                default => '#f3f4f6',
            };
            $statusColor = match ($note->status) {
                NoteStatus::New => '#92400e',
                NoteStatus::InProgress => '#1e40af',
                default => '#374151',
            };

            $contentHtml = e($note->content);
            $authorName = e($note->author_name);
            $timeAgo = $note->created_at->diffForHumans();

            // Gombok: "Folyamatban" csak ha még "Új", "Kész" mindig
            $buttons = '<div style="display: flex; gap: 6px;">';
            if ($note->status === NoteStatus::New) {
                $buttons .= sprintf(
                    '<button type="button" wire:click="markNoteStatus(%d, \'in_progress\')" style="padding: 5px 12px; background: #3b82f6; color: white; border: none; border-radius: 6px; font-size: 11px; font-weight: 500; cursor: pointer;">▶ Folyamatban</button>',
                    $note->id
                );
            }
            $buttons .= sprintf(
                '<button type="button" wire:click="markNoteStatus(%d, \'done\')" style="padding: 5px 12px; background: #10b981; color: white; border: none; border-radius: 6px; font-size: 11px; font-weight: 500; cursor: pointer;">✓ Kész</button>',
                $note->id
            );
            $buttons .= '</div>';

            $html .= sprintf(
                '<div style="padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: white;">'.
                '<div style="display: flex; justify-content: space-between; align-items: start; gap: 10px;">'.
                '<div style="flex: 1; font-size: 13px; line-height: 1.5; color: #374151;">%s</div>'.
                '<span style="flex-shrink: 0; padding: 3px 10px; border-radius: 9999px; font-size: 11px; font-weight: 600; background: %s; color: %s;">%s</span>'.
                '</div>'.
                '<div style="margin-top: 8px; display: flex; justify-content: space-between; align-items: center;">'.
                '<span style="font-size: 11px; color: #9ca3af;">%s • %s</span>'.
                '%s'.
                '</div>'.
                '</div>',
                $contentHtml,
                $statusBg,
                $statusColor,
                $note->status->label(),
                $authorName,
                $timeAgo,
                $buttons
            );
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    /**
     * Sablon választó komponens a form-okhoz
     */
    protected function getSnippetSelectorField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('snippet_id')
            ->label('Sablon betöltése')
            ->placeholder('Válassz sablont...')
            ->options(function () {
                $snippets = TabloEmailSnippet::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get();

                $featured = $snippets->where('is_featured', true);
                $regular = $snippets->where('is_featured', false);

                $options = [];

                if ($featured->isNotEmpty()) {
                    $options['⭐ Kiemelt sablonok'] = $featured->pluck('name', 'id')->toArray();
                }

                if ($regular->isNotEmpty()) {
                    $options['Egyéb sablonok'] = $regular->pluck('name', 'id')->toArray();
                }

                return $options;
            })
            ->searchable()
            ->live()
            ->afterStateUpdated(function ($state, callable $set) {
                if (! $state) {
                    return;
                }

                $snippet = TabloEmailSnippet::find($state);
                if (! $snippet) {
                    return;
                }

                /** @var TabloProject $project */
                $project = $this->getOwnerRecord();

                // Kontextus a placeholder cseréhez
                $context = [
                    'nev' => $project->contacts->first()?->name ?? '',
                    'osztaly' => $project->class_name ?? '',
                    'iskola' => $project->school?->name ?? '',
                    'ev' => $project->year ?? date('Y'),
                ];

                // Tartalom beállítása
                $set('body', $snippet->renderContent($context));

                // Tárgy beállítása (ha van és a mező üres vagy csak "Re: " tartalmaz)
                if ($snippet->subject) {
                    $set('subject', $snippet->renderSubject($context));
                }
            })
            ->helperText('A sablon betöltése felülírja az aktuális tartalmat')
            ->columnSpanFull();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        /** @var TabloProject $ownerRecord */
        $contactEmails = $ownerRecord->contacts->pluck('email')->filter()->map(fn ($e) => strtolower($e))->toArray();

        if (empty($contactEmails)) {
            return null;
        }

        $unanswered = $ownerRecord->emails()
            ->needsReply()
            ->where(function ($q) use ($contactEmails) {
                $q->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(from_email)'), $contactEmails)
                  ->orWhereIn(\Illuminate\Support\Facades\DB::raw('LOWER(to_email)'), $contactEmails);
            })
            ->count();

        return $unanswered > 0 ? (string) $unanswered : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'danger';
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                // Csak a kapcsolattartók email címeihez tartozó emaileket mutassuk
                /** @var \App\Models\TabloProject $project */
                $project = $this->getOwnerRecord();
                $contactEmails = $project->contacts->pluck('email')->filter()->map(fn ($e) => strtolower($e))->toArray();

                if (empty($contactEmails)) {
                    // Ha nincs kapcsolattartó, ne mutassunk semmit
                    return $query->whereRaw('1 = 0');
                }

                return $query->where(function ($q) use ($contactEmails) {
                    $q->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(from_email)'), $contactEmails)
                      ->orWhereIn(\Illuminate\Support\Facades\DB::raw('LOWER(to_email)'), $contactEmails);
                });
            })
            ->columns([
                Tables\Columns\TextColumn::make('direction')
                    ->label('')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'inbound' ? 'Bejövő' : 'Kimenő')
                    ->color(fn (string $state) => $state === 'inbound' ? 'info' : 'warning')
                    ->icon(fn (string $state) => $state === 'inbound' ? 'heroicon-m-arrow-down-tray' : 'heroicon-m-arrow-up-tray'),

                Tables\Columns\TextColumn::make('from_display')
                    ->label('Feladó')
                    ->state(fn (ProjectEmail $record) => $record->from_name ?: $record->from_email)
                    ->description(fn (ProjectEmail $record) => $record->from_name ? $record->from_email : null)
                    ->searchable(['from_email', 'from_name'])
                    ->limit(25),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Tárgy')
                    ->searchable()
                    ->limit(40)
                    ->weight(fn (ProjectEmail $record) => $record->is_read ? 'normal' : 'bold')
                    ->color(fn (ProjectEmail $record) => $record->is_read ? 'gray' : null)
                    ->icon(fn (ProjectEmail $record) => ! $record->is_read ? 'heroicon-s-envelope' : null)
                    ->iconPosition('before')
                    ->iconColor('primary')
                    ->description(fn (ProjectEmail $record) => \Illuminate\Support\Str::limit(
                        strip_tags($record->clean_body_text ?: $record->body_text ?: strip_tags($record->body_html ?? '')),
                        60
                    )),

                Tables\Columns\TextColumn::make('status_badges')
                    ->label('Státusz')
                    ->state(function (ProjectEmail $record) {
                        $badges = [];

                        if (! $record->is_read) {
                            $badges[] = '<span style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 9999px; font-size: 10px; font-weight: 600;">Olvasatlan</span>';
                        }

                        if ($record->direction === 'inbound' && $record->needs_reply && ! $record->is_replied) {
                            $badges[] = '<span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 9999px; font-size: 10px; font-weight: 600;">Válaszra vár</span>';
                        }

                        if ($record->is_replied) {
                            $badges[] = '<span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 9999px; font-size: 10px; font-weight: 600;">Megválaszolva</span>';
                        }

                        if ($record->hasAttachments()) {
                            $count = count($record->attachments);
                            $badges[] = '<span style="background: #6b7280; color: white; padding: 2px 8px; border-radius: 9999px; font-size: 10px; font-weight: 600;">📎 ' . $count . '</span>';
                        }

                        return implode(' ', $badges) ?: '<span style="color: #9ca3af;">-</span>';
                    })
                    ->html()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('email_date')
                    ->label('Dátum')
                    ->dateTime('m.d H:i')
                    ->sortable()
                    ->description(fn (ProjectEmail $record) => $record->email_date?->diffForHumans()),
            ])
            ->recordClasses(fn (ProjectEmail $record) => ! $record->is_read ? 'bg-blue-50 dark:bg-blue-900/20' : null)
            ->defaultSort('email_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('direction')
                    ->label('Irány')
                    ->options([
                        'inbound' => 'Bejövő',
                        'outbound' => 'Kimenő',
                    ]),
                Tables\Filters\TernaryFilter::make('is_read')
                    ->label('Olvasott')
                    ->trueLabel('Olvasott')
                    ->falseLabel('Olvasatlan')
                    ->queries(
                        true: fn ($query) => $query->where('is_read', true),
                        false: fn ($query) => $query->where('is_read', false),
                    ),
                Tables\Filters\TernaryFilter::make('needs_reply')
                    ->label('Válaszra vár')
                    ->trueLabel('Igen')
                    ->falseLabel('Nem')
                    ->queries(
                        true: fn ($query) => $query->where('needs_reply', true)->where('is_replied', false),
                        false: fn ($query) => $query->where(fn ($q) => $q->where('needs_reply', false)->orWhere('is_replied', true)),
                    ),
                Tables\Filters\TernaryFilter::make('has_attachments')
                    ->label('Van csatolmány')
                    ->trueLabel('Igen')
                    ->falseLabel('Nem')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('attachments')->whereRaw("jsonb_array_length(attachments) > 0"),
                        false: fn ($query) => $query->where(fn ($q) => $q->whereNull('attachments')->orWhereRaw("jsonb_array_length(attachments) = 0")),
                    ),
            ])
            ->headerActions([
                Action::make('syncProjectEmails')
                    ->label('Emailek szinkronizálása')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Emailek szinkronizálása')
                    ->modalDescription(function () {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();
                        $contacts = $project->contacts;

                        if ($contacts->isEmpty()) {
                            return 'A projekthez nincsenek kapcsolattartók rendelve. Adj hozzá kapcsolattartókat először!';
                        }

                        $contactList = $contacts->map(fn ($c) => "• {$c->name}" . ($c->email ? " ({$c->email})" : ''))->join("\n");

                        return "A következő kapcsolattartók alapján keres emaileket:\n\n{$contactList}";
                    })
                    ->modalSubmitActionLabel('Szinkronizálás')
                    ->disabled(function () {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();

                        return $project->contacts->isEmpty();
                    })
                    ->action(function () {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();
                        $contacts = $project->contacts;

                        $emails = collect();
                        $names = collect();

                        // Kapcsolattartók email címei és nevei
                        foreach ($contacts as $contact) {
                            if ($contact->email) {
                                $emails->push(strtolower($contact->email));
                            }
                            if ($contact->name) {
                                $names->push($contact->name);
                            }
                        }

                        // Emailek keresése és hozzárendelése
                        $linkedCount = 0;

                        // Email cím alapján keresés
                        if ($emails->isNotEmpty()) {
                            $linkedCount += ProjectEmail::whereNull('tablo_project_id')
                                ->where(function ($query) use ($emails) {
                                    $query->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(from_email)'), $emails)
                                        ->orWhereIn(\Illuminate\Support\Facades\DB::raw('LOWER(to_email)'), $emails);
                                })
                                ->update(['tablo_project_id' => $project->id]);
                        }

                        // Név alapján keresés (csak azoknál, amik még nincsenek hozzárendelve)
                        if ($names->isNotEmpty()) {
                            foreach ($names as $name) {
                                $linkedCount += ProjectEmail::whereNull('tablo_project_id')
                                    ->where(function ($query) use ($name) {
                                        $query->where('from_name', 'ILIKE', QueryHelper::safeLikePattern($name))
                                            ->orWhere('to_name', 'ILIKE', QueryHelper::safeLikePattern($name));
                                    })
                                    ->update(['tablo_project_id' => $project->id]);
                            }
                        }

                        if ($linkedCount > 0) {
                            Notification::make()
                                ->title('Emailek szinkronizálva')
                                ->body("{$linkedCount} email hozzárendelve a projekthez.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Nincs új email')
                                ->body('Nem találtam hozzárendeletlen emailt a kapcsolattartókhoz.')
                                ->info()
                                ->send();
                        }
                    }),

                Action::make('composeEmail')
                    ->label('Új email')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->modalWidth('7xl')
                    ->form([
                        Grid::make(2)
                            ->schema([
                                // BAL OSZLOP - Email form
                                Group::make([
                                    Forms\Components\Select::make('to_contact')
                                        ->label('Címzett')
                                        ->options(function () {
                                            /** @var TabloProject $project */
                                            $project = $this->getOwnerRecord();

                                            return $project->contacts
                                                ->filter(fn ($c) => $c->email)
                                                ->mapWithKeys(fn ($c) => [$c->email => "{$c->name} <{$c->email}>"]);
                                        })
                                        ->default(function () {
                                            /** @var TabloProject $project */
                                            $project = $this->getOwnerRecord();
                                            $contacts = $project->contacts->filter(fn ($c) => $c->email);

                                            // Elsődleges kapcsolattartó, vagy az első
                                            $primary = $contacts->firstWhere('is_primary', true);

                                            return $primary?->email ?? $contacts->first()?->email;
                                        })
                                        ->searchable()
                                        ->required()
                                        ->helperText('Válaszd ki a kapcsolattartót'),

                                    $this->getSnippetSelectorField(),

                                    Forms\Components\TextInput::make('subject')
                                        ->label('Tárgy')
                                        ->required()
                                        ->maxLength(255)
                                        ->default(function () {
                                            /** @var TabloProject $project */
                                            $project = $this->getOwnerRecord();

                                            return "[PRJ-{$project->id}] ";
                                        }),

                                    Forms\Components\RichEditor::make('body')
                                        ->label('Üzenet')
                                        ->required()
                                        ->toolbarButtons([
                                            'bold',
                                            'italic',
                                            'underline',
                                            'strike',
                                            'bulletList',
                                            'orderedList',
                                            'link',
                                        ]),

                                    Forms\Components\CheckboxList::make('selected_samples')
                                        ->label('Csatolt minták')
                                        ->options(function () {
                                            /** @var \App\Models\TabloProject $project */
                                            $project = $this->getOwnerRecord();
                                            $samples = $project->getMedia('samples')
                                                ->filter(fn ($m) => $m->getCustomProperty('is_active', true))
                                                ->sortByDesc('created_at')
                                                ->take(5);

                                            return $samples->mapWithKeys(function ($sample) {
                                                $thumbUrl = $sample->getUrl('thumb');
                                                $fullUrl = $sample->getUrl();
                                                $fileName = e($sample->file_name);
                                                $timeAgo = $sample->created_at->diffForHumans();

                                                $label = "<div style='display: flex; align-items: center; gap: 10px;'>"
                                                    . "<img src='{$thumbUrl}' onclick=\"event.stopPropagation(); event.preventDefault(); window.open('{$fullUrl}', '_blank', 'width=1200,height=900'); return false;\" style='width: 45px; height: 45px; object-fit: cover; border-radius: 4px; cursor: zoom-in;' title='Nagyítás' />"
                                                    . "<div>"
                                                    . "<div style='font-weight: 500;'>{$fileName}</div>"
                                                    . "<div style='font-size: 12px; color: #888;'>{$timeAgo}</div>"
                                                    . "</div>"
                                                    . "</div>";

                                                return [$sample->id => new HtmlString($label)];
                                            })->toArray();
                                        })
                                        ->default(function () {
                                            /** @var \App\Models\TabloProject $project */
                                            $project = $this->getOwnerRecord();
                                            $lastSample = $project->getMedia('samples')
                                                ->filter(fn ($m) => $m->getCustomProperty('is_active', true))
                                                ->sortByDesc('created_at')
                                                ->first();

                                            return $lastSample ? [$lastSample->id] : [];
                                        })
                                        ->columns(1),
                                ])->columnSpan(1),

                                // JOBB OSZLOP - Megjegyzések
                                Group::make([
                                    Forms\Components\Placeholder::make('notes_header')
                                        ->hiddenLabel()
                                        ->content(fn () => new HtmlString(
                                            '<div style="font-weight: 600; font-size: 14px; color: #374151; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">'.
                                            '<span>📋</span> Aktív megjegyzések'.
                                            '</div>'
                                        )),

                                    Forms\Components\Placeholder::make('notes_list')
                                        ->hiddenLabel()
                                        ->content(fn () => $this->renderNotesListHtml()),
                                ])->columnSpan(1),
                            ]),
                    ])
                    ->action(function (array $data) {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();

                        // Tablókirály SMTP fiók lekérése (projekt emailezéshez)
                        $smtpAccount = SmtpAccount::where('from_address', 'info@tablokiraly.hu')->first();
                        if (! $smtpAccount) {
                            Notification::make()
                                ->title('Nincs Tablókirály SMTP fiók')
                                ->body('Kérlek állítsd be a Tablókirály SMTP fiókot (info@tablokiraly.hu).')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $attachments = [];
                            $selectedSampleIds = $data['selected_samples'] ?? [];
                            if (! empty($selectedSampleIds)) {
                                $samples = $project->getMedia('samples')
                                    ->filter(fn ($m) => in_array($m->id, $selectedSampleIds));

                                foreach ($samples as $sample) {
                                    $attachments[] = [
                                        'path' => $sample->getPath(),
                                        'name' => $sample->file_name,
                                        'mime' => $sample->mime_type,
                                        'media_id' => $sample->id,
                                    ];
                                }
                            }

                            $mailerName = $smtpAccount->getDynamicMailerName();

                            Mail::mailer($mailerName)->send([], [], function ($message) use ($data, $attachments, $smtpAccount) {
                                $message->from($smtpAccount->from_address, $smtpAccount->from_name)
                                    ->to($data['to_contact'])
                                    ->subject($data['subject'])
                                    ->html($data['body']);

                                foreach ($attachments as $attachment) {
                                    $message->attach($attachment['path'], [
                                        'as' => $attachment['name'],
                                        'mime' => $attachment['mime'],
                                    ]);
                                }
                            });

                            // IMAP Sent mentés
                            if ($smtpAccount->canSaveToSent()) {
                                $imapAttachments = array_map(fn ($a) => [
                                    'content' => file_get_contents($a['path']),
                                    'filename' => $a['name'],
                                    'mime' => $a['mime'],
                                ], $attachments);
                                $smtpAccount->saveToSentFolder($data['to_contact'], $data['subject'], $data['body'], [], $imapAttachments);
                            }

                            // Email mentése a DB-be
                            ProjectEmail::create([
                                'tablo_project_id' => $project->id,
                                'message_id' => 'local-'.uniqid(),
                                'from_email' => $smtpAccount->from_address,
                                'from_name' => $smtpAccount->from_name,
                                'to_email' => $data['to_contact'],
                                'subject' => $data['subject'],
                                'body_html' => $data['body'],
                                'direction' => 'outbound',
                                'is_read' => true,
                                'needs_reply' => false,
                                'attachments' => $attachments ? array_map(fn ($a) => [
                                    'name' => $a['name'],
                                    'mime_type' => $a['mime'],
                                    'media_id' => $a['media_id'] ?? null,
                                ], $attachments) : null,
                                'email_date' => now(),
                            ]);

                            Notification::make()
                                ->title('Email elküldve')
                                ->body("Az email sikeresen elküldve: {$data['to_contact']}")
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Hiba az email küldésekor')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('replyToLast')
                    ->label('Válasz az utolsóra')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->modalWidth('4xl')
                    ->visible(function () {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();
                        $contactEmails = $project->contacts->pluck('email')->filter()->map(fn ($e) => strtolower($e))->toArray();

                        if (empty($contactEmails)) {
                            return false;
                        }

                        return $project->emails()
                            ->inbound()
                            ->where(function ($q) use ($contactEmails) {
                                $q->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(from_email)'), $contactEmails);
                            })
                            ->exists();
                    })
                    ->form(function () {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();
                        $contactEmails = $project->contacts->pluck('email')->filter()->map(fn ($e) => strtolower($e))->toArray();

                        $lastEmail = $project->emails()
                            ->inbound()
                            ->where(function ($q) use ($contactEmails) {
                                $q->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(from_email)'), $contactEmails);
                            })
                            ->latest('email_date')
                            ->first();

                        $cleanBody = $lastEmail?->clean_body_html ?: nl2br(e($lastEmail?->clean_body_text ?? ''));

                        return [
                            Forms\Components\Placeholder::make('original')
                                ->label('Eredeti üzenet')
                                ->content(fn () => new HtmlString(
                                    "<div style='background: #f5f5f5; padding: 12px; border-radius: 8px; border-left: 4px solid #e5a00d;'>".
                                    "<div style='margin-bottom: 8px; color: #666; font-size: 13px;'>".
                                    "<strong>Feladó:</strong> {$lastEmail->from_name} &lt;{$lastEmail->from_email}&gt;<br>".
                                    "<strong>Dátum:</strong> {$lastEmail->email_date->format('Y-m-d H:i')}<br>".
                                    "<strong>Tárgy:</strong> {$lastEmail->subject}".
                                    "</div>".
                                    "<div style='color: #333;'>{$cleanBody}</div>".
                                    "</div>"
                                )),

                            $this->getSnippetSelectorField(),

                            Forms\Components\TextInput::make('subject')
                                ->label('Tárgy')
                                ->required()
                                ->default('Re: ' . preg_replace('/^(Re:\s*)+/i', '', $lastEmail->subject)),

                            Forms\Components\RichEditor::make('body')
                                ->label('Válasz')
                                ->required()
                                ->extraInputAttributes(['style' => 'min-height: 300px;'])
                                ->columnSpanFull(),

                            Forms\Components\CheckboxList::make('selected_samples')
                                ->label('Csatolt minták')
                                ->options(function () {
                                    /** @var \App\Models\TabloProject $project */
                                    $project = $this->getOwnerRecord();
                                    $samples = $project->getMedia('samples')
                                        ->filter(fn ($m) => $m->getCustomProperty('is_active', true))
                                        ->sortByDesc('created_at')
                                        ->take(5);

                                    return $samples->mapWithKeys(function ($sample) {
                                        $thumbUrl = $sample->getUrl('thumb');
                                        $fullUrl = $sample->getUrl();
                                        $fileName = e($sample->file_name);
                                        $timeAgo = $sample->created_at->diffForHumans();

                                        $label = "<div style='display: flex; align-items: center; gap: 10px;'>"
                                            . "<img src='{$thumbUrl}' onclick=\"event.stopPropagation(); event.preventDefault(); window.open('{$fullUrl}', '_blank', 'width=1200,height=900'); return false;\" style='width: 45px; height: 45px; object-fit: cover; border-radius: 4px; cursor: zoom-in;' title='Nagyítás' />"
                                            . "<div>"
                                            . "<div style='font-weight: 500;'>{$fileName}</div>"
                                            . "<div style='font-size: 12px; color: #888;'>{$timeAgo}</div>"
                                            . "</div>"
                                            . "</div>";

                                        return [$sample->id => new HtmlString($label)];
                                    })->toArray();
                                })
                                ->default(function () {
                                    /** @var \App\Models\TabloProject $project */
                                    $project = $this->getOwnerRecord();
                                    $lastSample = $project->getMedia('samples')
                                        ->filter(fn ($m) => $m->getCustomProperty('is_active', true))
                                        ->sortByDesc('created_at')
                                        ->first();

                                    return $lastSample ? [$lastSample->id] : [];
                                })
                                ->columns(1),

                            Forms\Components\Hidden::make('to_email')
                                ->default($lastEmail->from_email),

                            Forms\Components\Hidden::make('in_reply_to')
                                ->default($lastEmail->message_id),
                        ];
                    })
                    ->action(function (array $data) {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();

                        // Tablókirály SMTP fiók lekérése (projekt emailezéshez)
                        $smtpAccount = SmtpAccount::where('from_address', 'info@tablokiraly.hu')->first();
                        if (! $smtpAccount) {
                            Notification::make()
                                ->title('Nincs Tablókirály SMTP fiók')
                                ->body('Kérlek állítsd be a Tablókirály SMTP fiókot (info@tablokiraly.hu).')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $attachments = [];
                            $selectedSampleIds = $data['selected_samples'] ?? [];
                            if (! empty($selectedSampleIds)) {
                                $samples = $project->getMedia('samples')
                                    ->filter(fn ($m) => in_array($m->id, $selectedSampleIds));

                                foreach ($samples as $sample) {
                                    $attachments[] = [
                                        'path' => $sample->getPath(),
                                        'name' => $sample->file_name,
                                        'mime' => $sample->mime_type,
                                        'media_id' => $sample->id,
                                    ];
                                }
                            }

                            $mailerName = $smtpAccount->getDynamicMailerName();

                            Mail::mailer($mailerName)->send([], [], function ($message) use ($data, $attachments, $smtpAccount) {
                                $message->from($smtpAccount->from_address, $smtpAccount->from_name)
                                    ->to($data['to_email'])
                                    ->subject($data['subject'])
                                    ->html($data['body']);

                                if ($data['in_reply_to']) {
                                    $message->getHeaders()->addTextHeader('In-Reply-To', $data['in_reply_to']);
                                    $message->getHeaders()->addTextHeader('References', $data['in_reply_to']);
                                }

                                foreach ($attachments as $attachment) {
                                    $message->attach($attachment['path'], [
                                        'as' => $attachment['name'],
                                        'mime' => $attachment['mime'],
                                    ]);
                                }
                            });

                            // IMAP Sent mentés
                            if ($smtpAccount->canSaveToSent()) {
                                $imapAttachments = array_map(fn ($a) => [
                                    'content' => file_get_contents($a['path']),
                                    'filename' => $a['name'],
                                    'mime' => $a['mime'],
                                ], $attachments);
                                $smtpAccount->saveToSentFolder($data['to_email'], $data['subject'], $data['body'], [], $imapAttachments);
                            }

                            // Email mentése
                            ProjectEmail::create([
                                'tablo_project_id' => $project->id,
                                'message_id' => 'local-'.uniqid(),
                                'thread_id' => $data['in_reply_to'],
                                'in_reply_to' => $data['in_reply_to'],
                                'from_email' => $smtpAccount->from_address,
                                'from_name' => $smtpAccount->from_name,
                                'to_email' => $data['to_email'],
                                'subject' => $data['subject'],
                                'body_html' => $data['body'],
                                'direction' => 'outbound',
                                'is_read' => true,
                                'needs_reply' => false,
                                'attachments' => $attachments ? array_map(fn ($a) => [
                                    'name' => $a['name'],
                                    'mime_type' => $a['mime'],
                                    'media_id' => $a['media_id'] ?? null,
                                ], $attachments) : null,
                                'email_date' => now(),
                            ]);

                            // Az eredeti email megválaszoltnak jelölése
                            $project->emails()
                                ->where('message_id', $data['in_reply_to'])
                                ->update(['is_replied' => true]);

                            Notification::make()
                                ->title('Válasz elküldve')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Hiba')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Action::make('view')
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (ProjectEmail $record) => $record->subject)
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Bezárás')
                    ->infolist(fn (ProjectEmail $record) => [
                        Section::make()
                            ->schema([
                                Infolists\Components\TextEntry::make('from')
                                    ->label('Feladó')
                                    ->state(fn () => $record->from_name
                                        ? "{$record->from_name} <{$record->from_email}>"
                                        : $record->from_email),
                                Infolists\Components\TextEntry::make('to')
                                    ->label('Címzett')
                                    ->state(fn () => $record->to_email),
                                Infolists\Components\TextEntry::make('email_date')
                                    ->label('Dátum')
                                    ->dateTime('Y-m-d H:i:s'),
                            ])
                            ->columns(3),
                        Section::make('Üzenet')
                            ->schema([
                                Infolists\Components\TextEntry::make('clean_body')
                                    ->hiddenLabel()
                                    ->html()
                                    ->state(fn () => new HtmlString(
                                        $record->clean_body_html ?: nl2br(e($record->clean_body_text ?? 'Nincs tartalom'))
                                    )),
                            ]),
                        Section::make('Csatolmányok')
                            ->schema([
                                Infolists\Components\ViewEntry::make('attachments_view')
                                    ->hiddenLabel()
                                    ->view('filament.infolists.email-attachments')
                                    ->viewData([
                                        'attachments' => $record->attachments ?? [],
                                        'project' => $this->getOwnerRecord(),
                                    ]),
                            ])
                            ->visible(fn () => ! empty($record->attachments)),
                        Section::make('Teljes email (idézetekkel)')
                            ->schema([
                                Infolists\Components\TextEntry::make('full_body')
                                    ->hiddenLabel()
                                    ->html()
                                    ->state(fn () => new HtmlString(
                                        $record->body_html ?: nl2br(e($record->body_text ?? 'Nincs tartalom'))
                                    )),
                            ])
                            ->collapsed()
                            ->collapsible(),
                    ])
                    ->after(function (ProjectEmail $record) {
                        if (! $record->is_read) {
                            $record->update(['is_read' => true]);
                        }
                    }),

                Action::make('reply')
                    ->label('')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->modalWidth('4xl')
                    ->form(fn (ProjectEmail $record) => [
                        Forms\Components\Placeholder::make('original')
                            ->label('Eredeti üzenet')
                            ->content(function () use ($record) {
                                $recipientEmail = $record->isOutbound() ? $record->to_email : $record->from_email;
                                $recipientName = $record->isOutbound() ? '' : $record->from_name;

                                return new HtmlString(
                                    "<div style='background: #f5f5f5; padding: 12px; border-radius: 8px; border-left: 4px solid #e5a00d;'>".
                                    "<div style='margin-bottom: 8px; color: #666; font-size: 13px;'>".
                                    ($record->isOutbound() ? "<strong>Címzett:</strong> " : "<strong>Feladó:</strong> ").
                                    e($recipientName).($recipientName ? " &lt;".e($recipientEmail)."&gt;" : e($recipientEmail))."<br>".
                                    "<strong>Dátum:</strong> ".$record->email_date->format('Y-m-d H:i')."<br>".
                                    "<strong>Tárgy:</strong> ".e($record->subject).
                                    "</div>".
                                    "<div style='color: #333;'>".($record->clean_body_html ?: nl2br(e($record->clean_body_text ?? '')))."</div>".
                                    "</div>"
                                );
                            }),

                        $this->getSnippetSelectorField(),

                        Forms\Components\TextInput::make('subject')
                            ->label('Tárgy')
                            ->required()
                            ->default('Re: ' . preg_replace('/^(Re:\s*)+/i', '', $record->subject)),

                        Forms\Components\RichEditor::make('body')
                            ->label('Válasz')
                            ->required()
                            ->extraInputAttributes(['style' => 'min-height: 300px;'])
                            ->columnSpanFull(),

                        Forms\Components\CheckboxList::make('selected_samples')
                            ->label('Csatolt minták')
                            ->options(function () {
                                /** @var \App\Models\TabloProject $project */
                                $project = $this->getOwnerRecord();
                                $samples = $project->getMedia('samples')
                                    ->filter(fn ($m) => $m->getCustomProperty('is_active', true))
                                    ->sortByDesc('created_at')
                                    ->take(5);

                                return $samples->mapWithKeys(function ($sample) {
                                    $thumbUrl = $sample->getUrl('thumb');
                                    $fullUrl = $sample->getUrl();
                                    $fileName = e($sample->file_name);
                                    $timeAgo = $sample->created_at->diffForHumans();

                                    $label = "<div style='display: flex; align-items: center; gap: 10px;'>"
                                        . "<img src='{$thumbUrl}' onclick=\"event.stopPropagation(); event.preventDefault(); window.open('{$fullUrl}', '_blank', 'width=1200,height=900'); return false;\" style='width: 45px; height: 45px; object-fit: cover; border-radius: 4px; cursor: zoom-in;' title='Nagyítás' />"
                                        . "<div>"
                                        . "<div style='font-weight: 500;'>{$fileName}</div>"
                                        . "<div style='font-size: 12px; color: #888;'>{$timeAgo}</div>"
                                        . "</div>"
                                        . "</div>";

                                    return [$sample->id => new HtmlString($label)];
                                })->toArray();
                            })
                            ->default(function () {
                                /** @var \App\Models\TabloProject $project */
                                $project = $this->getOwnerRecord();
                                $lastSample = $project->getMedia('samples')
                                    ->filter(fn ($m) => $m->getCustomProperty('is_active', true))
                                    ->sortByDesc('created_at')
                                    ->first();

                                return $lastSample ? [$lastSample->id] : [];
                            })
                            ->columns(1),
                    ])
                    ->action(function (ProjectEmail $record, array $data) {
                        /** @var TabloProject $project */
                        $project = $this->getOwnerRecord();

                        // Tablókirály SMTP fiók lekérése (projekt emailezéshez)
                        $smtpAccount = SmtpAccount::where('from_address', 'info@tablokiraly.hu')->first();
                        if (! $smtpAccount) {
                            Notification::make()
                                ->title('Nincs Tablókirály SMTP fiók')
                                ->body('Kérlek állítsd be a Tablókirály SMTP fiókot (info@tablokiraly.hu).')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $attachments = [];
                            $selectedSampleIds = $data['selected_samples'] ?? [];
                            if (! empty($selectedSampleIds)) {
                                $samples = $project->getMedia('samples')
                                    ->filter(fn ($m) => in_array($m->id, $selectedSampleIds));

                                foreach ($samples as $sample) {
                                    $attachments[] = [
                                        'path' => $sample->getPath(),
                                        'name' => $sample->file_name,
                                        'mime' => $sample->mime_type,
                                        'media_id' => $sample->id,
                                    ];
                                }
                            }

                            $mailerName = $smtpAccount->getDynamicMailerName();

                            // Kimenő emailnél a to_email-re válaszolunk, bejövőnél a from_email-re
                            $recipientEmail = $record->isOutbound() ? $record->to_email : $record->from_email;

                            Mail::mailer($mailerName)->send([], [], function ($message) use ($record, $data, $attachments, $smtpAccount, $recipientEmail) {
                                $message->from($smtpAccount->from_address, $smtpAccount->from_name)
                                    ->to($recipientEmail)
                                    ->subject($data['subject'])
                                    ->html($data['body']);

                                $message->getHeaders()->addTextHeader('In-Reply-To', $record->message_id);
                                $message->getHeaders()->addTextHeader('References', $record->message_id);

                                foreach ($attachments as $attachment) {
                                    $message->attach($attachment['path'], [
                                        'as' => $attachment['name'],
                                        'mime' => $attachment['mime'],
                                    ]);
                                }
                            });

                            // IMAP Sent mentés
                            if ($smtpAccount->canSaveToSent()) {
                                $imapAttachments = array_map(fn ($a) => [
                                    'content' => file_get_contents($a['path']),
                                    'filename' => $a['name'],
                                    'mime' => $a['mime'],
                                ], $attachments);
                                $smtpAccount->saveToSentFolder($recipientEmail, $data['subject'], $data['body'], [], $imapAttachments);
                            }

                            ProjectEmail::create([
                                'tablo_project_id' => $project->id,
                                'message_id' => 'local-'.uniqid(),
                                'thread_id' => $record->thread_id ?? $record->message_id,
                                'in_reply_to' => $record->message_id,
                                'from_email' => $smtpAccount->from_address,
                                'from_name' => $smtpAccount->from_name,
                                'to_email' => $recipientEmail,
                                'subject' => $data['subject'],
                                'body_html' => $data['body'],
                                'direction' => 'outbound',
                                'is_read' => true,
                                'needs_reply' => false,
                                'attachments' => $attachments ? array_map(fn ($a) => [
                                    'name' => $a['name'],
                                    'mime_type' => $a['mime'],
                                    'media_id' => $a['media_id'] ?? null,
                                ], $attachments) : null,
                                'email_date' => now(),
                            ]);

                            $record->update(['is_replied' => true]);

                            Notification::make()
                                ->title('Válasz elküldve')
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Hiba')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('markReplied')
                    ->label('')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ProjectEmail $record) => $record->needs_reply && ! $record->is_replied)
                    ->requiresConfirmation()
                    ->modalHeading('Megválaszoltnak jelölés')
                    ->action(fn (ProjectEmail $record) => $record->update(['is_replied' => true])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('markReplied')
                        ->label('Megválaszoltnak jelölés')
                        ->icon('heroicon-o-check')
                        ->action(fn (Collection $records) => $records->each->update(['is_replied' => true]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
