<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get first user
$user = App\Models\User::first();

if (!$user) {
    echo "ERROR: No users found\n";
    exit(1);
}

echo "User: {$user->email}\n";

// Generate magic link
$magicLinkService = app(App\Services\MagicLinkService::class);
$magicLink = $magicLinkService->generate($user, 24);
echo "Magic Link: {$magicLink['url']}\n";

// Find email event
$emailEvent = App\Models\EmailEvent::where('event_type', 'user_magic_login')
    ->where('is_active', true)
    ->first();

if (!$emailEvent || !$emailEvent->emailTemplate) {
    echo "ERROR: No active email event found for user_magic_login\n";
    exit(1);
}

$template = $emailEvent->emailTemplate;
echo "Template: {$template->name}\n";

// Prepare variables
$variableService = app(App\Services\EmailVariableService::class);
$variables = $variableService->resolveVariables(
    user: $user,
    authData: ['magic_link' => $magicLink['url']]
);

echo "Variables prepared\n";

// Send email
$emailService = app(App\Services\EmailService::class);
try {
    $emailService->sendFromTemplate(
        template: $template,
        recipientEmail: $user->email,
        variables: $variables,
        recipientUser: $user,
        eventType: 'user_magic_login'
    );
    echo "✅ Email sent successfully to {$user->email}!\n";
} catch (\Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
}
