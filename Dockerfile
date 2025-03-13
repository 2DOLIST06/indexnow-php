# Image officielle PHP 8.1 avec les outils de base
FROM php:8.1-cli

# On installe Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# On définit le dossier de travail
WORKDIR /app

# On copie le fichier composer.json et on installe les dépendances
COPY composer.json ./
RUN composer install --no-dev

# On copie tout le reste du code
COPY . /app

# Commande de démarrage : serveur PHP intégré, sur le port 8080, dossier public
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
