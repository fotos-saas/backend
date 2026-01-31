<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Services\BrandingService;
use App\Services\EmailService;
use App\Services\EmailVariableService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class PreviewEmailTemplate extends ViewRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected string $view = 'filament.resources.email-templates.pages.preview-email-template';

    public function getTitle(): string
    {
        return 'Email Előnézet: '.$this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send_test')
                ->label('Teszt Email Küldése')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->form([
                    Forms\Components\TextInput::make('recipient_email')
                        ->label('Címzett email')
                        ->email()
                        ->required()
                        ->default(fn () => auth()->user()->email),
                ])
                ->action(function (array $data) {
                    $emailService = app(EmailService::class);
                    $testVariables = $this->getTestVariables();

                    try {
                        $emailService->sendFromTemplate(
                            template: $this->record,
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
        ];
    }

    public function getPreviewHtml(): HtmlString
    {
        $variableService = app(EmailVariableService::class);
        $testVariables = $this->getTestVariables();

        $subject = $variableService->replaceVariables($this->record->subject, $testVariables);
        $body = $variableService->replaceVariables($this->record->body, $testVariables);

        return new HtmlString(view('emails.template', ['body' => $body])->render());
    }

    public function getResolvedSubject(): string
    {
        $variableService = app(EmailVariableService::class);

        return $variableService->replaceVariables($this->record->subject, $this->getTestVariables());
    }

    protected function getTestVariables(): array
    {
        $branding = app(BrandingService::class);

        return [
            'user_name' => 'Teszt Felhasználó',
            'user_email' => 'teszt@example.com',
            'user_phone' => '+36 30 123 4567',
            'user_class' => '9.A',
            'album_title' => 'Őszi Tablófotózás 2025',
            'album_class' => '9.A',
            'album_photo_count' => '42',
            'order_id' => '12345',
            'order_total' => '25 000 Ft',
            'order_status' => 'feldolgozás alatt',
            'order_items_count' => '3',
            'site_name' => $branding->getName(),
            'site_url' => $branding->getWebsite() ?? config('app.url', 'http://localhost'),
            'partner_email' => $branding->getEmail() ?? config('mail.from.address'),
            'partner_phone' => $branding->getPhone() ?? '',
            'partner_address' => $branding->getAddress() ?? '',
            'partner_tax_number' => $branding->getTaxNumber() ?? '',
            'partner_landing_page' => $branding->getLandingPageUrl() ?? ($branding->getWebsite() ?? config('app.url')),
            'current_date' => now()->format('Y-m-d'),
            'current_year' => now()->format('Y'),
        ];
    }
}
