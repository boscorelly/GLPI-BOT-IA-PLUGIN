<?php
/**
 * GLPI Bot IA - Providers IA (v2)
 *
 * Correctifs :
 *  - Factory vérifie la complétude des configs avant instanciation
 *  - Pas de curl_setopt CURLOPT_SSL_VERIFYPEER = false (TLS toujours vérifié)
 *  - Timeout adaptatifs selon présence d'images
 *  - Réponse HTTP 4xx/5xx traitée comme erreur (pas silencieuse)
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

// ============================================================
//  Classe de base
// ============================================================

abstract class PluginGlpibotiaAIProvider {

    abstract public function analyze(string $prompt): string;
    abstract public function analyzeWithImages(string $prompt, array $images): string;
    abstract public function supportsVision(): bool;
    abstract public function getName(): string;

    /**
     * HTTP POST cURL avec vérification TLS systématique.
     * Lève une exception si le code HTTP n'est pas 2xx.
     */
    protected function httpPost(
        string $url,
        array  $headers,
        array  $body,
        int    $timeout = 120
    ): array {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            // TLS toujours vérifié — ne jamais passer false ici
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // Désactiver les redirections pour éviter les rebinds SSRF
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            throw new RuntimeException("cURL error: {$curlErr}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $snippet = substr((string)$response, 0, 300);
            throw new RuntimeException("HTTP {$httpCode} — {$snippet}");
        }

        $decoded = json_decode((string)$response, true);
        if ($decoded === null) {
            throw new RuntimeException("JSON invalide (HTTP {$httpCode}): " . substr((string)$response, 0, 200));
        }

        return $decoded;
    }
}

// ============================================================
//  Claude (Anthropic)
// ============================================================

class PluginGlpiboticClaudeProvider extends PluginGlpibotiaAIProvider {

    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model) {
        $this->apiKey = $apiKey;
        $this->model  = $model;
    }

    public function getName(): string      { return "Claude ({$this->model})"; }
    public function supportsVision(): bool { return true; }

    public function analyze(string $prompt): string {
        return $this->call([['type' => 'text', 'text' => $prompt]]);
    }

    public function analyzeWithImages(string $prompt, array $images): string {
        $content = [['type' => 'text', 'text' => $prompt]];
        foreach ($images as $img) {
            $content[] = [
                'type'   => 'image',
                'source' => ['type' => 'base64', 'media_type' => $img['media_type'], 'data' => $img['data']],
            ];
        }
        return $this->call($content, count($images) > 0 ? 180 : 120);
    }

    private function call(array $content, int $timeout = 120): string {
        $result = $this->httpPost(
            'https://api.anthropic.com/v1/messages',
            [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            ['model' => $this->model, 'max_tokens' => 1500, 'messages' => [['role' => 'user', 'content' => $content]]],
            $timeout
        );

        if (isset($result['content'][0]['text'])) return $result['content'][0]['text'];
        if (isset($result['error']['message']))   throw new RuntimeException('Claude: ' . $result['error']['message']);
        throw new RuntimeException('Claude: réponse inattendue.');
    }
}

// ============================================================
//  OpenAI
// ============================================================

class PluginGlpibotiaOpenAIProvider extends PluginGlpibotiaAIProvider {

    protected string  $apiKey;
    protected string  $model;
    protected string  $apiUrl;

    public function __construct(string $apiKey, string $model, string $apiUrl = 'https://api.openai.com/v1/chat/completions') {
        $this->apiKey = $apiKey;
        $this->model  = $model;
        $this->apiUrl = $apiUrl;
    }

    public function getName(): string      { return "OpenAI ({$this->model})"; }
    public function supportsVision(): bool {
        return str_contains($this->model, 'gpt-4o') || str_contains($this->model, 'gpt-4-vision');
    }

    public function analyze(string $prompt): string {
        return $this->call([['type' => 'text', 'text' => $prompt]]);
    }

    public function analyzeWithImages(string $prompt, array $images): string {
        if (!$this->supportsVision()) return $this->analyze($prompt);

        $content = [['type' => 'text', 'text' => $prompt]];
        foreach ($images as $img) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => "data:{$img['media_type']};base64,{$img['data']}"]];
        }
        return $this->call($content, count($images) > 0 ? 180 : 120);
    }

    protected function call(array $content, int $timeout = 120): string {
        $result = $this->httpPost(
            $this->apiUrl,
            ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey],
            [
                'model'      => $this->model,
                'max_tokens' => 1500,
                'messages'   => [
                    ['role' => 'system', 'content' => 'Tu es un assistant technique de support informatique expert.'],
                    ['role' => 'user',   'content' => $content],
                ],
            ],
            $timeout
        );

        if (isset($result['choices'][0]['message']['content'])) return $result['choices'][0]['message']['content'];
        if (isset($result['error']['message']))                  throw new RuntimeException('OpenAI: ' . $result['error']['message']);
        throw new RuntimeException('OpenAI: réponse inattendue.');
    }
}

// ============================================================
//  Azure OpenAI
// ============================================================

class PluginGlpibotiaAzureProvider extends PluginGlpibotiaOpenAIProvider {

