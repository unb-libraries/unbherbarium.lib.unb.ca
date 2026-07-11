#!/usr/bin/env sh
if [ "$LTS_DEPLOY_KEY" != "" ] && [ "$LTS_DEPLOY_PATH" != "" ]; then
  NGINX_USER_HOME='/var/lib/nginx'

  # Setup SSH credentials.
  mkdir -p "${NGINX_USER_HOME}/.ssh"
  chmod 700 "${NGINX_USER_HOME}/.ssh"
  echo "$LTS_DEPLOY_KEY" > "${NGINX_USER_HOME}/.ssh/id_rsa"
  chmod 600 "${NGINX_USER_HOME}/.ssh/id_rsa"
  chown ${NGINX_RUN_USER}:${NGINX_RUN_GROUP} -R "${NGINX_USER_HOME}/.ssh"

  GIT_SSH="ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -i ${NGINX_USER_HOME}/.ssh/id_rsa"

  # Prepare the (now persistent) LFS repo mount.
  mkdir -p "${LTS_DEPLOY_PATH}"
  chown ${NGINX_RUN_USER}:${NGINX_RUN_GROUP} "${LTS_DEPLOY_PATH}"

  # Configure git BEFORE any checkout: dubious-ownership guard + skip-smudge (global),
  # so neither the clone nor a reset --hard ever downloads LFS blobs.
  su - ${NGINX_RUN_USER} -s /bin/sh -c "git config --global --add safe.directory ${LTS_DEPLOY_PATH} && git lfs install --skip-smudge"

  if [ -d "${LTS_DEPLOY_PATH}/.git" ]; then
    # Persistent volume already populated -> incremental refresh, NEVER re-clone.
    # Clear only CLEARLY-STALE locks from a prior crash; leave fresh locks that an
    # overlapping pod may legitimately hold during a rolling deploy.
    find "${LTS_DEPLOY_PATH}/.git" -name '*.lock' -type f -mmin +5 -delete 2>/dev/null
    # Non-fatal: a transient fetch failure must NOT block startup when a good
    # checkout already exists -- the pod comes up on the last-known-good tree.
    su - ${NGINX_RUN_USER} -s /bin/bash -c "cd ${LTS_DEPLOY_PATH} && \
      git remote set-url origin ${LTS_DEPLOY_REPO} && \
      GIT_LFS_SKIP_SMUDGE=1 GIT_SSH_COMMAND=\"${GIT_SSH}\" git fetch --prune origin && \
      if [ \"\$(git rev-parse HEAD)\" != \"\$(git rev-parse origin/master)\" ]; then \
        GIT_LFS_SKIP_SMUDGE=1 git reset --hard origin/master; \
      fi" || echo "WARN: lts-archive refresh failed; continuing on existing checkout"
  else
    # First-time init on an empty volume -> one-time thin clone.
    su - ${NGINX_RUN_USER} -s /bin/bash -c "GIT_LFS_SKIP_SMUDGE=1 GIT_SSH_COMMAND=\"${GIT_SSH}\" /usr/bin/git clone --progress --verbose ${LTS_DEPLOY_REPO} ${LTS_DEPLOY_PATH}"
  fi

  cd ${LTS_DEPLOY_PATH}
  echo -e "[lfs]\n    url = \"http://${LTS_LFS_SERVER_USER}:${LTS_LFS_SERVER_PASS}@${LTS_LFS_SERVER_HOST}:${LTS_LFS_SERVER_PORT}/\"\n" > .lfsconfig
  chown ${NGINX_RUN_USER}:${NGINX_RUN_GROUP} .lfsconfig

  # Ignore .lfsconfig file by default
  echo -e ".lfsconfig\n.gitattributes" > "${NGINX_USER_HOME}/.gitignore"
  chown ${NGINX_RUN_USER}:${NGINX_RUN_GROUP} "${NGINX_USER_HOME}/.gitignore"
  su - ${NGINX_RUN_USER} -s /bin/sh -c "git config --global core.excludesfile ~/.gitignore"

  # Ensure PHP has access to these variables for testing.
  sed -i "s|LTS_SERVER_HOST|$LTS_LFS_SERVER_HOST|g" "$NGINX_APP_CONF_FILE"
  sed -i "s|LTS_SERVER_PORT|$LTS_LFS_SERVER_PORT|g" "$NGINX_APP_CONF_FILE"
fi
