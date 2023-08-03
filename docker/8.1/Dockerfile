FROM composer:latest as composer
FROM php:8.2-fpm

ADD ./extra.ini /usr/local/etc/php/conf.d/

# User/Group globals
ENV PHP_VERSION="8.1" \
	PHALCON_VERSION="5.2.3"

# Update
RUN apt update -y && \
    apt install -y \
        apt-utils \
        git \
        nano \
        sudo \
        wget \
        zip

# PECL Packages
RUN pecl install phalcon-${PHALCON_VERSION}

RUN docker-php-ext-install \
        pdo_mysql

# Install PHP extensions
RUN docker-php-ext-enable \
        phalcon

# Composer
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
# Bash script with helper aliases
COPY ./.bashrc /root/.bashrc

CMD ["php-fpm"]
