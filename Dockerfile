# Imagem opcional para implantação via Docker/Dockhand.
# O caminho oficial (XAMPP / Windows Server) continua válido — ver docs/GUIA_IMPLANTACAO.md.
FROM php:8.2-apache

# Extensões exigidas pelo composer.json (mbstring, fileinfo, xmlreader e simplexml já vêm na imagem).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers

COPY docker/php.ini /usr/local/etc/php/conf.d/roteirizador.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/roteirizador
COPY . .

# Instala as dependências do composer.lock (PHPMailer) e gera o autoload PSR-4.
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data storage public/uploads

EXPOSE 80
