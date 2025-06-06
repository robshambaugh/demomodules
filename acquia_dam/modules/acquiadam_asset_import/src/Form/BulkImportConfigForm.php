<?php

declare(strict_types=1);

namespace Drupal\acquiadam_asset_import\Form;

use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media\MediaTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Acquia DAM asset import settings.
 */
class BulkImportConfigForm extends ConfigFormBase {

  /**
   * Acquia DAM client on behalf of the current user.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  protected $client;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Acquia DAM logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The current active user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->client = $container->get('acquia_dam.client.factory');
    $instance->messenger = $container->get('messenger');
    $instance->logger = $container->get('logger.channel.acquia_dam');
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentRouteMatch = $container->get('current_route_match');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquiadam_asset_import_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['acquiadam_asset_import.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Wrap entire form into single container.
    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'form-table-wrapper'],
    ];
    $form['wrapper']['source'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Assets to import from'),
    ];

    $source_opts = [
      'categories' => $this->t('Categories'),
      'asset_groups' => $this->t('Asset Groups'),
    ];
    $selected_source_type = $form_state->getValue('source_type', '');
    $form['wrapper']['source']['source_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Source type'),
      '#description' => $this->t('Asset source type. Either category or asset group'),
      '#options' => $source_opts,
      '#empty_value' => 'none',
      '#empty_option' => $this->t('Please choose one...'),
      '#ajax' => [
        'callback' => '::updateGroupContainer',
        'wrapper' => 'source-type-container',
      ],
    ];
    // The media bundle list container.
    $form['wrapper']['source']['group'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'source-type-container'],
    ];
    $selected_data = $this->getSelectedData($form_state);
    $asset_options = $this->getCachedOptions($form_state, 'asset_options');
    if ($selected_source_type && $selected_source_type !== "none") {
      $source_options[$selected_source_type] = $this->getCachedOptions($form_state, "source_options_{$selected_source_type}", $selected_source_type);
      $source_uuid = "{$selected_source_type}_uuid";
      $selected_category = $form_state->getValue($source_uuid, '');
      $asset_descriptions = $this->getCachedOptions($form_state, 'asset_descriptions');
      // Start building up the form.
      $form['wrapper']['source']['group'][$source_uuid] = [
        '#type' => 'select',
        '#title' => $this->t('Source :source', [':source' => $source_opts[$selected_source_type]]),
        '#description' => $this->t('List of :source in the remote DAM system available for the authorized user account. Please choose which of them the media assets should be imported from.', [
          ':source' => $source_opts[$selected_source_type],
        ]),
        '#options' => $source_options[$selected_source_type],
        '#empty_option' => $this->t('Please choose one…'),
        '#states' => [
          'visible' => [
            ':input[name="source_type"]' => ['value' => $selected_source_type],
          ],
        ],
        '#empty_value' => 'none',
        '#sort_options' => TRUE,
        '#ajax' => [
          'callback' => '::updateAssetsContainer',
          'wrapper' => 'media-bundles-container',
        ],
      ];

      // The media bundle list container.
      $form['wrapper']['source']['group']['assets'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'media-bundles-container'],
      ];

      if ($selected_category && $selected_category !== "none") {
        $selected_options = $selected_data[$selected_source_type][$selected_category] ?? [];
        $selected_options = array_values($selected_options);
        $form['wrapper']['source']['group']['assets']['enable_filter'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Filter assets'),
          '#description' => $this->t('Filter assets based on media type.'),
          '#default_value' => $form_state->get('enable_filter') ?? FALSE,
        ];
        $form['wrapper']['source']['group']['assets']['media_bundles'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Import only assets which would be assigned to these media types'),
          '#sort_options' => TRUE,
          '#multiple' => FALSE,
          '#options' => $asset_options,
          '#default_value' => $selected_options,
          '#states' => [
            'visible' => [
              ':input[name="enable_filter"]' => ['checked' => TRUE],
            ],
          ],
          ...$asset_descriptions,
        ];

        $form['wrapper']['source']['group']['assets']['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => isset($selected_data[$selected_source_type][$selected_category]) ? 'Update' : '+ Add',
          '#submit' => ['::saveCategoryForImport'],
          '#ajax' => [
            'callback' => '::resetFormValues',
            'wrapper' => 'form-table-wrapper',
          ],
        ];
      }
    }

    // Table of selected categories.
    $form['wrapper']['selected_table'] = [
      '#type' => 'table',
      '#header' => [
        'source' => $this->t('From the source…'),
        'asset_info' => $this->t('From these categories or asset groups…'),
        'media_bundles' => $this->t('…only these type of media'),
        'remove_button' => '',
      ],
      '#attributes' => ['id' => 'selected-data-table'],
      '#empty' => $this->t('No category or asset group has been selected yet.'),
    ];

    $form['wrapper']['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
        '#disabled' => $this->saveButtonVisibility($selected_data),
      ],
      'cancel' => [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => Url::fromRoute($this->currentRouteMatch->getRouteName()),
        '#attributes' => [
          'class' => ['button', 'button--danger'],
        ],
      ],
    ];

    foreach ($selected_data as $source => $import_details) {
      if ($source == '_core') {
        continue;
      }
      $source_options[$source] = $this->getCachedOptions($form_state, "source_options_{$source}", $source);
      foreach ($import_details as $category => $media_bundles) {
        $media_bundles = $media_bundles ?? [];
        $form['wrapper']['selected_table'][] = [
          'source' => ['#markup' => $source_opts[$source] ?? ''],
          'asset_info' => ['#markup' => $source_options[$source][$category] ?? ''],
          'media_bundles' => $this->renderMediaBundles($media_bundles, $asset_options),
          'remove_button' => [
            'data' => [
              '#type' => 'submit',
              '#value' => $this->t('Remove'),
              '#name' => 'delete_' . $category,
              '#submit' => ['::removeCategoryFromImport'],
              '#limit_validation_errors' => [
                ['selected_table'],
              ],
              '#ajax' => [
                'callback' => '::resetFormValues',
                'wrapper' => 'form-table-wrapper',
                'disable-refocus' => TRUE,
              ],
              '#attributes' => [
                'class' => ['button--danger', 'js-form-submit', 'use-ajax'],
                'data-disable-refocus' => 'true',
                'source-type' => $source,
              ],
            ],
          ],
        ];
      }
    }
    return $form;
  }

  /**
   * AJAX callback to load media_bundles and show Add/Update button.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function updateGroupContainer(array &$form, FormStateInterface $form_state): array {
    $selected_data = array_filter($this->getSelectedData($form_state));
    $source_type = $form_state->getValue('source_type');
    $source_uuid = "{$source_type}_uuid";
    foreach ($form['wrapper']['source']['source_type']['#options'] as $key => $label) {
      $form['wrapper']['source']['group'][$key]['#access'] = FALSE;
    }
    $form['wrapper']['source']['group'][$source_uuid]['#access'] = TRUE;
    return $form['wrapper']['source']['group'];
  }

  /**
   * AJAX callback to load media_bundles and show Add/Update button.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function updateAssetsContainer(array &$form, FormStateInterface $form_state): array {
    foreach ($form['wrapper']['source']['group']['assets']['media_bundles']['#options'] as $key => $label) {
      $form['wrapper']['source']['group']['assets']['media_bundles'][$key]['#checked'] = FALSE;
    }
    $selected_data = array_filter($this->getSelectedData($form_state));
    $source_type = $form_state->getValue('source_type');
    $selected_asset = $form_state->getValue("{$source_type}_uuid") ?? NULL;
    $selected_options = !empty($selected_data[$source_type][$selected_asset]) ? array_values($selected_data[$source_type][$selected_asset]) : [];
    if ($selected_options) {
      foreach ($form['wrapper']['source']['group']['assets']['media_bundles']['#default_value'] as $value) {
        $form['wrapper']['source']['group']['assets']['media_bundles'][$value]['#attributes']['checked'] = 'checked';
      }
    }
    $form['wrapper']['source']['group']['assets']['enable_filter']['#checked'] = $selected_options;
    return $form['wrapper']['source']['group']['assets'];
  }

  /**
   * AJAX callback to reset form values.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function resetFormValues(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element['#name'] != 'source_type') {
      $form['wrapper']['source']['source_type']['#value'] = 'none';
    }
    $form['wrapper']['source']['categories_uuid']['#value'] = 'none';
    $form['wrapper']['source']['asset_groups_uuid']['#value'] = 'none';
    $form['wrapper']['source']['group']['assets']['enable_filter']['#access'] = FALSE;
    $form['wrapper']['source']['group']['assets']['media_bundles']['#access'] = FALSE;
    return $form['wrapper'];
  }

  /**
   * Renders the media list in table.
   *
   * @param array $media_bundles
   *   The media bundles to render.
   * @param array $asset_options
   *   The asset options.
   */
  private function renderMediaBundles(array $media_bundles, array $asset_options): array {
    if (empty($media_bundles)) {
      return [
        '#markup' => $this->t('All assets (no filtering)'),
      ];
    }
    $items = [];
    foreach ($media_bundles as $subcategory) {
      $items[] = $asset_options[$subcategory];
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
  }

  /**
   * Saves the selected data and media bundles for import.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function saveCategoryForImport(array &$form, FormStateInterface $form_state): void {
    $selected_source = $form_state->getValue('source_type');
    $selected_cat_group = $form_state->getValue("{$selected_source}_uuid");
    $selected_media_bundles = array_filter($form_state->getValue('media_bundles', []));
    $selected_data = $this->getSelectedData($form_state);
    if ($selected_cat_group) {
      $selected_data[$selected_source][$selected_cat_group] = !empty($selected_media_bundles) ? $selected_media_bundles : [];
      $form_state->set('selected_data', $selected_data);
    }
    $form_state->set('enable_filter', FALSE);
    $form_state->setValue('categories_uuid', "none");
    $form_state->setValue('asset_groups_uuid', "none");
    $form_state->setRebuild();
  }

  /**
   * Removes a row from the selected data list.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function removeCategoryFromImport(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    if (!empty($triggering_element['#name'])) {
      $item_to_delete = str_replace('delete_', '', $triggering_element['#name']);
      $selected_data = $this->getSelectedData($form_state);
      $source_type = $triggering_element['#attributes']['source-type'];
      // Remove the row from stored data.
      unset($selected_data[$source_type][$item_to_delete]);
      $form_state->set('selected_data', $selected_data);
    }
    $form_state->setRebuild();
    return $form['wrapper']['selected_table'];
  }

  /**
   * Returns the selected data from the form state.
   */
  private function getSelectedData($form_state): array {
    if (!$form_state->has('selected_data')) {
      $form_state->set('selected_data', $this->loadConfig()->get() ?? []);
    }
    return $form_state->get('selected_data');
  }

  /**
   * Returns the config import settings configuration object.
   */
  private function loadConfig(): Config {
    return $this->config($this->getEditableConfigNames()[0]);
  }

  /**
   * Fetches categories from the remote DAM service.
   *
   * @param string $source
   *   The source type.
   */
  private function fetchSourceOptions(string $source): ?array {
    try {
      if ($source == 'categories') {
        $response = $this->client->getUserClient()->getCategories();
      }
      elseif ($source === 'asset_groups') {
        $response = $this->client->getUserClient()->getAssetGroups();
      }
    }
    catch (\Exception $exception) {
      $this->messenger->addWarning('Something went wrong while gathering categories from the remote DAM service. Please contact the site administrator.');
      $this->logger->error($exception->getMessage());
      return NULL;
    }
    if (empty($response['total_count'])) {
      return [];
    }
    return isset($response['items']) ? array_column($response['items'], 'name', ($source == 'categories') ? 'id' : 'uuid') : [];
  }

  /**
   * Returns the list of acquia dam asset.
   *
   * @param bool $needs_description
   *   Whether to include descriptions in the options.
   */
  private function getAssetOptions(bool $needs_description = FALSE): array {
    $media_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
    $media_types = array_filter($media_types, static fn(MediaTypeInterface $media_type) => $media_type->getSource() instanceof Asset);
    return array_map(fn($obj) => ($needs_description ? ['#description' => $obj->get('description')] : $obj->label()), $media_types);
  }

  /**
   * Returns the cached options if available, otherwise fetches & cache them.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Given form state.
   * @param string $key
   *   Given cache key.
   */
  private function getCachedOptions(FormStateInterface $form_state, string $key, string $source = ''): ?array {
    if (!$form_state->has($key)) {
      match ($key) {
        "source_options_{$source}" => $data = $this->fetchSourceOptions($source),
        'asset_options' => $data = $this->getAssetOptions(),
        'asset_descriptions' => $data = $this->getAssetOptions(TRUE),
      };
      if (isset($data)) {
        $form_state->set($key, $data);
      }
    }
    return $form_state->get($key);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->loadConfig()
      ->set('categories', $form_state->get('selected_data')['categories'] ?? [])
      ->set('asset_groups', $form_state->get('selected_data')['asset_groups'] ?? [])
      ->save();
    $this->messenger->addStatus($this->t('The configuration settings have been successfully saved.'));
  }

  /**
   * Helper method to determine save button status.
   *
   * If no category or asset group is selected then disbaled otherwise enabled.
   *
   * @param array $selected_data
   *  The selected data.
   *
   * @return bool
   *  Whether to show the save button.
   */
  protected function saveButtonVisibility(array $selected_data): bool {
    return empty($selected_data['categories'] ?? []) &&
      empty($selected_data['asset_groups'] ?? []) &&
      empty($this->loadConfig()->get('categories')) &&
      empty($this->loadConfig()->get('asset_groups'));
  }

}
