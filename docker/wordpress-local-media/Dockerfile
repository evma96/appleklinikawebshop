# Opt-in LOCAL media compatibility only; does not upgrade WordPress/PHP.
ARG WORDPRESS_VERSION=6.5.5
ARG PHP_VERSION=8.2
FROM wordpress:${WORDPRESS_VERSION}-php${PHP_VERSION}-apache

# The original Bookworm libheif 1.15 cannot decode newer iPhone HEIC files.
# Use Debian's supported backport and decoder; WordPress performs its native
# HEIC -> JPEG conversion and thumbnail generation on the existing attachment.
RUN set -eux; \
    . /etc/os-release; \
    test "$VERSION_CODENAME" = bookworm; \
    printf '%s\n' 'deb https://deb.debian.org/debian bookworm-backports main' > /etc/apt/sources.list.d/local-media-backports.list; \
    apt-get update; \
    apt-get install -y --no-install-recommends -t bookworm-backports libheif1 libheif-plugin-libde265; \
    apt-get install -y --no-install-recommends libimage-exiftool-perl; \
    rm -rf /var/lib/apt/lists/*
