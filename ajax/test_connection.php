<?php
/**
 * GLPI Bot IA - Test de connexion IA (AJAX)
 */

include('../../../inc/includes.php');

// Vérification CSRF et droits
Session::checkCSRF($_POST);
Session::checkRight('config', READ);

header('Content-Type: application/json');

try {
    $config   = PluginGlpibotiaConfig::getInstance();
    $provider = PluginGlpibotiaProviderFactory::create($config);

    $testPrompt = "Réponds uniquement avec le texte : 'GLPI Bot IA opérationnel' suivi du nom de ton modèle.";

    $response = $provider->analyze($testPrompt);

    if (empty(trim($response))) {
        throw new RuntimeException('Réponse vide reçue du provider.');
    }

    echo json_encode([
        'success' => true,
        'message' => "<strong>Provider :</strong> " . htmlspecialchars($provider->getName()) . "<br>"
                   . "<strong>Réponse :</strong> " . htmlspecialchars(substr($response, 0, 300)),
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => htmlspecialchars($e->getMessage()),
    ]);
}
