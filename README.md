creativeshop magento patch set
==============================

## How to apply patches in your *creativeshop* project?

Add to your `composer.json`:
```json
    {
        "require": {
            "cweagans/composer-patches": "~1.0"
        },
        "extra": {
            "patches-file": "composer.patches.json",
            "enable-patching": true
        }
    }
```

Then create `composer.patches.json` file containing:

```json
{
    "patches": {}
}
```

This setup will install only the essential patches.
In order to apply chosen optional patches find the patch you want
in `optional.patches.json` in this repository and copy-paste
the entry to your project's `composer.patches.json` file
changing the path to point to the vendor directory, e.g.:

```
    vendor/creativestyle/magento-patches/optional/some-name.patch
```

## How to create a patch?

_Unfortunately I haven't found a way to install magento modules
from source using composer. It seems magento repo contains only
dist versions._

This means that for now you have to install module you want to patch
by other means.

Let's say we'll patch the `customer` module's `indexer.xml` config.

Go into your creativeshop project directory, then:
```
# Remove the affected module
rm -rf vendor/magento/module-customer


