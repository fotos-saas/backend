<?php

namespace Database\Seeders;

use App\Models\EmailVariable;
use Illuminate\Database\Seeder;

class EmailVariableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $variables = [
            // General variables
            [
                'key' => 'company_name',
                'value' => 'Tabló Király',
                'description' => 'A cég/szolgáltató neve - minden emailben használható',
                'group' => 'general',
                'priority' => 10,
            ],
            [
                'key' => 'company_tagline',
                'value' => 'A legjobb fotónyomtatás',
                'description' => 'Cég szlogenje/mottója',
                'group' => 'general',
                'priority' => 20,
            ],
            [
                'key' => 'support_hours',
                'value' => 'H-P 9:00-17:00',
                'description' => 'Ügyfélszolgálat nyitvatartása',
                'group' => 'general',
                'priority' => 30,
            ],

            // Composite variables (using other variables)
            [
                'key' => 'footer_text',
                'value' => '© {current_year} {company_name}. Minden jog fenntartva.',
                'description' => 'Email lábléc szöveg - automatikusan frissülő évszámmal',
                'group' => 'general',
                'priority' => 100,
            ],
            [
                'key' => 'email_signature',
                'value' => "Üdvözlettel,\n{company_name} csapata\n{partner_email} | {partner_phone}",
                'description' => 'Email aláírás - komplex változókkal',
                'group' => 'general',
                'priority' => 110,
            ],
            [
                'key' => 'full_company_header',
                'value' => '{company_name} - {company_tagline}',
                'description' => 'Teljes cég header név + szlogen',
                'group' => 'general',
                'priority' => 120,
            ],

            // Support contact
            [
                'key' => 'support_message',
                'value' => 'Ha kérdésed van, írj nekünk: {partner_email} vagy hívj minket {support_hours} között.',
                'description' => 'Standard support üzenet rekurzív hivatkozásokkal',
                'group' => 'general',
                'priority' => 130,
            ],

            // Custom variables
            [
                'key' => 'privacy_policy_url',
                'value' => '{site_url}/adatvedelem',
                'description' => 'Adatvédelmi tájékoztató URL',
                'group' => 'custom',
                'priority' => 200,
            ],
            [
                'key' => 'terms_url',
                'value' => '{site_url}/aszf',
                'description' => 'ÁSZF URL',
                'group' => 'custom',
                'priority' => 210,
            ],
            [
                'key' => 'unsubscribe_url',
                'value' => '{site_url}/leiratkozas',
                'description' => 'Leiratkozás URL',
                'group' => 'custom',
                'priority' => 220,
            ],
        ];

        foreach ($variables as $variable) {
            EmailVariable::updateOrCreate(
                ['key' => $variable['key']],
                $variable
            );
        }
    }
}
