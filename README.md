## Composer GPG signature verification plugin

This package provides pluggable composer tag signature verification.

Specifically, all this package does is stop the installation process
when an un-trusted package is encountered.

The aim of this package is to be a first reference implementation to
be later used in composer itself to enforce good dependency checking
hygiene.

## Usage

This package provides no usable public API, but will only act during
the composer installation setup:

```php
composer require roave/composer-gpg-verify --prefer-source
```

Please note that the above may already fail if you have un-trusted
dependencies. In order to skip the checks provided by this package,
use the `--no-scripts` flag if you didn't yet figure out your
un-trusted dependencies:

```php
composer require roave/composer-gpg-verify --prefer-source --no-scripts
```

## Trusted dependencies

This package extensively uses [`gpg`](https://www.gnupg.org/) to
validate that all downloaded dependencies have a good and trusted
GIT tag or commit signature.

At this moment, the package will just use your local GPG trust
database to determine which signatures are to be trusted or not,
and will not mess with it other than reading from it.

In practice, this means that:

 * every package you install must be a `git` repository (use 
   `--prefer-source`)
 * the `HEAD` (current state) of each repository must be either a
   signed tag or a signed commit
 * you must have a local copy of the public key corresponding to
   each tag/commit signature
 * you must either have explicitly trusted, locally signed or
   signed each of the involved public keys

While this must sound like a useless complication to most users,
as they just trust packagist to provide "good" dependencies, these
may have been forged by an attacker that stole information from
your favorite maintainers.

Good dependency hygiene is extremely important, and this package
encourages maintainers to always sign their releases, and users
to always check them.
