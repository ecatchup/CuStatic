#!/bin/sh

cd $(cd $(dirname $0); cd ../../../../; pwd)
php ./app/Console/cake.php CuStatic.CuStatic $1 $2
