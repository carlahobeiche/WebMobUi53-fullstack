# HEIG-VD DévProdMéd Course - Mini-projet

Ce dépôt contient le mini-projet à réaliser dans le cadre du cours
_"[Développement de produit média (DévProdMéd)](https://github.com/heig-vd-devprodmed-course/heig-vd-devprodmed-course)"_
enseigné à la
[Haute Ecole d'Ingénierie et de Gestion du Canton de Vaud (HEIG-VD)](https://heig-vd.ch),
Suisse.

## Objectif du mini-projet

L'objectif de ce mini-projet est de créer un réseau social simple en utilisant le
framework [Laravel](https://laravel.com/). Ce projet permettra de mettre en pratique les concepts
appris dans le cours.

## Pré-requis

Afin de lancer ce projet, une stack compatible avec Laravel, est requise.

Voici les pré-requis nécessaires :

- PHP >= 8.0.
- Composer.
- Node.js et npm.
- Une base de données (MySQL, PostgreSQL, SQLite, etc.).
- Un serveur web (Apache, Nginx, etc.).

[Laravel Herd](https://helm.sh/docs/charts/laravel/) est recommandé pour une installation facile de Laravel et de ses dépendances.

## Développement local

Pour développer et tester le mini-projet en local, voici les étapes à suivre :

1. Forker ce dépôt

2. Installer les dépendances avec npm et Composer :

    ```bash
    npm install && npm run build

    composer install
    ```

3. Copier le fichier `.env.example` en `.env`.
4. Modifier les variables d'environnement si nécessaire (optionnel).
5. Générer la clé d'application Laravel :

    ```bash
    php artisan key:generate
    ```

6. Créer le lien symbolique pour les fichiers téléversés :

    ```bash
    php artisan storage:link
    ```

7. Créer la base de données et exécuter les migrations :

    ```bash
    php artisan migrate
    ```

    S'il est nécessaire de réinitialiser la base de données, utiliser la commande `php artisan migrate:reset` puis `php artisan migrate` à nouveau.

8. Optionnel : en mode développement, il est possible de peupler la base de données avec des données fictives :

    ```bash
    php artisan db:seed
    ```

9. Démarrer le serveur de développement Laravel :

    ```bash
    composer run dev
    ```

L'application sera accessible à l'adresse <http://127.0.0.1:8000>.

## Système de sondage

Cette application intègre un système de sondage complet développé avec Laravel et Vue.js.

### Fonctionnalités

- Créer, modifier et supprimer des sondages depuis un dashboard
- Lancer un sondage (brouillon → actif)
- Configurer les options : choix unique ou multiple, résultats publics, durée
- Partager un sondage via un lien unique contenant un token
- Voter sur un sondage (une seule fois par utilisateur)
- Voir les résultats en direct via polling toutes les 5 secondes
- Graphique en barres des résultats
- Accès aux résultats conditionnel (publics ou créateur uniquement)

### Pages

- `/polls/dashboard` — dashboard de gestion des sondages (authentification requise)
- `/polls/{token}` — page de vote et de résultats (accessible sans connexion si résultats publics)

### Architecture frontend

Deux applications Vue.js distinctes :
- `poll-dashboard.js` — gestion des sondages (création, édition, suppression)
- `poll-vote.js` — vote et affichage des résultats en direct