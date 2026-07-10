FROM ghcr.io/unb-libraries/drupal:11.x-1.x-unblib

# Install additional OS packages.
ENV ADDITIONAL_OS_PACKAGES="tiff-dev tiff postfix imagemagick bash openssh-client php${PHP_VERSION}-pecl-redis"
ENV DRUPAL_SITE_ID="unbherb"
ENV DRUPAL_SITE_URI="unbherbarium.lib.unb.ca"
ENV DRUPAL_SITE_UUID="85c96bf2-f1b6-4612-8305-d3d3769d5255"

ENV DRUPAL_PRIVATE_FILE_PATH="/app/private_filesystem"
ENV GIT_LFS_VERSION="2.7.2"

# Build application.
COPY ./build/ /build/
RUN ${RSYNC_MOVE} /build/scripts/container/ /scripts/ && \
  /scripts/addOsPackages.sh && \
  /scripts/setupStandardConf.sh && \
  curl -O https://raw.githubusercontent.com/VoidVolker/MagickSlicer/master/magick-slicer.sh && \
  mv magick-slicer.sh /usr/local/bin/magick-slicer && \
  chmod +x /usr/local/bin/magick-slicer && \
  /scripts/InstallGitLFS.sh && \
  /scripts/build.sh

# Deploy configuration.
COPY ./configuration ${DRUPAL_CONFIGURATION_DIR}
RUN /scripts/pre-init.d/72_secure_config_sync_dir.sh

# Deploy custom modules, themes.
COPY ./custom/themes ${DRUPAL_ROOT}/themes/custom
COPY ./custom/modules ${DRUPAL_ROOT}/modules/custom

# Container metadata.
ARG BUILD_DATE
ARG VCS_REF
ARG VERSION
LABEL ca.unb.lib.generator="drupal11" \
  org.opencontainers.image.title="unbherbarium.lib.unb.ca" \
  org.opencontainers.image.description="unbherbarium.lib.unb.ca provides a searchable database of the vascular plant collections maintained at the  Connell Memorial Herbarium, and in-depth information about its policies, facilities and collections." \
  org.opencontainers.image.vendor="University of New Brunswick Libraries" \
  org.opencontainers.image.authors="UNB Libraries <libsupport@unb.ca>" \
  org.opencontainers.image.url="https://unbherbarium.lib.unb.ca" \
  org.opencontainers.image.source="https://github.com/unb-libraries/unbherbarium.lib.unb.ca" \
  org.opencontainers.image.version="$VERSION" \
  org.opencontainers.image.revision="$VCS_REF" \
  org.opencontainers.image.created="$BUILD_DATE"
