## Composer GPG signature verification plugin

This package provides pluggable composer tag signature verification.

Specifically, all this package does is stop the installation process
when an un-trusted package is encountered.

The aim of this package is to be a first reference implementation to
be later used in composer itself to enforce good dependency checking
hygiene.
