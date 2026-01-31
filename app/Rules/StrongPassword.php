<?php

namespace App\Rules;

use App\Services\AuthenticationService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Strong Password Validation Rule
 *
 * Validates password strength requirements:
 * - Minimum 8 characters
 * - At least 1 uppercase letter
 * - At least 1 lowercase letter
 * - At least 1 number
 * - At least 1 special character
 *
 * Optionally checks against haveibeenpwned breach database.
 */
class StrongPassword implements ValidationRule
{
    private bool $checkBreach;

    public function __construct(bool $checkBreach = true)
    {
        $this->checkBreach = $checkBreach;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $authService = app(AuthenticationService::class);

        // Validate password strength
        $result = $authService->validatePassword($value);

        if (! $result['valid']) {
            foreach ($result['errors'] as $error) {
                $fail($error);
            }

            return;
        }

        // Optionally check for breached passwords
        if ($this->checkBreach && $authService->checkPasswordBreach($value)) {
            $fail('Ez a jelszó szerepel egy korábbi adatszivárgásban. Kérjük, válassz egy másik jelszót.');
        }
    }
}
