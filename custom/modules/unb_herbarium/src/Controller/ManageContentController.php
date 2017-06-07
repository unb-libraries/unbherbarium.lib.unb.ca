<?php

namespace Drupal\unb_herbarium\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Content/Taxonomy Manager controller.
 */
class ManageContentController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function content() {
    return array(
      '#type' => 'markup',
      '#markup' => t('Placeholder path for Tools > Manage menu item'),
    );
  }

}
