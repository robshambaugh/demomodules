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
