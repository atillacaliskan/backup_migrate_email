<?php

namespace Drupal\backup_migrate_email\Destination;

use Drupal\backup_migrate\Core\Config\ConfigurableInterface;
use Drupal\backup_migrate\Core\Destination\ListableDestinationInterface;
use Drupal\backup_migrate\Core\Destination\WritableDestinationInterface;
use Drupal\backup_migrate\Core\File\BackupFileInterface;
use Drupal\backup_migrate\Core\File\BackupFileReadableInterface;
use Drupal\backup_migrate\Core\Plugin\PluginBase;
use Drupal\Component\Utility\Bytes;
use Drupal\Core\StringTranslation\ByteSizeMarkup;

/**
 * An email destination for sending backup files via email.
 */
class EmailDestination extends PluginBase implements WritableDestinationInterface, ListableDestinationInterface, ConfigurableInterface
{

    /**
     * {@inheritdoc}
     */
    public function saveFile(BackupFileReadableInterface $file)
    {
        $emails = $this->confGet('email');
        $subject = $this->confGet('subject', $this->t('Backup File: @filename', ['@filename' => $file->getFullName()]));
        $from = $this->confGet('from', \Drupal::config('system.site')->get('mail'));

        // Parse multiple email addresses
        $email_addresses = $this->parseEmailAddresses($emails);

        // Check file size limit.
        $max_size = $this->confGet('max_size', '10MB');
        $max_size_bytes = $max_size ? Bytes::toNumber($max_size) : 10485760; // Default 10MB

        $file_size = $file->getMeta('filesize') ?: 0;
        if ($file_size > $max_size_bytes) {
            $message = $this->t('Backup file @filename (@size) exceeds the maximum email attachment size of @max_size.', [
                '@filename' => $file->getFullName(),
                '@size' => class_exists('\Drupal\Core\StringTranslation\ByteSizeMarkup') ?
                    ByteSizeMarkup::create($file_size) : $file_size . ' bytes',
                '@max_size' => $max_size,
            ]);
            \Drupal::logger('backup_migrate_email')->error($message);
            throw new \Exception($message);
        }

        // Read file content.
        $file->openForRead();
        $file_content = '';
        while ($data = $file->readBytes(1024 * 512)) {
            $file_content .= $data;
        }
        $file->close();

        // Handle encryption if enabled
        $final_filename = $file->getFullName();
        $final_content = $file_content;
        $encrypt_enabled = $this->confGet('encrypt', FALSE);

        if ($encrypt_enabled) {
            $encrypt_password = $this->confGet('encrypt_password');
            if (!empty($encrypt_password)) {
                $encrypted_result = $this->encryptBackupFile($file_content, $final_filename, $encrypt_password);
                $final_content = $encrypted_result['content'];
                $final_filename = $encrypted_result['filename'];
            }
        }

        // Create a temporary file for attachment
        $temp_file = \Drupal::service('file_system')->tempnam('temporary://', 'backup_');
        $temp_file_uri = 'temporary://' . basename($temp_file) . '_' . $final_filename;
        file_put_contents($temp_file_uri, $final_content);

        // Prepare email body with encryption info
        $body = $this->confGet('body', $this->t('Please find the backup file attached.'));
        if ($encrypt_enabled) {
            $body .= "\n\n" . $this->t('Note: The backup file has been encrypted with a password for security. Please use the password you configured to decrypt the file.');
        }

        // Prepare email parameters.
        $params = [
            'subject' => $subject,
            'body' => $body,
            'attachment' => [
                'filename' => $final_filename,
                'filepath' => $temp_file_uri,
                'content' => $final_content,
                'mimetype' => 'application/octet-stream',
            ],
        ];

        $mail_manager = \Drupal::service('plugin.manager.mail');
        $success_count = 0;
        $failed_emails = [];

        // Send email to each recipient
        foreach ($email_addresses as $to) {
            $result = $mail_manager->mail('backup_migrate_email', 'backup_file', $to, \Drupal::languageManager()->getDefaultLanguage()->getId(), $params, $from);

            if ($result['result']) {
                $success_count++;
                \Drupal::logger('backup_migrate_email')->info('Backup file @filename sent successfully to @email.', [
                    '@filename' => $final_filename,
                    '@email' => $to,
                ]);
            } else {
                $failed_emails[] = $to;
                \Drupal::logger('backup_migrate_email')->error('Failed to send backup file @filename to @email.', [
                    '@filename' => $final_filename,
                    '@email' => $to,
                ]);
            }
        }

        // Clean up temporary file
        if (isset($temp_file_uri) && file_exists($temp_file_uri)) {
            unlink($temp_file_uri);
        }

        // Report results
        if ($success_count > 0) {
            $message = $this->t('Backup file @filename sent successfully to @count recipient(s).', [
                '@filename' => $final_filename,
                '@count' => $success_count,
            ]);
            \Drupal::logger('backup_migrate_email')->info($message);
        }

        if (!empty($failed_emails)) {
            $message = $this->t('Failed to send backup file @filename to: @emails', [
                '@filename' => $final_filename,
                '@emails' => implode(', ', $failed_emails),
            ]);
            \Drupal::logger('backup_migrate_email')->error($message);

            if ($success_count === 0) {
                throw new \Exception($message);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function checkWritable()
    {
        $emails = $this->confGet('email');
        if (empty($emails)) {
            throw new \Exception($this->t('No email addresses configured for backup destination.'));
        }

        // Parse multiple email addresses
        $email_addresses = $this->parseEmailAddresses($emails);
        $validator = \Drupal::service('email.validator');

        foreach ($email_addresses as $email) {
            if (!$validator->isValid($email)) {
                throw new \Exception($this->t('Invalid email address: @email', ['@email' => $email]));
            }
        }

        // Check encryption settings
        if ($this->confGet('encrypt', FALSE)) {
            $password = $this->confGet('encrypt_password');
            if (empty($password)) {
                throw new \Exception($this->t('Encryption password is required when encryption is enabled.'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFile($id)
    {
        // Email destinations don't support reading files back.
        return NULL;
    }

    /**
     * {@inheritdoc}
     */
    public function loadFileMetadata(BackupFileInterface $file)
    {
        return $file;
    }

    /**
     * {@inheritdoc}
     */
    public function loadFileForReading(BackupFileInterface $file)
    {
        return $file;
    }

    /**
     * {@inheritdoc}
     */
    public function configSchema(array $params = [])
    {
        $schema = [];

        // Configuration for backup initialization.
        if ($params['operation'] == 'initialize') {
            $schema['fields']['email'] = [
                'type' => 'text',
                'title' => $this->t('Recipient Email Addresses'),
                'description' => $this->t('Enter email addresses separated by commas (e.g., admin@site.com, backup@site.com). This is required.'),
                'required' => TRUE,
            ];

            $schema['fields']['from'] = [
                'type' => 'text',
                'title' => $this->t('Sender Email Address'),
                'description' => $this->t('Enter the sender email address. Leave empty to use the site default.'),
            ];

            $schema['fields']['subject'] = [
                'type' => 'text',
                'title' => $this->t('Email Subject Line'),
                'description' => $this->t('Custom subject for backup emails. Leave empty for default.'),
            ];

            $schema['fields']['body'] = [
                'type' => 'text',
                'title' => $this->t('Email Message Body'),
                'description' => $this->t('Custom message to include in backup emails. Leave empty for default.'),
                'multiline' => TRUE,
            ];

            $schema['fields']['max_size'] = [
                'type' => 'text',
                'title' => $this->t('Maximum File Size'),
                'description' => $this->t('Maximum size for email attachments (e.g., "10MB", "25MB"). Default is 10MB.'),
                'default_value' => '10MB',
            ];

            $schema['fields']['encrypt'] = [
                'type' => 'boolean',
                'title' => $this->t('Encrypt Backup File'),
                'description' => $this->t('Encrypt the backup file with a password before sending. Recommended for sensitive data.'),
                'default_value' => FALSE,
            ];

            $schema['fields']['encrypt_password'] = [
                'type' => 'password',
                'title' => $this->t('Encryption Password'),
                'description' => $this->t('Password to encrypt the backup file. Required if encryption is enabled. Use a strong password.'),
            ];
        }

        return $schema;
    }

    /**
     * Parse comma-separated email addresses.
     * 
     * @param string $emails
     *   Comma-separated email addresses.
     * 
     * @return array
     *   Array of trimmed email addresses.
     */
    private function parseEmailAddresses($emails)
    {
        $addresses = explode(',', $emails);
        $parsed_addresses = [];

        foreach ($addresses as $address) {
            $trimmed = trim($address);
            if (!empty($trimmed)) {
                $parsed_addresses[] = $trimmed;
            }
        }

        return $parsed_addresses;
    }

    /**
     * Encrypt backup file content using ZIP password protection.
     * 
     * @param string $content
     *   File content to encrypt.
     * @param string $filename
     *   Original filename.
     * @param string $password
     *   Password for encryption.
     * 
     * @return array
     *   Array with 'content' and 'filename' keys.
     */
    private function encryptBackupFile($content, $filename, $password)
    {
        // Check if ZIP extension is available
        if (!class_exists('ZipArchive')) {
            \Drupal::logger('backup_migrate_email')->warning('ZipArchive not available, encryption disabled.');
            return ['content' => $content, 'filename' => $filename];
        }

        $temp_dir = \Drupal::service('file_system')->getTempDirectory();
        $zip_filename = $temp_dir . '/backup_' . uniqid() . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zip_filename, \ZipArchive::CREATE) === TRUE) {
            // Add the backup file to the ZIP
            $zip->addFromString($filename, $content);

            // Set password protection
            $zip->setPassword($password);
            $zip->setEncryptionName($filename, \ZipArchive::EM_AES_256);

            $zip->close();

            // Read the encrypted ZIP content
            $encrypted_content = file_get_contents($zip_filename);

            // Clean up temporary ZIP file
            unlink($zip_filename);

            // Return encrypted content with .zip extension
            $encrypted_filename = pathinfo($filename, PATHINFO_FILENAME) . '_encrypted.zip';

            return [
                'content' => $encrypted_content,
                'filename' => $encrypted_filename,
            ];
        } else {
            \Drupal::logger('backup_migrate_email')->error('Failed to create encrypted ZIP file.');
            return ['content' => $content, 'filename' => $filename];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportedOps()
    {
        return [
            'saveFile' => [],
            // Note: We don't advertise listFiles capabilities for UI purposes
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function listFiles(array $filters = [])
    {
        // Email destinations don't store files, return empty array
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function queryFiles(array $filters = [], $sort = 'datestamp', $sort_direction = SORT_DESC, $count = 100, $start = 0)
    {
        // Email destinations don't store files, return empty array
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function countFiles()
    {
        // Email destinations don't store files
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function fileExists($id)
    {
        // Email destinations don't store files
        return FALSE;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteFile($id)
    {
        // Email destinations don't store files, nothing to delete
        return FALSE;
    }

    /**
     * Check if this destination stores files for listing purposes.
     * 
     * @return bool
     *   FALSE because email destinations don't store files.
     */
    public function isStorageDestination()
    {
        return FALSE;
    }
}
