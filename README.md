# Backup Migrate Email Destination

This module extends the Backup and Migrate module to provide an email destination, allowing you to send backup files directly via email with support for multiple recipients and encryption.

## Features

- ✅ **Multiple Recipients**: Send backups to multiple email addresses
- ✅ **File Encryption**: Password-protect backup files using ZIP encryption
- ✅ **File Size Limits**: Configure maximum attachment sizes
- ✅ **Custom Messages**: Customize email subject and body
- ✅ **Logging**: Detailed logging of successful and failed sends
- ✅ **Error Handling**: Robust error handling and validation

## Requirements

- Drupal 10 or 11
- Backup and Migrate module
- PHP ZIP extension (for encryption feature)

## Installation

1. Copy this module to your `modules/custom` directory
2. Install dependencies: `composer require drupal/symfony_mailer`
3. Enable the module: `drush en backup_migrate_email symfony_mailer`
4. Configure mail system: `drush config:set mailsystem.settings defaults.sender symfony_mailer`
5. Clear the cache: `drush cr`

## Configuration

1. Go to Administration > Configuration > Development > Backup and Migrate
2. Navigate to the "Destinations" tab
3. Click "Add Destination"
4. Select "Email" as the destination type
5. Configure the following settings:
   - **Recipient Email Addresses**: **[REQUIRED]** Multiple emails separated by commas (e.g., `admin@site.com, backup@site.com`)
   - **Sender Email Address**: The "from" email address (optional, uses site default if empty)
   - **Email Subject Line**: Custom subject for backup emails (optional)
   - **Email Message Body**: Custom message content for backup emails (optional)
   - **Maximum File Size**: Maximum attachment size (e.g., "10MB", "25MB") - most email providers limit this to 10-25MB
   - **Encrypt Backup File**: Enable password protection for backup files
   - **Encryption Password**: Strong password for file encryption (required if encryption enabled)

## Multiple Recipients

You can send backups to multiple recipients by entering comma-separated email addresses:

```
admin@site.com, backup@site.com, security@company.com
```

## File Encryption

When encryption is enabled:

1. ✅ **AES-256 Encryption**: Uses strong ZIP AES-256 encryption
2. ✅ **Password Protection**: Files are protected with your chosen password
3. ✅ **Automatic Naming**: Encrypted files get `_encrypted.zip` suffix
4. ✅ **Email Notification**: Recipients are notified that the file is encrypted

### Encryption Example:

- Original file: `backup-2025-07-28.mysql.gz`
- Encrypted file: `backup-2025-07-28_encrypted.zip`

## Security Best Practices

- **Strong Passwords**: Use complex passwords for encryption (12+ characters with mixed case, numbers, symbols)
- **Secure Communication**: Share encryption passwords through secure channels (not email)
- **Regular Updates**: Change encryption passwords regularly
- **File Size**: Keep backups small to avoid email delivery issues

## Troubleshooting

### Email Not Delivered

- Check your site's mail configuration
- Verify SMTP settings if using custom mail transport
- Check spam folders
- Review logs: `drush watchdog:show --type=backup_migrate_email`

### Encryption Issues

- Ensure PHP ZIP extension is installed: `php -m | grep zip`
- Check file permissions in temp directory
- Verify password strength and complexity

### Multiple Recipients Issues

- Validate all email addresses are correct
- Check for extra spaces or special characters
- Use comma separation only (no semicolons)

## Important Notes

- **File Size Limits**: Most email servers have attachment size limits (typically 10-25MB)
- **Email Delivery**: This module relies on your Drupal site's mail configuration
- **Storage**: Email destinations are one-way only - files cannot be read back
- **Encryption**: Requires PHP ZIP extension for password protection features

## Usage

1. Create a backup schedule or perform a manual backup
2. Select the email destination you configured
3. The backup file will be automatically sent to the specified email address
4. Check the logs at Administration > Reports > Recent log messages for delivery status

## Troubleshooting

- **Email not received**: Check your spam folder and verify your site's email configuration
- **File too large**: Reduce the backup size or adjust the maximum file size setting
- **Permission errors**: Ensure the module has proper permissions to send emails
- **Check logs**: View the watchdog logs for detailed error messages

## Support

This module is provided as-is. For issues related to the core Backup and Migrate functionality, please refer to the main Backup and Migrate module documentation.
