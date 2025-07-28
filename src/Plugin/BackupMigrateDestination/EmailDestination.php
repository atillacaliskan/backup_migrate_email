<?php

namespace Drupal\backup_migrate_email\Plugin\BackupMigrateDestination;

use Drupal\backup_migrate\Drupal\EntityPlugins\DestinationPluginBase;

/**
 * Defines an email destination plugin.
 *
 * @BackupMigrateDestinationPlugin(
 *   id = "Email",
 *   title = @Translation("Email"),
 *   description = @Translation("Send backup files via email."),
 *   wrapped_class = "\Drupal\backup_migrate_email\Destination\EmailDestination"
 * )
 */
class EmailDestination extends DestinationPluginBase {}
