#!/bin/sh

pluginName="EventCandySets"

#docker exec -it shopware /var/www/html/bin/build-js.sh

#copy new storefront build
#sudo rm -rf ./src/Resources/app/storefront/dist/storefront
#docker cp shopware:/var/www/html/custom/plugins/EventCandyLabelMe/src/Resources/app/storefront/dist/. \
#./src/Resources/app/storefront/dist

#copy new administration
sudo rm -rf ./src/Resources/public/administration
docker cp shopware:/var/www/html/custom/plugins/$pluginName/src/Resources/public/. \
./src/Resources/public


timestamp=$(date +%m-%d-%Y-%s)
git add .
git commit -m "build-${timestamp}"
git push origin master