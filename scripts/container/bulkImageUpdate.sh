#!/usr/bin/env sh
cd /lts-archive/
GIT_FILE_LIST=$(git ls-tree -r master --name-only)
FILE_EXTENSION='tif'
DZI_PATH='/app/html/sites/default/files/dzi'
NUM_TO_OUTPUT='2'

OUTPUT_COUNT=0
echo "$GIT_FILE_LIST" | while read -r a; do

  if [[ "${a/$FILE_EXTENSION}" != "$a" ]]; then
    NODE_ID=$(echo $a | cut -f 1 -d '.')
    if [[ ! -f "$DZI_PATH/$NODE_ID.dzi" ]]; then
      echo "$NODE_ID"
      su -l -s /bin/sh nginx -c "drush --root=/app/html -u 1 regenerate-surrogates $NODE_ID" &
      sleep 2
      OUTPUT_COUNT=$((OUTPUT_COUNT+1))
    fi
  fi

  if [[ "$OUTPUT_COUNT" -ge "$NUM_TO_OUTPUT" ]]; then
    exit 0
  fi
done
