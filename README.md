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
