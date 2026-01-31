<?php

namespace Database\Seeders;

use App\Models\EmailEvent;
use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailEventsSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'welcome_email' => EmailTemplate::where('name', 'welcome_email')->first(),
            'order_confirmation' => EmailTemplate::where('name', 'order_confirmation')->first(),
            'album_created_notification' => EmailTemplate::where('name', 'album_created_notification')->first(),
            'order_status_changed' => EmailTemplate::where('name', 'order_status_changed')->first(),
            'user_created_credentials' => EmailTemplate::where('name', 'user_created_credentials')->first(),
            'work_session_access_code' => EmailTemplate::where('name', 'work_session_access_code')->first(),
            'work_session_invite' => EmailTemplate::where('name', 'work_session_invite')->first(),
            'order_payment_received' => EmailTemplate::where('name', 'order_payment_received')->first(),
            'order_shipped' => EmailTemplate::where('name', 'order_shipped')->first(),
            'password_changed' => EmailTemplate::where('name', 'password_changed')->first(),
            'registration_welcome' => EmailTemplate::where('name', 'registration_welcome')->first(),
            'user_magic_login' => EmailTemplate::where('name', 'user_magic_login')->first(),
            'tablo_workflow_completed' => EmailTemplate::where('name', 'tablo_workflow_completed')->first(),
            'tablo_user_registered' => EmailTemplate::where('name', 'tablo_user_registered')->first(),
            'zip_ready' => EmailTemplate::where('name', 'zip_ready')->first(),
        ];

        // Check if all templates exist
        $missingTemplates = [];
        foreach ($templates as $key => $template) {
            if (! $template) {
                $missingTemplates[] = $key;
            }
        }

        if (! empty($missingTemplates)) {
            $this->command->warn('Hiányzó email sablonok: '.implode(', ', $missingTemplates));
            $this->command->warn('Futtasd előbb az EmailTemplatesSeeder-t!');

            return;
        }

        $events = [
            // Auth & User Management
            [
                'event_type' => 'user_registered',
                'email_template_id' => $templates['welcome_email']->id,
                'recipient_type' => 'user',
                'is_active' => true,
            ],
            [
                'event_type' => 'user_created_credentials',
                'email_template_id' => $templates['user_created_credentials']->id,
                'recipient_type' => 'user',
                'is_active' => true,
            ],
            [
                'event_type' => 'registration_complete',
                'email_template_id' => $templates['registration_welcome']->id,
                'recipient_type' => 'user',
                'is_active' => true,
            ],
            [
                'event_type' => 'password_changed',
                'email_template_id' => $templates['password_changed']->id,
                'recipient_type' => 'user',
                'is_active' => true,
            ],
            [
                'event_type' => 'user_magic_login',
                'email_template_id' => $templates['user_magic_login']->id,
                'recipient_type' => 'user',
                'is_active' => true,
            ],

            // WorkSession
            [
                'event_type' => 'work_session_created',
                'email_template_id' => $templates['work_session_access_code']->id,
                'recipient_type' => 'user',
                'is_active' => true,
            ],
            [
                'event_type' => 'work_session_invite',
                'email_template_id' => $templates['work_session_invite']->id,
                'recipient_type' => 'user',
                'is_active' => true,
            ],

            // Orders
            [
                'event_type' => 'order_placed',
                'email_template_id' => $templates['order_confirmation']->id,
                'recipient_type' => 'order_user',
                'is_active' => true,
            ],
            [
                'event_type' => 'order_status_changed',
                'email_template_id' => $templates['order_status_changed']->id,
                'recipient_type' => 'order_user',
                'is_active' => true,
            ],
            [
                'event_type' => 'order_payment_received',
                'email_template_id' => $templates['order_payment_received']->id,
                'recipient_type' => 'order_user',
                'is_active' => true,
            ],
            [
                'event_type' => 'order_shipped',
                'email_template_id' => $templates['order_shipped']->id,
                'recipient_type' => 'order_user',
                'is_active' => true,
            ],

            // Albums
            [
                'event_type' => 'album_created',
                'email_template_id' => $templates['album_created_notification']->id,
                'recipient_type' => 'album_users',
                'is_active' => false, // Kezdetben inaktív, mert sok user-nek menne
            ],

            // Tablo Workflow
            [
                'event_type' => 'tablo_completed',
                'email_template_id' => $templates['tablo_workflow_completed']->id,
                'recipient_type' => 'user',
                'is_active' => true,
            ],
            [
                'event_type' => 'tablo_user_registered',
                'email_template_id' => $templates['tablo_user_registered']->id,
                'recipient_type' => 'user',
                'is_active' => true,
            ],

            // ZIP Downloads
            [
                'event_type' => 'zip_ready',
                'email_template_id' => $templates['zip_ready']->id,
                'recipient_type' => 'user',
                'is_active' => true,
            ],
        ];

        foreach ($events as $event) {
            EmailEvent::updateOrCreate(
                [
                    'event_type' => $event['event_type'],
                    'email_template_id' => $event['email_template_id'],
                ],
                $event
            );
        }

        $this->command->info('Email események sikeresen létrehozva!');
    }
}
