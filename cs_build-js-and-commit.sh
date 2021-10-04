#!/bin/sh

. .env

pluginName=$PLUGINNAME


#docker exec -it shopware /var/www/html/bin/build-administration.sh

#copy new administration
sudo rm -rf ./src/Resources/public/administration
docker cp shopware:/var/www/html/custom/plugins/$pluginName/src/Resources/public/. \
./src/Resources/public


#commit build with new timestamp
timestamp=$(date +%m-%d-%Y-%s)
git add .
git commit -m "build-${timestamp}-${SWVERSION}"
sudo -u $LOCALUSER git push origin master
