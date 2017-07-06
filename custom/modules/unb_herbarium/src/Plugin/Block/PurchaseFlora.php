<?php

namespace Drupal\unb_herbarium\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Dedication' Block.
 *
 * @Block(
 *   id = "purchaseflora",
 *   admin_label = @Translation("Purchase Floar of NB"),
 * )
 */
class PurchaseFlora extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $array = [
      '#markup' => $this->t('
        <div>
            <a href="/"><img alt="Flora of NB" class="flora-nb-block-thumb" src="//www.unbherbarium.ca/sites/default/files/floracover_norm.gif" />Purchase the <cite>Flora of New Brunswick</cite></a>
        </div>

      '),
    ];
    return $array;
  }

}
