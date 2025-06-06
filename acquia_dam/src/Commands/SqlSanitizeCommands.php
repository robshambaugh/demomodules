<?php

declare(strict_types=1);

namespace Drupal\acquia_dam\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\State\StateInterface;
use Drupal\user\UserDataInterface;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\sql\SanitizePluginInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Acquia DAM integration to SQL sanitize for Drush.
 */
final class SqlSanitizeCommands extends DrushCommands implements SanitizePluginInterface {

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * The user data.
   *
   * @var \Drupal\user\UserDataInterface
   */
  private UserDataInterface $userData;

  /**
   * Constructs a new SqlSanitizeCommands object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data.
   */
  public function __construct(StateInterface $state, UserDataInterface $user_data) {
    parent::__construct();
    $this->state = $state;
    $this->userData = $user_data;
  }

  /**
   * Removes Acquia DAM authentication information from the database.
   *
   * {@inheritdoc}
   *
   * @hook post-command sql-sanitize
   */
  public function sanitize($result, CommandData $commandData): void {
    $this->state->delete('acquia_dam_token');
    $this->state->delete('acquia_dam_refresh_token');
    $this->userData->delete('acquia_dam');
    $this->logger()->success(dt('Acquia DAM authentication information remove.'));
  }

  /**
   * {@inheritdoc}
   *
   * @hook on-event sql-sanitize-confirms
   */
  public function messages(&$messages, InputInterface $input): void {
    $messages[] = dt('Remove Acquia DAM authentication information.');
  }

}
