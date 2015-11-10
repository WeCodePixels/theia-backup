# Theia Backup

A modern Symfony2 wrapper over Duplicity. Execute backups and check their status via CLI or browser.

## Installation

This guide has been tested on Ubuntu Server 14.04 LTS, but the steps should be similar for other distributions as well.

### Install Duplicity

First you need to install Duplicity, along with support for Amazon S3 and others. Here is an example for Ubuntu 14.04 LTS:

```bash
sudo apt-get install duplicity python-boto
```

### Install Theia Backup

composer create-project ?

Configure permissions http://symfony.com/doc/current/book/installation.html#book-installation-permissions

Be sure that /etc/timezone is the same with the one in php.ini

### Configure ACL

First you will have to enable ACL. Note that it is already enabled for Ubuntu 14.04 or later, if you are using ext4 partitions (which usually are the default). If not, you can follow this guide: https://help.ubuntu.com/community/FilePermissionsACLs

Afterwards, simply run `./theia-fix-permissions-on-ubuntu.sh`

## Configure backups

All configuration is done using `app/config/parameters.yml`.
nano app/config/parameters.yml
app/console cache:clear --env=prod

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

### Automatically run updates

We will create a separate user called `theia_backup` for running the automatic backups.

```bash
sudo adduser --disabled-password theia_backup
sudo -u theia_backup crontab -e
`

Sample crontab:
```
0 */4 * * * /var/www/backup.wecodepixels.com/app/console/theia_backup:backup --env=prod
```