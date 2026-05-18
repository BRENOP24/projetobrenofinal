# 1. Usa a imagem oficial do PHP já com o servidor web Apache embutido
FROM php:8.2-apache

# 2. Instala os pacotes do Linux necessários para conversar com o banco PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# 3. Instala as extensões do PHP exclusivas para conectar no PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql pgsql

# 4. Habilita o "mod_rewrite" do Apache (essencial se o site usar URLs amigáveis)
RUN a2enmod rewrite

# 5. Copia todos os arquivos da sua pasta atual para a pasta oficial do servidor web
COPY . /var/www/html/

# 6. Ajusta as permissões para garantir que o servidor possa ler seus arquivos
RUN chown -R www-data:www-data /var/www/html/

# 7. Libera a porta 80 (porta padrão de internet)
EXPOSE 80
