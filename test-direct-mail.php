<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Sending test email directly via Laravel Mail...\n";

try {
    \Illuminate\Support\Facades\Mail::raw('This is a direct test email from Laravel!', function ($message) {
        $message->to('test@example.com')
            ->subject('Direct Test Email - ' . now());
    });

    echo "✅ Email sent successfully!\n";
    echo "Check Mailpit at: http://localhost:8026\n";
} catch (\Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
}
