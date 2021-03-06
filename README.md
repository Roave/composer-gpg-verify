## Composer GPG signature verification plugin

[![Packagist](https://img.shields.io/packagist/v/roave/composer-gpg-verify.svg)](https://packagist.org/packages/roave/composer-gpg-verify)
[![Build Status](https://travis-ci.org/Roave/composer-gpg-verify.svg?branch=master)](https://travis-ci.org/Roave/composer-gpg-verify)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Roave/composer-gpg-verify/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Roave/composer-gpg-verify/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/Roave/composer-gpg-verify/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Roave/composer-gpg-verify/?branch=master)

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

This package extensively uses [`GPG`](https://www.gnupg.org/) to
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

## Trusting someone's work

Assuming that you downloaded a signed package, you will likely
get the following failure during the first installation:

```
composer require some-vendor/some-package --prefer-source
# ... lots of lines here ...
The following packages need to be signed and verified, or added to exclusions:

some-vendor/some-package
[SIGNED] [NOT VERIFIED] Commit #4b825dc642cb6eb9a060e54bf8d69288fbee4904 (Key AABBCCDDEEFF1122)
Command: git verify-commit --verbose HEAD
Exit code: 1
Output: tree 4b825dc642cb6eb9a060e54bf8d69288fbee4904
author Mr. Maintainer <maintainer@example.com> 1495040303 +0200
committer Mr. Maintainer <maintainer@example.com> 1495040303 +0200

signed commit
gpg: Signature made Mi 17 Mai 2017 18:58:23 CEST
gpg:                using RSA key AABBCCDDEEFF1122
gpg: Can't check signature: No public key
... more lines ...
```

This means that `some-vendor/some-package` is not trusted.

That `AABBCCDDEEFF1122` is the key you are missing. Let's download it:

```
gpg --recv-keys AABBCCDDEEFF1122
```

Now the key is in your local DB, but it isn't yet trusted.

**IMPORTANT**: do not blindly trust or sign other people's GPG
keys - only do so if you effectively know that the key is provided
by them, and you know them at least marginally. Usually, contacting
the key author is the best way to check authenticity.

To trust a key, you can edit it:

```
gpg --edit-key AABBCCDDEEFF1122
...

gpg> trust 

...

Please decide how far you trust this user to correctly verify other users' keys
(by looking at passports, checking fingerprints from different sources, etc.)

  1 = I don't know or won't say
  2 = I do NOT trust
  3 = I trust marginally
  4 = I trust fully
  5 = I trust ultimately
  m = back to the main menu

Your decision? 3

gpg> save
```

Alternatively, if you want to sign the gpg key, you can create a
local signature:

```sh
gpg --lsign-key AABBCCDDEEFF1122
```

If you *really* trust a key, you can create a generic signature
that may be uploaded:

```sh
gpg --sign-key AABBCCDDEEFF1122
```

Once you did any of the above (signing or trusting), then you may
resume your composer installation or upgrade process.

## Examples

Please refer to the [examples](examples) directory for running
examples in your system. All examples are designed in a way that
will leave your current GPG settings untouched.

## Limitations

This package still has few serious limitations:

 * it needs `gpg` `2.x` to run - this means that you should probably
   be on Ubuntu 16.04 or equivalent.
 * it needs `gpg` `2.x`
 * it can only verify signatures of downloaded GIT repositories: any
   non-git packages will cause the validation to fail

These limitations will eventually be softened as development of
further versions of the library continues.
