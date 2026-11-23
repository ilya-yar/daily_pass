#!/bin/sh
set -eu
cd /app
php bin/yii migrate --interactive=0
exec php -S 0.0.0.0:8080 -t public public/router.php
