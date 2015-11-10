#!/bin/bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR

# Create folders if they do not exist.
sudo mkdir -p app/logs app/cache app/duplicity_archive

# Set proper ACL for current user, theia_backup, and www-data (or whatever web user is used in the current distribution)
HTTPDUSER=`ps axo user,comm | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1`
sudo setfacl -R -m u:"$HTTPDUSER":rwX -m u:`whoami`:rwX -m u:theia_backup:rwX app/cache app/logs app/duplicity_archive
sudo setfacl -dR -m u:"$HTTPDUSER":rwX -m u:`whoami`:rwX -m u:theia_backup:rwX app/cache app/logs app/duplicity_archive
