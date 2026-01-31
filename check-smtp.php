<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== SMTP Accounts ===\n\n";

$accounts = App\Models\SmtpAccount::all();

if ($accounts->isEmpty()) {
    echo "❌ No SMTP accounts found!\n";
    exit(1);
}

foreach ($accounts as $acc) {
    echo "ID: {$acc->id}\n";
    echo "Name: {$acc->name}\n";
    echo "Host: {$acc->host}\n";
    echo "Port: {$acc->port}\n";
    echo "Username: {$acc->username}\n";
    echo "Active: " . ($acc->is_active ? 'YES' : 'NO') . "\n";
    echo "Prod: " . ($acc->is_prod ? 'YES' : 'NO') . "\n";
    echo "Encryption: {$acc->encryption}\n";
    echo "---\n\n";
}

echo "\n=== Email Logs ===\n\n";

$logs = App\Models\EmailLog::orderBy('created_at', 'desc')->limit(5)->get();

foreach ($logs as $log) {
    echo "ID: {$log->id}\n";
    echo "To: {$log->recipient_email}\n";
    echo "Subject: {$log->subject}\n";
    echo "Status: {$log->status}\n";
    echo "SMTP Account: {$log->smtp_account_id}\n";
    if ($log->error_message) {
        echo "Error: {$log->error_message}\n";
    }
    echo "Created: {$log->created_at}\n";
    echo "---\n\n";
}
