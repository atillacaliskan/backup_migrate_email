# Installation and Setup Guide

## Quick Start

1. **Enable the module**:

   ```bash
   drush en backup_migrate_email
   drush cr
   ```

2. **Configure the destination**:

   - Go to `/admin/config/development/backup_migrate`
   - Click "Destinations" tab
   - Click "Add Destination"
   - Select "Email" from the dropdown
   - Fill in the required fields:
     - Email Address: recipient@example.com
     - Maximum File Size: 10MB (adjust as needed)

3. **Create a backup**:
   - Go to "Quick Backup" tab
   - Select your email destination
   - Click "Backup Database"

## Email Configuration Requirements

Your Drupal site must be configured to send emails. Common configurations:

### SMTP Module (Recommended)

```bash
drush en smtp
```

Configure at `/admin/config/system/smtp`

### System Mail

Ensure your server can send emails via PHP's mail() function.

## File Size Considerations

- Most email providers limit attachments to 10-25MB
- Gmail: 25MB
- Outlook: 20MB
- Yahoo: 25MB

Adjust the "Maximum File Size" setting accordingly.

## Troubleshooting

1. **Check logs**: `/admin/reports/dblog`
2. **Test email**: Send a test email from `/admin/config/system/site-information`
3. **Check spam folder**: Backup emails might be filtered
4. **File size**: Ensure backup is under the limit

## Security Notes

- Email is not encrypted in transit by default
- Consider using SMTP with TLS/SSL
- Be cautious with sensitive database content
- Consider password-protecting backup files if your mail system supports it
