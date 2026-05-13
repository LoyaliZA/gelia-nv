# ==============================================================================
# 1. Definición de Imagen Base y Argumentos
# ==============================================================================
FROM ubuntu:24.04

LABEL maintainer="Gelia TI"

ARG WWWGROUP=1000
ARG NODE_VERSION=24
ARG MYSQL_CLIENT="mysql-client"

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ="America/Mexico_City"

# ==============================================================================
# 2. Configuración de Zona Horaria y Repositorios
# ==============================================================================
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN echo "Acquire::http::Pipeline-Depth 0;" > /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::http::No-Cache true;" >> /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::BrokenProxy    true;" >> /etc/apt/apt.conf.d/99custom

# ==============================================================================
# 3. Instalación de Dependencias del Sistema y PHP 8.2 (FPM y CLI)
# ==============================================================================
# Nota: Se eliminaron xdebug, pcov, swoole y playwright por seguridad en producción.
# Se reemplazó el servidor integrado por php8.2-fpm.
RUN apt-get update && apt-get upgrade -y \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git sqlite3 libcap2-bin libpng-dev python3 dnsutils librsvg2-bin fswatch ffmpeg nano  \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && apt-get update \
    && apt-get install -y \
        libgd3 \
        php8.2-fpm \
        php8.2-cli \
        php8.2-pgsql \
        php8.2-sqlite3 \
        php8.2-gd \
        php8.2-curl \
        php8.2-mongodb \
        php8.2-imap \
        php8.2-mysql \
        php8.2-mbstring \
        php8.2-xml \
        php8.2-zip \
        php8.2-bcmath \
        php8.2-soap \
        php8.2-intl \
        php8.2-readline \
        php8.2-ldap \
        php8.2-msgpack \
        php8.2-igbinary \
        php8.2-redis \
        php8.2-memcached \
        php8.2-imagick \
    && apt-get install -y php-pear zlib1g-dev \
    && pecl channel-update pecl.php.net \
    && pecl install xlswriter \
    && echo "extension=xlswriter.so" > /etc/php/8.2/cli/conf.d/20-xlswriter.ini \
    && echo "extension=xlswriter.so" > /etc/php/8.2/fpm/conf.d/20-xlswriter.ini

# ==============================================================================
# 4. Instalación de Composer, Node.js y Clientes de BD
# ==============================================================================
RUN curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && npm install -g npm \
    && apt-get install -y $MYSQL_CLIENT \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# ==============================================================================
# 5. Configuración de Seguridad y Permisos
# ==============================================================================
RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.2

# Eliminamos usuario ubuntu por defecto y creamos el usuario de la aplicación
RUN userdel -r ubuntu || true
RUN groupadd --force -g $WWWGROUP gelia
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 gelia

# ==============================================================================
# 6. Configuración de PHP-FPM y Archivos de Arranque
# ==============================================================================
# Configuramos FPM para que se ejecute en primer plano y escuche en el puerto 9000
RUN mkdir -p /run/php \
    && sed -i 's/listen = \/run\/php\/php8.2-fpm.sock/listen = 9000/g' /etc/php/8.2/fpm/pool.d/www.conf \
    && sed -i 's/clear_env = no/clear_env = yes/g' /etc/php/8.2/fpm/pool.d/www.conf

COPY php.ini /etc/php/8.2/fpm/conf.d/99-gelia.ini
COPY php.ini /etc/php/8.2/cli/conf.d/99-gelia.ini

# Ya no copiamos start-container ni supervisor, el orquestador dictará el comando
EXPOSE 9000

# Comando por defecto (será sobreescrito por docker-compose para workers/reverb)
CMD ["php-fpm8.2", "-F"]