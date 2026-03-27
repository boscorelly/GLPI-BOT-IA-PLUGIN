<?php
/**
 * GLPI Bot IA - Classe de configuration (v2 - sécurisée)
 *
 * Correctifs de sécurité appliqués :
 *  🔴 Chiffrement AES-256-GCM des clés API en base de données
 *  🟠 Validation SSRF sur les URLs (Ollama, Azure)
 *  🟡 Liste blanche stricte sur ai_provider
 *  🟠 Rate limiting journalier configurable
 *  🟡 Verbosité des logs configurable (RGPD)
 *  🟡 Sanitisation des noms de modèles et versions
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginGlpibotiaConfig extends CommonGLPI {

    public array $fields = [];
    private static ?self $instance = null;

    // ────────────────────────────────────────────────────────
    //  Constantes de sécurité
    // ────────────────────────────────────────────────────────

    /** Liste blanche stricte des providers autorisés. */
    public const ALLOWED_PROVIDERS = ['ollama', 'claude', 'openai', 'azure'];

    /** Taille maximale d'une image transmise à l'IA (10 Mo). */
    public const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

    /**
     * Plages IP bloquées pour la protection SSRF.
     * RFC 1918, loopback, link-local, CGNAT, metadata cloud.
     */
    private const BLOCKED_CIDR = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '100.64.0.0/10',
        '0.0.0.0/8',
        '240.0.0.0/4',
    ];

    // ────────────────────────────────────────────────────────
    //  Chiffrement AES-256-GCM des clés API
    // ────────────────────────────────────────────────────────

    /**
     * Retourne la clé de chiffrement 32 octets.
     *
     * Priorité :
     *   1. Constante GLPIBOTIA_ENCRYPTION_KEY (config/local_define.php)
     *   2. GLPI_SECRET_KEY (dérivé via HKDF)
     *   3. Fallback basé sur GLPI_ROOT (moins sûr, affiche un avertissement)
     *
     * Recommandation : dans config/local_define.php
     *   define('GLPIBOTIA_ENCRYPTION_KEY', bin2hex(random_bytes(32)));
     */
    private static function getEncryptionKey(): string {
        if (defined('GLPIBOTIA_ENCRYPTION_KEY') && strlen(GLPIBOTIA_ENCRYPTION_KEY) >= 32) {
            return substr(GLPIBOTIA_ENCRYPTION_KEY, 0, 32);
        }

        if (defined('GLPI_SECRET_KEY') && strlen(GLPI_SECRET_KEY) >= 16) {
            return hash_hkdf('sha256', GLPI_SECRET_KEY, 32, 'glpibotia-v1');
        }

        // Fallback — clé dérivée de l'installation, moindre sécurité
        Toolbox::logInFile(
            'glpibotia',
            '[SECURITY][WARNING] GLPIBOTIA_ENCRYPTION_KEY non définie dans config/local_define.php.'
            . ' Les clés API sont chiffrées avec une clé de fallback moins sûre.'
        );
        return hash('sha256', GLPI_ROOT . 'glpibotia_fallback_v1', true);
    }

    /**
     * Chiffre une chaîne avec AES-256-GCM.
     * Format stocké : base64( iv[12] + tag[16] + ciphertext )
     */
    public static function encrypt(string $plaintext): string {
        if ($plaintext === '') {
            return '';
        }
        $key  = self::getEncryptionKey();
        $iv   = random_bytes(12);
        $tag  = '';

        $cipher = openssl_encrypt(
            $plaintext, 'aes-256-gcm', $key,
            OPENSSL_RAW_DATA, $iv, $tag, '', 16
        );

        if ($cipher === false) {
            throw new RuntimeException('[BotIA] Échec du chiffrement OpenSSL.');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    /**
     * Déchiffre une chaîne produite par encrypt().
     * Retourne '' en cas d'échec (clé changée, données corrompues).
     */
    public static function decrypt(string $encoded): string {
        if ($encoded === '') {
            return '';
        }

        $raw = base64_decode($encoded, true);

        // Valeur non chiffrée (migration depuis v1) : la retourner telle quelle
        if ($raw === false || strlen($raw) < 29) {
            return $encoded;
        }

        $key    = self::getEncryptionKey();
        $iv     = substr($raw, 0, 12);
        $tag    = substr($raw, 12, 16);
        $cipher = substr($raw, 28);

        $plain = openssl_decrypt(
            $cipher, 'aes-256-gcm', $key,
            OPENSSL_RAW_DATA, $iv, $tag
        );

        if ($plain === false) {
            Toolbox::logInFile(
                'glpibotia',
                '[SECURITY][ERROR] Déchiffrement échoué — clé changée ou données corrompues.'
            );
            return '';
        }

        return $plain;
    }

    // ────────────────────────────────────────────────────────
    //  Singleton
    // ────────────────────────────────────────────────────────

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->load();
        }
        return self::$instance;
    }

    public function load(): void {
        global $DB;

        $it = $DB->request(['FROM' => 'glpi_plugin_glpibotia_configs', 'LIMIT' => 1]);

        if ($row = $it->current()) {
            $this->fields = $row;
            // Déchiffrement en mémoire uniquement — jamais réécrit en clair
            $this->fields['anthropic_api_key'] = self::decrypt($row['anthropic_api_key'] ?? '');
            $this->fields['openai_api_key']    = self::decrypt($row['openai_api_key'] ?? '');
            $this->fields['azure_api_key']     = self::decrypt($row['azure_api_key'] ?? '');
        } else {
            $this->fields = $this->defaults();
        }
    }

    private function defaults(): array {
        return [
            'id' => 0, 'is_active' => 1,
            'ai_provider' => 'ollama',
            'anthropic_api_key' => '', 'claude_model' => 'claude-sonnet-4-20250514',
            'openai_api_key' => '',    'openai_model' => 'gpt-4o-mini',
            'ollama_url' => 'http://localhost:11434', 'ollama_model' => 'llava-phi3',
            'azure_endpoint' => '', 'azure_api_key' => '',
            'azure_deployment' => '', 'azure_api_version' => '2024-02-15-preview',
            'excluded_emails' => '',
            'analyze_images' => 1, 'analyze_on_reopen' => 0, 'followup_is_private' => 1,
            'max_images' => 3, 'min_image_size_kb' => 50,
            'max_daily_api_calls' => 200,
            'log_verbosity' => 'normal',
            'custom_prompt' => '',
        ];
    }

    // ────────────────────────────────────────────────────────
    //  GLPI CommonGLPI
    // ────────────────────────────────────────────────────────

    public static function getTypeName($nb = 0): string {
        return __('GLPI Bot IA', 'glpibotia');
    }

    public static function canCreate(): bool {
        return Session::haveRight('config', UPDATE);
    }

    public static function canView(): bool {
        return Session::haveRight('config', READ);
    }

    // ────────────────────────────────────────────────────────
    //  Sauvegarde avec validation complète
    // ────────────────────────────────────────────────────────

    public function saveFromPost(array $input): void {
        global $DB;

        // 1. Validation provider (liste blanche stricte)
        $provider = $input['ai_provider'] ?? '';
        if (!in_array($provider, self::ALLOWED_PROVIDERS, true)) {
            Session::addMessageAfterRedirect(__('Provider IA invalide.', 'glpibotia'), true, ERROR);
            return;
        }

        // 2. Validation SSRF sur l'URL Ollama
        $ollamaUrl = rtrim($input['ollama_url'] ?? 'http://localhost:11434', '/');
        if (!empty($ollamaUrl) && !$this->isSafeUrl($ollamaUrl)) {
            Session::addMessageAfterRedirect(
                __("URL Ollama refusée : adresse réseau privée ou invalide (protection SSRF).", 'glpibotia'),
                true, ERROR
            );
            return;
        }

        // 3. Validation SSRF sur l'endpoint Azure
        $azureEndpoint = rtrim($input['azure_endpoint'] ?? '', '/');
        if (!empty($azureEndpoint) && !$this->isSafeUrl($azureEndpoint)) {
            Session::addMessageAfterRedirect(
                __("Endpoint Azure refusé : adresse réseau privée ou invalide (protection SSRF).", 'glpibotia'),
                true, ERROR
            );
            return;
        }

        // 4. Valeurs numériques bornées
        $maxImages    = max(1, min(10,    (int)($input['max_images']           ?? 3)));
        $minSizeKb    = max(0, min(10240, (int)($input['min_image_size_kb']    ?? 50)));
        $maxDailyCall = max(0, min(10000, (int)($input['max_daily_api_calls']  ?? 200)));

        // 5. Verbosité logs
        $verbosity = $input['log_verbosity'] ?? 'normal';
        if (!in_array($verbosity, ['silent', 'normal', 'verbose'], true)) {
            $verbosity = 'normal';
        }

        $data = [
            'is_active'           => (int)($input['is_active']           ?? 0),
            'ai_provider'         => $provider,
            'claude_model'        => $this->sanitizeModel($input['claude_model']     ?? 'claude-sonnet-4-20250514'),
            'openai_model'        => $this->sanitizeModel($input['openai_model']     ?? 'gpt-4o-mini'),
            'ollama_url'          => $ollamaUrl,
            'ollama_model'        => $this->sanitizeModel($input['ollama_model']     ?? 'llava-phi3'),
            'azure_endpoint'      => $azureEndpoint,
            'azure_deployment'    => $this->sanitizeModel($input['azure_deployment'] ?? ''),
            'azure_api_version'   => $this->sanitizeAzureVersion($input['azure_api_version'] ?? '2024-02-15-preview'),
            'excluded_emails'     => substr($input['excluded_emails']  ?? '', 0, 10000),
            'analyze_images'      => (int)($input['analyze_images']      ?? 0),
            'analyze_on_reopen'   => (int)($input['analyze_on_reopen']   ?? 0),
            'followup_is_private' => (int)($input['followup_is_private'] ?? 1),
            'max_images'          => $maxImages,
            'min_image_size_kb'   => $minSizeKb,
            'max_daily_api_calls' => $maxDailyCall,
            'log_verbosity'       => $verbosity,
            'custom_prompt'       => substr($input['custom_prompt'] ?? '', 0, 2000),
            'date_mod'            => date('Y-m-d H:i:s'),
        ];

        // 6. Clés API : chiffrer avant persistance, ne pas écraser si vide
        if (!empty($input['anthropic_api_key'])) {
            $data['anthropic_api_key'] = self::encrypt(trim($input['anthropic_api_key']));
        }
        if (!empty($input['openai_api_key'])) {
            $data['openai_api_key'] = self::encrypt(trim($input['openai_api_key']));
        }
        if (!empty($input['azure_api_key'])) {
            $data['azure_api_key'] = self::encrypt(trim($input['azure_api_key']));
        }

        if ($this->fields['id'] > 0) {
            $DB->update('glpi_plugin_glpibotia_configs', $data, ['id' => $this->fields['id']]);
        } else {
            $DB->insert('glpi_plugin_glpibotia_configs', $data);
        }

        self::$instance = null;
        Session::addMessageAfterRedirect(__('Configuration sauvegardée.', 'glpibotia'), true, INFO);
    }

    // ────────────────────────────────────────────────────────
    //  Validation SSRF
    // ────────────────────────────────────────────────────────

    /**
     * Retourne true si l'URL est sûre (pas de SSRF vers réseau interne).
     *
     * Pour autoriser Ollama en localhost, définir dans config/local_define.php :
     *   define('GLPIBOTIA_ALLOW_LOCAL_OLLAMA', true);
     */
    public function isSafeUrl(string $url): bool {
        if ($url === '') {
            return true;
        }

        $p = parse_url($url);
        if (!$p || empty($p['host']) || empty($p['scheme'])) {
            return false;
        }
        if (!in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = $p['host'];

        // Exception explicite pour localhost Ollama
        if (defined('GLPIBOTIA_ALLOW_LOCAL_OLLAMA') && GLPIBOTIA_ALLOW_LOCAL_OLLAMA
            && in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        // Résoudre l'IP
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Rejeter toutes les plages privées
        foreach (self::BLOCKED_CIDR as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private function ipInCidr(string $ip, string $cidr): bool {
        [$subnet, $bits] = explode('/', $cidr, 2);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        $mask = $bits > 0 ? (~0 << (32 - (int)$bits)) : 0;
        return (ip2long($ip) & $mask) === (ip2long($subnet) & $mask);
    }

    // ────────────────────────────────────────────────────────
    //  Rate limiting
    // ────────────────────────────────────────────────────────

    public function isDailyLimitReached(): bool {
        global $DB;

        $limit = (int)($this->fields['max_daily_api_calls'] ?? 200);
        if ($limit === 0) {
            return false; // 0 = illimité
        }

        $count = $DB->request([
            'COUNT' => 'cpt',
            'FROM'  => 'glpi_plugin_glpibotia_processed',
            'WHERE' => [
                'date_process' => ['>=', date('Y-m-d') . ' 00:00:00'],
                'success'      => 1,
            ],
        ])->current()['cpt'] ?? 0;

        return $count >= $limit;
    }

    // ────────────────────────────────────────────────────────
    //  Emails exclus
    // ────────────────────────────────────────────────────────

    public function getExcludedEmailsList(): array {
        $raw = $this->fields['excluded_emails'] ?? '';
        return empty($raw)
            ? []
            : array_filter(array_map('trim', explode("\n", $raw)));
    }

    public function isEmailExcluded(string $email): bool {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }
        foreach ($this->getExcludedEmailsList() as $pattern) {
            $pattern = strtolower(trim($pattern));
            if ($pattern === '') continue;
            $regex = '/^' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/i';
            if (preg_match($regex, $email)) {
                return true;
            }
        }
        return false;
    }

    // ────────────────────────────────────────────────────────
    //  Helpers de sanitisation
    // ────────────────────────────────────────────────────────

    /** Noms de modèles : alphanumérique + tirets, points, slashes, underscores. */
    private function sanitizeModel(string $name): string {
        return substr(preg_replace('/[^a-zA-Z0-9\-_.:\/]/', '', $name), 0, 100);
    }

    /** Version API Azure : format YYYY-MM-DD ou YYYY-MM-DD-preview. */
    private function sanitizeAzureVersion(string $v): string {
        return preg_match('/^\d{4}-\d{2}-\d{2}(-preview)?$/', $v) ? $v : '2024-02-15-preview';
    }

    // ────────────────────────────────────────────────────────
    //  Interface de configuration (HTML)
    // ────────────────────────────────────────────────────────

    public function showForm(int $id = 0, array $options = []): void {
        $this->load();
        $c = $this->fields;

        if (!defined('GLPIBOTIA_ENCRYPTION_KEY')) {
            echo "<div class='alert alert-warning'><i class='fas fa-shield-alt'></i> ";
            echo "<strong>Chiffrement :</strong> ajoutez ";
            echo "<code>define('GLPIBOTIA_ENCRYPTION_KEY', bin2hex(random_bytes(32)));</code>";
            echo " dans <code>config/local_define.php</code> pour un chiffrement optimal des clés API.</div>";
        }

        echo "<div class='card'><div class='card-header'><h3>🤖 GLPI Bot IA — Configuration</h3></div>";
        echo "<div class='card-body'>";
        echo "<form method='POST' action='config.form.php'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

        // Activation
        echo "<h4 class='mt-3'>Activation</h4><hr>";
        self::cb('is_active',           'Activer le bot IA',                                   $c['is_active']);
        self::cb('analyze_on_reopen',   'Ré-analyser les tickets rouverts',                    $c['analyze_on_reopen']);
        self::cb('followup_is_private', 'Suivi privé (visible techniciens uniquement)',        $c['followup_is_private']);

        // Provider
        echo "<h4 class='mt-4'>Provider IA</h4><hr>";
        echo "<div class='row mb-3'><label class='col-sm-3 col-form-label'>Fournisseur</label>";
        echo "<div class='col-sm-9'><select name='ai_provider' id='ai_provider' class='form-select' onchange='botiaToggle(this.value)'>";
        foreach (['ollama'=>'Ollama (local, gratuit)','claude'=>'Claude (Anthropic)','openai'=>'OpenAI (GPT)','azure'=>'Azure OpenAI'] as $v=>$l) {
            echo "<option value='" . htmlspecialchars($v) . "'" . ($c['ai_provider']===$v?' selected':'') . ">" . htmlspecialchars($l) . "</option>";
        }
        echo "</select></div></div>";

        // Ollama
        echo "<div id='p_ollama' class='provider-section border rounded p-3 mb-3 bg-light'><h5>🏠 Ollama</h5>";
        echo "<div class='alert alert-info py-2 small mb-2'>Pour localhost : <code>define('GLPIBOTIA_ALLOW_LOCAL_OLLAMA', true);</code> dans <code>config/local_define.php</code></div>";
        self::tf('ollama_url',   'URL',    $c['ollama_url'],   'text', 'http://localhost:11434');
        self::tf('ollama_model', 'Modèle', $c['ollama_model'], 'text', 'llava-phi3');
        echo "</div>";

        // Claude
        echo "<div id='p_claude' class='provider-section border rounded p-3 mb-3 bg-light'><h5>🤖 Claude (Anthropic)</h5>";
        self::tf('anthropic_api_key', 'Clé API', '', 'password', 'sk-ant-...', '🔒 Chiffrée AES-256-GCM en base');
        if (!empty($c['anthropic_api_key'])) echo "<small class='text-success d-block mb-2'>✓ Clé configurée — laisser vide pour conserver</small>";
        self::tf('claude_model', 'Modèle', $c['claude_model'], 'text', 'claude-sonnet-4-20250514');
        echo "</div>";

        // OpenAI
        echo "<div id='p_openai' class='provider-section border rounded p-3 mb-3 bg-light'><h5>💡 OpenAI</h5>";
        self::tf('openai_api_key', 'Clé API', '', 'password', 'sk-...', '🔒 Chiffrée AES-256-GCM en base');
        if (!empty($c['openai_api_key'])) echo "<small class='text-success d-block mb-2'>✓ Clé configurée — laisser vide pour conserver</small>";
        self::tf('openai_model', 'Modèle', $c['openai_model'], 'text', 'gpt-4o-mini');
        echo "</div>";

        // Azure
        echo "<div id='p_azure' class='provider-section border rounded p-3 mb-3 bg-light'><h5>☁️ Azure OpenAI</h5>";
        self::tf('azure_endpoint',    'Endpoint',    $c['azure_endpoint'],    'text',     'https://your-resource.openai.azure.com');
        self::tf('azure_api_key',     'Clé API',     '',                       'password', '...', '🔒 Chiffrée AES-256-GCM en base');
        if (!empty($c['azure_api_key'])) echo "<small class='text-success d-block mb-2'>✓ Clé configurée — laisser vide pour conserver</small>";
        self::tf('azure_deployment',  'Déploiement', $c['azure_deployment'],  'text',     'gpt-4o-mini');
        self::tf('azure_api_version', 'Version API', $c['azure_api_version'], 'text',     '2024-02-15-preview');
        echo "</div>";

        // Images
        echo "<h4 class='mt-4'>Analyse des images</h4><hr>";
        self::cb('analyze_images', "Analyser les captures d'écran jointes (modèles vision)", $c['analyze_images']);
        self::tf('max_images',        'Images max/ticket (1–10)',                     (string)$c['max_images'],        'number');
        self::tf('min_image_size_kb', 'Taille min. image KB (filtre logos/signatures)', (string)$c['min_image_size_kb'], 'number');

        // Limites & sécurité
        echo "<h4 class='mt-4'>Limites &amp; Sécurité</h4><hr>";
        self::tf('max_daily_api_calls', 'Quota journalier (0 = illimité)', (string)$c['max_daily_api_calls'], 'number', '200', 'Stoppe les appels IA au-delà du seuil — protection coûts.');
        echo "<div class='row mb-3'><label class='col-sm-3 col-form-label'>Verbosité des logs</label>";
        echo "<div class='col-sm-9'><select name='log_verbosity' class='form-select'>";
        foreach (['silent'=>'Silencieux (erreurs seulement)','normal'=>'Normal','verbose'=>'Verbeux (debug)'] as $v=>$l) {
            echo "<option value='{$v}'" . (($c['log_verbosity']??'normal')===$v?' selected':'') . ">{$l}</option>";
        }
        echo "</select><small class='text-muted'>Mode silencieux : aucune donnée de ticket en log (RGPD).</small></div></div>";

        // Exclusion emails
        echo "<h4 class='mt-4'>Exclusions d'emails</h4><hr>";
        echo "<div class='row mb-3'><label class='col-sm-3 col-form-label'>Emails à ignorer</label>";
        echo "<div class='col-sm-9'><textarea name='excluded_emails' class='form-control' rows='4'>" . htmlspecialchars($c['excluded_emails'] ?? '') . "</textarea>";
        echo "<small class='text-muted'>Un email par ligne. Wildcards * et ? supportés.</small></div></div>";

        // Prompt
        echo "<h4 class='mt-4'>Prompt personnalisé</h4><hr>";
        echo "<div class='row mb-3'><label class='col-sm-3 col-form-label'>Instruction additionnelle</label>";
        echo "<div class='col-sm-9'><textarea name='custom_prompt' class='form-control' rows='3' maxlength='2000'>" . htmlspecialchars($c['custom_prompt'] ?? '') . "</textarea>";
        echo "<small class='text-muted'>Max 2000 caractères.</small></div></div>";

        echo "<div class='row mt-4'><div class='offset-sm-3 col-sm-9'>";
        echo "<button type='submit' name='save' class='btn btn-primary'><i class='fas fa-save'></i> Sauvegarder</button>";
        echo "<a href='config.php' class='btn btn-outline-secondary ms-2'><i class='fas fa-eye'></i> Statut</a>";
        echo "</div></div>";
        echo Html::closeForm();
        echo "</div></div>";

        echo "<script>
function botiaToggle(v){document.querySelectorAll('.provider-section').forEach(e=>e.style.display='none');var s=document.getElementById('p_'+v);if(s)s.style.display='block';}
document.addEventListener('DOMContentLoaded',()=>botiaToggle(document.getElementById('ai_provider').value));
</script>";
    }

    private static function tf(string $n, string $l, string $v, string $t='text', string $ph='', string $help=''): void {
        echo "<div class='row mb-2'><label class='col-sm-3 col-form-label'>" . htmlspecialchars($l) . "</label><div class='col-sm-9'>";
        echo "<input type='{$t}' name='" . htmlspecialchars($n) . "' class='form-control' value='" . htmlspecialchars($v) . "' placeholder='" . htmlspecialchars($ph) . "'>";
        if ($help) echo "<small class='text-muted'>" . htmlspecialchars($help) . "</small>";
        echo "</div></div>";
    }

    private static function cb(string $n, string $l, $checked): void {
        echo "<div class='row mb-2'><div class='col-sm-12 d-flex align-items-center'>";
        echo "<input type='checkbox' name='" . htmlspecialchars($n) . "' value='1' class='form-check-input me-2' id='" . htmlspecialchars($n) . "'" . ($checked ? ' checked' : '') . ">";
        echo "<label for='" . htmlspecialchars($n) . "' class='form-check-label'>" . htmlspecialchars($l) . "</label>";
        echo "</div></div>";
    }
}
