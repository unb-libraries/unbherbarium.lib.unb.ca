#!/usr/bin/env sh
DRUSH_COMMAND="drush --root=${DRUPAL_ROOT} --uri=default --yes"

# Import content.
if [ "$DEPLOY_ENV" = "local" ]; then
  $DRUSH_COMMAND en migrate_plus migrate_source_csv migrate_tools migrate_upgrade
  $DRUSH_COMMAND cache-rebuild

  # $DRUSH_COMMAND en --yes features herbarium_specimen herbarium_specimen_lts
  # $DRUSH_COMMAND en --yes features_ui

  $DRUSH_COMMAND en --yes unb_herbarium_migrate_csv
  $DRUSH_COMMAND migrate-status
  $DRUSH_COMMAND migrate-import herbarium_samples_csv --limit=50

  echo 'Rebuilding specimen taxonomy terms'
  $DRUSH_COMMAND eval '_herbarium_core_rebuild_specimen_taxonomy_terms();'

  echo 'Enabling UNB herbarium theme'
  # $DRUSH_COMMAND en unbherbarium_ca
  # $DRUSH_COMMAND config-set system.theme default unbherbarium_ca

  $DRUSH_COMMAND pmu unb_herbarium_migrate_csv

  echo 'Enabling UNB herbarium module'
  # $DRUSH_COMMAND en --yes --skip unb_herbarium
  $DRUSH_COMMAND cache-rebuild
fi
