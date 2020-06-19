#!/bin/sh

cd $(cd $(dirname $0); cd ../../../../; pwd)
php ./app/Console/cake.php BcStatic.BcStatic $1
