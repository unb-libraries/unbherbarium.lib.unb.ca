#!/usr/bin/env sh
# Local alterations for your instance.
# i.e. drush --root=${DRUPAL_ROOT} --uri=default --yes en thirty_two_project

# Import content.
if [ "$DEPLOY_ENV" = "local" ]; then
  DRUSH_COMMAND="drush --root=${DRUPAL_ROOT} --uri=default --yes"
  $DRUSH_COMMAND en migrate_plus migrate_source_csv migrate_tools migrate_upgrade
  $DRUSH_COMMAND cache-rebuild

  $DRUSH_COMMAND en --yes unb_herbarium_migrate_csv unb_herbarium
  $DRUSH_COMMAND migrate-status
  $DRUSH_COMMAND migrate-import --all
fi
