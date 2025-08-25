<?php

namespace Drupal\docgen_demo\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the main form for the Document Generation Demo.
 */
class DocGenForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'docgen_demo_form';
  }

  /**
   * Mock data for the demo.
   */
  private function getMockData() {
    return [
      'acme_pharma' => [
        'name' => 'ACME Pharmaceuticals',
        'plan_id' => 'ACME-PPO-2025',
        'slogan' => 'Innovating for a Healthier Tomorrow.',
        'documents' => [
          'sbc' => 'Summary of Benefits & Coverage (SBC)',
          'sob' => 'Schedule of Benefits (SOB)',
          'contract' => 'Group Health Plan Contract',
        ],
      ],
      'pioneer_logistics' => [
        'name' => 'Pioneer Logistics',
        'plan_id' => 'PIO-HMO-2025',
        'slogan' => 'Delivering the Future, On Time.',
        'documents' => [
          'sbc' => 'Summary of Benefits & Coverage (SBC)',
          'sob' => 'Schedule of Benefits (SOB)',
          'rider_vision' => 'Vision Plan Rider',
        ],
      ],
      'summit_financial' => [
        'name' => 'Summit Financial Group',
        'plan_id' => 'SFG-HDHP-2025',
        'slogan' => 'Your Peak Financial Partner.',
        'documents' => [
          'sbc' => 'Summary of Benefits & Coverage (SBC)',
          'sob' => 'Schedule of Benefits (SOB)',
          'contract' => 'Group Health Plan Contract',
          'rider_dental' => 'Dental Plan Rider',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $mock_data = $this->getMockData();
    $company_options = [];
    foreach ($mock_data as $key => $company) {
      $company_options[$key] = $company['name'];
    }

    $form['#prefix'] = '<div id="docgen-wrapper">';
    $form['#suffix'] = '</div>';

    $form['company'] = [
      '#type' => 'select',
      '#title' => $this->t('Select an Employee Group Health Plan'),
      '#options' => $company_options,
      '#empty_option' => $this->t('- Select a Company -'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateDocumentOptions',
        'wrapper' => 'document-options-wrapper',
        'event' => 'change',
      ],
    ];

    $form['document_options_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'document-options-wrapper'],
    ];

    $selected_company = $form_state->getValue('company');
    if (!empty($selected_company)) {
      $form['document_options_wrapper']['document_type'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select a Document to Generate'),
        '#options' => $mock_data[$selected_company]['documents'],
        '#required' => TRUE,
      ];
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Document & Send for Approval'),
      '#button_type' => 'primary',
      '#ajax' => [
          'callback' => '::submitFormAjax',
          'wrapper' => 'docgen-wrapper',
      ],
    ];

    $form['results'] = [
      '#type' => 'markup',
      '#markup' => '<div id="results-container" class="hidden"></div>',
    ];

    return $form;
  }

  /**
   * AJAX callback for the company select field.
   */
  public function updateDocumentOptions(array &$form, FormStateInterface $form_state) {
    return $form['document_options_wrapper'];
  }
    
  /**
   * AJAX callback for the form submission.
   */
  public function submitFormAjax(array &$form, FormStateInterface $form_state) {
      // In a real scenario, you would perform the generation and API calls here.
      // For the demo, we just return a success message.
      $company_key = $form_state->getValue('company');
      $doc_key = $form_state->getValue('document_type');
      $mock_data = $this->getMockData();
      
      $company_name = $mock_data[$company_key]['name'];
      $doc_name = $mock_data[$company_key]['documents'][$doc_key];

      // Use Drupal's AJAX API to send commands to the browser.
      $response = new \Drupal\Core\Ajax\AjaxResponse();
      $response->addCommand(new \Drupal\Core\Ajax\HtmlCommand('#results-container', ''));
      $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand(NULL, 'startProcess', [$company_name, $doc_name]));
      
      return $response;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This function is required by the interface but logic is handled in AJAX.
  }

}