FROM php:8.2-apache

# Copia os arquivos do seu projeto para o diretório padrão do Apache
COPY . /var/www/html/

# Ativa o módulo de reescrita do Apache (caso use .htaccess no futuro)
RUN a2enmod rewrite

# Garante as permissões corretas para o Apache ler os arquivos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
