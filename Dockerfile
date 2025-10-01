FROM php:7.4-apache

# Install mysqli and required dependencies
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Optional: install other useful PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Set working directory
WORKDIR /var/www/html
