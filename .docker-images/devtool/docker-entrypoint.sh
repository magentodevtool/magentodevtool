#!/bin/bash

set -e

DEVTOOL_USER=${DEVTOOL_USER}
DEVTOOL_HOME=${DEVTOOL_HOME}
DEVTOOL_UID=${DEVTOOL_UID}
DOCKER_GID=${DOCKER_GID}

[[ -z ${DEVTOOL_USER} ]] && echo -en '$DEVTOOL_USER environment variable is required with username for devtool user
    docker -e DEVTOOL_USER=$(id) ...
' && exit 1;

[[ -z ${DEVTOOL_UID} ]] && echo -en '$DEVTOOL_UID environment variable is required with uid for devtool user
    with docker-compose run
        DEVTOOL_UID=$(id -u) docker-compose ...
    with docker run
        docker -e DEVTOOL_UID=$(id -u) ...
' && exit 2;

[[ -z ${DOCKER_GID} ]] && echo -en '$DOCKER_GID environment variable is required with gid for docker group
    with docker-compose run
        DOCKER_GID=$(grep docker /etc/group | cut -d: -f3) docker-compose ...
    with docker run
        docker -e DOCKER_GID=$(grep docker /etc/group | cut -d: -f3) ...
' && exit 2;

groupmod -g ${DOCKER_GID} docker

DEVTOOL_DIR=/home/devtool/.devtool
DEVTOOL_CONFIG=${DEVTOOL_DIR}/config.json

# add devtool user if does not exists
[[ -z $(cut -d: -f1 /etc/passwd | grep ${DEVTOOL_USER}) ]] \
    && useradd -u ${DEVTOOL_UID} -g docker -G www-data,docker,root -N -s /bin/bash -d "${DEVTOOL_HOME}" "${DEVTOOL_USER}"

[[ -d ${DEVTOOL_HOME} ]] && chown ${DEVTOOL_USER} ${DEVTOOL_HOME}

: "${APACHE_CONFDIR:=/etc/apache2}"
: "${APACHE_ENVVARS:=$APACHE_CONFDIR/envvars}"
if test -f "$APACHE_ENVVARS"; then
	. "$APACHE_ENVVARS"
fi

# Apache gets grumpy about PID files pre-existing
: "${APACHE_PID_FILE:=${APACHE_RUN_DIR:=/var/run/apache2}/apache2.pid}"
rm -f "$APACHE_PID_FILE"

# enable xdebug extension if ENV variable is set

[ -z "$WITH_XDEBUG" ] && WITH_XDEBUG="WITH_XDEBUG"

if env | grep -q ^WITH_XDEBUG=
then

  # replace variable with the host IP
  MODS_DIR=/usr/local/etc/php/conf.d/
  INI_FILE=docker-php-ext-xdebug-settings.ini

  mv ${MODS_DIR}/${INI_FILE} ${MODS_DIR}/${INI_FILE}.original
  XDEBUG_HOST=$(ip route | awk '/default/ { print $3 }') envsubst < ${MODS_DIR}/${INI_FILE}.original \
    > ${MODS_DIR}/${INI_FILE}
  rm ${MODS_DIR}/${INI_FILE}.original

  docker-php-ext-enable xdebug
fi

exec "$@"
