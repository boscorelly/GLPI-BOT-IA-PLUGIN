# Politique de sécurité — GLPI Bot IA Plugin

## Versions supportées

| Version | Support sécurité |
|---------|-----------------|
| 1.0.x   | ✅ Oui           |
| < 1.0   | ❌ Non           |

---

## 🔐 Signaler une vulnérabilité

**Ne pas ouvrir d'issue publique GitHub pour une vulnérabilité de sécurité.**

Une divulgation publique avant qu'un correctif soit disponible expose tous les utilisateurs du plugin.

### Processus de divulgation responsable

1. **Contactez le mainteneur** en privé via l'une de ces méthodes :
   - Email : [à compléter par le mainteneur]
   - GitHub Security Advisory : [Onglet "Security" → "Report a vulnerability"](https://github.com/VOTRE_USER/GLPI-Bot-IA-Plugin/security/advisories/new)

2. **Incluez dans votre rapport** :
   - Description du problème et impact potentiel
   - Étapes pour reproduire (proof-of-concept si possible)
   - Versions affectées
   - Suggestion de correctif si vous en avez une

3. **Délai de réponse** : accusé de réception sous 72h, correctif cible sous 14 jours selon la criticité.

4. **Crédit** : les chercheurs qui signalent des vulnérabilités valides seront crédités dans le CHANGELOG (sauf si anonymat demandé).

---

## 🛡️ Mesures de sécurité en place

### Stockage des données sensibles
- Les clés API sont chiffrées avec **AES-256-GCM** avant stockage en base de données
- La clé de chiffrement est définie hors de la BDD (`config/local_define.php`)
- Les clés déchiffrées ne résident qu'en mémoire PHP, le temps du process

### Protection contre les attaques réseau
- **SSRF** : validation DNS + vérification CIDR contre les plages RFC 1918, loopback, link-local, CGNAT
- **Redirections HTTP** : `CURLOPT_FOLLOWLOCATION = false` sur tous les appels cURL
- **TLS** : `CURLOPT_SSL_VERIFYPEER = true` imposé, ne jamais désactiver

### Protection contre les injections
- **SQL** : ORM GLPI exclusivement, zéro requête SQL construite à la main
- **XSS** : `htmlspecialchars()` systématique sur toutes les sorties
- **CSRF** : token GLPI sur tous les formulaires et endpoints AJAX
- **Path traversal** : `realpath()` + containment check sur les chemins de fichiers
- **Prompt injection** : délimiteurs `===DATA_START===` / `===DATA_END===`, instructions système séparées

### Contrôle des ressources
- Rate limiting journalier configurable (protection coûts et DoS)
- Borne supérieure sur la taille des images (10 Mo par fichier)
- Vérification des magic bytes des images (défense en profondeur)
- Accès direct aux fichiers PHP bloqué (`if (!defined('GLPI_ROOT')) die()`)

### Confidentialité des logs
- Verbosité configurable : mode `silent` (erreurs uniquement, aucune donnée de ticket)
- Aucun contenu de ticket loggué par défaut (mode `normal`)

---

## ⚠️ Risques résiduels connus

| Risque | Niveau | Notes |
|--------|--------|-------|
| Prompt injection complète | Moyen | Aucun LLM n'est immunisé — atténuation par délimiteurs |
| Clés API si `local_define.php` compromis | Élevé | Protection OS/permissions fichier requise |
| Données tickets chez fournisseurs IA tiers | Variable | Cadrer via DPA contractuel (Anthropic, OpenAI, Azure) |
| Exécution synchrone PHP (Ollama lent) | Faible | File d'attente disponible, Ollama déconseillé en prod sans GPU |

---

## 🏗️ Recommandations de déploiement

```php
// config/local_define.php — à sécuriser (chmod 640, propriétaire www-data)

// 1. Clé de chiffrement forte (OBLIGATOIRE)
define('GLPIBOTIA_ENCRYPTION_KEY', bin2hex(random_bytes(32)));

// 2. Autoriser Ollama local UNIQUEMENT si nécessaire
// define('GLPIBOTIA_ALLOW_LOCAL_OLLAMA', true);
```

```bash
# Permissions recommandées
chmod 640 /var/www/glpi/config/local_define.php
chown www-data:www-data /var/www/glpi/config/local_define.php

# Logs non accessibles via le web
# Vérifier que files/_log/ n'est pas exposé dans votre config nginx/apache
```

---

## 📋 Historique des CVE / avis de sécurité

*Aucun avis publié pour le moment.*
