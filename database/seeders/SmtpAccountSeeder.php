<?php

namespace Database\Seeders;

use App\Models\SmtpAccount;
use Illuminate\Database\Seeder;

class SmtpAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Mailpit - Lokális teszt mail server (Docker)
        SmtpAccount::create([
            'name' => 'Mailpit (Lokális Teszt)',
            'mailer_type' => 'smtp',
            'host' => 'mailpit',  // Docker service név
            'port' => 1025,
            'username' => null,
            'password' => null,
            'encryption' => null,  // Nincs TLS/SSL
            'from_address' => 'test@localhost.dev',
            'from_name' => 'Photo Stack Teszt',
            'rate_limit_per_minute' => 2,  // 30 másodpercenként 1 email (2/perc)
            'rate_limit_per_hour' => 120,  // 120 email/óra
            'priority' => 0,
            'is_prod' => false,
            'is_active' => true,  // Alapértelmezett aktív
            'health_status' => 'unchecked',
        ]);

        // Example 1: Gmail SMTP (Production)
        SmtpAccount::create([
            'name' => 'Gmail Production',
            'mailer_type' => 'smtp',
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'your-email@gmail.com',
            'password' => 'your-app-password',
            'encryption' => 'tls',
            'from_address' => 'noreply@yourcompany.com',
            'from_name' => 'Your Company Name',
            'rate_limit_per_minute' => 10,
            'rate_limit_per_hour' => 100,
            'priority' => 1,
            'is_prod' => true,
            'is_active' => false, // Állítsd true-ra használathoz
            'health_status' => 'unchecked',
            'dkim_domain' => 'yourcompany.com',
            'dkim_selector' => 'default',
            'dmarc_policy' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc-reports@yourcompany.com',
            'spf_record' => 'v=spf1 include:_spf.google.com ~all',
            'bounce_email' => 'bounce@yourcompany.com',
        ]);

        // Example 2: Mailgun (Development)
        SmtpAccount::create([
            'name' => 'Mailgun Development',
            'mailer_type' => 'smtp',
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'username' => 'postmaster@sandbox.mailgun.org',
            'password' => 'your-mailgun-password',
            'encryption' => 'tls',
            'from_address' => 'test@sandbox.mailgun.org',
            'from_name' => 'Test Environment',
            'rate_limit_per_minute' => 20,
            'rate_limit_per_hour' => 300,
            'priority' => 2,
            'is_prod' => false,
            'is_active' => false,
            'health_status' => 'unchecked',
        ]);

        // Example 3: AWS SES (Production)
        SmtpAccount::create([
            'name' => 'AWS SES Production',
            'mailer_type' => 'ses',
            'host' => 'email-smtp.eu-central-1.amazonaws.com',
            'port' => 587,
            'username' => 'your-ses-smtp-username',
            'password' => 'your-ses-smtp-password',
            'encryption' => 'tls',
            'from_address' => 'noreply@yourcompany.com',
            'from_name' => 'Your Company',
            'rate_limit_per_minute' => 50,
            'rate_limit_per_hour' => 1000,
            'priority' => 1,
            'is_prod' => true,
            'is_active' => false,
            'health_status' => 'unchecked',
            'dkim_domain' => 'yourcompany.com',
            'dkim_selector' => 'ses',
            'spf_record' => 'v=spf1 include:amazonses.com ~all',
        ]);

        // Example 4: Local Mailtrap (Development)
        SmtpAccount::create([
            'name' => 'Mailtrap Development',
            'mailer_type' => 'smtp',
            'host' => 'sandbox.smtp.mailtrap.io',
            'port' => 2525,
            'username' => 'your-mailtrap-username',
            'password' => 'your-mailtrap-password',
            'encryption' => 'tls',
            'from_address' => 'test@example.com',
            'from_name' => 'Test Sender',
            'rate_limit_per_minute' => null, // Unlimited
            'rate_limit_per_hour' => null,
            'priority' => 1,
            'is_prod' => false,
            'is_active' => false, // Mailpit használata preferált
            'health_status' => 'unchecked',
        ]);
    }
}
