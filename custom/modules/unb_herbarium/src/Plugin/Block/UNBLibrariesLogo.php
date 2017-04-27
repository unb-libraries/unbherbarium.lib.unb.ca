<?php

namespace Drupal\unb_herbarium\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Dedication' Block.
 *
 * @Block(
 *   id = "unblibrarieslogo",
 *   admin_label = @Translation("UNB Libraries Logo"),
 * )
 */
class UNBLibrariesLogo extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
        <div>
            <img alt="UNB Libraries" class="unb-lib-logo" src="/modules/custom/unb_herbarium/images/UNB-Libraries-Red-Black.png" />
        </div>
      '),
    );
  }

}
