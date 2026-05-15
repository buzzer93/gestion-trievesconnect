# Gestion Trièves Connect

## Description

**Gestion Trièves Connect** est une application web conçue pour la gestion d'une boutique d'informatique.

Elle permet de gérer un catalogue de produits et services, de scanner des codes-barres à l'aide d'une douchette, de préparer des étiquettes imprimables, de réaliser un inventaire, et d'exporter l'ensemble du catalogue au format Excel.

Le projet a été pensé comme un outil simple et pratique pour faciliter les tâches courantes d'une boutique : suivi du stock, étiquetage des articles, gestion des services et crédits d'impression.

## Stack technique

| Couche | Technologies |
|---|---|
| Backend | PHP >= 8.2, Symfony, Doctrine ORM |
| Frontend | Twig, Tailwind CSS |
| Base de données | SQLite |
| Export Excel | PhpSpreadsheet |
| Outils | Composer, Symfony CLI |

## Prérequis

Avant d'installer le projet, assurez-vous d'avoir :

- PHP 8.2 ou supérieur avec les extensions :
  - `pdo_sqlite`
  - `fileinfo`
- Composer 2+
- Symfony CLI (recommandé)

## Fonctionnalités

- **Authentification** avec un compte de démonstration : `demo` / `demo`
- **Gestion des produits et services** : ajout, édition, listing
- **Étiquetage** :
  - scan des codes-barres avec une douchette
  - préparation d'une liste d'articles à étiqueter
  - génération et impression d'étiquettes
- **Inventaire** :
  - scan des produits enregistrés
  - saisie des quantités en stock
- Sauvegarde temporaire en session des listes d'étiquetage et d'inventaire en cours
- Export Excel du catalogue complet
- Réinitialisation automatique des données pour conserver une démonstration propre
- Gestion des crédits d'impression des clients
- Impression de cartes de crédits d'impression

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/buzzer93/gestion-trievesconnect.git
cd gestion-trievesconnect
```

### 2. Configurer l'environnement local

Créer un fichier `.env.local` à la racine du projet :

```env
APP_ENV=dev
APP_SECRET=votre_secret_ici
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

### 3. Installer les dépendances

```bash
composer install
```

### 4. Initialiser la base de données

```bash
php bin/console doctrine:database:create
php bin/console doctrine:schema:update --force
php bin/console doctrine:fixtures:load
```

### 5. Lancer le serveur local

```bash
symfony server:start
```

L'application sera accessible à l'adresse : [http://127.0.0.1:8000](http://127.0.0.1:8000)

## Utilisation

1. Se connecter avec les identifiants de démonstration :
   - **Identifiant** : `demo`
   - **Mot de passe** : `demo`
2. Ajouter des produits ou services depuis les onglets dédiés.
3. Utiliser l'onglet **Étiquetage** pour scanner les codes-barres, préparer une liste d'articles, puis imprimer les étiquettes.
4. Utiliser l'onglet **Inventaire** pour scanner les produits enregistrés et saisir les quantités en stock.
5. Depuis l'onglet **Produits**, exporter le catalogue complet au format Excel.
