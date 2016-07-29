#!/bin/bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR

app/console assetic:dump --env=prod
app/console cache:clear --env=prod