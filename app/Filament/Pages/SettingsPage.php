<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Application Settings Page
 *
 * Manages system-wide configuration like Google Maps API key
 */
class SettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.pages.settings-page';

    protected static ?string $title = 'Beállítások';

    protected static ?string $navigationLabel = 'Beállítások';

    protected static ?int $navigationSort = 999;

    public static function getNavigationGroup(): ?string
    {
        return 'Rendszer';
    }

    /**
     * Check if the current user can access this page
     */
    public static function canAccess(): bool
    {
        return can_access_permission('settings.view');
    }

    public ?array $data = [];

    /**
     * Mount the page and load current settings
     */
    public function mount(): void
    {
        $this->form->fill([
            'google_maps_api_key' => Setting::get('google_maps_api_key', ''),
            // Auth settings
            'auth_registration_enabled' => Setting::get('auth.registration_enabled', false),
            'auth_email_verification_required' => Setting::get('auth.email_verification_required', true),
            'auth_password_breach_check' => Setting::get('auth.password_breach_check', true),
            'auth_max_sessions_per_user' => Setting::get('auth.max_sessions_per_user', 5),
            'auth_lockout_threshold' => Setting::get('auth.lockout_threshold', 5),
            'auth_lockout_duration_minutes' => Setting::get('auth.lockout_duration_minutes', 30),
        ]);
    }

    /**
     * Define the form schema
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Térkép Beállítások')
                    ->description('Google Maps integráció a csomagpont választóhoz')
                    ->components([
                        Forms\Components\TextInput::make('google_maps_api_key')
                            ->label('Google Maps API Kulcs')
                            ->password()
                            ->revealable()
                            ->helperText('Opcionális. Ha be van állítva, Google Maps jelenik meg. Ha üres, OpenStreetMap használata.')
                            ->placeholder('AIzaSy...')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('info')
                            ->label('')
                            ->content('**Hogyan szerzek API kulcsot?**

1. Menj a Google Cloud Console-ra: https://console.cloud.google.com/
2. Hozz létre új projektet
3. Engedélyezd a "Maps JavaScript API"-t
4. Generálj API kulcsot (Credentials)
5. Korlátozd a kulcsot a domain-edre
6. Állítsd be a billing-ot (van ingyenes 28,500 load/hó kvóta)')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Autentikáció Beállítások')
                    ->description('Frontend (frontend-tablo) felhasználói bejelentkezés beállítások')
                    ->components([
                        Forms\Components\Toggle::make('auth_registration_enabled')
                            ->label('Nyilvános regisztráció engedélyezése')
                            ->helperText('Ha bekapcsolod, bárki tud regisztrálni email és jelszó megadásával.')
                            ->default(false),

                        Forms\Components\Toggle::make('auth_email_verification_required')
                            ->label('Email cím megerősítés kötelező')
                            ->helperText('Ha bekapcsolod, a felhasználóknak meg kell erősíteniük az email címüket bejelentkezés előtt.')
                            ->default(true),

                        Forms\Components\Toggle::make('auth_password_breach_check')
                            ->label('Jelszó szivárgás ellenőrzés')
                            ->helperText('Ellenőrzi, hogy a jelszó nem szerepel-e korábbi adatszivárgásokban (haveibeenpwned.com API).')
                            ->default(true),

                        Forms\Components\TextInput::make('auth_max_sessions_per_user')
                            ->label('Maximum egyidejű munkamenetek')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->default(5)
                            ->helperText('Hány eszközön lehet egyszerre bejelentkezve egy felhasználó.'),

                        Forms\Components\TextInput::make('auth_lockout_threshold')
                            ->label('Fiók zárolás küszöb')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->default(5)
                            ->helperText('Hány sikertelen bejelentkezési kísérlet után zárolódik a fiók.'),

                        Forms\Components\TextInput::make('auth_lockout_duration_minutes')
                            ->label('Zárolás időtartama (perc)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->default(30)
                            ->suffix('perc')
                            ->helperText('Mennyi ideig marad zárolva a fiók a túl sok sikertelen kísérlet után.'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    /**
     * Save the settings
     */
    public function save(): void
    {
        $data = $this->form->getState();

        // Map settings
        Setting::set('google_maps_api_key', $data['google_maps_api_key'] ?? '');

        // Auth settings
        Setting::set('auth.registration_enabled', $data['auth_registration_enabled'] ?? false);
        Setting::set('auth.email_verification_required', $data['auth_email_verification_required'] ?? true);
        Setting::set('auth.password_breach_check', $data['auth_password_breach_check'] ?? true);
        Setting::set('auth.max_sessions_per_user', (int) ($data['auth_max_sessions_per_user'] ?? 5));
        Setting::set('auth.lockout_threshold', (int) ($data['auth_lockout_threshold'] ?? 5));
        Setting::set('auth.lockout_duration_minutes', (int) ($data['auth_lockout_duration_minutes'] ?? 30));

        Notification::make()
            ->title('Beállítások sikeresen mentve')
            ->success()
            ->send();
    }
}
