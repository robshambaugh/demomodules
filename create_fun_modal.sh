#!/bin/bash

# This script creates a complete Drupal 10 module structure for the "fun_modal" module.
# To use:
# 1. Save this script as a file (e.g., create_module.sh).
# 2. Make it executable: chmod +x create_module.sh
# 3. Run it from your Drupal project's root directory: ./create_module.sh

# Define the module name and the base directory.
MODULE_NAME="fun_modal"
MODULE_DIR="modules/custom/$MODULE_NAME"

echo "Creating the directory structure for the $MODULE_NAME module..."

# Create the nested directory structure.
mkdir -p "$MODULE_DIR/src/Controller"
mkdir -p "$MODULE_DIR/css"

echo "Directory structure created successfully."
echo "Creating the module files..."

# Create fun_modal.info.yml
cat << 'EOF' > "$MODULE_DIR/$MODULE_NAME.info.yml"
name: Fun Modal
description: A Drupal 10 module that displays a fun modal in the admin interface.
type: module
core_version_requirement: ^10
EOF

echo "-> $MODULE_NAME.info.yml created."

# Create fun_modal.routing.yml
cat << 'EOF' > "$MODULE_DIR/$MODULE_NAME.routing.yml"
fun_modal.settings_page:
  path: '/admin/config/fun-modal'
  defaults:
    _controller: '\Drupal\fun_modal\Controller\FunModalController::settingsPage'
    _title: 'Fun Modal'
  requirements:
    _permission: 'access administration pages'

fun_modal.modal_content:
  path: '/admin/fun-modal/content'
  defaults:
    _controller: '\Drupal\fun_modal\Controller\FunModalController::modalContent'
  requirements:
    _permission: 'access administration pages'
  options:
    _no_ui_modes: TRUE
EOF

echo "-> $MODULE_NAME.routing.yml created."

# Create fun_modal.links.menu.yml
cat << 'EOF' > "$MODULE_DIR/$MODULE_NAME.links.menu.yml"
fun_modal.settings_link:
  title: 'Fun Modal'
  description: 'Display a fun modal with a button.'
  route_name: fun_modal.settings_page
  parent: system.admin_config_development
  weight: 10
EOF

echo "-> $MODULE_NAME.links.menu.yml created."

# Create fun_modal.libraries.yml
cat << 'EOF' > "$MODULE_DIR/$MODULE_NAME.libraries.yml"
fun_modal_styles:
  version: 1.0
  css:
    theme:
      css/fun_modal.css: {}
EOF

echo "-> $MODULE_NAME.libraries.yml created."

# Create the CSS file
cat << 'EOF' > "$MODULE_DIR/css/fun_modal.css"
/* Style the modal content area for the fun modal. */
.ui-dialog .ui-dialog-content .fun-modal-content {
  background-color: #0074bd; /* A solid blue */
  color: #fff; /* Large white text */
  font-size: 2em;
  text-align: center;
  padding: 2em;
  font-weight: bold;
}
EOF

echo "-> fun_modal.css created."

# Create the PHP controller file
cat << 'EOF' > "$MODULE_DIR/src/Controller/FunModalController.php"
<?php

namespace Drupal\fun_modal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Controller for the Fun Modal module.
 */
class FunModalController extends ControllerBase {

  /**
   * Renders the settings page with the "Click for fun!" button.
   *
   * @return array
   * A renderable array.
   */
  public function settingsPage() {
    $url = Url::fromRoute('fun_modal.modal_content');
    $link = Link::fromTextAndUrl($this->t('Click for fun!'), $url);
    $link = $link->set('attributes', [
      'class' => ['use-ajax', 'button', 'button--primary'],
      'data-dialog-type' => 'modal',
      'data-dialog-options' => json_encode(['width' => 400]),
    ]);

    return [
      '#type' => 'container',
      '#markup' => '<p>' . $this->t('Click the button below to see a fun modal.') . '</p>',
      'button' => [
        '#type' => 'markup',
        '#markup' => $link->toString(),
      ],
      '#attached' => [
        'library' => [
          'core/drupal.ajax',
          'core/drupal.dialog.ajax',
        ],
      ],
    ];
  }

  /**
   * Generates the modal content in an AJAX response.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * An Ajax response with the modal content.
   */
  public function modalContent() {
    $response = new AjaxResponse();

    $content = [
      '#type' => 'container',
      '#markup' => '<div class="fun-modal-content">Drupal is fun!</div>',
      '#attached' => [
        'library' => ['fun_modal/fun_modal_styles'],
      ],
    ];

    $title = new TranslatableMarkup('A Fun Message');
    $response->addCommand(new OpenModalDialogCommand($title, $content, ['width' => 'auto']));

    return $response;
  }

}
EOF

echo "-> FunModalController.php created."

echo ""
echo "Fun Modal module creation complete!"
echo "The new module is located at $MODULE_DIR"
echo ""
echo "You can now go to your Drupal site, enable the 'Fun Modal' module, and test it out."
echo "Remember to clear your cache after enabling the module."

