<?php
/**
 * GLPI Bot IA - Page de statut / tableau de bord
 */

include('../../../inc/includes.php');

Session::checkRight('config', READ);

$config = PluginGlpibotiaConfig::getInstance();

Html::header(
    __('GLPI Bot IA — Statut', 'glpibotia'),
    $_SERVER['PHP_SELF'],
    'config',
    'plugins'
);

echo "<div class='container-fluid mt-4'>";
echo "<div class='row'>";
echo "<div class='col-12 col-xl-10 offset-xl-1'>";

// Breadcrumb
echo "<nav aria-label='breadcrumb'><ol class='breadcrumb'>";
echo "<li class='breadcrumb-item'><a href='" . $CFG_GLPI['root_doc'] . "/front/config.php'>" . __('Configuration') . "</a></li>";
echo "<li class='breadcrumb-item active'>GLPI Bot IA</li>";
echo "</ol></nav>";

// ── En-tête ─────────────────────────────────────────────────────
echo "<div class='card mb-4'>";
echo "<div class='card-header d-flex justify-content-between align-items-center'>";
echo "  <h3 class='mb-0'>🤖 GLPI Bot IA — Tableau de bord</h3>";
echo "  <a href='config.form.php' class='btn btn-sm btn-outline-primary'><i class='fas fa-cog'></i> " . __('Configurer', 'glpibotia') . "</a>";
echo "</div>";
echo "<div class='card-body'>";

// Statut activation
$isActive = (bool)$config->fields['is_active'];
$statusClass = $isActive ? 'success' : 'danger';
$statusLabel = $isActive ? '✅ Actif' : '❌ Inactif';

echo "<div class='row g-3 mb-4'>";
echo "<div class='col-md-3'>";
echo "  <div class='card border-{$statusClass} text-center p-3'>";
echo "    <div class='fs-4'>{$statusLabel}</div>";
echo "    <small class='text-muted'>" . __('État du bot', 'glpibotia') . "</small>";
echo "  </div>";
echo "</div>";

// Provider actif
echo "<div class='col-md-3'>";
echo "  <div class='card border-info text-center p-3'>";
echo "    <div class='fs-4'>🤖 " . strtoupper($config->fields['ai_provider'] ?? 'N/A') . "</div>";
echo "    <small class='text-muted'>" . __('Provider IA', 'glpibotia') . "</small>";
echo "  </div>";
echo "</div>";

// Tickets traités aujourd'hui
global $DB;
$today = date('Y-m-d');
$countToday = $DB->request([
    'COUNT' => 'cpt',
    'FROM'  => 'glpi_plugin_glpibotia_processed',
    'WHERE' => ['date_process' => ['>=', $today . ' 00:00:00']],
])->current()['cpt'] ?? 0;

echo "<div class='col-md-3'>";
echo "  <div class='card border-primary text-center p-3'>";
echo "    <div class='fs-4'>📊 {$countToday}</div>";
echo "    <small class='text-muted'>" . __('Tickets traités aujourd\'hui', 'glpibotia') . "</small>";
echo "  </div>";
echo "</div>";

// Total traités
$countTotal = $DB->request([
    'COUNT' => 'cpt',
    'FROM'  => 'glpi_plugin_glpibotia_processed',
])->current()['cpt'] ?? 0;

echo "<div class='col-md-3'>";
echo "  <div class='card border-secondary text-center p-3'>";
echo "    <div class='fs-4'>🗂️ {$countTotal}</div>";
echo "    <small class='text-muted'>" . __('Tickets traités au total', 'glpibotia') . "</small>";
echo "  </div>";
echo "</div>";
echo "</div>";

// ── Test de connexion ─────────────────────────────────────────────
echo "<h5>" . __('Test de connexion IA', 'glpibotia') . "</h5>";
echo "<button type='button' class='btn btn-outline-success mb-3' onclick='botiaTestConnection()'>";
echo "  <i class='fas fa-plug'></i> " . __('Tester la connexion', 'glpibotia');
echo "</button>";
echo "<div id='botia-test-result' class='alert d-none'></div>";

// ── Configuration actuelle ─────────────────────────────────────────
echo "<h5>" . __('Configuration actuelle', 'glpibotia') . "</h5>";
echo "<table class='table table-sm table-bordered'>";
echo "<tbody>";

