# Gestion Trièves Connect

Application Symfony 7.1 de gestion d'inventaire et d'étiquetage pour la boutique Trièves Connect, avec lecture de codes-barres, génération d'étiquettes imprimables, gestion des crédits d'impression clients et export Excel.

---

## Présentation

Ce projet permet de :

- Scanner les codes-barres des produits via une douchette pour enregistrer prix et quantité.
- Générer et imprimer des étiquettes produits.
- Réaliser un inventaire par scan des articles en stock.
- Gérer les clients et leurs crédits d'impression.
- Imprimer des cartes de crédits d'impression pour les clients.
- Exporter le catalogue complet au format Excel.

---

## Stack principale

### Backend

- **PHP 8.2+**
- **Symfony 7.1** — Framework Bundle, Security, Form, Validator, AssetMapper, Stimulus
- **Doctrine ORM 3** + Doctrine Migrations 3.3
- **PhpSpreadsheet** — export Excel du catalogue produits
- **picqer/php-barcode-generator** — génération des codes-barres dans les étiquettes

### Frontend

- **Tailwind CSS v4** (`symfonycasts/tailwind-bundle`) via AssetMapper
- **Twig** — templates
- **Stimulus** (`symfony/stimulus-bundle`) — comportements JS

### Base de données

- **SQLite** (`var/data.db`) — en local
- **MySQL / MariaDB 10.11** — en production

### Infrastructure & outils

- **AssetMapper** — gestion des assets JS/CSS sans bundler
- **PHPUnit 9.5** — tests
- **Symfony CLI** — serveur de développement

---

## Fonctionnalités

### Étiquetage

- Scan de codes-barres via douchette pour alimenter la liste d'étiquetage.
- Génération et impression des étiquettes produits.

### Inventaire

- Scan des produits enregistrés pour saisir les quantités en stock.

### Produits & Services

- Ajout, édition et listing des produits et services.
- Gestion des catégories de produits.
- Export du catalogue complet au format Excel.

### Clients & Crédits d'impression

- Gestion des clients : ajout, édition, suppression.
- Suivi du solde de crédits d'impression par client.
- Ajout ou débit de crédits depuis la fiche client.
- Impression d'une carte de crédits d'impression au format étiquette.
- Décompte automatique des crédits lors d'une impression.

### Authentification

- Accès sécurisé via Symfony Security.
- Sauvegarde temporaire en session des listes d'étiquetage et d'inventaire (effacées à la fermeture du navigateur).

---

## Installation locale

### 1. Prérequis

- PHP 8.2+ avec les extensions :
  - `pdo_sqlite`
  - `fileinfo`
- Composer 2+
- Symfony CLI (recommandé)

### 2. Cloner le projet

```bash
git clone https://github.com/buzzer93/gestion-trievesconnect.git
cd gestion-trievesconnect
```

### 3. Installer les dépendances

```bash
composer install
```

### 4. Configurer l'environnement

```bash
cp .env .env.local
```

Variables utiles :

```dotenv
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=votre_secret_ici
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

### 5. Base de données SQLite

```bash
mkdir -p var
touch var/data.db
php bin/console doctrine:migrations:migrate -n
```

### 6. Fixtures

```bash
php bin/console doctrine:fixtures:load -n
```

> **Attention :** cette commande vide les tables avant de recharger les données.

### 7. Assets Tailwind

```bash
php bin/console tailwind:build
```

### 8. Lancer le serveur

```bash
symfony serve
```

| URL | Accès |
| --- | --- |
| `http://127.0.0.1:8000` | Application |

---

## Tests

```bash
php bin/phpunit
```

---

## Commandes utiles

```bash
# Appliquer les migrations
php bin/console doctrine:migrations:migrate -n

# Vider le cache
php bin/console cache:clear

# Reconstruire Tailwind
php bin/console tailwind:build

# Compiler les assets AssetMapper
php bin/console asset-map:compile

# Recharger les fixtures
php bin/console doctrine:fixtures:load -n
```

---

## Déploiement production

Exemple de déploiement sur un VPS avec Caddy et PHP-FPM.

### 1. Récupérer les modifications

```bash
git pull
```

### 2. Installer les dépendances de production

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Configurer `.env.local`

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=votre_secret_production
DATABASE_URL="mysql://user:password@127.0.0.1:3306/gestion_trieves?serverVersion=10.11.0-MariaDB&charset=utf8mb4"
```

### 4. Appliquer les migrations

```bash
APP_ENV=prod APP_DEBUG=0 php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Compiler les assets

```bash
APP_ENV=prod APP_DEBUG=0 php bin/console tailwind:build --minify
APP_ENV=prod APP_DEBUG=0 php bin/console asset-map:compile
```

### 6. Vider le cache

```bash
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
```

### 7. Redémarrer les services

```bash
sudo systemctl restart php8.2-fpm
sudo systemctl reload caddy
```

---

## Checklist après un `git pull` en production

```bash
git pull

composer install --no-dev --optimize-autoloader

APP_ENV=prod APP_DEBUG=0 php bin/console doctrine:migrations:migrate --no-interaction

APP_ENV=prod APP_DEBUG=0 php bin/console tailwind:build --minify
APP_ENV=prod APP_DEBUG=0 php bin/console asset-map:compile

APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear

sudo systemctl restart php8.2-fpm
sudo systemctl reload caddy
```

---

## Liens

- Dépôt : [github.com/buzzer93/gestion-trievesconnect](https://github.com/buzzer93/gestion-trievesconnect)
- Signaler un bug : [Issues bug](https://github.com/buzzer93/gestion-trievesconnect/issues/new?labels=bug)
- Demander une fonctionnalité : [Issues enhancement](https://github.com/buzzer93/gestion-trievesconnect/issues/new?labels=enhancement)
