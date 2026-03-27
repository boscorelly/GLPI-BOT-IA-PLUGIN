<?php
/**
 * GLPI Bot IA - Plugin setup
 * Compatible GLPI 10.x
 * Licence : CC BY-NC-SA 4.0
 */

define('PLUGIN_GLPIBOTIA_VERSION', '1.0.0');
define('PLUGIN_GLPIBOTIA_MIN_GLPI', '10.0.0');
define('PLUGIN_GLPIBOTIA_MAX_GLPI', '10.99.99');

/**
 * Init the hooks of the plugin.
 */
function plugin_init_glpibotia() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['glpibotia'] = true;

    // Lien vers la config dans le menu Administration
    $PLUGIN_HOOKS['config_page']['glpibotia'] = 'front/config.form.php';

    // Hook principal : après ajout d'un ticket
    $PLUGIN_HOOKS['item_add']['glpibotia'] = [
        'Ticket' => 'plugin_glpibotia_post_item_add_Ticket',
    ];

    // Hook optionnel : après mise à jour (réouverture du ticket)
    $PLUGIN_HOOKS['item_update']['glpibotia'] = [
        'Ticket' => 'plugin_glpibotia_post_item_update_Ticket',
    ];
}

/**
 * Get the name and version of the plugin.
 */
function plugin_version_glpibotia() {
    return [
        'name'           => 'GLPI Bot IA',
        'version'        => PLUGIN_GLPIBOTIA_VERSION,
        'author'         => 'GLPI Bot IA Contributors',
        'license'        => 'CC BY-NC-SA 4.0',
        'homepage'       => 'https://github.com/VOTRE_USER/GLPI-Bot-IA',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_GLPIBOTIA_MIN_GLPI,
                'max' => PLUGIN_GLPIBOTIA_MAX_GLPI,
            ],
            'php'  => [
                'min' => '7.4',
            ],
        ],
    ];
}

/**
 * Optional : Check prerequisites before install.
 */
function plugin_glpibotia_check_prerequisites() {
    if (version_compare(GLPI_VERSION, PLUGIN_GLPIBOTIA_MIN_GLPI, 'lt')) {
        echo "Ce plugin nécessite GLPI >= " . PLUGIN_GLPIBOTIA_MIN_GLPI;
        return false;
    }
    if (!function_exists('curl_init')) {
        echo "Ce plugin nécessite l'extension PHP cURL.";
        return false;
    }
    return true;
}

/**
 * Check configuration process.
 */
function plugin_glpibotia_check_config($verbose = false) {
    if (true) { // Additional checks can go here
        return true;
    }
    if ($verbose) {
        echo __('Installed / not configured', 'glpibotia');
    }
    return false;
}
