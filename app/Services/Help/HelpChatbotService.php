<?php

namespace App\Services\Help;

use App\Models\HelpArticle;
use App\Services\ClaudeService;
use Illuminate\Support\Collection;

class HelpChatbotService
{
    public function __construct(
        private ClaudeService $claudeService,
        private HelpKnowledgeBaseService $knowledgeBaseService,
    ) {}

    /**
     * System prompt összeállítása a kontextus alapján.
     */
    public function buildSystemPrompt(
        string $role,
        string $plan,
        ?string $route = null,
        array $featureKeys = [],
        ?Collection $relevantArticles = null,
    ): string {
        $planName = config("plans.plans.{$plan}.name", $plan);
        $planFeatures = config("plans.plans.{$plan}.feature_labels", []);

        $roleLabel = match ($role) {
            'partner' => 'Fotós (partner)',
            'designer' => 'Grafikus',
            'marketer' => 'Marketinges/ügyintéző',
            'printer' => 'Nyomdász',
            'assistant' => 'Ügyintéző',
            'guest' => 'Diák/Szülő (vendég)',
            'client' => 'Ügyfél',
            'super_admin' => 'Rendszergazda',
            default => $role,
        };

        $prompt = <<<PROMPT
Te Tabi vagy, a TablóStúdió platform segítője. Segítőkész, barátságos és informatív vagy.
Magyarul válaszolsz, röviden és tömören (max 3-4 bekezdés).

## Felhasználó kontextus
- **Szerepkör:** {$roleLabel}
- **Előfizetési csomag:** {$planName}
- **Elérhető funkciók:** %s
PROMPT;

        $prompt = sprintf($prompt, implode(', ', $planFeatures));

        if ($route) {
            $prompt .= "\n- **Jelenlegi oldal:** {$route}";
        }

        $prompt .= <<<'RULES'


## Szabályok
1. A felhasználó MÁR BE VAN JELENTKEZVE - soha ne magyarázd a bejelentkezést, QR kód beolvasást, vagy regisztrációt. Onnan folytasd, ahol már bent van a rendszerben.
2. Ha a felhasználó olyan funkcióról kérdez, ami NEM érhető el a csomagjában:
   - Mondd el melyik csomagtól érhető el (Iskola/Stúdió/VIP)
   - Vagy melyik addon aktiválja (pl. Közösségi csomag = Fórum + Szavazás)
3. Ha a felhasználó más szerepkör funkciójáról kérdez (pl. diák kérdez Stripe-ról):
   - Mondd el, hogy "Ezt a fotósod kezeli" vagy "Ez a partner felületen érhető el"
4. Mindig a felhasználó aktuális kontextusához és szerepköréhez igazodj
5. Ha nem tudod a választ, mondd el őszintén és javasolj alternatívát
6. Ne említsd, hogy tudásbázis cikkekből dolgoztál - természetesen válaszolj

## Platform leírás
A TablóStúdió egy tablófotós platform, ahol:
- **Fotósok (partnerek)** kezelik a projekteket, iskolákat, feltöltik a fotókat, beállítják a galériákat
- **Diákok/szülők (vendégek)** QR kóddal lépnek be, képet választanak, rendelhetnek nyomtatást
- **Webshop** fotónyomtatás rendelés Stripe fizetéssel
- **Közösségi funkciók:** Fórum, szavazás, newsfeed, poke (csomag-függő)
- **Gamification:** pontok, jelvények, rangsor
RULES;

        // Releváns KB cikkek hozzáadása
        if ($relevantArticles === null && $route) {
            $relevantArticles = $this->knowledgeBaseService->getForRoute($route, $role, $plan, 5);
        }

        if ($relevantArticles && $relevantArticles->isNotEmpty()) {
            $prompt .= "\n\n## Releváns információk\n";
            foreach ($relevantArticles as $article) {
                $prompt .= "\n### {$article->title}\n{$article->content_plain}\n";
            }
        }

        return $prompt;
    }

    /**
     * Chat válasz generálása.
     *
     * @param  array  $messages  Korábbi üzenetek [{role, content}, ...]
     * @param  string  $systemPrompt  System prompt
     * @return array{content: string, usage: array}
     */
    public function chat(array $messages, string $systemPrompt): array
    {
        return $this->claudeService->chatWithHistory($messages, $systemPrompt, [
            'max_tokens' => 1000,
            'temperature' => 0.7,
        ]);
    }
}
