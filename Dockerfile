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
# 3. Instalación de Dependencias del Sistema y PHP 8.4 (FPM y CLI)
# ==============================================================================
# Nota: Se eliminaron xdebug, pcov, swoole y playwright por seguridad en producción.
# Se reemplazó el servidor integrado por php8.4-fpm.
RUN apt-get update && apt-get upgrade -y \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git sqlite3 libcap2-bin libpng-dev python3 dnsutils librsvg2-bin fswatch ffmpeg nano  \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && apt-get update \
    && apt-get install -y \
        libgd3 \
        php8.4-fpm \
        php8.4-cli \
        php8.4-dev \
        php8.4-pgsql \
        php8.4-sqlite3 \
        php8.4-gd \
        php8.4-curl \
        php8.4-mongodb \
        php8.4-imap \
        php8.4-mysql \
        php8.4-mbstring \
        php8.4-xml \
        php8.4-zip \
        php8.4-bcmath \
        php8.4-soap \
        php8.4-intl \
        php8.4-readline \
        php8.4-ldap \
        php8.4-msgpack \
        php8.4-igbinary \
        php8.4-redis \
        php8.4-memcached \
        php8.4-imagick \
    && apt-get install -y php-pear zlib1g-dev \
    && pecl channel-update pecl.php.net \
    && pecl install xlswriter \
    && echo "extension=xlswriter.so" > /etc/php/8.4/cli/conf.d/20-xlswriter.ini \
    && echo "extension=xlswriter.so" > /etc/php/8.4/fpm/conf.d/20-xlswriter.ini

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
RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.4

# Eliminamos usuario ubuntu por defecto y creamos el usuario de la aplicación
RUN userdel -r ubuntu || true
RUN groupadd --force -g $WWWGROUP gelia
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 gelia

# ==============================================================================
# 6. Configuración de PHP-FPM y Archivos de Arranque
# ==============================================================================
RUN mkdir -p /run/php \
    && sed -i 's/listen = \/run\/php\/php8.4-fpm.sock/listen = 9000/g' /etc/php/8.4/fpm/pool.d/www.conf \
    && sed -i 's/clear_env = no/clear_env = yes/g' /etc/php/8.4/fpm/pool.d/www.conf \
    && sed -i 's/user = www-data/user = gelia/g' /etc/php/8.4/fpm/pool.d/www.conf \
    && sed -i 's/group = www-data/group = gelia/g' /etc/php/8.4/fpm/pool.d/www.conf

COPY php.ini /etc/php/8.4/fpm/conf.d/99-gelia.ini
COPY php.ini /etc/php/8.4/cli/conf.d/99-gelia.ini

EXPOSE 9000

CMD ["php-fpm8.4", "-F"]