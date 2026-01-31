<?php

namespace Database\Seeders;

use App\Models\EmailEvent;
use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class TabloPhotoConflictEmailSeeder extends Seeder
{
    /**
     * Seed the tablo photo conflict email event and template
     */
    public function run(): void
    {
        // Create email template
        $template = EmailTemplate::updateOrCreate(
            ['name' => 'Tablófotó Konfliktus - Értesítés'],
            [
                'subject' => 'Néhány kép már nem elérhető - {{album_title}}',
                'body' => $this->getEmailBody(),
                'is_active' => true,
                'priority' => 'normal',
            ]
        );

        // Create email event
        EmailEvent::updateOrCreate(
            ['event_type' => 'tablo_photo_conflict'],
            [
                'email_template_id' => $template->id,
                'recipient_type' => 'user',
                'is_active' => true,
            ]
        );

        $this->command->info('✅ Tablo Photo Conflict email event and template created successfully.');
    }

    /**
     * Get email body HTML
     */
    private function getEmailBody(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Képek eltávolítva</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 30px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">
                                ⚠️ Néhány kép már nem elérhető
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                Kedves <strong>{{user_name}}</strong>!
                            </p>

                            <div style="padding: 20px; background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; margin-bottom: 24px;">
                                <p style="margin: 0 0 12px 0; color: #856404; font-size: 15px; font-weight: 600;">
                                    Sajnáljuk, de néhány általad kiválasztott kép már nem elérhető,
                                    mert egy másik felhasználó véglegesítette a rendelését előbb.
                                </p>
                                <p style="margin: 0; color: #856404; font-size: 14px;">
                                    <strong>Eltávolított képek száma:</strong> {{removed_count}} db
                                </p>
                            </div>

                            <div style="padding: 20px; background-color: #e7f3ff; border-left: 4px solid #2196f3; border-radius: 4px; margin-bottom: 24px;">
                                <p style="margin: 0 0 12px 0; color: #0d47a1; font-size: 14px; line-height: 1.6;">
                                    <strong>Hogyan működik a tablófotózás?</strong>
                                </p>
                                <p style="margin: 0; color: #1565c0; font-size: 14px; line-height: 1.6;">
                                    A rendszer <strong>"első véglegesít, első nyer"</strong> elvet követ.
                                    Több felhasználó is kiválaszthatja ugyanazokat a képeket,
                                    de aki előbb véglegesíti a rendelését, az kapja meg őket.
                                </p>
                            </div>

                            <h3 style="margin: 24px 0 16px 0; color: #333333; font-size: 18px; font-weight: 600;">
                                Mit tehetsz most?
                            </h3>

                            <ul style="margin: 0 0 24px 0; padding-left: 24px; color: #555555; font-size: 15px; line-height: 1.8;">
                                <li>Válassz <strong>új képeket</strong> a rendelésedhez a rendelkezésre álló képek közül</li>
                                <li>Folytasd a rendelésed a <strong>megmaradt képekkel</strong></li>
                                <li>Tekintsd át az aktuális kiválasztásod</li>
                            </ul>

                            <div style="text-align: center; margin: 32px 0;">
                                <a href="{{work_session_url}}" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);">
                                    Folytatom a munkamenetet
                                </a>
                            </div>

                            <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #e0e0e0;">
                                <p style="margin: 0; color: #666666; font-size: 13px; line-height: 1.6;">
                                    Ha bármilyen kérdésed van, vedd fel velünk a kapcsolatot:
                                    <a href="mailto:{{partner_email}}" style="color: #667eea; text-decoration: none;">{{partner_email}}</a>
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px; background-color: #f8f9fa; border-radius: 0 0 8px 8px; text-align: center;">
                            <p style="margin: 0 0 8px 0; color: #666666; font-size: 14px;">
                                Üdvözlettel,<br>
                                <strong>{{site_name}}</strong> csapat
                            </p>
                            <p style="margin: 8px 0 0 0; color: #999999; font-size: 12px;">
                                © {{current_year}} {{site_name}}. Minden jog fenntartva.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
