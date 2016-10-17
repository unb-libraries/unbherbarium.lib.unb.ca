<?php

namespace Drupal\unb_herbarium\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure UNB Herbarium filesystem settings for this site.
 */
class HerbariumFilesystemSettingsForm extends ConfigFormBase {

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new HerbariumFilesystemSettingsForm.
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
    return 'unb_herbarium_admin_filesystem';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['unb_herbarium.filesystem'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('unb_herbarium.filesystem');

    $form['filepath'] = array(
      '#type' => 'fieldset',
      '#title' => t('Path'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    );

    $form['filepath']['filepath_archival_masters'] = array(
      '#type' => 'textfield',
      '#title' => t('Path to Archival Masters'),
      '#default_value' => $config->get('filepath.archival_masters'),
      '#description' => t('The full, absolute path to the specimen archival master images, i.e. /data/images/archival'),
    );

    $form['filepath']['filepath_jp2_surrogates'] = array(
      '#type' => 'textfield',
      '#title' => t('Path to JP2 Surrogates'),
      '#default_value' => $config->get('filepath.jp2_surrogates'),
      '#description' => t('The full, absolute path to the JP2 surrogate files, i.e. /data/images/jp2'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('unb_herbarium.filesystem')
      ->set('filepath.archival_masters', $form_state->getValue('filepath_archival_masters'))
      ->save();

    $this->config('unb_herbarium.filesystem')
      ->set('filepath.jp2_surrogates', $form_state->getValue('filepath_jp2_surrogates'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
