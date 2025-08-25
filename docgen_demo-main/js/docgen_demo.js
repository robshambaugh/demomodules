(function ($, Drupal) {
  'use strict';

  // Define a new function in the global scope for the AJAX callback.
  $.fn.startProcess = function(companyName, docName) {
    const resultsContainer = $('#results-container');
    resultsContainer.removeClass('hidden').html('');

    const steps = [
      {
        message: `1. Initiating document generation for <strong>${companyName}</strong>...`,
        delay: 1000,
        icon: '▶️'
      },
      {
        message: `2. Simulating API call to Salesforce for client data...`,
        delay: 1500,
        icon: '☁️'
      },
      {
        message: `3. Simulating API call to Facets for plan details...`,
        delay: 1500,
        icon: '📊'
      },
      {
        message: `4. Assembling document: <strong>"${docName}"</strong>...`,
        delay: 2000,
        icon: '📄'
      },
      {
        message: `5. Pushing document to Acquia DAM and initiating "Legal Review" workflow...`,
        delay: 1500,
        icon: '🚀'
      },
      {
        message: `✅ <strong>Success!</strong> The document has been sent for approval. Legal and Brand teams have been notified.`,
        delay: 500,
        icon: '✅'
      }
    ];

    let delay = 0;
    steps.forEach((step, index) => {
      setTimeout(() => {
        const stepElement = $(`<div class="step"><span class="icon">${step.icon}</span> ${step.message}</div>`).hide().fadeIn();
        resultsContainer.append(stepElement);
        // Scroll to the bottom
        resultsContainer.scrollTop(resultsContainer[0].scrollHeight);
      }, delay);
      delay += step.delay;
    });
  };

})(jQuery, Drupal);