<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;

/**
 * Tests tokens for the Acquia DAM media types.
 *
 * @group acquia_dam
 */
final class TokensTest extends AcquiaDamKernelTestBase {

  /**
   * The test media entity.
   *
   * @var \Drupal\media\MediaInterface
   */
  private $media;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->setDamSiteToken();
    $media_type = $this->createPdfMediaType();
    $this->media = Media::create([
      'bundle' => $media_type->id(),
      'acquia_dam_asset_id' => [
        'asset_id' => '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
        'version_id' => '7b67948f-ee7e-405c-a0cd-344a24d8afb2',
        'external_id' => '8a1ouvfchk',
      ],
    ]);
    $this->media->save();
  }

  /**
   * Tests that the `embed-code` token works without the `token` module.
   */
  public function testTokens(): void {
    $this->assertTokenInfo('embed-code', [
      'url' => [
        'name' => 'URL',
        'module' => 'acquia_dam',
      ],
    ]);

    $this->assertTokenReplacements(
      ['embed-code:url' => '[media:embed-code:url]'],
      $this->media,
      ['[media:embed-code:url]' => 'https://laser.widen.net/content/8a1ouvfchk/original/Explorer-owners-manual.pdf?u=xgdchd&download=true']
    );
  }

  /**
   * Tests token data when the `token` module is installed.
   *
   * @requires module token
   */
  public function testTokensWithTokenModule(): void {
    $this->installModule('token');

    $this->assertTokenInfo('acquia_dam_asset_id', [
      'asset_id' => [
        'name' => 'Asset ID',
        'description' => 'The asset identifier.',
        'module' => 'token',
      ],
      'version_id' => [
        'name' => 'Version ID',
        'description' => 'The version ID for the asset.',
        'module' => 'token',
      ],
      'external_id' => [
        'name' => 'External ID',
        'description' => 'The external ID for the asset.',
        'module' => 'token',
      ],
    ]);
    $this->assertTokenInfo('embed-code', [
      'url' => [
        'name' => 'URL',
        'module' => 'acquia_dam',
      ],
    ]);

    $this->assertTokenReplacements(
      [
        'acquia_dam_asset_id:asset_id' => '[media:acquia_dam_asset_id:asset_id]',
        'acquia_dam_asset_id:version_id' => '[media:acquia_dam_asset_id:version_id]',
      ],
      $this->media,
      [
        '[media:acquia_dam_asset_id:asset_id]' => '0324b0b2-5293-4aa0-b0aa-c85b003395e2',
        '[media:acquia_dam_asset_id:version_id]' => '7b67948f-ee7e-405c-a0cd-344a24d8afb2',
      ]
    );

    $this->assertTokenReplacements(
      ['embed-code:url' => '[media:embed-code:url]'],
      $this->media,
      ['[media:embed-code:url]' => 'https://laser.widen.net/content/8a1ouvfchk/original/Explorer-owners-manual.pdf?u=xgdchd&download=true']
    );

  }

  /**
   * Gets the token info.
   *
   * @return array
   *   The token info.
   */
  private function getTokenInfo(): array {
    return $this->container->get('token')->getInfo();
  }

  /**
   * Asserts token info.
   *
   * @param string $token
   *   The token to assert info for.
   * @param array $expected_info
   *   The expected token info.
   */
  private function assertTokenInfo(string $token, array $expected_info): void {
    $token_info = $this->getTokenInfo();
    self::assertArrayHasKey(
      "media-$token",
      $token_info['tokens'],
      var_export(array_keys($token_info['tokens']), TRUE)
    );
    self::assertEquals($expected_info, $token_info['tokens']["media-$token"]);
  }

  /**
   * Asserts token replacements.
   *
   * @param array $tokens
   *   The tokens being used.
   * @param \Drupal\media\MediaInterface $media
   *   The media.
   * @param array $expected_replacements
   *   The expected replacements.
   */
  private function assertTokenReplacements(array $tokens, MediaInterface $media, array $expected_replacements): void {
    $token = $this->container->get('token');
    $replacements = $token->generate(
      'media',
      $tokens,
      ['media' => $media],
      [],
      new BubbleableMetadata()
    );
    self::assertEquals($expected_replacements, $replacements);
  }

}
