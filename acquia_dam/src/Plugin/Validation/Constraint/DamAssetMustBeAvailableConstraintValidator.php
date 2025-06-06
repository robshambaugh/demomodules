<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\Validation\Constraint;

use Drupal\acquia_dam\Client\AcquiaDamClientFactory;
use Drupal\acquia_dam\Plugin\media\Source\Asset;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the DamAssetMustBeAvailable constraint.
 */
class DamAssetMustBeAvailableConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The DAM client factory.
   *
   * @var \Drupal\acquia_dam\Client\AcquiaDamClientFactory
   */
  private $clientFactory;

  /**
   * Creates a new DamAssetMustBeAvailableConstraintValidator instance.
   *
   * @param \Drupal\acquia_dam\Client\AcquiaDamClientFactory $client_factory
   *   The DAM client factory.
   */
  public function __construct(AcquiaDamClientFactory $client_factory) {
    $this->clientFactory = $client_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_dam.client.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    // This is a single-item field so we only need to validate the first item.
    $status_field_item = $items->first();

    // Controlling only the case of publishing.
    if ((bool) $status_field_item->value === FALSE) {
      return;
    }

    $media_item = $status_field_item->getEntity();

    // Controlling only DAM-related media items.
    if (!$media_item->getSource() instanceof Asset) {
      return;
    }

    $asset_id = $media_item->getSource()->getMetadata($media_item, 'asset_id');
    $asset_data = $this->clientFactory->getSiteClient()->getAsset($asset_id);

    // Either the DAM asset has been deleted or became unavailable meanwhile.
    if (!$asset_data['released_and_not_expired']) {
      $this->context->buildViolation($constraint->message, [
        '%asset_id' => $asset_id,
      ])->addViolation();
    }

  }

}
