# AquaWatt — image PHP + Apache pour Render
FROM php:8.2-apache

# L'application utilise curl (client HTTP Turso), déjà compilé dans l'image officielle.
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

# Render fournit le port d'écoute dans $PORT (10000 par défaut) :
# Apache doit écouter sur ce port et non sur le 80.
ENV PORT=10000
CMD sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf \
 && sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf \
 && apache2-foreground
