# ==============================================================================
# 1. Definición de Imagen Base y Argumentos
# ==============================================================================
FROM ubuntu:24.04

LABEL maintainer="Gelia TI"

ARG WWWGROUP=1000
ARG MYSQL_CLIENT="mysql-client"
ARG PHP_PACKAGE_VERSION=8.4.27-1+ubuntu24.04.1+deb.sury.org+1
ARG NODE_VERSION=24.22.1
ARG COMPOSER_VERSION=2.10.2

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
RUN apt-get update \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git sqlite3 libcap2-bin libpng-dev python3 dnsutils librsvg2-bin fswatch ffmpeg nano xz-utils make \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        libgd3 \
        php8.4-common=${PHP_PACKAGE_VERSION} \
        php8.4-opcache=${PHP_PACKAGE_VERSION} \
        php8.4-fpm=${PHP_PACKAGE_VERSION} \
        php8.4-cli=${PHP_PACKAGE_VERSION} \
        php8.4-dev=${PHP_PACKAGE_VERSION} \
        php8.4-pgsql=${PHP_PACKAGE_VERSION} \
        php8.4-sqlite3=${PHP_PACKAGE_VERSION} \
        php8.4-gd=${PHP_PACKAGE_VERSION} \
        php8.4-curl=${PHP_PACKAGE_VERSION} \
        php8.4-mysql=${PHP_PACKAGE_VERSION} \
        php8.4-mbstring=${PHP_PACKAGE_VERSION} \
        php8.4-xml=${PHP_PACKAGE_VERSION} \
        php8.4-zip=${PHP_PACKAGE_VERSION} \
        php8.4-bcmath=${PHP_PACKAGE_VERSION} \
        php8.4-soap=${PHP_PACKAGE_VERSION} \
        php8.4-intl=${PHP_PACKAGE_VERSION} \
        php8.4-readline=${PHP_PACKAGE_VERSION} \
        php8.4-ldap=${PHP_PACKAGE_VERSION} \
        php8.4-mongodb \
        php8.4-imap \
        php8.4-msgpack \
        php8.4-igbinary \
        php8.4-redis \
        php8.4-memcached \
        php8.4-imagick \
        php-pear \
        zlib1g-dev \
    && pecl channel-update pecl.php.net \
    && pecl install xlswriter-3.0.0 \
    && echo "extension=xlswriter.so" > /etc/php/8.4/cli/conf.d/20-xlswriter.ini \
    && echo "extension=xlswriter.so" > /etc/php/8.4/fpm/conf.d/20-xlswriter.ini \
    && php -v | grep -q "PHP 8.4.27"

# ==============================================================================
# 4. Instalación de Composer, Node.js y Clientes de BD
# ==============================================================================
RUN curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer --version=${COMPOSER_VERSION} \
    && arch="$(dpkg --print-architecture)" \
    && case "$arch" in \
        amd64) node_arch=x64 ;; \
        arm64) node_arch=arm64 ;; \
        *) echo "arquitectura no soportada: $arch" >&2; exit 1 ;; \
    esac \
    && curl -fsSLO "https://nodejs.org/dist/v${NODE_VERSION}/SHASUMS256.txt" \
    && curl -fsSLO "https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-linux-${node_arch}.tar.xz" \
    && grep " node-v${NODE_VERSION}-linux-${node_arch}.tar.xz$" SHASUMS256.txt | sha256sum -c - \
    && tar -xJf "node-v${NODE_VERSION}-linux-${node_arch}.tar.xz" -C /usr/local --strip-components=1 \
    && rm -f SHASUMS256.txt "node-v${NODE_VERSION}-linux-${node_arch}.tar.xz" \
    && node -v | grep -qx "v${NODE_VERSION}" \
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