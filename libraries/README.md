# Reusable libraries

This directory owns portable implementations of blueprint-wide contracts. Product-specific
libraries remain with the products that define and release them. Group a blueprint package by its
package manager (`composer/`, `npm/`, and so on), then by package name. Keep each package's manifest,
source, migrations or other runtime assets, tests, and usage guide together. Products own their
composition, credentials, deployment, and domain-specific adapters; they install a package instead
of copying its source.

## Composer

- [FactDB](composer/fact-db/README.md) (`software-dev-blueprint/fact-db`) implements the
  [language-neutral FactDB contract](../architecture/FACT_DB.md).

Until a package registry or release repository is configured, sibling projects install the package
from a Composer `path` repository such as `../software-dev-blueprint/libraries/composer/fact-db`.
Use a mirrored install (`symlink: false`), commit the consumer's lockfile, and deploy the installed
`vendor/` package. The path is a local build input; production does not need the blueprint checkout.
A Composer path reference is not an immutable source release: record the blueprint revision used by
the consumer and refresh the lock and installed package together when the library changes. The
repository owning the package source runs its contract tests; consuming projects test their integration.

Add `npm/` when the first package is independently reusable beyond a product's own workspaces.
