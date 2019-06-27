FROM unblibraries/drupal:8.x-1.x
MAINTAINER UNB Libraries <libsupport@unb.ca>

LABEL name="nbbib.lib.unb.ca"
LABEL vcs-ref=""
LABEL vcs-url="https://github.com/unb-libraries/nbbib.lib.unb.ca"

ENV DRUPAL_SITE_ID unbherb
ENV DRUPAL_SITE_URI unbherbarium.lib.unb.ca
ENV DRUPAL_SITE_UUID 85c96bf2-f1b6-4612-8305-d3d3769d5255

ENV DRUPAL_PRIVATE_FILE_PATH /app/private_filesystem
ENV GIT_LFS_VERSION 2.2.0

# Deploy upstream scripts, and then override with any local.
RUN curl -sSL https://raw.githubusercontent.com/unb-libraries/CargoDock/drupal-8.x-1.x/container/deploy.sh | sh
COPY ./scripts/container /scripts

# Add additional OS packages.
ENV ADDITIONAL_OS_PACKAGES tiff-dev tiff postfix imagemagick bash rsyslog openssh-client
RUN /scripts/addOsPackages.sh && \
  /scripts/initRsyslog.sh && \
  curl -O https://raw.githubusercontent.com/VoidVolker/MagickSlicer/master/magick-slicer.sh && \
  mv magick-slicer.sh /usr/local/bin/magick-slicer && \
  chmod +x /usr/local/bin/magick-slicer && \
  echo "TLS_REQCERT never" > /etc/openldap/ldap.conf && \
  /scripts/InstallGitLFS.sh

# Add package conf.
COPY ./package-conf /package-conf
RUN /scripts/setupStandardConf.sh

# Build the contrib Drupal tree.
ARG COMPOSER_DEPLOY_DEV=no-dev
ENV DRUPAL_BASE_PROFILE minimal
ENV DRUPAL_BUILD_TMPROOT ${TMP_DRUPAL_BUILD_DIR}/webroot

COPY ./build/ ${TMP_DRUPAL_BUILD_DIR}
RUN /scripts/build.sh ${COMPOSER_DEPLOY_DEV} ${DRUPAL_BASE_PROFILE}

# Deploy repo assets.
COPY ./tests/ ${DRUPAL_TESTING_ROOT}/
COPY ./config-yml ${TMP_DRUPAL_BUILD_DIR}/config-yml
COPY ./custom/themes ${TMP_DRUPAL_BUILD_DIR}/custom_themes
COPY ./custom/modules ${TMP_DRUPAL_BUILD_DIR}/custom_modules

# Universal environment variables.
ENV DEPLOY_ENV prod
ENV DRUPAL_DEPLOY_CONFIGURATION TRUE
ENV DRUPAL_CONFIGURATION_EXPORT_SKIP devel
