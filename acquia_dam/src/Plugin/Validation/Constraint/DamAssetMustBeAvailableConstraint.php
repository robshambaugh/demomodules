<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * DamAssetMustBeAvailableConstraint.
 *
 * Validation constraint that forces Drupal media items to reference only to
 * available DAM assets in Widen.
 *
 * @todo Figure out the backward-compatible annotating for D10.
 *
 * @Constraint(
 *   id = "DamAssetMustBeAvailableConstraint",
 *   label = @Translation("Enforce DAM assets are available.", context = "Validation"),
 * )
 */
#[Constraint(
  id: 'DamAssetMustBeAvailableConstraint',
  label: new TranslatableMarkup('Enforce DAM assets are available', options: ['context' => 'Validation'])
)]
final class DamAssetMustBeAvailableConstraint extends SymfonyConstraint {

  /**
   * The default violation message.
   *
   * Used when a remote DAM asset referenced by a local media item is not
   * publicly available.
   *
   * @var string
   */
  public string $message = 'Media items in Drupal are allowed to reference in Widen only those DAM assets that are publicly available. The most recent check on loading this page shows that the associated asset %asset_id is not available publicly. Therefore this media item cannot be published until the associated asset is made publicly available again.';

}
