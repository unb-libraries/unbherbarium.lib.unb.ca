<?php

namespace Drupal\herbarium_core\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Herbarium filesystem settings for this site.
 */
class HerbariumImageSettingsForm extends ConfigFormBase {

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new HerbariumImageSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state keyvalue collection to use.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state) {
    parent::__construct($config_factory);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'herbarium_core_admin_image';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['herbarium_core.image'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('herbarium_core.image');

    $form['MagickSlicer'] = array(
      '#type' => 'fieldset',
      '#title' => t('Path'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    );

    $form['MagickSlicer']['execution_options'] = array(
      '#type' => 'textfield',
      '#title' => t('MagickSlicker CLI Options'),
      '#default_value' => $config->get('magickslicker.cli_options'),
      '#description' => t('All options to be passed to MagickSlicer when creating the DZI and tiled image'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('herbarium_core.image')
      ->set('magickslicker.cli_options', $form_state->getValue('execution_options'))
      ->save();


    parent::submitForm($form, $form_state);
  }

}
