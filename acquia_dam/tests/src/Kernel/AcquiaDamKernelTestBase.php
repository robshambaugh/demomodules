<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\media\Entity\MediaType;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base test class for acquia_dam.
 *
 * @group acquia_dam
 */
abstract class AcquiaDamKernelTestBase extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Remove when dropping support below Drupal 10.3.
   */
  //phpcs:ignore DrupalPractice.Objects.StrictSchemaDisabled.StrictConfigSchema
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'file',
    'image',
    'media',
    'media_library',
    'views_remote_data',
    'token',
    'acquia_dam',
    'acquia_dam_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('acquia_dam', [
      'acquia_dam_integration_link_tracking',
      'acquia_dam_integration_link_aggregate',
    ]);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', 'file_usage');

    $this->grantDamDomain();
    $this->setDirectoryPath();
    $this->setDamSiteToken();

    // Burn uid 1.
    $this->createUser();
  }

  /**
   * {@inheritdoc}
   */
  protected function drupalSetUpCurrentUser(array $values = [], array $permissions = [], $admin = FALSE): UserInterface {
    $name = $values['name'] ?? NULL;
    // Using drupalCreateUser instead of drupalSetUpCurrentUser.
    // @see https://www.drupal.org/project/drupal/issues/3324384
    $user = $this->drupalCreateUser($permissions, $name, $admin, $values);
    $this->container->get('acquia_dam.authentication_service')->setUserData(
      (int) $user->id(),
      [
        'acquia_dam_username' => $user->getEmail(),
        'acquia_dam_token' => $this->randomString(),
      ]
    );
    $this->setCurrentUser($user);

    return $user;
  }

  /**
   * Set a random string as mock site token for DAM.
   *
   * @throws \Exception
   */
  protected function setDamSiteToken() {
    $this
      ->container
      ->get('state')
      ->setMultiple([
        'acquia_dam_token' => $this->randomString(),
        'acquia_dam_refresh_token' => $this->randomString(),
      ]);
  }

  /**
   * Creates a PDF media type.
   *
   * @param array $source_config_override
   *   (optional) Provides the opportunity to override any source configuration
   *   setting on a case-by-case basis.
   *
   * @return \Drupal\media\Entity\MediaType
   *   The media type.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createPdfMediaType(array $source_config_override = []): MediaType {
    return $this->createMediaType('acquia_dam_asset:pdf', $source_config_override);
  }

  /**
   * Creates a Video media type.
   *
   * @param array $source_config_override
   *   (optional) Provides the opportunity to override any source configuration
   *   setting on a case-by-case basis.
   *
   * @return \Drupal\media\Entity\MediaType
   *   The media type.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createVideoMediaType(array $source_config_override = []): MediaType {
    return $this->createMediaType('acquia_dam_asset:video', $source_config_override);
  }

  /**
   * Creates a Spinset media type.
   *
   * @param array $source_config_override
   *   (optional) Provides the opportunity to override any source configuration
   *   setting on a case-by-case basis.
   *
   * @return \Drupal\media\Entity\MediaType
   *   The media type.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createSpinsetMediaType(array $source_config_override = []): MediaType {
    return $this->createMediaType('acquia_dam_asset:spinset', $source_config_override);
  }

  /**
   * Creates an Image media type.
   *
   * @param array $source_config_override
   *   (optional) Provides the opportunity to override any source configuration
   *   setting on a case-by-case basis.
   *
   * @return \Drupal\media\Entity\MediaType
   *   The media type.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createImageMediaType(array $source_config_override = []): MediaType {
    $media_type = $this->createMediaType('acquia_dam_asset:image', $source_config_override);
    $media_type->setNewRevision(TRUE)->save();
    return $media_type;
  }

  /**
   * Creates a document media type.
   *
   * @param array $source_config_override
   *   (optional) Provides the opportunity to override any source configuration
   *   setting on a case-by-case basis.
   *
   * @return \Drupal\media\Entity\MediaType
   *   The media type.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createDocumentMediaType(array $source_config_override = []): MediaType {
    return $this->createMediaType('acquia_dam_asset:documents', $source_config_override);
  }

  /**
   * Creates a generic media type.
   *
   * @param array $source_config_override
   *   (optional) Provides the opportunity to override any source configuration
   *   setting on a case-by-case basis.
   *
   * @return \Drupal\media\Entity\MediaType
   *   The media type.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createGenericMediaType(array $source_config_override = []): MediaType {
    $source_config_override['download_assets'] = TRUE;
    return $this->createMediaType('acquia_dam_asset:generic', $source_config_override);
  }

  /**
   * Creates an audio media type.
   *
   * @param array $source_config_override
   *   (optional) Provides the opportunity to override any source configuration
   *   setting on a case-by-case basis.
   *
   * @return \Drupal\media\Entity\MediaType
   *   The media type.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createAudioMediaType(array $source_config_override = []): MediaType {
    $source_config_override['download_assets'] = TRUE;
    return $this->createMediaType('acquia_dam_asset:audio', $source_config_override);
  }

  /**
   * Creates a media type.
   *
   * @param string $source_plugin_id
   *   The plugin ID.
   * @param array $source_config_override
   *   (optional) Provides the opportunity to override any source configuration
   *   setting on a case-by-case basis.
   *
   * @return \Drupal\media\Entity\MediaType
   *   The media type.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createMediaType(string $source_plugin_id, array $source_config_override = []): MediaType {
    $media_type = MediaType::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
      'source' => $source_plugin_id,
      'queue_thumbnail_downloads' => FALSE,
    ]);
    $source = $media_type->getSource();
    $source_field = $source->createSourceField($media_type);
    $source_configuration = $source->getConfiguration();
    $source_configuration['source_field'] = $source_field->getName();

    foreach ($source_config_override as $setting_name => $value) {
      $source_configuration[$setting_name] = $value;
    }

    $source->setConfiguration($source_configuration);
    $this->assertSame(SAVED_NEW, $media_type->save());
    return $media_type;
  }

  /**
   * Grants the site a dummy DAM domain.
   *
   * @param string $domain
   *   Domain to save into DAM config.
   */
  protected function grantDamDomain(string $domain = 'test.widencollective.com') {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory */
    $configFactory = $this->container->get('config.factory');
    $configFactory->getEditable('acquia_dam.settings')
      ->set('domain', $domain)
      ->save();
  }

  /**
   * Sets the default directory path to store downloaded files.
   *
   * @param string $directory_path
   *   Directory path to save into module settings.
   */
  protected function setDirectoryPath(string $directory_path = 'dam/asset_external_id') {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory */
    $configFactory = $this->container->get('config.factory');
    $configFactory->getEditable('acquia_dam.settings')
      ->set('asset_file_directory_path', $directory_path)
      ->save();
  }

  /**
   * Creates a request object.
   *
   * @param string $uri
   *   The uri.
   * @param string $method
   *   The method.
   * @param array $document
   *   The document.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   *
   * @throws \Exception
   */
  protected function getMockedRequest(string $uri, string $method, array $document = []): Request {
    return Request::create($uri, $method, [], [], [], [], $document ? Json::encode($document) : NULL);
  }

  /**
   * Process a request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Exception
   */
  protected function processRequest(Request $request): Response {
    $response = $this->container->get('http_kernel')->handle($request);
    $content = $response->getContent();
    self::assertNotFalse($content);
    $this->setRawContent($content);
    return $response;
  }

  /**
   * Submits a form.
   *
   * You must have revisited the form first in order to generate the required
   * values to validate the form submission.
   *
   * @param string $uri
   *   The form submit URI.
   * @param array $data
   *   The form data.
   * @param string $op
   *   The submit button text.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The form submission response.
   */
  protected function doFormSubmit(string $uri, array $data, string $op = 'Save'): Response {
    $data += [
      // @phpstan-ignore-next-line
      'form_build_id' => (string) $this->cssSelect('input[name="form_build_id"]')[0]->attributes()->value[0],
      // @phpstan-ignore-next-line
      'form_token' => (string) $this->cssSelect('input[name="form_token"]')[0]->attributes()->value[0],
      // @phpstan-ignore-next-line
      'form_id' => (string) $this->cssSelect('input[name="form_id"]')[0]->attributes()->value[0],
      'op' => $op,
    ];
    $request = Request::create($uri, 'POST', $data);
    return $this->processRequest($request);
  }

}
