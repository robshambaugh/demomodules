<?php

/**
 * @file
 * Provide views data for acquia_dam.
 */

use Drupal\acquia_dam\BundleFieldViewsData;

/**
 * Implements hook_views_data().
 */
function acquia_dam_views_data(): array {
  $data = [];
  $data['acquia_dam_assets']['table']['group'] = t('DAM Assets');
  $data['acquia_dam_assets']['table']['entity type'] = 'media';
  $data['acquia_dam_assets']['table']['entity revision'] = FALSE;
  $data['acquia_dam_assets']['table']['base'] = [
    'title' => t('DAM Assets'),
    'query_id' => 'views_remote_data_query',
  ];
  $data['acquia_dam_assets']['media_library_select_form'] = [
    'title' => t('Select asset'),
    'help' => t('Provides a field for selecting Assets in our media library view'),
    'real field' => 'id',
    'field' => [
      'id' => 'acquia_dam_media_library_select_form',
    ],
  ];
  $data['acquia_dam_assets']['metadata_text'] = [
    'title' => t('Metadata: Text data'),
    'help' => t('The metadata of the type text, text_short and text_long that to be queried'),
    'filter' => [
      'field' => 'field_type',
      'id' => 'asset_metadata_string',
    ],
  ];
  $data['acquia_dam_assets']['search'] = [
    'title' => t('Search'),
    'help' => t('Search text'),
    'filter' => [
      'field' => 'search',
      'id' => 'asset_search_filter',
    ],
  ];
  $data['acquia_dam_assets']['category'] = [
    'title' => t('DAM category filter'),
    'help' => t('WIDEN category to filter query result'),
    'filter' => [
      'id' => 'asset_category_filter',
    ],
  ];
  $data['acquia_dam_assets']['metadata_selection_list'] = [
    'title' => t('Metadata: Selection list'),
    'help' => t('The metadata selection list, checkbox, or palette field that you want to query'),
    'filter' => [
      'field' => 'field_type',
      'id' => 'asset_metadata_in_operator',
    ],
  ];
  $data['acquia_dam_assets']['filename'] = [
    'title' => t('Filename'),
    'help' => t('The filename'),
    'field' => [
      'id' => 'standard',
    ],
  ];
  $data['acquia_dam_assets']['thumbnail'] = [
    'title' => t('Thumbnail'),
    'help' => t('A thumbnail preview'),
    'field' => [
      'id' => 'field',
    ],
    'entity field' => 'thumbnail',
  ];
  $data['acquia_dam_assets']['asset_id'] = [
    'title' => t('Asset reference'),
    'help' => t('Makes available the asset IDs for Views'),
    'field' => [
      'id' => 'field',
    ],
    'entity field' => 'acquia_dam_asset_id',
  ];
  $data['acquia_dam_assets']['FileType'] = [
    'title' => t('File type'),
    'help' => t('The file type'),
    'argument' => [
      'id' => 'acquia_dam_asset_type',
    ],
  ];
  $data['acquia_dam_assets']['acquia_dam_no_results_text'] = [
    'title' => t('Acquia Dam: No results text'),
    'help' => t('Provide the message if the query returns empty.'),
    'area' => [
      'id' => 'acquia_dam_no_results_text',
    ],
  ];

  $data['media']['dam_asset'] = [
    'title' => t('DAM Asset filter'),
    'filter' => [
      'title' => t('DAM Asset filter'),
      'field' => 'id',
      'id' => 'dam_asset_filter',
    ],
  ];

  $data['media']['dam_asset_usage'] = [
    'title' => t('DAM asset counter field'),
    'help' => t('Custom field for counting DAM asset usage.'),
    'field' => [
      'id' => 'dam_asset_usage_counter',
    ],
  ];
  $data['media']['availability_publishing_status_comparison'] = [
    'title' => t('Availability/Publishing status'),
    'help' => t('Displays the results of comparing the availability flag of the remote DAM asset with the publishing status of its associated local media item.'),
    'field' => [
      'id' => 'availability_publishing_status_comparison',
    ],
  ];

  $bundle_field_handler = \Drupal::entityTypeManager()->createHandlerInstance(
    BundleFieldViewsData::class,
    \Drupal::entityTypeManager()->getDefinition('media')
  );
  assert($bundle_field_handler instanceof BundleFieldViewsData);
  $data = array_merge($data, $bundle_field_handler->getViewsData());

  $data['acquia_dam_integration_link_tracking'] = [];
  $data['acquia_dam_integration_link_tracking']['table']['group'] = t('Acquia DAM links');
  $data['acquia_dam_integration_link_tracking']['table']['base'] = [
    'field' => 'integration_link_id',
    'title' => 'Acquia DAM links',
  ];

  // Fields.
  $data['acquia_dam_integration_link_tracking']['integration_link_id'] = [
    'title' => t('DAM link id'),
    'help' => t('DAM link id.'),
    'field' => [
      'id' => 'standard',
    ],
  ];

  $data['acquia_dam_integration_link_tracking']['asset_uuid'] = [
    'title' => t('DAM asset id'),
    'help' => t('DAM asset uuid.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['acquia_dam_integration_link_tracking']['path'] = [
    'title' => t('DAM asset link'),
    'help' => t('DAM asset integration link url.'),
    'field' => [
      'id' => 'standard',
    ],
  ];

  $data['acquia_dam_integration_link_tracking']['entity_type'] = [
    'title' => t('Entity type'),
    'help' => t('Entity type that the asset is referenced to.'),
    'field' => [
      'id' => 'standard',
    ],
  ];

  return $data;
}
