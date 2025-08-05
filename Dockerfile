FROM php:8.2-apache

# Copy app code to Apache web root
COPY . /var/www/html/

# Enable Apache mod_rewrite (if needed)
RUN a2enmod rewrite
