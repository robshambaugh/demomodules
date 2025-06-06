<?php

declare(strict_types=1);

namespace Drupal\acquia_dam;

use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\media\MediaTypeInterface;
use Drupal\media_library\MediaLibraryState;
use Drupal\media_library\MediaLibraryUiBuilder;
use Drupal\user\UserDataInterface;
use Drupal\views\ViewEntityInterface;

/**
 * Decorates the media library builder to add our customizations.
 *
 * @phpstan-ignore-next-line
 */
final class AssetLibraryBuilder extends MediaLibraryUiBuilder {

  /**
   * Acquia DAM authentication service.
   *
   * @var \Drupal\acquia_dam\AcquiadamAuthService
   */
  protected $damAuthService;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Current user object.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Acquia DAM logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $damLoggerChannel;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * Sets the messenger.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The new messenger.
   */
  public function setMessenger(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * Setter function for authentication service.
   *
   * @param \Drupal\acquia_dam\AcquiadamAuthService $auth_service
   *   Acquia DAM authentication service.
   */
  public function setAuthService(AcquiadamAuthService $auth_service) {
    $this->damAuthService = $auth_service;
  }

  /**
   * Setter function for current user object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current user object.
   */
  public function setCurrentUser(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * Setter function for Acquia DAM logger channel.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger_channel
   *   Acquia DAM logger channel.
   */
  public function setLogger(LoggerChannelInterface $logger_channel) {
    $this->damLoggerChannel = $logger_channel;
  }

  /**
   * Setter function for user data.
   *
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   */
  public function setUserData(UserDataInterface $user_data) {
    $this->userData = $user_data;
  }

  /**
   * Sets the module extension list.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   */
  public function setExtensionList(ModuleExtensionList $module_extension_list) {
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * Sets the file URL generator.
   *
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   */
  public function setFileUrlGenerator(FileUrlGeneratorInterface $file_url_generator) {
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Builds the authorization UI.
   *
   * @param \Drupal\media_library\MediaLibraryState $state
   *   The current state of the media library, derived from the
   *   current request.
   *
   * @return array
   *   The render array.
   */
  private function buildAuthorizationUi(MediaLibraryState $state): array {
    $return_link = Url::fromRoute('acquia_dam.user_auth', [], [
      'query' => [
        'uid' => $this->currentUser->id(),
      ],
    ])
      ->setAbsolute()
      ->toString();
    $auth_url = $this->damAuthService->generateAuthUrl($return_link);

    return [
      '#theme' => 'media_library_wrapper',
      '#attributes' => [
        'id' => 'media-library-wrapper',
      ],
      '#attached' => [
        'library' => [
          'media_library/ui',
          'acquia_dam/media_library.style',
          'acquia_dam/acquia_dam.authorization',
        ],
        'drupalSettings' => [
          'media_library' => [
            'selection_remaining' => 0,
            'url' => Url::fromRoute('media_library.ui', [], [
              'query' => $state->all(),
            ])->toString(),
          ],
        ],
      ],
      'content' => [
        '#type' => 'container',
        '#theme_wrappers' => [
          'container__media_library_content',
        ],
        '#attributes' => [
          'id' => 'media-library-content',
        ],
        'authorization' => [
          '#type' => 'fieldset',
          '#title' => $this->t('Connect your account'),
          'description' => [
            '#markup' => '<p>To initialize the Acquia DAM module, you need to authenticate with a user that has permission to view & download assets that are applicable to your website.',
          ],
          'actions' => [
            '#type' => 'actions',
            'skip' => [
              '#type' => 'link',
              '#url' => Url::fromRoute('media_library.ui', [], [
                'query' => $state->all(),
              ]),
              '#title' => $this->t('Skip'),
              '#options' => [
                'attributes' => [
                  'id' => 'acquia-dam-user-authorization-skip',
                  'class' => ['button'],
                ],
              ],
            ],
            'continue' => [
              '#type' => 'link',
              '#url' => Url::fromUri($auth_url),
              '#title' => $this->t('Connect'),
              '#options' => [
                'attributes' => [
                  'id' => 'acquia-dam-user-authorization',
                  'class' => ['button', 'button--primary'],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildMediaLibraryView(MediaLibraryState $state): array {
    // @todo remove after https://www.drupal.org/project/drupal/issues/2971209.
    // Currently, there is no way to influence the View ID used for a specific
    // media type.
    $selected_type = $state->getSelectedTypeId();
    $media_type = $this->entityTypeManager->getStorage('media_type')->load($selected_type);
    if ($media_type instanceof MediaTypeInterface && !$media_type->getSource() instanceof Asset) {
      return parent::buildMediaLibraryView($state);
    }
    if (!$this->damAuthService->isConfigured()) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t(
          'Site is not configured for Acquia DAM. Please <a href="@auth" target="blank">configure</a> it to browse assets.',
          [
            '@auth' => Url::fromRoute('acquia_dam.config')->toString(),
          ]
        ),
      ];
    }

    if (!$this->damAuthService->isSiteAuthenticated()) {
      $return_link = Url::fromRoute('acquia_dam.site_auth')
        ->setAbsolute()
        ->toString();
      $auth_url = $this->damAuthService->generateAuthUrl($return_link);

      return [
        '#type' => 'markup',
        '#markup' => $this->t(
          'Site is not authenticated with Acquia DAM. Please <a href="@auth" target="blank">authenticate</a> it to browse assets. Once successfully authenticated, close this modal and reopen it to browse Acquia DAM assets.',
          [
            '@auth' => $auth_url,
          ]
        ),
      ];
    }

    if (!$this->damAuthService->isAuthenticated((int) $this->currentUser->id())) {
      $return_link = Url::fromRoute('acquia_dam.user_auth', [], [
        'query' => [
          'uid' => $this->currentUser->id(),
        ],
      ])
        ->setAbsolute()
        ->toString();
      $auth_url = $this->damAuthService->generateAuthUrl($return_link);

      return [
        '#type' => 'markup',
        '#markup' => $this->t('This user account is not authenticated with Acquia DAM. Please <a id="acquia-dam-user-authorization" href="@auth" target="blank">authenticate</a> to browse assets. Once successfully authenticated, close this modal and reopen it to browse Acquia DAM assets.',
          [
            '@auth' => $auth_url,
          ]
        ),
      ];
    }

    $view_id = 'acquia_dam_asset_library';
    $display_id = 'widget';
    // We have to completely copy the code from the parent in order to render
    // our specific Media Library view.
    $view = $this->entityTypeManager->getStorage('view')->load($view_id);
    assert($view instanceof ViewEntityInterface);
    $view_executable = $this->viewsExecutableFactory->get($view);
    $display_id = $state->get('views_display_id', $display_id);
    // Make sure the state parameters are set in the request so the view can
    // pass the parameters along in the pager, filters etc.
    $view_request = $view_executable->getRequest();
    $view_request->query->add($state->all());
    $view_executable->setRequest($view_request);

    $args = [$state->getSelectedTypeId()];

    // Make sure the state parameters are set in the request so the view can
    // pass the parameters along in the pager, filters etc.
    $request = $view_executable->getRequest();
    $request->query->add($state->all());
    $view_executable->setRequest($request);

    try {
      $view_executable->setDisplay($display_id);
      $view_executable->preExecute($args);
      $view_executable->execute($display_id);
    }
    catch (\Exception $exception) {
      $this->messenger->addWarning('Something went wrong gathering Acquia DAM assets. Please contact the site administrator.');
      $this->damLoggerChannel->error($exception->getMessage());
      return [];
    }

    return $view_executable->buildRenderable($display_id, $args, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildLibraryContent(MediaLibraryState $state) {
    $build = parent::buildLibraryContent($state);
    if (\Drupal::theme()->getActiveTheme()->getName() != \Drupal::config('system.theme')->get('admin')) {
      $build['#attached']['library'][] = 'acquia_dam/media_library.style.non_admin';

    }
    $build['#attached']['library'][] = 'acquia_dam/media_library.style';
    $build['#attached']['library'][] = 'acquia_dam/acquia_dam.authorization';
    $build['#attached']['library'][] = "acquia_dam/acquia_dam.media_library.reset_filter";

    return $build;
  }

  /**
   * {@inheritDoc}
   *
   * Overrides the menu links to omit DAM word on media library modal.
   */
  protected function buildMediaTypeMenu(MediaLibraryState $state) {
    $menu = parent::buildMediaTypeMenu($state);
    $links = $menu['#links'] ?? [];
    // Bail early if there are no links.
    if ($links === []) {
      return $menu;
    }

    foreach ($links as $link_id => $link) {
      if (strpos($link_id, 'acquia_dam_') === FALSE) {
        continue;
      }
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $link_title */
      $link_title = $link['title']['#markup'];
      $untranslated = $link_title->getUntranslatedString();
      $title = $link['attributes']['data-title'];
      $title = str_replace('Acquia DAM: ', '', $title);
      $link['attributes']['data-title'] = $title;
      $link['title'] = [
        // phpcs:ignore
        '#markup' => $this->t($untranslated, ['@title' => $title]),
      ];
      $links[$link_id] = $link;
    }
    $menu['#links'] = $links;
    return $menu;
  }

  /**
   * {@inheritdoc}
   *
   * Overrides the parent to manipulate the allowed_type_ids with the selection
   * made in the source option.
   */
  public function buildUi(?MediaLibraryState $state = NULL) {
    if (!$state) {
      $state = MediaLibraryState::fromRequest($this->request);
    }

    $query = $this->request->query;
    if (!$query->all('complete_allowed_list')) {
      $query->set('complete_allowed_list', $state->get('media_library_allowed_types'));
    }

    /** @var string[] $allowed_type_ids */
    $allowed_type_ids = $query->all('complete_allowed_list');
    /** @var array<string, array<string, \Drupal\media\MediaTypeInterface>> $grouped_allowed_types */
    $grouped_allowed_types = [];
    /** @var array<string, \Drupal\media\MediaTypeInterface> $source_allowed_types */
    $source_allowed_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple($allowed_type_ids);
    foreach ($source_allowed_types as $source_allowed_type) {
      $provider = $source_allowed_type->getSource()->getPluginDefinition()['provider'];
      // Adjust the provider, for now, to only have `acquia_dam` and `core`.
      if ($provider !== 'acquia_dam') {
        $provider = 'core';
      }
      if (!isset($grouped_allowed_types[$provider])) {
        $grouped_allowed_types[$provider] = [];
      }
      $grouped_allowed_types[$provider][] = $source_allowed_type;
    }

    // If the user hasn't authorized their Drupal account with the DAM, and
    // it's the first time opening the media library, display the authorization
    // prompt.
    if (array_key_exists('acquia_dam', $grouped_allowed_types) && $this->damAuthService->shouldShowAuthorizationPrompt((int) $this->currentUser->id())) {
      $this->damAuthService->markAuthorizationPromptShown((int) $this->currentUser->id());
      return $this->buildAuthorizationUi($state);
    }

    // Check if the media library is only loading a specific tab.
    if ($state->get('media_library_content') === '1') {
      return parent::buildUi($state);
    }

    // If there is only one source for the media types, no need to render the
    // source selector.
    if (count($grouped_allowed_types) === 1) {
      return parent::buildUi($state);
    }

    // Check if we have a source value to show media types for.
    $source_value = $query->get('source', $this->userData->get('acquia_dam', $this->currentUser->id(), 'media_library_source'));
    // If we have no source selected and there are multiple sources, display
    // the prompt to select a media type source.
    if ($source_value === NULL || $source_value === '') {
      return $this->buildNoSourceSelectedUi($state);
    }

    // We entered an invalid state, revert to normal UI.
    if (!isset($grouped_allowed_types[$source_value]) || count($grouped_allowed_types[$source_value]) === 0) {
      return parent::buildUi($state);
    }

    // Remember the source selection.
    $this->userData->set('acquia_dam', $this->currentUser->id(), 'media_library_source', $source_value);

    $allowed_type_ids = array_map(static function (MediaTypeInterface $media_type) {
      return $media_type->id();
    }, $grouped_allowed_types[$source_value]);
    // Retain the tab value if the source field isn't changed.
    if (!in_array($state->get('media_library_selected_type'), $allowed_type_ids)) {
      $state->set('media_library_selected_type', array_values($allowed_type_ids)[0]);
    }
    $state->set('media_library_allowed_types', $allowed_type_ids);
    $state->set('complete_allowed_list', $query->all('complete_allowed_list'));
    $state->set('source', $source_value);
    // Resetting the hash with new allowed types.
    $state->set('hash', $state->getHash());
    return $this->addSourceMenu($state, $source_value);
  }

  /**
   * Returns build with no views or menu.
   *
   * @param \Drupal\media_library\MediaLibraryState $state
   *   The current state of the media library, derived from the current request.
   *
   * @return array
   *   The render array with no view or menu.
   */
  public function buildNoSourceSelectedUi(MediaLibraryState $state): array {
    $build = $this->addSourceMenu($state);
    unset($build['content']['form']);
    $build['content']['view'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="acquia-dam-message-wrapper"><img src="{{ path }}" alt="Empty state" width="201px"><h3>{{ main_content }}</h3><p>{{ description }}</p></div>',
      '#context' => [
        'path' => $this->fileUrlGenerator->generateString($this->moduleExtensionList->getPath('acquia_dam') . '/images/empty_state.svg'),
        'main_content' => $this->t('To begin searching for media, select a source.'),
        'description' => $this->t('Your selection saves as your default choice. You can change your source anytime from the dropdown in the upper left of this module.'),
      ],
    ];
    return $build;
  }

  /**
   * Helper function to attach the source menu with the media library menu.
   *
   * @param \Drupal\media_library\MediaLibraryState $state
   *   The current state of the media library, derived from the current request.
   * @param string $source_state
   *   Source state value of the media library.
   *
   * @return array
   *   The render array including the source menu.
   */
  public function addSourceMenu(MediaLibraryState $state, string $source_state = ''): array {
    $build = parent::buildUi($state);
    $source_state_base = $state->all();
    unset($source_state_base['ajax_form']);
    unset($source_state_base['_wrapper_format']);
    $source_state_core = [
      'source' => 'core',
    ] + $source_state_base;

    $source_state_acquia_dam = [
      'source' => 'acquia_dam',
    ] + $source_state_base;

    $source_menu = [
      '#type' => 'container',
      'field' => [
        '#type' => 'select',
        '#title' => $this->t('Select media source'),
        '#options' => [
          'none' => $this->t('Choose value'),
          'acquia_dam' => $this->t('Acquia DAM'),
          'core' => $this->t('Media Types'),
        ],
        '#attributes' => [
          'class' => ['js-acquia-dam-source-field'],
        ],
      ],
      '#attributes' => [
        'id' => ['acquia-dam-source-menu-wrapper'],
      ],
    ];

    if ($source_state !== '') {
      $source_menu['field']['#value'] = $source_state;
      $source_menu['link'] = $build['menu'];
      unset($source_menu['field']['#options']['none']);
    }
    $build['menu'] = $source_menu;
    $build['#attached']['library'][] = "acquia_dam/acquia_dam.media_library.source_menu";
    $build['#attached']['drupalSettings']['media_library']['core'] = Url::fromRoute('media_library.ui', [], [
      'query' => $source_state_core,
    ])->toString();
    $build['#attached']['drupalSettings']['media_library']['acquia_dam'] = Url::fromRoute('media_library.ui', [], [
      'query' => $source_state_acquia_dam,
    ])->toString();

    if (isset($build['menu']['link']['#links']) && $state->get('source') !== NULL) {
      // Tagging each of our available url with the source parameter and total
      // allowed list so that it won't lose its value when a particular tab is
      // reached.
      foreach ($build['menu']['link']['#links'] as $link) {
        $options = $link['url']->getOptions();
        $options['query']['source'] = $state->get('source');
        $options['query']['complete_allowed_list'] = $state->get('complete_allowed_list');
        $link['url']->setOptions($options);
      }
    }
    return $build;
  }

  /**
   * Check access to the update media form.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access results.
   */
  public function checkUpdateFormAccess(AccountInterface $account) {
    $hash = implode(':', [
      $this->request->request->get('form_id'),
      $this->request->query->get('triggered_value'),
      $this->request->query->get('parent_field'),
    ]);

    if (hash_equals($this->request->query->get('hash'), Crypt::hmacBase64($hash, \Drupal::service('private_key')->get() . Settings::getHashSalt()))) {
      $is_allowed = AccessResult::allowed();
    }
    else {
      $is_allowed = AccessResult::forbidden('Unable to fetch necessary data for the form.');
    }
    return AccessResult::allowedIfHasPermission($account, 'view all media revisions')->andIf($is_allowed);
  }

}
