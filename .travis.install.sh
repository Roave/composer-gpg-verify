#!/usr/bin/env bash

set -xeu
IFS=$'\n\t'

composer self-update
composer clear-cache
composer update --no-scripts

if [ "$DEPENDENCIES" = 'low' ] ; then
    composer update --prefer-lowest --prefer-stable --no-scripts
fi
