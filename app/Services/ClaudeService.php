<?php

namespace App\Services;

use Anthropic\Laravel\Facades\Anthropic;
use Illuminate\Support\Facades\Log;

/**
 * Újrahasználható Claude AI service.
 * Anthropic API hívások kezelése JSON válaszokkal.
 */
class ClaudeService
{
    protected string $defaultModel;

    protected int $defaultMaxTokens;

    protected int $timeout;

    public function __construct()
    {
        $this->defaultModel = config('anthropic.model', 'claude-sonnet-4-20250514');
        $this->defaultMaxTokens = config('anthropic.max_tokens', 4096);
        $this->timeout = config('anthropic.request_timeout', 60);
    }

    /**
     * Chat kérés küldése Claude-nak.
     *
     * @param  string  $userPrompt  Felhasználói üzenet
     * @param  string|null  $systemPrompt  Rendszer prompt (opcionális)
     * @param  array  $options  További opciók (model, max_tokens, temperature)
     * @return array{content: string, model: string, usage: array}
     *
     * @throws \Exception
     */
    public function chat(string $userPrompt, ?string $systemPrompt = null, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $maxTokens = $options['max_tokens'] ?? $this->defaultMaxTokens;
        $temperature = $options['temperature'] ?? 0.0;

        $parameters = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
        ];

        if ($systemPrompt) {
            $parameters['system'] = $systemPrompt;
        }

        Log::debug('Claude API request', [
            'model' => $model,
            'system_prompt_length' => $systemPrompt ? strlen($systemPrompt) : 0,
            'user_prompt_length' => strlen($userPrompt),
        ]);

        try {
            $response = Anthropic::messages()->create($parameters);

            $content = $response->content[0]->text ?? '';

            Log::debug('Claude API response', [
                'model' => $response->model,
                'input_tokens' => $response->usage->inputTokens,
                'output_tokens' => $response->usage->outputTokens,
                'content_length' => strlen($content),
            ]);

            return [
                'content' => $content,
                'model' => $response->model,
                'usage' => [
                    'input_tokens' => $response->usage->inputTokens,
                    'output_tokens' => $response->usage->outputTokens,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Claude API error', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);

            throw $e;
        }
    }

    /**
     * JSON válasz kinyerése a Claude válaszból.
     *
     * @param  string  $content  Claude válasz szövege
     * @return array Dekódolt JSON
     *
     * @throws \JsonException Ha nem sikerül a JSON parsing
     */
    public function parseJsonResponse(string $content): array
    {
        // Keresés JSON blokk után (```json ... ``` vagy { ... })
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $jsonString = $matches[1];
        } elseif (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $jsonString = $matches[0];
        } else {
            throw new \JsonException('No JSON found in response');
        }

        return json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Chat kérés JSON válasszal.
     *
     * @param  string  $userPrompt  Felhasználói üzenet
     * @param  string|null  $systemPrompt  Rendszer prompt
     * @param  array  $options  További opciók
     * @return array Dekódolt JSON válasz
     *
     * @throws \Exception
     */
    public function chatJson(string $userPrompt, ?string $systemPrompt = null, array $options = []): array
    {
        $response = $this->chat($userPrompt, $systemPrompt, $options);

        return $this->parseJsonResponse($response['content']);
    }

    /**
     * PDF dokumentum elemzése vision API-val.
     *
     * @param  string  $pdfPath  PDF fájl elérési útja
     * @param  string  $prompt  Elemzési instrukciók
     * @param  string|null  $systemPrompt  Rendszer prompt
     * @param  array  $options  További opciók
     * @return array Dekódolt JSON válasz
     *
     * @throws \Exception
     */
    public function analyzePdf(string $pdfPath, string $prompt, ?string $systemPrompt = null, array $options = []): array
    {
        if (!file_exists($pdfPath)) {
            throw new \Exception("PDF file not found: {$pdfPath}");
        }

        $pdfContent = file_get_contents($pdfPath);
        $base64Pdf = base64_encode($pdfContent);

        $model = $options['model'] ?? $this->defaultModel;
        $maxTokens = $options['max_tokens'] ?? 8192;
        $temperature = $options['temperature'] ?? 0.0;

        $parameters = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'document',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => 'application/pdf',
                                'data' => $base64Pdf,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        ];

        if ($systemPrompt) {
            $parameters['system'] = $systemPrompt;
        }

        Log::debug('Claude PDF analysis request', [
            'model' => $model,
            'pdf_size' => strlen($pdfContent),
            'prompt_length' => strlen($prompt),
        ]);

        try {
            $response = Anthropic::messages()->create($parameters);

            $content = $response->content[0]->text ?? '';

            Log::debug('Claude PDF analysis response', [
                'model' => $response->model,
                'input_tokens' => $response->usage->inputTokens,
                'output_tokens' => $response->usage->outputTokens,
                'content_length' => strlen($content),
            ]);

            return $this->parseJsonResponse($content);
        } catch (\Exception $e) {
            Log::error('Claude PDF analysis error', [
                'error' => $e->getMessage(),
                'model' => $model,
                'pdf_path' => $pdfPath,
            ]);

            throw $e;
        }
    }

    /**
     * Keresztnév kinyerése magyar névből Haiku modellel.
     * Felismeri a magyar névsorrendet (vezetéknév + keresztnév).
     *
     * @param  string  $fullName  Teljes név
     * @return string Keresztnév ékezetek nélkül (SMS-hez)
     */
    public function extractFirstName(string $fullName): string
    {
        if (empty(trim($fullName))) {
            return '';
        }

        try {
            $response = $this->chat(
                userPrompt: "Név: \"{$fullName}\"\n\nMi a keresztnév? Csak a keresztnevet írd, semmi mást!",
                systemPrompt: "Magyar nevek keresztnevét kell kinyerned. A magyar névsorrendben általában a vezetéknév van elöl, a keresztnév hátul (pl. \"Kovács Anna\" -> \"Anna\", \"Nagy Péter\" -> \"Péter\"). De néha fordítva is lehet. Csak a keresztnevet válaszold, semmi mást, semmi magyarázatot!",
                options: [
                    'model' => 'claude-haiku-4-20250514',
                    'max_tokens' => 50,
                    'temperature' => 0.0,
                ]
            );

            $firstName = trim($response['content']);

            // Ékezetek eltávolítása SMS-hez (GSM-7 kompatibilitás)
            return strtr($firstName, [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
                'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
                'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ö' => 'O', 'Ő' => 'O',
                'Ú' => 'U', 'Ü' => 'U', 'Ű' => 'U',
            ]);
        } catch (\Exception $e) {
            Log::warning('Claude keresztnév kinyerés hiba, fallback első szóra', [
                'name' => $fullName,
                'error' => $e->getMessage(),
            ]);

            // Fallback: utolsó szó (általában keresztnév magyar sorrendben)
            $parts = explode(' ', trim($fullName));
            $firstName = end($parts) ?: $fullName;

            return strtr($firstName, [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
                'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
                'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ö' => 'O', 'Ő' => 'O',
                'Ú' => 'U', 'Ü' => 'U', 'Ű' => 'U',
            ]);
        }
    }
}
