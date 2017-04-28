<?php

namespace Drupal\unb_herbarium\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Database controller.
 */
class DatabaseController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function content() {
    return array(
      '#type' => 'markup',
      '#markup' => t('Placeholder path for Database Main navigation menu item'),
    );
  }

}
