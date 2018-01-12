<?php

/**
 * @file
 * Contains unb_herbarium.install.
 */

use Drupal\taxonomy\Entity\Term;

/**
 * HERB-110 Flatten multi-value fields into new hidden field for Views search.
 */
function unb_herbarium_update_8101() {
  $vocabulary = 'herbarium_specimen_taxonomy';

  $parent_terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadTree(
      $vocabulary,
      $parent = 0,
      $max_depth = 1,
      $load_entities = FALSE
    );

  foreach ($parent_terms as $term) {
    $term->save;
  }

  return t('The paremt taxonomy terms were resaved.');

  throw new UpdateException('Something went wrong!');
}
