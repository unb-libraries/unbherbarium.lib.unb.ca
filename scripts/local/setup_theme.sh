#!/usr/bin/env bash
if ls custom/themes* 1> /dev/null 2>&1; then
  cd custom/themes/*/
  ../../../node_modules/.bin/bower install
  npm install
fi
