#!/usr/bin/env sh
if [ "$LTS_DEPLOY_KEY" != "" ] && [ "$LTS_DEPLOY_PATH" != "" ]; then
  NGINX_USER_HOME='/var/lib/nginx'

  # Setup SSH credentials.
  mkdir -p "${NGINX_USER_HOME}/.ssh"
  chmod 700 "${NGINX_USER_HOME}/.ssh"
  echo "$LTS_DEPLOY_KEY" > "${NGINX_USER_HOME}/.ssh/id_rsa"
  chmod 600 "${NGINX_USER_HOME}/.ssh/id_rsa"
  chown ${NGINX_RUN_USER}:${NGINX_RUN_GROUP} -R "${NGINX_USER_HOME}/.ssh"

  # Clone LFS repo.
  mkdir -p ${LTS_DEPLOY_PATH}
  chown ${NGINX_RUN_USER}:${NGINX_RUN_GROUP} ${LTS_DEPLOY_PATH}
  su - ${NGINX_RUN_USER} -s /bin/bash -c "GIT_SSH_COMMAND=\"ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -i ${NGINX_USER_HOME}/.ssh/id_rsa \" /usr/bin/git clone ${LTS_DEPLOY_REPO} ${LTS_DEPLOY_PATH}"
  cd ${LTS_DEPLOY_PATH}
  echo -e "[lfs]\n    url = \"http://${LTS_LFS_SERVER_USER}:${LTS_LFS_SERVER_PASS}@${LTS_LFS_SERVER_HOST}:${LTS_LFS_SERVER_PORT}/\"\n" > .lfsconfig
  chown ${NGINX_RUN_USER}:${NGINX_RUN_GROUP} .lfsconfig

  #  Setup local LFS and track tif without smudge on clone, saving space.
  su - ${NGINX_RUN_USER} -s /bin/sh -c "git lfs install --skip-smudge"

  # Ignore .lfsconfig file by default
  echo -e ".lfsconfig\n.gitattributes" > "${NGINX_USER_HOME}/.gitignore"
  chown ${NGINX_RUN_USER}:${NGINX_RUN_GROUP} "${NGINX_USER_HOME}/.gitignore"
  su - ${NGINX_RUN_USER} -s /bin/sh -c "git config --global core.excludesfile ~/.gitignore"

  # Ensure PHP has access to these variables for testing.
  sed -i "s|LTS_SERVER_HOST|$LTS_LFS_SERVER_HOST|g" /etc/nginx/conf.d/zz_app.conf
  sed -i "s|LTS_SERVER_PORT|$LTS_LFS_SERVER_PORT|g" /etc/nginx/conf.d/zz_app.conf
fi
