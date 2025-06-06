<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Plugin\Field\FieldFormatter;

use Drupal\acquia_dam\Entity\MediaExpiryDateField;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\Plugin\Field\FieldType\TimestampItem;
use Drupal\Core\Render\Markup;
use Drupal\Core\TypedData\Plugin\DataType\Timestamp;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field formatter to render warning for expired assets.
 *
 * @FieldFormatter(
 *   id = "acquia_dam_expry_date_warning",
 *   label = @Translation("Expiration date warning"),
 *   field_types = {
 *     "timestamp"
 *   }
 * )
 */
final class ExpiryDateWarningFormatter extends FormatterBase {

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private $time;

  /**
   * The module list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  private $moduleList;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->time = $container->get('datetime.time');
    $instance->moduleList = $container->get('extension.list.module');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    if ($items->isEmpty()) {
      return $elements;
    }
    $value = $items->first();
    assert($value instanceof TimestampItem);
    $value = $value->get('value');
    assert($value instanceof Timestamp);

    $elements['#attached']['library'][] = 'acquia_dam/acquia_dam.expired_assets';

    $now = (new \DateTime())->setTimestamp($this->time->getRequestTime());
    $expiration = (new \DateTime())->setTimestamp($value->getCastedValue());
    if ($now >= $expiration) {
      $svg = file_get_contents($this->moduleList->getPath('acquia_dam') . '/images/alert-triangle.svg');
      $elements[0] = [
        '#type' => 'inline_template',
        '#template' => '<div class="acquia-dam-expired-asset-container"><div class="acquia-dam-expired-asset">{{ svg }}<div class="acquia-dam-asset-expired__popper">{{ description }}<div class="acquia-dam-asset-expired__arrow" data-popper-arrow></div></div></div><span class="acquia-dam-asset-expired__label">{{ label }}</span></div>',
        '#context' => [
          'label' => $this->t('Expired media'),
          'description' => $this->t('Expired media is not visible to content viewers, replace the media.'),
          'svg' => Markup::create($svg),
        ],
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getName() === MediaExpiryDateField::EXPIRY_DATE_FIELD_NAME;
  }

}
