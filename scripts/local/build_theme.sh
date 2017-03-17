#!/usr/bin/env bash
if ls custom/themes* 1> /dev/null 2>&1; then
  if ! ls custom/themes/*/bower_components 1> /dev/null 2>&1; then
     scripts/local/setup_theme.sh
  fi

  cd custom/themes/*/
  gulp
fi
