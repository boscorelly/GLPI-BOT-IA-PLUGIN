<?php
/**
 * GLPI Bot IA - Page de configuration (formulaire)
 */

include('../../../inc/includes.php');

// Vérification des droits d'administration
Session::checkRight('config', UPDATE);

$config = PluginGlpibotiaConfig::getInstance();

// Traitement du formulaire de sauvegarde
if (isset($_POST['save'])) {
    Session::checkCSRF($_POST);
    $config->saveFromPost($_POST);
    Html::back();
}

// Affichage de la page
Html::header(
    __('GLPI Bot IA — Configuration', 'glpibotia'),
    $_SERVER['PHP_SELF'],
    'config',
    'plugins'
);

echo "<div class='container-fluid mt-4'>";
echo "<div class='row'>";
echo "  <div class='col-12 col-xl-10 offset-xl-1'>";

// Breadcrumb
echo "<nav aria-label='breadcrumb'><ol class='breadcrumb'>";
echo "  <li class='breadcrumb-item'><a href='" . $CFG_GLPI['root_doc'] . "/front/config.php'>" . __('Configuration') . "</a></li>";
echo "  <li class='breadcrumb-item active'>GLPI Bot IA</li>";
echo "</ol></nav>";

// Afficher les messages (succès/erreur)
Html::displayMessageAfterRedirect();

// Afficher le formulaire
$config->showForm();

echo "  </div>";
echo "</div>";
echo "</div>";

Html::footer();
