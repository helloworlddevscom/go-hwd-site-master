#!/usr/bin/env bash

# Script to enable/disable Xdebug and set mode.
# See: https://github.com/lando/lando/issues/1668#issuecomment-772829423

if [ "$#" -ne 1 ] || [ "$1" = "on" ]; then
  echo xdebug.mode = debug > /usr/local/etc/php/conf.d/zzz-lando-xdebug.ini
  docker-php-ext-enable xdebug && pkill -o -USR2 php-fpm
  php -v
  echo && tput setaf 2 && echo "Xdebug is loaded in debug mode." && tput sgr 0 && echo
  echo "To load in a different mode, follow the syntax: 'lando xdebug <mode>'."
  echo "Valid modes: https://xdebug.org/docs/all_settings#mode."
elif [ "$1" = "off" ]; then
  echo xdebug.mode = off > /usr/local/etc/php/conf.d/zzz-lando-xdebug.ini
  rm /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && pkill -o -USR2 php-fpm
  php -v
  echo && tput setaf 1 && echo "Xdebug has been turned off." && tput sgr 0 && echo
else
  mode="$1"
  echo xdebug.mode = "$mode" > /usr/local/etc/php/conf.d/zzz-lando-xdebug.ini
  docker-php-ext-enable xdebug && pkill -o -USR2 php-fpm
  php -v
  echo && tput setaf 2 && echo "Xdebug is loaded in "$mode" mode." && tput sgr 0 && echo
fi
