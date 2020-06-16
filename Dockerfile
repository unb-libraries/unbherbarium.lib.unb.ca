FROM unblibraries/drupal:dockworker-2.x
MAINTAINER UNB Libraries <libsupport@unb.ca>

ARG COMPOSER_DEPLOY_DEV=no-dev
ENV DRUPAL_SITE_ID unbherb
ENV DRUPAL_SITE_URI unbherbarium.lib.unb.ca
ENV DRUPAL_SITE_UUID 85c96bf2-f1b6-4612-8305-d3d3769d5255

ENV DRUPAL_CHOWN_PUBLIC_FILES_STARTUP FALSE
ENV DRUPAL_PRIVATE_FILE_PATH /app/private_filesystem
ENV GIT_LFS_VERSION 2.7.2

# Override upstream scripts with those from this repository.
COPY ./scripts/container /scripts

# Install additional OS packages.
ENV ADDITIONAL_OS_PACKAGES tiff-dev tiff postfix imagemagick bash rsyslog openssh-client php7-redis
RUN /scripts/addOsPackages.sh && \
  /scripts/initRsyslog.sh

# Add package configuration, build webtree.
COPY ./package-conf /package-conf
RUN /scripts/setupStandardConf.sh && \
  curl -O https://raw.githubusercontent.com/VoidVolker/MagickSlicer/master/magick-slicer.sh && \
  mv magick-slicer.sh /usr/local/bin/magick-slicer && \
  chmod +x /usr/local/bin/magick-slicer && \
  /scripts/InstallGitLFS.sh
COPY ./build /build
RUN /scripts/build.sh ${COMPOSER_DEPLOY_DEV} ${DRUPAL_BASE_PROFILE}

# Deploy repository assets.
COPY ./tests/ ${DRUPAL_TESTING_ROOT}/
COPY ./config-yml ${DRUPAL_CONFIGURATION_DIR}
COPY ./custom/themes ${DRUPAL_ROOT}/themes/custom
COPY ./custom/modules ${DRUPAL_ROOT}/modules/custom

# Metadata
ARG BUILD_DATE
ARG VCS_REF
ARG VERSION
LABEL ca.unb.lib.generator="drupal8" \
      com.microscaling.docker.dockerfile="/Dockerfile" \
      com.microscaling.license="MIT" \
      org.label-schema.build-date=$BUILD_DATE \
      org.label-schema.description="unbherbarium.lib.unb.ca provides a searchable database of the vascular plant collections maintained at the  Connell Memorial Herbarium, and in-depth information about its policies, facilities and collections." \
      org.label-schema.name="unbherbarium.lib.unb.ca" \
      org.label-schema.schema-version="1.0" \
      org.label-schema.url="https://unbherbarium.lib.unb.ca" \
      org.label-schema.vcs-ref=$VCS_REF \
      org.label-schema.vcs-url="https://github.com/unb-libraries/unbherbarium.lib.unb.ca" \
      org.label-schema.vendor="University of New Brunswick Libraries" \
      org.label-schema.version=$VERSION