$confRows = [
    [__('Provider IA', 'glpibotia'),          strtoupper($config->fields['ai_provider'] ?? '-')],
    [__('Analyse des images', 'glpibotia'),    $config->fields['analyze_images'] ? '✅ Oui' : '❌ Non'],
    [__('Images max/ticket', 'glpibotia'),     (string)($config->fields['max_images'] ?? 3)],
    [__('Taille min. image', 'glpibotia'),     ($config->fields['min_image_size_kb'] ?? 50) . ' KB'],
    [__('Suivi privé', 'glpibotia'),           $config->fields['followup_is_private'] ? '🔒 Oui' : '🌐 Non'],
    [__('Analyse à la réouverture', 'glpibotia'), $config->fields['analyze_on_reopen'] ? '✅ Oui' : '❌ Non'],
];

foreach ($confRows as [$label, $value]) {
    echo "<tr><th style='width:40%'>{$label}</th><td>{$value}</td></tr>";
}
echo "</tbody></table>";

// ── Emails exclus ──────────────────────────────────────────────
$excludedList = $config->getExcludedEmailsList();
if (!empty($excludedList)) {
    echo "<h5>" . __('Emails exclus', 'glpibotia') . " (" . count($excludedList) . ")</h5>";
    echo "<ul class='list-group list-group-flush mb-3'>";
    foreach ($excludedList as $email) {
        echo "<li class='list-group-item py-1'><code>{$email}</code></li>";
    }
    echo "</ul>";
}

echo "</div></div>";

// ── Derniers tickets traités ────────────────────────────────────
echo "<div class='card'>";
echo "<div class='card-header'><h5 class='mb-0'>" . __('Derniers tickets traités', 'glpibotia') . "</h5></div>";
echo "<div class='card-body p-0'>";

$recent = $DB->request([
    'FROM'    => 'glpi_plugin_glpibotia_processed',
    'ORDER'   => 'date_process DESC',
    'LIMIT'   => 20,
]);

if ($recent->count() > 0) {
    echo "<table class='table table-sm table-hover mb-0'>";
    echo "<thead><tr>";
    echo "  <th>#</th>";
    echo "  <th>" . __('Ticket', 'glpibotia') . "</th>";
    echo "  <th>" . __('Date', 'glpibotia') . "</th>";
    echo "  <th>" . __('Provider', 'glpibotia') . "</th>";
    echo "  <th>" . __('Statut', 'glpibotia') . "</th>";
    echo "</tr></thead><tbody>";

    foreach ($recent as $row) {
        $ticketUrl = $CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=' . $row['tickets_id'];
        $statusBadge = $row['success']
            ? "<span class='badge bg-success'>OK</span>"
            : "<span class='badge bg-danger' title='" . htmlspecialchars($row['error_message']) . "'>Erreur</span>";

        echo "<tr>";
        echo "  <td>{$row['id']}</td>";
        echo "  <td><a href='{$ticketUrl}' target='_blank'>Ticket #{$row['tickets_id']}</a></td>";
        echo "  <td>" . Html::convDateTime($row['date_process']) . "</td>";
        echo "  <td><code>{$row['ai_provider']}</code></td>";
        echo "  <td>{$statusBadge}</td>";
        echo "</tr>";
    }

    echo "</tbody></table>";
} else {
    echo "<p class='text-muted p-3 mb-0'>" . __('Aucun ticket traité pour le moment.', 'glpibotia') . "</p>";
}

echo "</div></div>";

echo "</div></div></div>";

// JS : test de connexion via AJAX
echo "<script>
async function botiaTestConnection() {
    const btn    = event.target;
    const result = document.getElementById('botia-test-result');
    
    btn.disabled = true;
    btn.innerHTML = '<i class=\"fas fa-spinner fa-spin\"></i> Test en cours...';
    result.className = 'alert alert-info';
    result.textContent = 'Connexion en cours...';
    result.classList.remove('d-none');

    try {
        const resp = await fetch('ajax/test_connection.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: '_glpi_csrf_token=' + encodeURIComponent('" . Session::getNewCSRFToken() . "')
        });
        const data = await resp.json();
        
        if (data.success) {
            result.className = 'alert alert-success';
            result.innerHTML = '✅ <strong>Connexion réussie !</strong><br>' + data.message;
        } else {
            result.className = 'alert alert-danger';
            result.innerHTML = '❌ <strong>Erreur :</strong> ' + data.message;
        }
    } catch (e) {
        result.className = 'alert alert-danger';
        result.textContent = '❌ Erreur réseau : ' + e.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class=\"fas fa-plug\"></i> Tester la connexion';
    }
}
</script>";

Html::footer();
