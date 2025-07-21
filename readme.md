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
5. Puis `docker compose exec app php bin/console doctrine:fixtures:load --purge-exclusions=user --purge-exclusions=mails_type --purge-exclusions=ecoride --no-interaction` pour ajouter des exemples sans effacer les tables mysql nécessaires
6. Puis `docker compose exec app bin/console doctrine:mongodb:fixtures:load` pour ajouter des exemples dans les collections MongoDB

L'utilisateur admin est inséré via la migration doctrine Version20250515093520.php
Les constantes de configuration de l'appli ne se trouvant pas dans les fichiers de configuration sont insérés via la migration doctrine Version20250515115601.php
Les mails type sont eux insérés via la migration doctrine Version20250516113841.php 

Une fois l'installation terminée, le backend est disponible ici https://localhost:8000.
Vous avez accès à un gestionnaire de MySQL à l'adresse http://localhost:8081,  
et un accès à un gestionnaire MongoDB à l'adresse http://localhost:8082. 

