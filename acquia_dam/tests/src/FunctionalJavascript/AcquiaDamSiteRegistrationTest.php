<?php

namespace Drupal\Tests\acquia_dam\FunctionalJavascript;

/**
 * Test Acquia Dam site registration.
 *
 * @group acquia_dam
 */
class AcquiaDamSiteRegistrationTest extends AcquiaDamWebDriverTestBase {

  /**
   * User that has admin permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
    ]);
  }

  /**
   * Tests site authorisation link is present on config.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testSiteAuthorisationLink() {
    $this->drupalLogin($this->adminUser);
    $session = $this->assertSession();

    // Must be done manually via config, otherwise it'll redirect to site.
    $this->config('acquia_dam.settings')->set('domain', 'valid-domain.com')->save();

    $this->drupalGet('/admin/config/acquia-dam');
    $session->pageTextContains('Acquia DAM Configuration');

    $this->getSession()->getPage()->hasLink('Authenticate Site');
  }

  /**
   * Tests disconnect site button is present on config.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testSiteDisconnectLink() {
    $this->drupalLogin($this->adminUser);
    $session = $this->assertSession();

    $this->grantSiteRegistrationToken();
    $this->grantDamDomain();

    $this->drupalGet('/admin/config/acquia-dam');
    $session->pageTextContains('Acquia DAM Configuration');

    $session->pageTextContains('Site is authenticated with Acquia DAM.');
    $this->getSession()->getPage()->hasLink('Disconnect Site');
  }

}
