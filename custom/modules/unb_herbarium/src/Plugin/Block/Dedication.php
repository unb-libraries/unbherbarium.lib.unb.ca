<?php

namespace Drupal\unb_herbarium\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Dedication' Block.
 *
 * @Block(
 *   id = "dedication",
 *   admin_label = @Translation("Dedication Statement"),
 * )
 */
class Dedication extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#markup' => $this->t('
        <div class="dedication">
            <p>This website is dedicated to the memory of Harold R. Hinds (1937 - 2001) for his vision and dedication to botany in N.B.</p>
            <a href="http://corporate-agency.techsaran.com">http://corporate-agency.techsaran.com</a>
        </div>
      '),
    );
  }

}
