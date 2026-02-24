# BugFinder Version Upgrade Manager (Laravel Package)

## Installation (development)

Create folder packages -> BugFinder -> version-upgrade in your root directory & place the rest of the code on the folder

 In composer.json place the code after require-dev.
```
 "repositories": [
        {
            "type": "path",
            "url": "packages/BugFinder/version-upgrade"
        }
    ],
```
 In composer.json autoload psr-4 place the code
```
"BugFinder\\VersionUpgrade\\": "packages/BugFinder/version-upgrade/src/"
```
Run `composer dump-autoload` and `composer require bugfinder/version-upgrade-manager:@dev `.


## Installation (production)

Run the command <br>
`Step:1 composer require bugfinder/version-upgrade-manager`<br>
`Step:2 php artisan migrate`

