# Theia Backup

A modern Symfony2 wrapper over Duplicity. Execute backups and check their status via CLI or browser.

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