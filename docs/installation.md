# Installation

Composer install, the supported PHP and Laravel range, and the optional Laravel Boost skills.

You can install the package via composer:

```bash
composer require laranail/validation
```

Requires PHP 8.5+ and Laravel 13.

## Support matrix and EOL policy

| Dimension | Supported |
|---|---|
| PHP | `^8.5` (the test matrix runs 8.5) |
| Laravel | `^13.0` |

Floors move only in a major. A new Laravel major is adopted within one minor release of the
laranail foundation (`package-tools`/`console`) supporting it; a PHP version leaves the matrix
when it leaves [php.net's active support](https://www.php.net/supported-versions.php), and
dropping it from `composer.json` is a major.

> The package is pre-1.0. The public surface is settling, so pin a constraint you are happy to
> review — `^0.1` tracks the current line.

## AI-assisted development

If you use [Laravel Boost](https://github.com/laravel/boost), this package ships with skills that give AI assistants the full FluentRule API reference:

```bash
php artisan boost:install    # adds the skills
php artisan boost:update     # publishes updates after package upgrades
```


---

[← Docs index](../README.md#documentation)
