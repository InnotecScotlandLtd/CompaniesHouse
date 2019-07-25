# CompaniesHouse
[![Latest Stable Version]()](https://innotec.co.uk)
[![Total Downloads]()]()
[![Build Status]()]()

A package for Companies House. [https://innotec.co.uk]
## Installation via Composer

### Require the package

```
$ composer require innotecscotlandltd/companies-house
```
```json
{
    "require": {
        "innotecscotlandltd/companies-house": "dev-master"
    }
}
```
Put this as psr4 in main composer.json
```
"InnotecScotlandLtd\\CompaniesHouse\\": "path-to-package/CompaniesHouse/src/"
```
Put this as repositories object
```
"repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/InnotecScotlandLtd/CompaniesHouse"
    }
  ],
```
Add the Service Provider to your config/app.php under providers

```
\InnotecScotlandLtd\CompaniesHouse\Providers\CompaniesHouseServiceProvider::class,
```

Publish the configuration file
```
php artisan vendor:publish
```
This will create a companiesHouse.php within your config directory. Edit the relevant values in the companiesHouse.php file.

## Usage