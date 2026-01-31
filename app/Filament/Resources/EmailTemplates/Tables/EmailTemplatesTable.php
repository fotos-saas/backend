<?php

namespace App\Filament\Resources\EmailTemplates\Tables;

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Services\BrandingService;
use App\Services\EmailService;
use App\Services\EmailVariableService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

class EmailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Azonosító')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Tárgy')
                    ->limit(60)
                    ->tooltip(fn (EmailTemplate $record) => $record->subject)
                    ->searchable(),
                Tables\Columns\TextColumn::make('smtpAccount.name')
                    ->label('SMTP Fiók')
                    ->badge()
                    ->default('Alapértelmezett')
                    ->sortable(),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioritás')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'low' => 'Alacsony',
                        'normal' => 'Normál',
                        'high' => 'Magas',
                        'critical' => 'Kritikus',
                        default => $state,
                    })
                    ->colors([
                        'gray' => 'low',
                        'info' => 'normal',
                        'warning' => 'high',
                        'danger' => 'critical',
                    ])
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktív')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Módosítva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktív státusz')
                    ->trueLabel('Csak aktív')
                    ->falseLabel('Csak inaktív'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('preview')
                        ->label('Előnézet')
                        ->url(fn (EmailTemplate $record) => EmailTemplateResource::getUrl('preview', ['record' => $record]))
                        ->icon('heroicon-o-eye')
                        ->color('info'),
                    EditAction::make()
                        ->label('Szerkesztés'),
                    Action::make('send_test')
                        ->label('Teszt Email')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->form([
                            Forms\Components\TextInput::make('recipient_email')
                                ->label('Címzett email')
                                ->email()
                                ->required()
                                ->default(fn () => auth()->user()->email)
                                ->helperText('Add meg az email címet, ahova a teszt emailt szeretnéd küldeni.'),
                        ])
                        ->action(function (EmailTemplate $record, array $data) {
                            $emailService = app(EmailService::class);
                            $variableService = app(EmailVariableService::class);

                            // Teszt változók generálása
                            $branding = app(BrandingService::class);
                            $testVariables = [
                                'user_name' => 'Teszt Felhasználó',
                                'user_email' => 'teszt@example.com',
                                'user_phone' => '+36 30 123 4567',
                                'user_class' => '9.A',
                                'album_title' => 'Teszt Album',
                                'album_class' => '9.A',
                                'album_photo_count' => '42',
                                'order_id' => '12345',
                                'order_total' => '25 000 Ft',
                                'order_status' => 'feldolgozás alatt',
                                'order_items_count' => '3',
                                'site_name' => $branding->getName(),
                                'site_url' => $branding->getWebsite() ?? config('app.url'),
                                'partner_email' => $branding->getEmail() ?? config('mail.from.address'),
                                'partner_phone' => $branding->getPhone() ?? '',
                                'partner_address' => $branding->getAddress() ?? '',
                                'partner_tax_number' => $branding->getTaxNumber() ?? '',
                                'partner_landing_page' => $branding->getLandingPageUrl() ?? ($branding->getWebsite() ?? config('app.url')),
                                'current_date' => now()->format('Y-m-d'),
                                'current_year' => now()->format('Y'),
                            ];

                            try {
                                $emailService->sendFromTemplate(
                                    template: $record,
                                    recipientEmail: $data['recipient_email'],
                                    variables: $testVariables,
                                    recipientUser: auth()->user(),
                                    eventType: 'manual'
                                );

                                Notification::make()
                                    ->title('Teszt email elküldve!')
                                    ->body("Az email sikeresen elküldve a következő címre: {$data['recipient_email']}")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Hiba történt!')
                                    ->body("Az email küldése sikertelen: {$e->getMessage()}")
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
                    ->label('Műveletek')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->description('Email sablonok nem törölhetők, csak szerkeszthetők. Ez biztosítja, hogy az automatikus email küldések mindig működjenek.')
            ->modifyQueryUsing(function ($query) {
                $newTemplateId = session('new_email_template_id');

                if ($newTemplateId) {
                    return $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$newTemplateId])
                        ->orderBy('created_at', 'desc');
                }

                return $query->orderBy('created_at', 'desc');
            })
            ->recordClasses(function (EmailTemplate $record) {
                $createdAt = $record->created_at;
                $tenSecondsAgo = now()->subSeconds(10);

                return $createdAt && $createdAt->isAfter($tenSecondsAgo)
                    ? 'fi-ta-row-new'
                    : null;
            });
    }
}
