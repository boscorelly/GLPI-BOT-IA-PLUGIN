# Guide de contribution — GLPI Bot IA Plugin

Merci de l'intérêt que vous portez à ce projet ! Toute contribution est la bienvenue, qu'il s'agisse d'un bug report, d'une suggestion, d'une traduction ou d'une Pull Request.

---

## 📋 Avant de contribuer

### Licence

En soumettant une contribution, vous acceptez qu'elle soit diffusée sous la licence **CC BY-NC-SA 4.0** du projet. Cela signifie notamment qu'elle ne pourra pas être utilisée à des fins commerciales.

Si vous n'êtes pas à l'aise avec cette licence, ne soumettez pas de contribution.

### Code de conduite

- Soyez respectueux et constructif
- Les critiques doivent porter sur le code, pas sur les personnes
- Les demandes non conformes à la licence seront fermées sans discussion

---

## 🐛 Signaler un bug

1. Vérifiez que le bug n'est pas déjà signalé dans les [Issues](https://github.com/VOTRE_USER/GLPI-Bot-IA-Plugin/issues)
2. Ouvrez une nouvelle issue avec le template **Bug Report**
3. Incluez :
   - Version de GLPI (`Administration → Informations système`)
   - Version PHP (`php -v`)
   - Provider IA utilisé
   - Les logs pertinents (`files/_log/glpibotia.log`) — **anonymisez les données personnelles**
   - Les étapes pour reproduire le problème

> 🔐 Pour les vulnérabilités de sécurité, voir [SECURITY.md](SECURITY.md) — **ne pas ouvrir d'issue publique**.

---

## 💡 Proposer une fonctionnalité

1. Ouvrez une [Discussion](https://github.com/VOTRE_USER/GLPI-Bot-IA-Plugin/discussions) pour en discuter avant de coder
2. Si la fonctionnalité est validée, ouvrez une issue **Feature Request**
3. Forkez et implémentez (voir ci-dessous)

---

## 🔧 Contribuer du code

### Prérequis

- GLPI 10.x en local (Docker recommandé)
- PHP 7.4+ avec extensions `curl` et `openssl`
- Git configuré avec votre nom et email

### Workflow

```bash
# 1. Forker le repo sur GitHub, puis cloner
git clone https://github.com/VOTRE_FORK/GLPI-Bot-IA-Plugin.git
cd GLPI-Bot-IA-Plugin

# 2. Créer une branche descriptive
git checkout -b fix/path-traversal-images
# ou
git checkout -b feature/nouveau-provider-mistral

# 3. Développer et tester sur votre instance GLPI locale
cp -r . /var/www/glpi/plugins/glpibotia/

# 4. Commiter avec des messages clairs
git add .
git commit -m "🔒 Fix : protection path traversal sur les images jointes"

# Préfixes de commit suggérés :
# ✨ Feature  : nouvelle fonctionnalité
# 🐛 Fix      : correction de bug
# 🔒 Security : correctif de sécurité
# 📝 Docs     : documentation uniquement
# ♻️ Refactor : refactoring sans changement fonctionnel
# 🎨 Style    : formatage, indentation
# 🧪 Test     : ajout ou modification de tests
# ⬆️ Upgrade  : mise à jour de compatibilité

# 5. Pousser et ouvrir une Pull Request
git push origin fix/path-traversal-images
```

### Standards de code

- **Indentation** : 4 espaces (pas de tabulations)
- **Nommage** : `camelCase` pour les méthodes, `PascalCase` pour les classes
- **Préfixe classes** : toujours `PluginGlpibotia` (requis par GLPI)
- **Sécurité** :
  - Toutes les entrées utilisateur doivent être validées/sanitisées
  - Les sorties HTML doivent être passées par `htmlspecialchars()`
  - Toutes les requêtes SQL via l'ORM GLPI (`$DB->request()`)
  - Jamais de `CURLOPT_SSL_VERIFYPEER = false`
- **Compatibilité** : tester sur PHP 7.4 ET 8.x, GLPI 10.0 et 10.1+
- **Pas de dépendances** : le plugin ne doit pas nécessiter Composer

### Ajouter un nouveau provider IA

1. Créer une classe dans `inc/aiprovider.class.php` qui étend `PluginGlpibotiaAIProvider`
2. Implémenter `analyze()`, `analyzeWithImages()`, `supportsVision()`, `getName()`
3. Ajouter le case dans `PluginGlpibotiaProviderFactory::create()`
4. Ajouter la validation dans `PluginGlpibotiaConfig::saveFromPost()` (liste blanche)
5. Ajouter la section d'UI dans `PluginGlpibotiaConfig::showForm()`
6. Mettre à jour `CHANGELOG.md`

---

## 🌐 Contribuer des traductions

Le plugin est actuellement en français. Pour ajouter une langue :

1. Copier `locales/fr_FR.php` vers `locales/en_GB.php` (ou autre code langue)
2. Traduire les chaînes
3. Soumettre une Pull Request

---

## ✅ Checklist Pull Request

Avant de soumettre votre PR, vérifiez :

- [ ] Le code respecte les standards ci-dessus
- [ ] Les nouvelles fonctionnalités sont documentées dans le README
- [ ] `CHANGELOG.md` est mis à jour
- [ ] Aucune clé API, mot de passe ou donnée sensible dans le code
- [ ] `CURLOPT_SSL_VERIFYPEER` n'est jamais mis à `false`
- [ ] Les entrées utilisateur sont validées
- [ ] Les sorties HTML utilisent `htmlspecialchars()`
- [ ] Testé sur une instance GLPI 10.x fonctionnelle
