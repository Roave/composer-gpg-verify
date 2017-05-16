#!/usr/bin/env bash

set -xeuo pipefail
IFS=$'\n\t'

composer self-update
composer clear-cache
composer update

if [ "$DEPENDENCIES" = 'low' ] ; then
    composer update --prefer-lowest --prefer-stable
fi
