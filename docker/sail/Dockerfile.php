# Capa PHP de AS02. La base es el tag local gelianv-sail-base:20260831,
# el mismo ID que sail-8.4/app:latest al preparar el parche. No usa un digest de registro.
FROM gelianv-sail-base:20260831

USER root

ARG PHP_PACKAGE_VERSION=8.4.27-1+ubuntu24.04.1+deb.sury.org+1

# Los paquetes del intérprete comparten la versión 8.4.x. Las extensiones PECL
# empaquetadas (redis, imagick, xdebug, …) tienen versión propia y se suben
# junto al intérprete, sin forzarles ese número.
RUN apt-get update \
    && core="$(dpkg-query -W -f '${Package} ${Version}\n' 'php8.4-*' | awk '$2 ~ /^8\.4\./ {print $1}')" \
    && set -- \
    && for pkg in $core; do set -- "$@" "${pkg}=${PHP_PACKAGE_VERSION}"; done \
    && apt-get install -y --only-upgrade --no-install-recommends "$@" \
    && apt-get install -y --only-upgrade --no-install-recommends \
        php8.4-igbinary \
        php8.4-imagick \
        php8.4-imap \
        php8.4-memcached \
        php8.4-mongodb \
        php8.4-msgpack \
        php8.4-pcov \
        php8.4-redis \
        php8.4-swoole \
        php8.4-xdebug \
    && apt-get install -y --no-install-recommends zlib1g-dev \
    && pecl install -f xlswriter-3.0.0 \
    && echo "extension=xlswriter.so" > /etc/php/8.4/cli/conf.d/20-xlswriter.ini \
    && php -m | grep -qx xlswriter \
    && php -v | grep -q 'PHP 8.4.27' \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear /tmp/xlswriter
