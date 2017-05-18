#!/usr/bin/env bash

set -xeuo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
LIBRARY_COMMIT="$( git rev-parse HEAD )"
BRANCH="$( git rev-parse --abbrev-ref HEAD )"

echo "First, we will work in a temporary directory"
BASEDIR="$( mktemp -d )"
export COMPOSER_HOME="$BASEDIR"

echo "Let's not mess with your system's GPG environment :-)"
export GNUPGHOME="$BASEDIR/gnupg-home"
mkdir "$GNUPGHOME"
chmod 0700 "$GNUPGHOME"

echo "This is ocramius@gmail.com's GPG key that we are downloading:"
gpg --keyserver pgp.mit.edu --recv-keys 7BB9B50FE8E021187914A5C09F65FBC212EC2DF8

echo "Here we instructing GPG to trust this key:"
echo -e "trust\n5\ny\nsave" | gpg --command-fd 0 --edit-key 7BB9B50FE8E021187914A5C09F65FBC212EC2DF8

echo "Installing and configuring our composer project:"
COMPOSER_PHAR="$BASEDIR/composer.phar"
curl -sS https://getcomposer.org/installer | php --
mv composer.phar "$COMPOSER_PHAR"
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

echo "Here we install the roave/composer-gpg-verify plugin, using the version from this repository:"
"$COMPOSER_PHAR" --working-dir="$BASEDIR" require "roave/composer-gpg-verify:dev-$BRANCH#$LIBRARY_COMMIT" --prefer-source

echo "This package installation will work, because this guy knows how to do releases, and this release is signed:"
"$COMPOSER_PHAR" --working-dir="$BASEDIR" require ocramius/package-versions:1.1.2 --prefer-source

echo "This commit is not signed - this command should fail with an error:"
"$COMPOSER_PHAR" --working-dir="$BASEDIR" require "ocramius/lazy-map:dev-master#5c77102e225d225ae2d74d5f2cc488527834b821" --prefer-source || exit 0

echo "Something went wrong - the last command should have failed, and this location should never be reached!"
exit 1
