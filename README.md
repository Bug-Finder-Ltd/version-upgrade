# BugFinder Version Upgrade Manager (Laravel Package)

## Installation (development)

- In composer.json place the code after require-dev.
`
 "repositories": [
        {
            "type": "path",
            "url": "packages/BugFinder/version-upgrade"
        }
    ],
`
- In composer.json autoload psr-4 place the code
`
"BugFinder\\VersionUpgrade\\": "packages/BugFinder/version-upgrade/src/"
`
Run `composer dump-autoload` and `composer require bugfinder/version-upgrade-manager:@dev `.

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
