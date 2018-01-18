# Backupphp
Generates a sql backup entirely by php code

## Install
```
composer require danilocgsilva/backupphp
```

## Usage
Once you've installed the package in your project, just call:
```php
\Danilocgsilva\Backupphp::backup('mysql_host', 'mysql_user', 'database_name', 'password', 'full_server_path_to_store_sql_files', (optional)'database_prefix');
```

## Security Notice
There's no security checkings after the backup method is called! It was thought out to serve as an api function, so after entering the parameters in the ```backup()``` method, you must make your own security filtering.

## Where it was already successfully tested
1. Recovering a basic WordPress database.
1. Recovering a 60mb database serving several system, including some wordpresses and drupal installations. Not tested the systems after restoring, but that was no error on restoring.

## ToDo
Provides a csrf_token to put some security in html forms.
