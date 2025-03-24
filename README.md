<a id="readme-top"></a>
[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![Issues][issues-shield]][issues-url]

<!-- PROJECT LOGO -->
<br />
<div align="center">
  <a href="https://github.com/buzzer93/gestion-trievesconnect">
    <img src="public/image/favicon.ico" alt="Logo" width="80" height="80">
  </a>

<h3 align="center">Gestion-Trievesconnect-Demo</h3>

<p align="center">
    Une solution web dédiée à faciliter les inventaires et à l'étiquetage des produits pour une boutique.
</p>
    <br />
    <br />
    <a href="https://gestion-trieves.nicolas-rodriguez.fr">View Demo</a>
    &middot;
    <a href="https://github.com/buzzer93/gestion-trievesconnect/issues/new?labels=bug&template=bug-report---.md">Report Bug</a>
    &middot;
    <a href="https://github.com/buzzer93/gestion-trievesconnect/issues/new?labels=enhancement&template=feature-request---.md">Request Feature</a>
  </p>
</div>

<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li>
      <a href="#about-the-project">About The Project</a>
      <ul>
        <li><a href="#built-with">Built With</a></li>
      </ul>
    </li>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installation">Installation</a></li>
      </ul>
    </li>
    <li><a href="#usage">Usage</a></li>
    <li><a href="#contact">Contact</a></li>
  </ol>
</details>

<!-- ABOUT THE PROJECT -->

## About The Project

Ce projet est la démo d'un outil que j'ai conçu pour un ami qui avait besoin d'un logiciel pour simplifier les tâches d'inventaire.

Il souhaitait scanner les produits à l'aide d'un lecteur de codes-barres et enregistrer le prix ainsi que la quantité en stock pour chaque article de sa boutique.

Par la suite, il a demandé une fonctionnalité supplémentaire pour créer des étiquettes pour ses produits, lui permettant de scanner directement l'étiquette sur l'étagère au lieu du produit lui-même.

J'ai implémenté cette fonctionnalité, lui permettant de sélectionner des produits et d'imprimer des étiquettes pour les articles choisis.

Enfin, il a demandé une fonctionnalité d'exportation pour sauvegarder toutes les données au format Excel, ce qui a été rendu possible grâce à la bibliothèque PhpSpreadsheet.

<p align="right">(<a href="#readme-top">retour en haut</a>)</p>

[![Product Name Screen Shot][product-screenshot]](https://example.com)

<p align="right">(<a href="#readme-top">back to top</a>)</p>

### Built With

- [![Symfony][symfony-shield]][symfony-url]
- [![Bootstrap][bootstrap-shield]][bootstrap-url]
- [![PhpSpreadsheet][phpspreadsheet-shield]][phpspreadsheet-url]

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- GETTING STARTED -->

## Getting Started

Ce projet étant une version de démonstration, les données sont générées et réinitialisées automatiquement par un EventListener. Celui-ci nettoie la base de données SQLite à chaque nouvelle session.

### Prerequisites

Vous aurez besoin des éléments suivants pour exécuter ce projet :

- **Composer** : Un gestionnaire de dépendances pour PHP.
- **PHP 8.2** : Assurez-vous que les extensions suivantes sont activées :
  - `pdo_sqlite`
  - `fileinfo`

### Installation

1. Clone the repository:

   ```sh
   git clone https://github.com/buzzer93/gestion-trievesconnect.git
   ```

2. Navigate to the project directory:

   ```sh
   cd gestion-trievesconnect
   ```

3. Install dependencies using Composer:

   ```sh
   composer install
   ```

4. Configure your environment variables pour:

   - Create a `.env.local` file if it doesn't exist.
   - Add your application secret:
     ```env
     APP_SECRET=your_secret
     ```

5. Update the Git remote URL to avoid accidental pushes to the original repository:

   ```sh
   git remote set-url origin https://github.com/your_username/your_repository_name.git
   git remote -v # Verify the changes
   ```

6. Initialisez la base de données :

   ```sh
   php bin/console doctrine:database:create
   php bin/console doctrine:schema:update --force
   php bin/console doctrine:fixtures:load
   ```

7. Start the development server:
`sh symfony server:start`
<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- USAGE EXAMPLES -->

## Usage

1. Connectez-vous à l'application avec les identifiants `demo` / `demo`.
2. Ajoutez des **produits** ou des **services** via les onglets dédiés.
3. Dans l'onglet **Étiquetage**, scannez les codes-barres des articles pour les ajouter, puis imprimez leurs étiquettes correspondantes.
4. Utilisez l'onglet **Inventaire** pour scanner les produits enregistrés et indiquer leurs quantités afin de réaliser un inventaire.
5. Les listes d'**étiquetage** et d'**inventaire** en cours sont sauvegardées dans la session de votre navigateur (elles seront **supprimées à la fermeture du navigateur**).
6. Depuis l'onglet **Produits**, téléchargez la liste complète de vos produits et services enregistrés au format Excel.
<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CONTACT -->

## Contact

Project Link: [https://github.com/buzzer93/gestion-trievesconnect](https://github.com/buzzer93/gestion-trievesconnect)

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- MARKDOWN LINKS & IMAGES -->

[contributors-shield]: https://img.shields.io/github/contributors/buzzer93/gestion-trievesconnect.svg?style=for-the-badge
[contributors-url]: https://github.com/buzzer93/gestion-trievesconnect/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/buzzer93/gestion-trievesconnect.svg?style=for-the-badge
[forks-url]: https://github.com/buzzer93/gestion-trievesconnect/network/members
[stars-shield]: https://img.shields.io/github/stars/buzzer93/gestion-trievesconnect.svg?style=for-the-badge
[stars-url]: https://github.com/buzzer93/gestion-trievesconnect/stargazers
[issues-shield]: https://img.shields.io/github/issues/buzzer93/gestion-trievesconnect.svg?style=for-the-badge
[issues-url]: https://github.com/buzzer93/gestion-trievesconnect/issues
[license-shield]: https://img.shields.io/github/license/buzzer93/gestion-trievesconnect.svg?style=for-the-badge
[license-url]: https://github.com/buzzer93/gestion-trievesconnect/blob/master/LICENSE.txt
[linkedin-shield]: https://img.shields.io/badge/-LinkedIn-black.svg?style=for-the-badge&logo=linkedin&colorB=555
[linkedin-url]: https://linkedin.com/in/linkedin_username
[product-screenshot]: public/image/gestion.png
[symfony-shield]: https://img.shields.io/badge/Symfony-000000?style=for-the-badge&logo=symfony&logoColor=white
[symfony-url]: https://symfony.com/
[bootstrap-shield]: https://img.shields.io/badge/Bootstrap-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white
[bootstrap-url]: https://getbootstrap.com/
[phpspreadsheet-shield]: https://img.shields.io/badge/PhpSpreadsheet-217346?style=for-the-badge&logo=php&logoColor=white
[phpspreadsheet-url]: https://phpspreadsheet.readthedocs.io/
