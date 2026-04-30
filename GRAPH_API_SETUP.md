# Microsoft Graph API Setup Guide
 
## Overview
 
This document explains how to migrate from Outlook COM to Microsoft Graph API for email sending. This migration eliminates the dependency on Outlook desktop application running on the server.
 
## Benefits of Graph API
 
- ✅ No need for Outlook desktop application on server
- ✅ Emails sent from user's own Office 365 account
- ✅ Sent items appear in user's mailbox (not server)
- ✅ Modern REST API, actively maintained by Microsoft
- ✅ Better reliability and performance
- ✅ Supports multi-user scenarios (each user sends from their own account)
 
## Prerequisites
 
1. **Office 365 / Microsoft 365 subscription**
   - Your organization must have Office 365 or Microsoft 365
   - Users must have Exchange Online mailboxes
 
2. **Azure AD Admin access**
   - Need admin access to Azure Active Directory
   - To register application and configure permissions
 
## Step 1: Register Application in Azure AD
 
1. Go to [Azure Portal](https://portal.azure.com)
2. Navigate to **Azure Active Directory** → **App registrations**
3. Click **New registration**
4. Fill in:
   - **Name**: Email Dispatcher Suite
   - **Supported account types**: Accounts in this organizational directory only (Single tenant)
   - **Redirect URI**: Web → `http://localhost` (or your actual URL)
5. Click **Register**
 
## Step 2: Get Application Credentials
 
1. In your newly registered app, go to **Overview**
2. Copy:
   - **Application (client) ID** → This is your `GRAPH_CLIENT_ID`
   - **Directory (tenant) ID** → This is your `GRAPH_TENANT_ID`
 
3. Go to **Certificates & secrets** → **New client secret**
   - Description: Email Dispatcher Secret
   - Expires: Choose appropriate expiration (recommended: 180 days or 2 years)
   - Click **Add**
4. Copy the **Value** immediately (you won't see it again!) → This is your `GRAPH_CLIENT_SECRET`
 
## Step 3: Configure API Permissions
 
1. Go to **API permissions** in your app
2. Click **Add a permission** → **Microsoft Graph** → **Delegated permissions**
3. Search for and add:
   - `Mail.Send`
   - `Mail.ReadWrite`
4. Click **Add permissions**
 
5. Click **Add a permission** → **Microsoft Graph** → **Application permissions**
6. Search for and add:
   - `Mail.Send`
7. Click **Add permissions**
 
8. Click **Grant admin consent** for your organization (required for application permissions)
 
## Step 4: Configure Environment Variables
 
Add the following to your environment variables or `.env` file:
 
```bash
# Microsoft Graph API Configuration
GRAPH_TENANT_ID=your-tenant-id-here
GRAPH_CLIENT_ID=your-client-id-here
GRAPH_CLIENT_SECRET=your-client-secret-here
GRAPH_REDIRECT_URI=http://localhost
 
# Email sending mode
EMAIL_SENDING_MODE=graph_api
```
 
### On Windows (System Environment Variables):
 
1. Press `Win + R`, type `sysdm.cpl`
2. Go to **Advanced** → **Environment Variables**
3. Add new **System variables**:
   - `GRAPH_TENANT_ID`
   - `GRAPH_CLIENT_ID`
   - `GRAPH_CLIENT_SECRET`
   - `GRAPH_REDIRECT_URI`
   - `EMAIL_SENDING_MODE` = `graph_api`
 
### On Laragon:
 
Edit `C:\laragon\etc\php\php.ini` or add to `.env` file in your project root.
 
## Step 5: Update User Email Configuration
 
Each user needs their Office 365 email configured:
 
1. Login to your application
2. Go to **Settings** → **User Management**
3. Edit user and set their `sender_email` to their Office 365 email address
   - Example: `john.doe@yourcompany.onmicrosoft.com`
 
## Step 6: Switch to Graph API Mode
 
Set the environment variable:
 
```bash
EMAIL_SENDING_MODE=graph_api
```
 
Or edit `config.php` temporarily:
 
```php
define('EMAIL_SENDING_MODE', 'graph_api');
```
 
## Step 7: Test Email Sending
 
1. Go to **Compose** page
2. Create a test email
3. Send to your own email address
4. Check:
   - Email received in your mailbox
   - Email appears in **Sent Items** folder
   - No error messages in application logs
 
## Troubleshooting
 
### Error: "Graph API credentials not configured"
 
**Solution**: Ensure all three environment variables are set:
- `GRAPH_TENANT_ID`
- `GRAPH_CLIENT_ID`
- `GRAPH_CLIENT_SECRET`
 
### Error: "Failed to get Graph API access token"
 
**Possible causes**:
1. Invalid credentials (double-check client secret)
2. Application permissions not granted
3. Network/firewall blocking access to `login.microsoftonline.com`
 
**Solution**:
1. Verify credentials in Azure Portal
2. Grant admin consent for API permissions
3. Check network connectivity
 
### Error: "HTTP error: 401 - Unauthorized"
 
**Possible causes**:
1. Access token expired
2. User email not found in Office 365
3. Insufficient permissions
 
**Solution**:
1. Verify user's Office 365 email is correct
2. Ensure application has `Mail.Send` permission
3. Check if user's mailbox exists in Exchange Online
 
### Error: "HTTP error: 403 - Forbidden"
 
**Possible causes**:
1. Application permissions not configured correctly
2. User doesn't have permission to send from that account
 
**Solution**:
1. Grant admin consent for application permissions
2. Verify user has valid Office 365 license
 
### Error: "HTTP error: 404 - Not Found"
 
**Possible causes**:
1. User email not found in Office 365
2. Tenant ID incorrect
 
**Solution**:
1. Verify user's Office 365 email address
2. Double-check tenant ID in Azure Portal
 
## Switching Back to Outlook COM
 
If you need to switch back to Outlook COM mode:
 
```bash
EMAIL_SENDING_MODE=outlook_com
```
 
Or edit `config.php`:
 
```php
define('EMAIL_SENDING_MODE', 'outlook_com');
```
 
## Security Best Practices
 
1. **Rotate secrets regularly**: Update client secrets every 180 days
2. **Use least privilege**: Only grant necessary API permissions
3. **Monitor usage**: Check Azure AD sign-in logs for suspicious activity
4. **Secure storage**: Never commit secrets to version control
5. **IP restrictions**: Consider restricting app access by IP address in Azure AD
 
## Performance Considerations
 
- **Rate limiting**: Graph API has rate limits (~100 requests per minute)
- **Batch sending**: Current implementation sends emails sequentially with 0.5s delay
- **Async processing**: For large batches, consider implementing async job queue
- **Token caching**: Access tokens are cached for 1 hour by default
 
## Support
 
For issues related to:
- **Azure AD configuration**: Contact your Azure AD administrator
- **Office 365 setup**: Contact your Microsoft 365 administrator
- **Application bugs**: Check application logs in `storage/logs/` directory
 
## Additional Resources
 
- [Microsoft Graph API Documentation](https://docs.microsoft.com/graph/api/resources/mail-api-overview)
- [Azure AD App Registration Guide](https://docs.microsoft.com/azure/active-directory/develop/app-registrations-training-guide)
- [Graph API Permissions Reference](https://docs.microsoft.com/graph/permissions-reference)
 

