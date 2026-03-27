# Changelog

Toutes les modifications notables de ce projet sont documentées dans ce fichier.

Format basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
versioning selon [Semantic Versioning](https://semver.org/lang/fr/).

---

## [1.0.0] — 2026-03-16

### ✨ Ajouté
- Release initiale du plugin GLPI natif (portage depuis la version Go autonome)
- Hook GLPI natif `item_add` sur les tickets — déclenchement instantané sans polling
- Support multi-IA : Claude (Anthropic), OpenAI GPT, Azure OpenAI, Ollama local
- Analyse d'images jointes (captures d'écran) avec les modèles vision
  - Lecture directe disque GLPI (pas de re-téléchargement HTTP)
  - Vérification des magic bytes (JPEG, PNG, WebP)
  - Filtrage automatique des signatures email et logos
- Interface de configuration graphique dans l'administration GLPI
- Tableau de bord avec historique des tickets traités et quota journalier
- Test de connexion IA en un clic (endpoint AJAX CSRF-protégé)
- File d'attente asynchrone pour les tickets bloqués par le rate limiting
- Anti-doublons : table `glpi_plugin_glpibotia_processed`

### 🛡️ Sécurité (v1.0.0 — correctifs intégrés dès la release initiale)
- Chiffrement AES-256-GCM des clés API stockées en base de données
- Protection SSRF sur les URLs configurées (Ollama, Azure)
- `realpath()` + containment check contre le path traversal sur les images
- Séparation prompt injection : délimiteurs `===DATA_START===` / `===DATA_END===`
- Rate limiting journalier configurable (défaut : 200 appels/jour)
- Liste blanche stricte sur `ai_provider`
- Borne supérieure sur la taille des images (10 Mo)
- Verbosité des logs configurable (mode silencieux pour RGPD)
- `CURLOPT_FOLLOWLOCATION = false` sur tous les appels cURL
- Vérification des codes HTTP 4xx/5xx (plus de réponses silencieuses)

### 📦 Technique
- Compatible GLPI 10.0.0+
- PHP 7.4+ (extensions `curl`, `openssl`)
- 3 tables SQL : `configs`, `processed`, `queue`
- Zéro dépendance externe (pas de Composer)

---

## Différences avec la version Go (daemon externe)

| Fonctionnalité | Version Go | Plugin GLPI |
|---|---|---|
| Déclenchement | Polling toutes les N secondes | Hook natif instantané |
| Authentification GLPI | Token API externe | Session GLPI interne |
| Lecture images | Téléchargement HTTP | Lecture disque directe |
| Configuration | Fichier `.env` | Interface admin GLPI |
| Logs | stdout / journald | `files/_log/glpibotia.log` |
| Déploiement | Binaire + service systemd | Plugin intégré |
| Mémoire | ~25 MB (Go) | Process PHP partagé |
| Sécurité images | Téléchargement avec session | Containment + magic bytes |
| Clés API | Fichier `.env` (hors BDD) | Chiffrement AES-256-GCM |
