# Theia Backup

A modern Symfony2 wrapper over Duplicity. Execute backups and check their status via CLI or browser.

## Install application

composer create-project ?

Configure permissions http://symfony.com/doc/current/book/installation.html#book-installation-permissions

Be sure that /etc/timezone is the same with the one in php.ini

## Configure backups

All configuration is done using `app/config/parameters.yml`.

## Execute backups

```bash
app/console theia_backup:backup
```

Use `-v` for verbose output.

## Check status of backups

```bash
app/console theia_backup:status
```

Use `-v` for verbose output.