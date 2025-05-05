FROM node:20

# Crée le dossier de l'app
WORKDIR /app

# Copie les fichiers de l'app
COPY . .

# Installe les dépendances
RUN npm install

# Expose le port (adapter si nécessaire)
EXPOSE 8000

# Commande pour démarrer l'app
CMD ["server", "start"] 