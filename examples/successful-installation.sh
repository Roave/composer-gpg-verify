#!/usr/bin/env bash

set -xeuo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
LIBRARY_COMMIT="$( git rev-parse HEAD )"

# we work in a temporary directory
BASEDIR="$( mktemp -d )"
COMPOSER_PHAR="$BASEDIR/composer.phar"
export COMPOSER_HOME="$BASEDIR"

# let's not mess with your system's GPG environment :-)
export GNUPGHOME="$BASEDIR/gnupg-home"
mkdir "$GNUPGHOME"
chmod 0700 "$GNUPGHOME"

curl -sS https://getcomposer.org/installer | php --
mv composer.phar "$COMPOSER_PHAR"

# this is ocramius@gmail.com's GPG key
gpg --keyserver pgp.mit.edu --recv-keys 7BB9B50FE8E021187914A5C09F65FBC212EC2DF8

# this is just a non-interactive way to run `gpg --edit-key` and then typing "trust", "4", "y"
echo -e "trust\n5\ny\nsave" | gpg --command-fd 0 --edit-key 7BB9B50FE8E021187914A5C09F65FBC212EC2DF8

# we need to tell composer where to find this repository, as we want to test against the current HEAD
cat > "$BASEDIR/composer.json" << JSON
{
    "minimum-stability": "dev",
    "config": {
        "preferred-install": "source"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "$DIR/../"
        }
    ]
}
JSON

# let's install this library first
"$COMPOSER_PHAR" --working-dir="$BASEDIR" require "roave/composer-gpg-verify:dev-master#$LIBRARY_COMMIT" --prefer-source

# this package installation will work, because this guy knows how to do releases, and this release is signed
"$COMPOSER_PHAR" --working-dir="$BASEDIR" require ocramius/package-versions:1.1.2 --prefer-source

# this commit is not signed - this command should fail
echo "The next command is supposed to fail - this is expected:"
"$COMPOSER_PHAR" --working-dir="$BASEDIR" require "ocramius/lazy-map:dev-master#5c77102e225d225ae2d74d5f2cc488527834b821" --prefer-source || exit 0

# this location should never be reached
echo "Something went wrong - the last command should have failed"
exit 1
