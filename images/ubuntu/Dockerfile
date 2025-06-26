FROM ubuntu:20.04

RUN apt-get update && apt-get install -y software-properties-common
RUN LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php


RUN apt-get update -y && apt-get install -y nginx supervisor curl vim
RUN apt-get update -y && apt-get install -y php7.4 \
    php7.4-fpm \
    php7.4-common \
    php7.4-mysql \
    php7.4-gmp \
    php7.4-ldap \
    php7.4-curl \
    php7.4-intl \
    php7.4-mbstring \
    php7.4-xmlrpc \
    php7.4-gd \
    php7.4-bcmath \
    php7.4-xml \
    php7.4-cli \
    php7.4-zip

#Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY ./conf/nginx.conf /etc/nginx/conf.d/default.conf
COPY ./conf/supervisord.conf /etc/supervisor/conf.d/supervisord.conf


EXPOSE 80
EXPOSE 443
#EXPOSE 22
#EXPOSE 9000

CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]