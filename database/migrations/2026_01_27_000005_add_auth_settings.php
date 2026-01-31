<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds authentication-related settings to the settings table.
     */
    public function up(): void
    {
        // Registration settings
        Setting::set('auth.registration_enabled', false);
        Setting::set('auth.email_verification_required', true);

        // Security settings
        Setting::set('auth.password_breach_check', true); // haveibeenpwned integration
        Setting::set('auth.two_factor_available', false); // Will enable later

        // Session management
        Setting::set('auth.max_sessions_per_user', 5);

        // Account lockout settings (brute force protection)
        Setting::set('auth.lockout_threshold', 5); // Failed attempts before lock
        Setting::set('auth.lockout_duration_minutes', 30);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Setting::where('key', 'like', 'auth.%')->delete();
    }
};
