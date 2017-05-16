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
composer require roave/composer-gpg-verify
```

Please note that the above may already fail if you have un-trusted
dependencies. In order to skip the checks provided by this package,
use the `--no-scripts` flag if you didn't yet figure out your
un-trusted dependencies:

```php
composer require roave/composer-gpg-verify --no-scripts
```
