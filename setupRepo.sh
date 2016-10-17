#!/usr/bin/env bash
URI_STRING='unbherbarium.ca'
SLUG_STRING='herbarium'
UUID_STRING='3081'
DRUPAL_UUID_STRING='85c96bf2-f1b6-4612-8305-d3d3769d5255'

rm -rf .git

find . -type f -print0 | xargs -0 sed -i.bak "s/unbherbarium.ca/$URI_STRING/g"
find . -type f -print0 | xargs -0 sed -i.bak "s/herbarium/$SLUG_STRING/g"
find . -type f -print0 | xargs -0 sed -i.bak "s/85c96bf2-f1b6-4612-8305-d3d3769d5255/$DRUPAL_UUID_STRING/g"
find . -type f -print0 | xargs -0 sed -i.bak "s/3081/$UUID_STRING/g"
find . -name "*.bak" -type f -delete

rm README.md
mv README.repo.md README.md

git init
git add .
git add -f ./config-yml/.gitkeep
git add -f ./custom/modules/.gitkeep
git add -f ./custom/themes/.gitkeep

git commit -m 'Initial commit from template repo.'
