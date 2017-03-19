#!/usr/bin/env bash
CWD=$(pwd)

for THEME_DIR in custom/themes/*/
do
  # Is this a theme that needs building?
  if ls "$THEME_DIR/gulpfile.js" 1> /dev/null 2>&1; then
      cd "$THEME_DIR"
      ../../../node_modules/.bin/bower install
      npm install
      cd "$CWD"
  fi
done
