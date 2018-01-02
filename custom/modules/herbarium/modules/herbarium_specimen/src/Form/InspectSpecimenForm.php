<?php

namespace Drupal\herbarium_specimen\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * ManageArchivalMasterForm object.
 */
class InspectSpecimenForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'herbarium_specimen_manage_archival_master_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    $form = [];
    $nid = $node;
    $node = Node::load($nid);

    $title = [
      "#type" => "processed_text",
      "#text" => t("High Resolution Image"),
      "#format" => "full_html",
      "#langcode" => "en",
    ];

    $form['sample_view']['title'] = $title;
    $form['sample_view']['title']['#prefix'] = '<h2>';
    $form['sample_view']['title']['#suffix'] = '</h2>';

    $form['sample_view']['zoom'] = [
      '#markup' => '<div id="seadragon-viewer"></div>',
    ];

    $form['#attached'] = [
      'library' => [
        'herbarium_specimen/openseadragon',
        'herbarium_specimen/openseadragon_viewer',
      ],
      'drupalSettings' => [
        'herbarium_specimen' => [
          'dzi_filepath' => "/sites/default/files/dzi/$nid.dzi",
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
