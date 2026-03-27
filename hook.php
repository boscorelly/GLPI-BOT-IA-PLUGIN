<?php
/**
 * GLPI Bot IA - Hooks (v2)
 *
 * Correctifs :
 *  - Table de file d'attente pour le traitement asynchrone (rate limiting)
 *  - Colonnes max_daily_api_calls et log_verbosity dans la table config
 */

function plugin_glpibotia_post_item_add_Ticket(Ticket $ticket) {
    $config = PluginGlpibotiaConfig::getInstance();
    if (!$config->fields['is_active']) return;
    if ((int)$ticket->fields['status'] !== Ticket::INCOMING) return;

    $analyzer = new PluginGlpibotiaTicketAnalyzer($config);
    $analyzer->processTicket($ticket);
}

function plugin_glpibotia_post_item_update_Ticket(Ticket $ticket) {
    $config = PluginGlpibotiaConfig::getInstance();
    if (!$config->fields['is_active'])         return;
    if (!$config->fields['analyze_on_reopen']) return;
    if (!isset($ticket->updates) || !in_array('status', $ticket->updates)) return;
    if ((int)$ticket->fields['status'] !== Ticket::INCOMING) return;

    $analyzer = new PluginGlpibotiaTicketAnalyzer($config);
    $analyzer->processTicket($ticket);
}

// ============================================================
//  Installation
// ============================================================

function plugin_glpibotia_install() {
    global $DB;

    $cs = DBConnection::getDefaultCharset();
    $co = DBConnection::getDefaultCollation();

    // ── Table de configuration ───────────────────────────────
    if (!$DB->tableExists('glpi_plugin_glpibotia_configs')) {
        $DB->queryOrDie(
            "CREATE TABLE `glpi_plugin_glpibotia_configs` (
                `id`                    INT(11)       NOT NULL AUTO_INCREMENT,
                `is_active`             TINYINT(1)    NOT NULL DEFAULT 1,
                `ai_provider`           VARCHAR(50)   NOT NULL DEFAULT 'ollama',
                `anthropic_api_key`     VARCHAR(512)  NOT NULL DEFAULT '',
                `claude_model`          VARCHAR(100)  NOT NULL DEFAULT 'claude-sonnet-4-20250514',
                `openai_api_key`        VARCHAR(512)  NOT NULL DEFAULT '',
                `openai_model`          VARCHAR(100)  NOT NULL DEFAULT 'gpt-4o-mini',
                `ollama_url`            VARCHAR(255)  NOT NULL DEFAULT 'http://localhost:11434',
                `ollama_model`          VARCHAR(100)  NOT NULL DEFAULT 'llava-phi3',
                `azure_endpoint`        VARCHAR(255)  NOT NULL DEFAULT '',
                `azure_api_key`         VARCHAR(512)  NOT NULL DEFAULT '',
                `azure_deployment`      VARCHAR(100)  NOT NULL DEFAULT '',
                `azure_api_version`     VARCHAR(50)   NOT NULL DEFAULT '2024-02-15-preview',
                `excluded_emails`       TEXT          NOT NULL DEFAULT '',
                `analyze_images`        TINYINT(1)    NOT NULL DEFAULT 1,
                `analyze_on_reopen`     TINYINT(1)    NOT NULL DEFAULT 0,
                `followup_is_private`   TINYINT(1)    NOT NULL DEFAULT 1,
                `max_images`            INT(11)       NOT NULL DEFAULT 3,
                `min_image_size_kb`     INT(11)       NOT NULL DEFAULT 50,
                `max_daily_api_calls`   INT(11)       NOT NULL DEFAULT 200,
                `log_verbosity`         VARCHAR(10)   NOT NULL DEFAULT 'normal',
                `custom_prompt`         TEXT          NOT NULL DEFAULT '',
                `date_mod`              DATETIME      DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$cs} COLLATE={$co}",
            "Error creating glpi_plugin_glpibotia_configs"
        );

        $DB->insert('glpi_plugin_glpibotia_configs', [
            'is_active'           => 1,
            'ai_provider'         => 'ollama',
            'claude_model'        => 'claude-sonnet-4-20250514',
            'openai_model'        => 'gpt-4o-mini',
            'ollama_url'          => 'http://localhost:11434',
            'ollama_model'        => 'llava-phi3',
            'azure_api_version'   => '2024-02-15-preview',
            'analyze_images'      => 1,
            'followup_is_private' => 1,
            'max_images'          => 3,
            'min_image_size_kb'   => 50,
            'max_daily_api_calls' => 200,
            'log_verbosity'       => 'normal',
            'date_mod'            => date('Y-m-d H:i:s'),
        ]);
    }

    // ── Table des tickets traités (anti-doublons + stats) ────
    if (!$DB->tableExists('glpi_plugin_glpibotia_processed')) {
        $DB->queryOrDie(
            "CREATE TABLE `glpi_plugin_glpibotia_processed` (
                `id`            INT(11)      NOT NULL AUTO_INCREMENT,
                `tickets_id`    INT(11)      NOT NULL,
                `date_process`  DATETIME     NOT NULL,
                `ai_provider`   VARCHAR(50)  NOT NULL DEFAULT '',
                `success`       TINYINT(1)   NOT NULL DEFAULT 1,
                `error_message` VARCHAR(500) NOT NULL DEFAULT '',
                PRIMARY KEY (`id`),
                UNIQUE KEY `tickets_id` (`tickets_id`),
                KEY `date_process` (`date_process`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$cs} COLLATE={$co}",
            "Error creating glpi_plugin_glpibotia_processed"
        );
    }

    // ── Table de file d'attente (rate limiting async) ────────
    if (!$DB->tableExists('glpi_plugin_glpibotia_queue')) {
        $DB->queryOrDie(
            "CREATE TABLE `glpi_plugin_glpibotia_queue` (
                `id`          INT(11)  NOT NULL AUTO_INCREMENT,
                `tickets_id`  INT(11)  NOT NULL,
                `date_queued` DATETIME NOT NULL,
                `attempts`    INT(11)  NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `tickets_id` (`tickets_id`),
                KEY `date_queued` (`date_queued`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$cs} COLLATE={$co}",
            "Error creating glpi_plugin_glpibotia_queue"
        );
    }

    return true;
}

// ============================================================
//  Désinstallation
// ============================================================

function plugin_glpibotia_uninstall() {
    global $DB;

    foreach ([
        'glpi_plugin_glpibotia_configs',
        'glpi_plugin_glpibotia_processed',
        'glpi_plugin_glpibotia_queue',
    ] as $table) {
        if ($DB->tableExists($table)) {
            $DB->queryOrDie("DROP TABLE `{$table}`", "Error dropping {$table}");
        }
    }

    return true;
}
