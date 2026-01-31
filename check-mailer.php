<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Active SMTP Account Details ===\n\n";

$account = App\Models\SmtpAccount::where('is_active', true)
    ->where('is_prod', false)
    ->first();

if (!$account) {
    echo "❌ No active dev SMTP account found!\n";
    exit(1);
}

echo "ID: {$account->id}\n";
echo "Name: {$account->name}\n";
echo "Host: {$account->host}\n";
echo "Port: {$account->port}\n";
echo "Username: {$account->username}\n";
echo "Mailer Type: {$account->mailer_type}\n";
echo "Encryption: {$account->encryption}\n";

echo "\n=== Testing Mailer Configuration ===\n\n";

$mailerName = $account->getDynamicMailerName();
echo "Dynamic Mailer Name: {$mailerName}\n";

$config = config("mail.mailers.{$mailerName}");
echo "Mailer Config:\n";
print_r($config);

echo "\n=== Sending Test Email ===\n\n";

try {
    \Illuminate\Support\Facades\Mail::mailer($mailerName)
        ->raw('Test email via dynamic mailer', function ($message) {
            $message->to('test@example.com')
                ->subject('Test via Dynamic Mailer - ' . now());
        });

    echo "✅ Email sent successfully via {$mailerName}!\n";
    echo "Check Mailpit at: http://localhost:8026\n";
} catch (\Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n";
}
