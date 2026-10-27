# TEST only: extend the verified existing runtime, never change persistent WP core.
ARG WORDPRESS_BASE_IMAGE=wordpress:7.0.2-php8.2-apache@sha256:680df6fd52a1ec7948deb6ca5fa57f1bca0d5d062396ffd0c57b8b4f24adc23f
FROM ${WORDPRESS_BASE_IMAGE}
RUN set -eux; \
    . /etc/os-release; \
    test "$VERSION_CODENAME" = trixie; \
    apt-get update; \
    apt-get install -y --no-install-recommends libheif1 libheif-plugin-libde265 libimage-exiftool-perl; \
    rm -rf /var/lib/apt/lists/*; \
    php -r 'if (!extension_loaded("imagick") || !function_exists("proc_open") || !Imagick::queryFormats("HEIC")) exit(1);'; \
    exiftool -ver
