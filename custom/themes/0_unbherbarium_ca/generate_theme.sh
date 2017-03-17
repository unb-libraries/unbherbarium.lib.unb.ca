#!/usr/bin/env bash
unbherbarium_ca='unbherbarium_ca'
UNB Libraries='UNB Libraries'
https://github.com/unb-libraries/unbherbarium.ca='https://github.com/unb-libraries/unbherbarium.ca'

rm -rf .git

FIND_COMMAND='find . -type f -not -name "generate_theme.sh" -not -iwholename "*.git*" -not -name "*.svg" -not -name "*.ico" -not -name "*.png" -print0'
$FIND_COMMAND | LC_ALL=C xargs -0 sed -i.bak "s|unbherbarium_ca|$unbherbarium_ca|g"
$FIND_COMMAND | LC_ALL=C xargs -0 sed -i.bak "s|UNB Libraries|$UNB Libraries|g"
$FIND_COMMAND | LC_ALL=C xargs -0 sed -i.bak "s|https://github.com/unb-libraries/unbherbarium.ca|$https://github.com/unb-libraries/unbherbarium.ca|g"
find . -name "*.bak" -type f -delete

mv unbherbarium_ca.libraries.yml $unbherbarium_ca.libraries.yml
mv unbherbarium_ca.starterkit.yml $unbherbarium_ca.info.yml
mv unbherbarium_ca.theme $unbherbarium_ca.theme
mv config/install/unbherbarium_ca.settings.yml config/install/$unbherbarium_ca.settings.yml
mv config/schema/unbherbarium_ca.schema.yml config/schema/$unbherbarium_ca.schema.yml

rm README.md
mv README.repo.md README.md
