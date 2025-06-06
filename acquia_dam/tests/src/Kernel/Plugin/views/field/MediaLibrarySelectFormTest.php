<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_dam\Kernel\Plugin\views\field;

use Drupal\acquia_dam\Entity\MediaSourceField;
use Drupal\acquia_dam\Plugin\views\field\MediaLibrarySelectForm;
use Drupal\Core\Form\FormState;
use Drupal\media\Entity\Media;
use Drupal\Tests\acquia_dam\Kernel\AcquiaDamKernelTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests the MediaLibrarySelectForm views field plugin.
 *
 * @group acquia_dam
 */
final class MediaLibrarySelectFormTest extends AcquiaDamKernelTestBase {

  use MediaTypeCreationTrait {
    createMediaType as drupalCreateMediaType;
  }

  /**
   * Tests processing selected IDs.
   *
   * @dataProvider providerSelectedIds
   */
  public function testProcessInputValues(string $selected_ids, string $expected_ids): void {
    $this->drupalSetUpCurrentUser();

    $media_type = $this->createPdfMediaType();
    // Ensure there is an existing asset media item.
    Media::create([
      'bundle' => $media_type->id(),
      MediaSourceField::SOURCE_FIELD_NAME => [
        'asset_id' => 'a56fb261-8ad5-4e0d-8323-0e8a3659ed39',
      ],
    ])->save();
    // Ensure mid:4 is occupied by non-asset media entity.
    $this->drupalCreateMediaType('file', ['id' => 'document']);
    Media::create(['bundle' => 'document'])->save();
    Media::create(['bundle' => 'document'])->save();
    Media::create(['bundle' => 'document'])->save();
    Media::create(['bundle' => 'document'])->save();

    $instance = $this->container
      ->get('plugin.manager.views.field')
      ->createInstance('acquia_dam_media_library_select_form');
    self::assertInstanceOf(MediaLibrarySelectForm::class, $instance);

    $form_state = new FormState();
    $form_state->setTriggeringElement([
      '#field_id' => 'sut_field_ids',
    ]);
    $form_state->setValue('sut_field_ids', $selected_ids);
    $instance->processInputValues([], $form_state);
    self::assertEquals($expected_ids, $form_state->getValue('sut_field_ids'));
  }

  /**
   * Selected IDs provider.
   */
  public static function providerSelectedIds() {
    yield 'empty no fail' => ['', ''];
    yield 'import new' => ['0324b0b2-5293-4aa0-b0aa-c85b003395e2', '6'];
    yield 'not import existing' => ['a56fb261-8ad5-4e0d-8323-0e8a3659ed39', '1'];
    yield 'multiple' => [
      '0324b0b2-5293-4aa0-b0aa-c85b003395e2,a56fb261-8ad5-4e0d-8323-0e8a3659ed39',
      '1,6',
    ];
    // @note this will not fail on sqlite!
    yield 'uuid leads with valid mid integer' => [
      '4f656c07-6a08-47b3-9403-16082d2fcda2',
      '6',
    ];
  }

}
