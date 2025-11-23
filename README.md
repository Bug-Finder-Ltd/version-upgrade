# BugFinder Version Upgrade Manager (Laravel Package)

## Installation (development)

Place the package code inside your Laravel project (e.g. `packages/BugFinder/version-upgrade`) then add to composer.json autoload (or use path repository).

Run `composer dump-autoload` then add provider if automatic discovery not available.

## Features

- Adds routes, controller, service and view for version upgradation.
- Merges a `version_upgradation` menu item into runtime `config('generalsettings.settings')` so the menu appears without editing files.
- Provides an artisan command to permanently inject the menu into `config/generalsettings.php`:
```
php artisan version-upgrade:inject-config
```

## Notes

- The injection command makes a backup at `config/generalsettings.php.bak.version-upgrade` before editing.
- You should review the changes before committing them.