    public function __construct(
        string $endpoint,
        string $apiKey,
        string $deployment,
        string $apiVersion
    ) {
        $url = rtrim($endpoint, '/') . "/openai/deployments/{$deployment}/chat/completions?api-version={$apiVersion}";
        parent::__construct($apiKey, $deployment, $url);
    }

    public function getName(): string      { return "Azure OpenAI ({$this->model})"; }
    public function supportsVision(): bool { return true; }

    protected function call(array $content, int $timeout = 120): string {
        // Azure utilise "api-key" au lieu de "Authorization: Bearer"
        $result = $this->httpPost(
            $this->apiUrl,
            ['Content-Type: application/json', 'api-key: ' . $this->apiKey],
            [
                'max_tokens' => 1500,
                'messages'   => [
                    ['role' => 'system', 'content' => 'Tu es un assistant technique de support informatique expert.'],
                    ['role' => 'user',   'content' => $content],
                ],
            ],
            $timeout
        );

        if (isset($result['choices'][0]['message']['content'])) return $result['choices'][0]['message']['content'];
        if (isset($result['error']['message']))                  throw new RuntimeException('Azure: ' . $result['error']['message']);
        throw new RuntimeException('Azure: réponse inattendue.');
    }
}

// ============================================================
//  Ollama (local)
// ============================================================

class PluginGlpibotiaOllamaProvider extends PluginGlpibotiaAIProvider {

    private string $baseUrl;
    private string $model;

    private const VISION_MODELS = ['llama3.2-vision','llama3.1-vision','llava','bakllava','llava-phi3','llava-llama3','moondream'];

    public function __construct(string $baseUrl, string $model) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model   = $model;
    }

    public function getName(): string { return "Ollama ({$this->model})"; }

    public function supportsVision(): bool {
        $lower = strtolower($this->model);
        foreach (self::VISION_MODELS as $vm) {
            if (str_contains($lower, $vm)) return true;
        }
        return false;
    }

    public function analyze(string $prompt): string {
        return $this->call($prompt, []);
    }

    public function analyzeWithImages(string $prompt, array $images): string {
        if (!$this->supportsVision()) return $this->analyze($prompt);
        return $this->call($prompt, array_column($images, 'data'), count($images) > 0 ? 900 : 300);
    }

    private function call(string $prompt, array $imageData, int $timeout = 300): string {
        $body = [
            'model'  => $this->model,
            'prompt' => "Tu es un assistant technique de support informatique expert.\n\n{$prompt}",
            'stream' => false,
        ];
        if (!empty($imageData)) {
            $body['images'] = $imageData;
        }

        // Ollama écoute en local — pas de HTTPS, mais CURLOPT_FOLLOWLOCATION=false
        // est maintenu pour éviter les redirections inattendues
        $ch = curl_init($this->baseUrl . '/api/generate');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') throw new RuntimeException("Ollama cURL: {$curlErr}");
        if ($httpCode < 200 || $httpCode >= 300) throw new RuntimeException("Ollama HTTP {$httpCode}");

        $decoded = json_decode((string)$response, true);
        if (isset($decoded['response'])) return $decoded['response'];
        if (isset($decoded['error']))    throw new RuntimeException('Ollama: ' . $decoded['error']);
        throw new RuntimeException('Ollama: réponse inattendue.');
    }
}

// ============================================================
//  Factory
// ============================================================

class PluginGlpibotiaProviderFactory {

    public static function create(PluginGlpibotiaConfig $config): PluginGlpibotiaAIProvider {
        $f = $config->fields;
        $provider = $f['ai_provider'] ?? 'ollama';

        // Double vérification liste blanche (la config valide déjà à la saisie)
        if (!in_array($provider, PluginGlpibotiaConfig::ALLOWED_PROVIDERS, true)) {
            throw new RuntimeException("Provider IA non autorisé : {$provider}");
        }

        switch ($provider) {

            case 'claude':
                if (empty($f['anthropic_api_key'])) {
                    throw new RuntimeException('Clé API Anthropic manquante.');
                }
                return new PluginGlpiboticClaudeProvider(
                    $f['anthropic_api_key'],
                    $f['claude_model'] ?? 'claude-sonnet-4-20250514'
                );

            case 'openai':
                if (empty($f['openai_api_key'])) {
                    throw new RuntimeException('Clé API OpenAI manquante.');
                }
                return new PluginGlpibotiaOpenAIProvider(
                    $f['openai_api_key'],
                    $f['openai_model'] ?? 'gpt-4o-mini'
                );

            case 'azure':
                foreach (['azure_endpoint','azure_api_key','azure_deployment'] as $key) {
                    if (empty($f[$key])) throw new RuntimeException("Config Azure incomplète : {$key} manquant.");
                }
                return new PluginGlpibotiaAzureProvider(
                    $f['azure_endpoint'],
                    $f['azure_api_key'],
                    $f['azure_deployment'],
                    $f['azure_api_version'] ?? '2024-02-15-preview'
                );

            default: // ollama
                return new PluginGlpibotiaOllamaProvider(
                    $f['ollama_url']   ?? 'http://localhost:11434',
                    $f['ollama_model'] ?? 'llava-phi3'
                );
        }
    }
}
