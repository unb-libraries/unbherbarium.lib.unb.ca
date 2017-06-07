#!/usr/bin/env sh
# Local alterations for your instance.
# i.e. drush --root=${DRUPAL_ROOT} --uri=default --yes en thirty_two_project

# Import content.
if [ "$DEPLOY_ENV" = "local" ]; then
  DRUSH_COMMAND="drush --root=${DRUPAL_ROOT} --uri=default --yes"
  $DRUSH_COMMAND en migrate_plus migrate_source_csv migrate_tools migrate_upgrade
  $DRUSH_COMMAND cache-rebuild

  $DRUSH_COMMAND en --yes features herbarium_specimen
  $DRUSH_COMMAND en --yes features_ui

  $DRUSH_COMMAND en --yes unb_herbarium_migrate_csv
  $DRUSH_COMMAND migrate-status
  $DRUSH_COMMAND migrate-import herbarium_samples_csv

  echo 'Rebuilding specimen taxonomy terms'
  $DRUSH_COMMAND eval '_herbarium_core_rebuild_specimen_taxonomy_terms();'

  echo 'Enabling UNB herbarium theme'
  $DRUSH_COMMAND en unbherbarium_ca
  $DRUSH_COMMAND config-set system.theme default unbherbarium_ca

  $DRUSH_COMMAND pmu migrate_plus unb_herbarium_migrate_csv migrate_source_csv migrate_tools migrate_upgrade

  echo 'Enabling UNB herbarium module'
  $DRUSH_COMMAND en --yes --skip unb_herbarium
  $DRUSH_COMMAND cache-rebuild
fi
