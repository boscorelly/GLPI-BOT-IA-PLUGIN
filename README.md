# 🤖 GLPI Bot IA — Plugin natif GLPI 10

Plugin GLPI qui analyse automatiquement les nouveaux tickets et propose des suggestions de solutions en **suivi privé**, directement dans l'interface GLPI — sans daemon externe, sans cron, sans binaire à déployer.

[![License: CC BY-NC-SA 4.0](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-lightgrey.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![GLPI 10.x](https://img.shields.io/badge/GLPI-10.0%2B-blue)](https://glpi-project.org/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Version](https://img.shields.io/badge/version-1.0.0-green.svg)](https://github.com/VOTRE_USER/GLPI-Bot-IA-Plugin/releases)

---

## ✨ Fonctionnalités

- 🎯 **Analyse instantanée** — Hook GLPI natif, déclenché à la création du ticket (pas de polling)
- 🧠 **Multi-IA** — Claude (Anthropic), OpenAI GPT, Azure OpenAI, Ollama (local/gratuit)
- 🖼️ **Analyse d'images** — Captures d'écran jointes analysées par les modèles vision
  - Lecture directe depuis le disque GLPI (pas de re-téléchargement HTTP)
  - Vérification des magic bytes (JPEG, PNG, WebP)
  - Filtrage automatique des signatures email, logos, icônes
  - Limite configurable (3 images max par défaut)
- 🔒 **Suivis privés** — Suggestions visibles uniquement par les techniciens
- 🚫 **Exclusion d'emails** — Wildcards `*` et `?` supportés (ex: `backup+*@domaine.com`)
- ✅ **Anti-doublons** — Chaque ticket n'est analysé qu'une seule fois
- 🛡️ **Sécurisé** — Clés API chiffrées AES-256-GCM, protection SSRF, rate limiting
- ⚙️ **Configuration graphique** — Interface d'administration intégrée à GLPI
- 🔌 **Test de connexion** — Vérification IA en un clic depuis l'interface
- 📊 **Tableau de bord** — Historique des tickets traités, quota journalier, statut

---

## 📋 Prérequis

| Composant | Version minimale |
|-----------|-----------------|
| GLPI | 10.0.0 |
| PHP | 7.4 (8.x recommandé) |
| Extensions PHP | `curl`, `openssl` |
| Provider IA | Ollama (gratuit) **ou** clé API cloud |

---

## 🚀 Installation rapide

### Option 1 : Depuis Git (recommandé)

```bash
# Se placer dans le dossier plugins de GLPI
cd /var/www/glpi/plugins/

# Cloner — le dossier DOIT s'appeler "glpibotia"
git clone https://github.com/VOTRE_USER/GLPI-Bot-IA-Plugin.git glpibotia

# Permissions
chown -R www-data:www-data glpibotia/
```

### Option 2 : Archive ZIP

```bash
cd /var/www/glpi/plugins/

wget https://github.com/VOTRE_USER/GLPI-Bot-IA-Plugin/releases/latest/download/glpibotia-v1.0.0.zip
unzip glpibotia-v1.0.0.zip
chown -R www-data:www-data glpibotia/
```

### Activation dans GLPI

1. **Configuration → Plugins**
2. Trouver **GLPI Bot IA** → cliquer **Installer**
3. Puis cliquer **Activer**
4. Aller dans **Configuration → GLPI Bot IA** pour paramétrer

---

## ⚙️ Configuration

### Étape 1 : Clé de chiffrement (recommandé)

Ajoutez dans `config/local_define.php` de votre installation GLPI :

```php
// Générer une clé : php -r "echo bin2hex(random_bytes(32));"
define('GLPIBOTIA_ENCRYPTION_KEY', 'votre_cle_64_caracteres_hex_ici');
```

> ⚠️ **Important** : Conservez cette clé précieusement. La perdre invalide toutes les clés API enregistrées.
> Sans cette constante, un fallback moins robuste est utilisé (avertissement affiché dans l'UI).

### Étape 2 : Provider IA

#### 🏠 Ollama (gratuit, données locales)

```bash
# Installer Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Télécharger un modèle
ollama pull llava-phi3          # Vision + texte, 2.9 GB — recommandé
ollama pull llama3.2-vision     # Vision + texte, meilleure qualité, 7.9 GB
ollama pull qwen2.5:0.5b        # Texte uniquement, ultra-rapide, 400 MB
```

Puis autoriser l'accès localhost (bloqué par défaut — protection SSRF) :

```php
// config/local_define.php
define('GLPIBOTIA_ALLOW_LOCAL_OLLAMA', true);
```

Dans l'interface : provider = **Ollama**, URL = `http://localhost:11434`

#### 🤖 Claude (Anthropic)

1. Créer une clé API sur [console.anthropic.com](https://console.anthropic.com)
2. Dans GLPI : provider = **Claude**, coller la clé, modèle = `claude-sonnet-4-20250514`

#### 💡 OpenAI (GPT)

1. Créer une clé API sur [platform.openai.com](https://platform.openai.com)
2. Dans GLPI : provider = **OpenAI**, coller la clé, modèle = `gpt-4o-mini`

#### ☁️ Azure OpenAI

1. Créer un déploiement dans Azure AI Studio
2. Dans GLPI : provider = **Azure**, remplir endpoint, clé, déploiement, version API

---

## 🤖 Comparatif des providers

| Provider | Vision | Coût estimé | Vitesse (CPU) | Idéal pour |
|----------|--------|-------------|---------------|------------|
| **Ollama** llava-phi3 | ✅ | Gratuit | ~2–5 min/ticket | Tests, données sensibles |
| **Ollama** qwen2.5:0.5b | ❌ | Gratuit | ~20–40 s/ticket | Volume sans GPU |
| **Claude Sonnet** | ✅ | ~0,02 €/ticket | ~2–3 s | Meilleure qualité |
| **GPT-4o-mini** | ✅ | ~0,002 €/ticket | ~2–3 s | Meilleur rapport qualité/prix |
| **Azure OpenAI** | ✅ | ~0,002 €/ticket | ~2–3 s | Conformité entreprise (DPA EU) |

> ⚠️ Sur CPU sans GPU, Ollama est fonctionnel mais lent. Pour plus de 10 tickets/jour, préférez une IA cloud.

---

## 🛡️ Sécurité

| Menace | Protection |
|--------|-----------|
| Clés API en clair en BDD | Chiffrement AES-256-GCM, IV aléatoire 96 bits, clé hors BDD |
| Path traversal sur images | `realpath()` + containment check + vérification magic bytes |
| Prompt injection | Délimiteurs `===DATA_START===` / `===DATA_END===` + instructions système |
| SSRF via URL Ollama | Validation DNS + 8 plages CIDR bloquées (RFC 1918, link-local…) |
| DoS / explosion de coûts | Rate limiting journalier configurable + file d'attente async |
| CSRF | Token GLPI sur tous les formulaires et endpoints AJAX |
| SQL injection | ORM GLPI exclusivement, zéro requête brute |
| XSS | `htmlspecialchars()` systématique sur toutes les sorties |
| Accès direct aux fichiers | `if (!defined('GLPI_ROOT')) die()` sur tous les fichiers `inc/` |
| Redirections HTTP malveillantes | `CURLOPT_FOLLOWLOCATION = false` |
| Données sensibles dans les logs | Verbosité configurable (mode silencieux disponible) |

Voir [SECURITY.md](SECURITY.md) pour signaler une vulnérabilité.

---

## 📊 Fonctionnement

```
Nouveau ticket créé dans GLPI
            │
            ▼
  plugin_glpibotia_post_item_add_Ticket()
            │
            ▼
    ┌────────────────────┐
    │    Vérifications   │
    │  • Déjà traité ?   │──── OUI ──▶ Skip
    │  • Quota atteint ? │──── OUI ──▶ File d'attente
    │  • Email exclu ?   │──── OUI ──▶ Skip
    └────────┬───────────┘
             │ NON
             ▼
    ┌────────────────────┐
    │   Images jointes   │
    │  • realpath()      │
    │  • Magic bytes     │
    │  • Filtre signat.  │
    │  • Max 3 images    │
    └────────┬───────────┘
             │
             ▼
    ┌────────────────────┐
    │    Appel IA        │
    │  • Prompt sécurisé │
    │  • DATA_START/END  │
    └────────┬───────────┘
             │
             ▼
    ┌────────────────────┐
    │  Suivi privé GLPI  │
    │  🤖 Suggestion IA  │
    └────────────────────┘
```

---

## 🗂️ Structure du projet

```
glpibotia/
├── setup.php                       ← Déclaration plugin, hooks, prérequis
├── hook.php                        ← Events GLPI + install/uninstall SQL
│
├── inc/
│   ├── config.class.php            ← Config, chiffrement AES, SSRF, rate limit
│   ├── aiprovider.class.php        ← Providers IA (Claude, OpenAI, Ollama, Azure) + Factory
│   └── ticketanalyzer.class.php    ← Orchestration, images, prompt, suivi
│
├── front/
│   ├── config.form.php             ← Page configuration administrateur
│   └── config.php                  ← Tableau de bord, historique, quota
│
├── ajax/
│   └── test_connection.php         ← Test connexion IA (CSRF protégé)
│
├── locales/
│   └── fr_FR.php                   ← Traductions
│
├── LICENSE                         ← CC BY-NC-SA 4.0
├── README.md
├── CHANGELOG.md
├── CONTRIBUTING.md
└── SECURITY.md
```

---

## 🔍 Exemples de filtrage d'emails

```
# Dans l'interface GLPI : Configuration → GLPI Bot IA → Emails à ignorer
# Un pattern par ligne — supporte * (n'importe quoi) et ? (un caractère)

backup+*@example.com          # backup+db@, backup+srv1@...
*@monitoring.example.com      # Tous les emails du domaine
noreply@*                     # Tous les no-reply, quel que soit le domaine
alerting-*@example.com        # alerting-prod@, alerting-dev@...
*nagios*@*                    # Tout email contenant "nagios"
```

---

## 🛠️ Dépannage

### Consulter les logs

```bash
# Logs du plugin (verbosité réglable dans l'interface)
tail -f /var/www/glpi/files/_log/glpibotia.log

# Logs PHP de GLPI
tail -f /var/www/glpi/files/_log/php-errors.log
```

### Le plugin n'apparaît pas dans GLPI

```bash
# 1. Le dossier doit s'appeler EXACTEMENT "glpibotia"
ls /var/www/glpi/plugins/

# 2. Permissions
chown -R www-data:www-data /var/www/glpi/plugins/glpibotia/

# 3. Version PHP >= 7.4
php -r "echo PHP_VERSION . PHP_EOL;"

# 4. Extensions requises
php -m | grep -E "^(curl|openssl)$"
```

### Ollama ne répond pas

```bash
# Vérifier le statut
systemctl status ollama
curl http://localhost:11434/api/tags

# Redémarrer
systemctl restart ollama

# Vérifier la constante GLPI
grep GLPIBOTIA_ALLOW_LOCAL_OLLAMA /var/www/glpi/config/local_define.php
```

### Clés API perdues après migration

Les clés sont chiffrées avec `GLPIBOTIA_ENCRYPTION_KEY`. Copiez cette constante dans `local_define.php` **avant** de migrer la base de données. Sans elle, les clés chiffrées seront illisibles sur le nouveau serveur.

### Quota journalier atteint

Augmenter `max_daily_api_calls` dans la configuration GLPI Bot IA, ou attendre minuit (remise à zéro automatique).

---

## 📈 Performance & coûts

### Temps d'analyse par ticket

| Modèle | Texte seul | + 1 image | + 3 images |
|--------|-----------|-----------|-----------|
| Ollama llava-phi3 (CPU) | ~30–60 s | ~2–5 min | ~5–15 min |
| Ollama llava-phi3 (GPU) | ~5 s | ~15 s | ~30 s |
| GPT-4o-mini | ~2 s | ~3 s | ~5 s |
| Claude Sonnet | ~2 s | ~3 s | ~4 s |

### Coût mensuel API cloud (estimation)

| Volume | GPT-4o-mini | Claude Sonnet |
|--------|-------------|---------------|
| 50 tickets/jour | ~3 €/mois | ~30 €/mois |
| 100 tickets/jour | ~6 €/mois | ~60 €/mois |
| 200 tickets/jour | ~12 €/mois | ~120 €/mois |

> Estimations texte uniquement. Avec images : ×1,5 à ×3.

---

## 🔄 Mise à jour

```bash
cd /var/www/glpi/plugins/glpibotia/
git pull origin main

# Si le schéma SQL a changé (voir CHANGELOG.md) :
# GLPI → Configuration → Plugins → GLPI Bot IA → Désinstaller → Réinstaller
```

---

## 🤝 Contribution

Les contributions sont les bienvenues ! Voir [CONTRIBUTING.md](CONTRIBUTING.md).

```bash
# Fork + clone
git clone https://github.com/VOTRE_FORK/GLPI-Bot-IA-Plugin.git
cd GLPI-Bot-IA-Plugin

# Nouvelle branche
git checkout -b feature/ma-fonctionnalite

# ... développement ...

git commit -m "✨ Ajout : ma fonctionnalité"
git push origin feature/ma-fonctionnalite
# → Ouvrir une Pull Request sur GitHub
```

---

## 💬 Support

- 🐛 **Bugs** : [GitHub Issues](https://github.com/VOTRE_USER/GLPI-Bot-IA-Plugin/issues)
- 💡 **Idées & questions** : [GitHub Discussions](https://github.com/VOTRE_USER/GLPI-Bot-IA-Plugin/discussions)
- 🔐 **Vulnérabilités** : Voir [SECURITY.md](SECURITY.md) — ne pas ouvrir d'issue publique

---

## 📄 Licence

**Creative Commons BY-NC-SA 4.0** — Usage non-commercial uniquement.

| ✅ Autorisé | ❌ Interdit |
|------------|------------|
| Usage interne dans votre organisation | Vente du logiciel ou de versions modifiées |
| Modification du code source | Utilisation dans un produit/service commercial |
| Partage sous la même licence | Intégration dans une offre d'infogérance facturée |
| — | Génération de revenus directs ou indirects |

[Voir la licence complète](LICENSE)

---

## 🙏 Remerciements

- [GLPI Project](https://glpi-project.org/) pour l'excellent outil ITSM open source
- [Ollama](https://ollama.ai/) pour démocratiser les LLM en local
- La communauté GLPI pour les retours et les contributions

---

*Développé avec ❤️ pour la communauté GLPI — Usage non-commercial uniquement*
