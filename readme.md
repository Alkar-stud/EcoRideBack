# Ecoride - BackEnd

## Présentation
Ce projet est réalisé dans le cadre de l'ECF de Studi.

## Pré-requis
* Docker doit être installé sur la machine hôte

## Installation
1. Cloner le projet
2. Remplacer les mots de passe 'fake' présents dans docker-compose.yml par les vôtres
3. Dans le répertoire du projet, lancer `docker compose up -d --build`
4. Puis `docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction`


Une fois l'installation terminée, le backend est disponible ici https://localhost:8000.
Vous avez accès à un gestionnaire de MySQL à l'adresse http://localhost:8081,  
et un accès à un gestionnaire MongoDB à l'adresse http://localhost:8082. 
