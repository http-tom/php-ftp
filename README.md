# php-ftp

A simple PHP utility to work with FTP servers.

## Install

Install package with composer

```
composer require http-tom/php-ftp
```

## How to use

```php
require_once 'vendor/autoload.php';
use HttpTom\Ftp\FTP as FTP;

$ftp = new FTP();
```

Connec to FTP server

```php
$connectionid = FTP::connect('hostname', 'username', 'password');
```


