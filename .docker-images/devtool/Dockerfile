FROM php:7.1-apache

MAINTAINER ISM eCompany "info@ism.nl"

ENV CONFIG_DIR /usr/local/etc/php/conf.d/

# Set PHP settings
RUN echo "\n[PHP] \
           \nmemory_limit=2G" \
       >> $CONFIG_DIR/docker-php.ini

# Install required PHP extensions
RUN docker-php-ext-install pdo_mysql

# install mcrypt extension
RUN buildDeps=" \
        libmcrypt-dev \
        " \
    && apt-get update && apt-get install -y $buildDeps libmcrypt4 --no-install-recommends \
    && docker-php-ext-install mcrypt \
    && apt-get purge -y --auto-remove ${buildDeps} \
    && rm -rf /var/lib/apt/lists/*

# install libssh2
RUN EXT_DIR=/tmp/.libssh2 buildDeps=" \
        libssh2-1-dev \
        " \
    && apt-get update -qq && apt-get install -y -qq $buildDeps libssh2-1 --no-install-recommends \
    && mkdir -p $EXT_DIR \
    && curl -fsSL https://github.com/Sean-Der/pecl-networking-ssh2/archive/master.tar.gz \
        > $EXT_DIR/master.tar.gz \
    && tar -xzf $EXT_DIR/master.tar.gz -C $EXT_DIR --strip-components=1 \
    && rm $EXT_DIR/master.tar.gz \
    && ( \
            cd $EXT_DIR \
            && phpize \
            && ./configure \
            && make -j$(nproc) --silent \
            && make install \
            && make clean \
        ) \
    && rm -rf $EXT_DIR \
    && rm -rf /var/lib/apt/lists/* \
    && apt-get purge -y --auto-remove libssh2-1-dev \
    && docker-php-ext-enable ssh2


# Install required packages
RUN apt-get update && apt-get install -y --no-install-recommends sudo git openssh-client mysql-client gettext-base rsync \
    && rm -rf /var/lib/apt/lists/*

# Install docker-compose
ENV DOCKER_COMPOSE_VERSION 1.11.2

RUN curl -fsSL https://github.com/docker/compose/releases/download/$DOCKER_COMPOSE_VERSION/docker-compose-`uname -s`-`uname -m` \
    > /usr/local/bin/docker-compose \
    && chmod +x /usr/local/bin/docker-compose

# Install docker
RUN apt-get update && apt-get install -y apt-transport-https ca-certificates software-properties-common gnupg2 \
    && curl -fsSL https://download.docker.com/linux/debian/gpg | apt-key add - \
    && apt-key fingerprint 0EBFCD88 \
    && add-apt-repository -y \
          "deb [arch=amd64] https://download.docker.com/linux/debian \
          $(lsb_release -cs) \
          stable" \
    && apt-get update && apt-get install -y docker-ce \
    && add-apt-repository -y --remove \
              "deb [arch=amd64] https://download.docker.com/linux/debian \
              $(lsb_release -cs) \
              stable" \
    && apt-get purge -y --auto-remove apt-transport-https software-properties-common gnupg2 \
    && rm -rf /var/lib/apt/lists/* /etc/apt/sources.list.d/docker.list

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer

# Install Xdebug extension
ENV XDEBUG_VERSION 2_5_5

ENV EXT_DIR /usr/src/php/ext/xdebug

RUN mkdir -p $EXT_DIR \
    && curl -fsSL https://github.com/xdebug/xdebug/archive/XDEBUG_$XDEBUG_VERSION.tar.gz \
        > $EXT_DIR/$XDEBUG_VERSION.tar.gz \
    && tar -xf $EXT_DIR/$XDEBUG_VERSION.tar.gz -C $EXT_DIR --strip-components=1 \
    && rm $EXT_DIR/$XDEBUG_VERSION.tar.gz \
    && ( \
            cd $EXT_DIR \
            && phpize \
            && ./configure \
            && make -j$(nproc) --silent \
            && make install \
            && make clean \
        ) \
    && rm -r $EXT_DIR \
    && echo "\n[xdebug] \
           \nxdebug.max_nesting_level=300 \
           \nxdebug.remote_enable = on \
           \nxdebug.remote_autostart = off \
           \nxdebug.remote_host = \${XDEBUG_HOST} \
           \nxdebug.remote_port = 9000 \
           \nxdebug.scream=0 \
           \nxdebug.show_local_vars=1 \
           \nxdebug.idekey=PHPSTORM\n" \
       >> $CONFIG_DIR/docker-php-ext-xdebug-settings.ini

# Install compass
RUN buildDeps=" \
            libffi-dev gnupg2 \
            " \
    && apt-get update && apt-get install -y --no-install-recommends ${buildDeps} libffi6 ruby-full \
    && curl -sSL https://rvm.io/mpapis.asc | gpg --import - \
    && curl -sSL https://get.rvm.io | bash -s stable \
    && gem update --system --no-document \
    && gem install --no-document compass \
    && apt-get purge -y --auto-remove ${buildDeps} \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /root/.gem \
    && rm -rf /root/.gnupg

# Install PHP ZIP extension
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libzip4 libpcre3-dev \
    # fix a bug when this directory was empty, but not deleted by a previous ext install
    && docker-php-source delete \
    && docker-php-ext-install zip \
    && apt-get purge -y --auto-remove libzip-dev libpcre3-dev \
    && rm -rf /var/lib/apt/lists/*

# install intl extension
RUN buildDeps=" \
        libicu-dev \
        icu-devtools \
        " \
    && apt-get update && apt-get install -y $buildDeps libicu57 --no-install-recommends \
    && docker-php-ext-install intl \
    && apt-get purge -y --auto-remove ${buildDeps} \
    && rm -rf /var/lib/apt/lists/*

# Install more utils
RUN apt-get update && apt-get install -y --no-install-recommends patch pigz lbzip2 \
    && rm -rf /var/lib/apt/lists/*

# Install PHP GD extension
RUN buildDeps=" \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        " \
    && apt-get update && apt-get install -y $buildDeps libjpeg62-turbo zlib1g --no-install-recommends \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd \
    && apt-get purge -y --auto-remove ${buildDeps} \
    && rm -rf /var/lib/apt/lists/*

# install XSL extension
RUN buildDeps=" \
        libxslt1-dev \
        " \
    && apt-get update && apt-get install -y $buildDeps libxslt1.1 --no-install-recommends \
    && docker-php-ext-install xsl \
    && apt-get purge -y --auto-remove ${buildDeps} \
    && rm -rf /var/lib/apt/lists/*

# install bcmath extension
RUN docker-php-ext-install bcmath

# install SOAP extension
RUN buildDeps=" \
        libxml2-dev \
        " \
    && apt-get update && apt-get install -y $buildDeps libxml2 --no-install-recommends \
    && docker-php-ext-install soap \
    && apt-get purge -y --auto-remove ${buildDeps} \
    && rm -rf /var/lib/apt/lists/*

# install ACL
RUN buildDeps=" \
        " \
    && apt-get update && apt-get install -y $buildDeps acl --no-install-recommends \
    && apt-get purge -y --auto-remove ${buildDeps} \
    && rm -rf /var/lib/apt/lists/*

# Allow sudo without password for the docker group
RUN echo "%docker ALL=(ALL) NOPASSWD: $(which pwd), $(which ls), $(which echo), $(which cp), $(which mkdir), $(which rm), " \
    "$(which chmod), $(which chown), $(which getfacl), $(which setfacl), $(which find), $(which xargs), $(which test)" \
    " \n" >> /etc/sudoers

RUN chmod a+w /etc/apache2/ && chmod a+w /etc/apache2/apache2.conf

COPY ./docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

ENV DEVTOOL_DOCKER 1

VOLUME ["/var/www/html", "/var/run/docker.sock"]

WORKDIR /var/www/html/

ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["apache2-foreground"]
