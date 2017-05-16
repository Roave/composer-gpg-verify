#!/usr/bin/env bash

set -xeuo pipefail
IFS=$'\n\t'

if [ "$TRAVIS_PHP_VERSION" = '7.1' ] ; then
    wget https://scrutinizer-ci.com/ocular.phar
    php ocular.phar code-coverage:upload --format=php-clover ./clover.xml
fi
