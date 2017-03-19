#!/usr/bin/env bash
CWD=$(pwd)

for THEME_DIR in custom/themes/*/
do
  # Is this a theme that needs building?
  if ls "$THEME_DIR/gulpfile.js" 1> /dev/null 2>&1; then
    # Have the themes been setup yet?
    if ! ls "$THEME_DIR/bower_components" 1> /dev/null 2>&1; then
      scripts/local/setup_themes.sh
    fi

    # Build this theme.
    cd "$THEME_DIR"
    node_modules/gulp/bin/gulp.js
    cd "$CWD"
  fi
done
