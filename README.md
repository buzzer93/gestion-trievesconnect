# Gestion Trievesconnect — Démo

## Description
Outil web de gestion d'inventaire et d'étiquetage destiné aux petites boutiques (magasin Trièves Connect'). Permet de **scanner les codes-barres des produits** à l'aide d'une douchette pour enregistrer prix et quantité en stock, **générer des étiquettes imprimables**, et **exporter l'ensemble du catalogue au format Excel**.

Ce projet est une **version de démonstration** : les données sont régénérées et la base réinitialisée à chaque nouvelle session via un EventListener.

🌐 Démo en ligne : [https://gestion-trieves.nicolas-rodriguez.fr](https://gestion-trieves.nicolas-rodriguez.fr)

## Stack technique
- **Backend :** PHP >= 8.2, Symfony, Doctrine ORM
- **Frontend :** Twig, Bootstrap
- **Base de données :** SQLite (réinitialisée à chaque session pour la démo)
- **Export Excel :** [PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/)
- **Outils :** Composer, Symfony CLI

## Prérequis
- PHP 8.2+ avec les extensions :
  - `pdo_sqlite`
  - `fileinfo`
- Composer 2+
- Symfony CLI (recommandé)

## Fonctionnalités
- **Authentification** par compte démo (`demo` / `demo`)
- **Produits & Services** : ajout, édition, listing
- **Étiquetage** : scan des codes-barres pour ajouter des articles, génération et impression d'étiquettes
- **Inventaire** : scan des produits enregistrés pour saisir les quantités en stock
- **Sauvegarde temporaire en session** des listes d'étiquetage et d'inventaire en cours (effacées à la fermeture du navigateur)
- **Export Excel** de la liste complète des produits et services
- **Réinitialisation automatique** des données via EventListener pour conserver une démo propre

## Installation

```bash
# 1. Cloner le dépôt
git clone https://github.com/buzzer93/gestion-trievesconnect.git
cd gestion-trievesconnect

# 2. Installer les dépendances
composer install

# 3. Configurer l'environnement
# Créer .env.local et y ajouter :
echo "APP_SECRET=votre_secret_ici" > .env.local

# 4. (Optionnel) Pointer vers votre propre remote
git remote set-url origin https://github.com/<votre_user>/<votre_repo>.git
git remote -v   # vérification

# 5. Initialiser la base
php bin/console doctrine:database:create
php bin/console doctrine:schema:update --force
php bin/console doctrine:fixtures:load

# 6. Lancer le serveur
symfony server:start
```

## Utilisation
1. Se connecter sur [http://127.0.0.1:8000](http://127.0.0.1:8000) avec les identifiants `demo` / `demo`
2. Ajouter des **produits** ou **services** depuis les onglets dédiés
3. Onglet **Étiquetage** : scanner les codes-barres pour préparer la liste, puis imprimer les étiquettes
4. Onglet **Inventaire** : scanner les produits et saisir les quantités pour faire un inventaire
5. Onglet **Produits** : télécharger l'ensemble du catalogue au format Excel

> ⚠️ Les listes d'étiquetage et d'inventaire **ne sont pas persistées** : elles sont effacées à la fermeture du navigateur (stockage en session).

## Liens
- Démo : [gestion-trieves.nicolas-rodriguez.fr](https://gestion-trieves.nicolas-rodriguez.fr)
- Dépôt : [github.com/buzzer93/gestion-trievesconnect](https://github.com/buzzer93/gestion-trievesconnect)
- Signaler un bug : [Issues bug](https://github.com/buzzer93/gestion-trievesconnect/issues/new?labels=bug)
- Demander une fonctionnalité : [Issues enhancement](https://github.com/buzzer93/gestion-trievesconnect/issues/new?labels=enhancement)



## TODO:

Plan détaillé — Refonte Tailwind / Cursor warm-dark


Phase 2 — Composants Twig réutilisables (≈ 30 min)
Création dans templates/components/ :

_button.html.twig : variants primary (surface warm), accent (orange CTA), ghost, pill, danger (crimson), success (teal), tailles sm/md/lg.
_card.html.twig : avec blocs header/body/footer, variant elevated.
_page_header.html.twig : titre Geist large + sous-titre EB Garamond + slot actions à droite.
_pill.html.twig : badges Cursor (thinking, grep, read, edit, neutre).
_alert.html.twig : variants success/danger/warning/info avec bordure-gauche colorée.
_form_row.html.twig : label + input/select/textarea Symfony Form-compatible.
Phase 3 — Layout & pages d'authentification (≈ 45 min)
base.html.twig : refonte complète Tailwind :
Navbar sticky : logo + brand text + liens nav + CTA login/logout, menu mobile via Stimulus (controller dropdown).
Container principal Tailwind (max-w-screen-2xl, mx-auto, padding responsive).
Conservation du is-print body class.
partials/flash.html.twig : utilise _alert.
security/login.html.twig : carte centrée Cursor-style, titre Geist display, formulaire propre.
registration/register.html.twig : idem login.
Stimulus controller assets/controllers/dropdown_controller.js pour le toggle navbar mobile.
Phase 4 — Validation visuelle (≈ 10 min)
php bin/console tailwind:build → vérifie compilation.
symfony serve + ouverture navigateur :
Page d'accueil non authentifié → login OK ?
Login → home OK ?
Une page admin (par ex. customer index) → encore Bootstrap, doit rester lisible.
Mobile (DevTools) : navbar burger OK ?
Mode impression : @media print → blanc/noir comme avant ?