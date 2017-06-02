<?php

namespace Drupal\unb_herbarium\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Taxonomy Manager controller.
 */
class ManageTaxonomyController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function content() {
    return array(
      '#type' => 'markup',
      '#markup' => t('Placeholder path for Manage Taxonomy Tools menu item'),
    );
  }

}
