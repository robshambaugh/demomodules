<?php

namespace Drupal\acquia_dam\Form;

use Drupal\acquia_dam\EmbedCodeFactory;
use Drupal\acquia_dam\ImageStyleHelper;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Drupal\media_library\MediaLibraryState;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Embed selection form for the assets in CKEditor Media library plugin.
 */
class EmbedSelectForm extends FormBase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Acquia DAM image style helper.
   *
   * @var \Drupal\acquia_dam\ImageStyleHelper
   */
  protected $imageStyleHelper;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Plugin manager for field types.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * Plugin manager for field formatters.
   *
   * @var \Drupal\Core\Field\FormatterPluginManager
   */
  protected $fieldFormatterManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The EmbedSelectForm constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\acquia_dam\ImageStyleHelper $imageStyleHelper
   *   Acquia DAM image style helper.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $fieldTypeManager
   *   Plugin manager for field types.
   * @param \Drupal\Core\Field\FormatterPluginManager $fieldFormatterManager
   *   Plugin manager for field formatters.
   * @param \Drupal\Core\Messenger\MessengerInterface
   *   The messenger service.
   */
  public function __construct(RequestStack $requestStack, ImageStyleHelper $imageStyleHelper, EntityTypeManagerInterface $entityTypeManager, FieldTypePluginManagerInterface $fieldTypeManager, FormatterPluginManager $fieldFormatterManager, MessengerInterface $messenger) {
    $this->requestStack = $requestStack;
    $this->imageStyleHelper = $imageStyleHelper;
    $this->entityTypeManager = $entityTypeManager;
    $this->fieldTypeManager = $fieldTypeManager;
    $this->fieldFormatterManager = $fieldFormatterManager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('acquia_dam.image_style_support'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('plugin.manager.field.formatter'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'acquia_dam_embed_select_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $asset_type = '', string $selected_ids = '') {
    $selected_ids = explode(',', $selected_ids);
    $media = $this->entityTypeManager->getStorage('media')->loadMultiple($selected_ids);

    // @todo static::ajaxSubmit() requires data-drupal-selector to be the same
    //   between the various Ajax requests. A bug in
    //   \Drupal\Core\Form\FormBuilder prevents that from happening unless
    //   $form['#id'] is also the same. Normally, #id is set to a unique HTML
    //   ID via Html::getUniqueId(), but here we bypass that in order to work
    //   around the data-drupal-selector bug. This is okay so long as we
    //   assume that this form only ever occurs once on a page. Remove this
    //   workaround in https://www.drupal.org/node/2897377.
    $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);

    // Placeholder for AJAX message.
    $form['ajax_message'] = [
      '#type' => 'markup',
      '#markup' => '<div id="ajax-message"></div>',
    ];
    foreach ($media as $media_item) {
      assert($media_item instanceof MediaInterface);
      $form['#theme'] = ['acquia_dam_embed_select_form'];
      $form['thumbnail'] = $media_item->get('acquia_dam_asset_id')->view([
        'settings' => [
          'thumbnail_width' => 300,
        ],
      ]);
      $form['thumbnail']['#title'] = $media_item->getName();

      /** @var \Drupal\acquia_dam\Plugin\media\Source\Asset $asset */
      $asset = $media_item->getSource();

      // @todo Ajax part of the form element is still under development.
      $embed_options = EmbedCodeFactory::getSelectOptions($asset->getDerivativeId());

      $form['embed_code'] = [
        '#type' => 'radios',
        '#title' => $this->t('Embed code'),
        '#description' => $this->t('Your media will automatically update on this page if the files is updated in the DAM.'),
        '#description_display' => 'before',
        '#options' => $embed_options,
        '#attributes' => [
          'class' => [
            'form-radios-embed',
          ],
        ],
        '#ajax' => [
          'callback' => '::checkFocalPointUsage',
          'url' => Url::fromRoute('acquia_dam.add_embed', [
            'asset_type' => $asset_type,
            'selected_ids' => implode(',', $selected_ids),
          ]),
          'options' => [
            'query' => $this->requestStack->getCurrentRequest()->query->all(),
          ],

          'disable-refocus' => TRUE,
          'event' => 'change',
          'wrapper' => $form['#id'],
        ],
        '#required' => TRUE,
      ];

      // If the universal 'remotely referenced thumbnail image' option is the
      // default embed style of this formatter used for this field type, then
      // mark its radio button as selected.
      $field_definition = $this->fieldTypeManager->getDefinition('acquia_dam_asset');

      if (isset($field_definition['default_formatter'])) {
        $default_formatter_settings = $this->fieldFormatterManager->getDefaultSettings('acquia_dam_embed_code');

        if (isset($default_formatter_settings['embed_style'])) {
          $default_embed_style = $default_formatter_settings['embed_style'];
          $remote_thumbnail_embed_style_name = 'remotely_referenced_thumbnail_image';

          if ($default_embed_style === $remote_thumbnail_embed_style_name && isset($embed_options[$remote_thumbnail_embed_style_name])) {
            $form['embed_code']['#default_value'] = $remote_thumbnail_embed_style_name;
          }
        }
      }

      if ($media_item->bundle() === 'acquia_dam_image_asset') {
        if (count($embed_options) > 5) {
          $form['embed_code']['#type'] = 'select';
        }

        $form['versioning'] = [
          '#type' => 'radios',
          '#title' => $this->t('Version'),
          '#description' => $this->t('Select to automatically update media as versions are added in DAM or manually update when you know a new version is availabe in the DAM.'),
          '#description_display' => 'before',
          '#default_value' => 'auto',
          '#options' => [
            'auto' => $this->t('Automatically update'),
            'manual' => $this->t('Manually update'),
          ],
          '#attributes' => [
            'class' => [
              'form-radios-version',
            ],
          ],
        ];

        $embed_code = NestedArray::getValue($form_state->getUserInput(), ['embed_code']);
        if ($embed_code && $this->imageStyleHelper->getCropTypeOfFocalPointEffect($embed_code)) {
          /** @var \Drupal\acquia_dam\Plugin\media\Source\Asset $asset */
          $asset = $media_item->getSource();

          [$asset_id, $version_id] = array_values($asset->getSourceFieldValue($media_item));

          if ($this->entityTypeManager
            ->getStorage('crop')
            ->getQuery()
            ->accessCheck()
            ->condition(
              'uri',
              $this->imageStyleHelper->buildUriForCrop($asset_id, $version_id, $embed_code)
            )
            ->count()
            ->execute()) {
            $form['focal_point'] = [
              '#markup' => '<p>There is already a cropped image for the given asset.</p>',
            ];
          }
          else {
            $thumbnail_uri = $media_item->getSource()->getMetadata($media_item, 'thumbnail_uri');
            $form['focal_point'] = [
              '#type' => 'acquia_dam_focal_point',
              '#title' => $this->t('Focal point selection'),
              '#description' => $this->t('Select the point you would like to crop your media from.'),
              '#description_display' => 'before',
              '#thumbnail_uri' => $thumbnail_uri,
              '#style_name' => 'media_library',
            ];
          }
        }
      }

    }
    $form['selected_id'] = [
      '#type' => 'value',
      '#value' => $selected_ids,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Insert selected'),
      '#ajax' => [
        'url' => Url::fromRoute('acquia_dam.add_embed', [
          'asset_type' => $asset_type,
          'selected_ids' => implode(',', $selected_ids),
        ]),
        'options' => [
          'query' => $this->requestStack->getCurrentRequest()->query->all(),
        ],
        'callback' => '::submitEmbedFormAjax',
        'wrapper' => 'ajax-message',
        'disable-refocus' => TRUE,
      ],
    ];

    $form['#attached']['library'][] = 'acquia_dam/acquia_dam.embed_form.style';
    return $form;
  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitEmbedFormAjax(array $form, FormStateInterface $form_state) {
    $form_state->setValidationComplete(FALSE);
    $this->validateForm($form, $form_state);

    // If there are validation errors, rebuild the form and stop processing.
    if ($form_state->getErrors()) {
      $messages = [];
      // Get all error messages from the Messenger service.
      foreach ($this->messenger->all() as $type => $type_messages) {
        foreach ($type_messages as $message) {
          $messages[] = '<div class="messages messages--' . $type . '">' . $message . '</div>';
        }
      }
      $this->messenger->deleteAll();
      return (new AjaxResponse())->addCommand(
        new HtmlCommand('#ajax-message', implode('', $messages))
      );
    }
    $request = \Drupal::requestStack()->getCurrentRequest();
    $state = MediaLibraryState::fromRequest($request);
    $selected_ids = $form_state->getValue('selected_id');
    $state->set('embed_code', $form_state->getValue('embed_code'));
    $state->set('versioning', $form_state->getValue('versioning'));
    $state->set('focal_point', $form_state->getValue('focal_point'));
    return \Drupal::service('media_library.opener_resolver')
      ->get($state)
      ->getSelectionResponse($state, $selected_ids)
      ->addCommand(new CloseDialogCommand());
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Ajax callback.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state instance.
   *
   * @return array
   *   Form.
   */
  public function checkFocalPointUsage(array &$form, FormStateInterface $form_state): array {
    return $form;
  }

}
