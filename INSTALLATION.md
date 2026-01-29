# Centinela Theme Installation Guide

This guide will walk you through installing and configuring the Centinela Theme with external API integration.

## Prerequisites

Before you begin, ensure you have:
- WordPress 5.0 or higher installed
- PHP 7.2 or higher
- MySQL 5.6 or higher (or MariaDB 10.1 or higher)
- Server with internet access for API calls

## Step 1: Download the Theme

Clone or download this repository:
```bash
git clone https://github.com/SebastianDavidNG/centinela-group.git
```

## Step 2: Copy Theme to WordPress

Copy the theme folder to your WordPress themes directory:

```bash
# Option 1: Direct copy
cp -r centinela-group/wp-content/themes/centinela-theme /path/to/wordpress/wp-content/themes/

# Option 2: Using symbolic link (for development)
ln -s /path/to/centinela-group/wp-content/themes/centinela-theme /path/to/wordpress/wp-content/themes/centinela-theme
```

## Step 3: Activate the Theme

1. Log in to your WordPress admin panel (usually at `http://yoursite.com/wp-admin`)
2. Navigate to **Appearance** > **Themes**
3. Find "Centinela Theme" in the list
4. Click **Activate**

## Step 4: Configure API Settings

1. In WordPress admin, go to **Appearance** > **API Settings**
2. Configure your API settings:
   - **API URL**: Enter your external API base URL
     - Default: `https://jsonplaceholder.typicode.com` (demo API)
     - Example: `https://api.yourdomain.com/v1`
   - **Cache Duration**: Set cache time in seconds
     - Default: `3600` (1 hour)
     - Recommended: 300-7200 depending on data update frequency
3. Click **Save Settings**

## Step 5: Test the Integration

### Using the Homepage

1. Visit your site's homepage
2. You should see API data displayed automatically
3. Check for any error messages

### Using Shortcodes

Create a new post or page and add:
```
[centinela_api endpoint="posts" limit="5"]
```

**Preview the page** to see the API data displayed.

### Using the API Template

1. Create a new page in WordPress
2. Set the template to "API Data Display"
3. Publish and view the page
4. You'll see multiple sections with different API data

## Step 6: Verify Installation

Run the included test script to verify everything is working:

```bash
cd centinela-group
php test-api-integration.php
```

Expected output:
- ✓ API is accessible
- ✓ All theme files exist
- ✓ PHP syntax is valid

## Common API Configurations

### JSONPlaceholder (Demo/Testing)
```
API URL: https://jsonplaceholder.typicode.com
Available endpoints: posts, users, comments, albums, photos
```

### WordPress REST API
```
API URL: https://yoursite.com/wp-json/wp/v2
Available endpoints: posts, pages, categories, tags, users
```

### Custom REST API
```
API URL: https://api.yourdomain.com/v1
Configure according to your API documentation
```

## Customization

### Changing the Default API

Edit `functions.php` and modify the constructor:
```php
public function __construct() {
    $this->api_url = get_option('centinela_api_url', 'https://your-api.com');
    // ...
}
```

### Adding Custom Endpoints

Add methods to the `Centinela_API_Service` class:
```php
public function get_custom_data() {
    return $this->fetch_data('custom-endpoint');
}
```

### Styling Customization

Edit `style.css` to customize the appearance:
```css
.api-data-container {
    /* Your custom styles */
}
```

## Troubleshooting

### Issue: API Data Not Displaying

**Solution:**
1. Check API URL in settings
2. Verify server can make outbound HTTP requests
3. Check PHP error logs
4. Clear cache using "Clear Cache" button

### Issue: Permission Denied

**Solution:**
```bash
# Set proper file permissions
chmod 644 wp-content/themes/centinela-theme/*.php
chmod 644 wp-content/themes/centinela-theme/style.css
```

### Issue: White Screen

**Solution:**
1. Enable WordPress debug mode
2. Check PHP error logs
3. Verify PHP version compatibility
4. Check file syntax with `php -l filename.php`

### Issue: Cache Not Clearing

**Solution:**
```bash
# Clear WordPress transients via WP-CLI
wp transient delete --all

# Or use the admin panel button
Appearance > API Settings > Clear Cache
```

## Security Considerations

1. **API Keys**: If your API requires authentication, store keys securely:
   ```php
   define('CENTINELA_API_KEY', 'your-secret-key');
   ```

2. **SSL/TLS**: Always use HTTPS for API communications

3. **Rate Limiting**: Implement appropriate cache duration to avoid rate limits

4. **Input Validation**: The theme validates all inputs, but verify your API does too

## Performance Tips

1. **Optimal Cache Duration**:
   - Frequently updated data: 300-900 seconds (5-15 minutes)
   - Hourly updates: 3600 seconds (1 hour)
   - Daily updates: 86400 seconds (24 hours)

2. **Pagination**: Use the `limit` parameter to control data volume:
   ```
   [centinela_api endpoint="posts" limit="10"]
   ```

3. **Monitoring**: Watch for:
   - API response times
   - Cache hit rates
   - Error rates

## Next Steps

After installation, you can:

1. **Customize Templates**: Modify template files to match your design
2. **Add Custom Endpoints**: Extend the API service with your endpoints
3. **Create Child Theme**: For advanced customizations, create a child theme
4. **Add Widgets**: Create custom widgets that use the API service
5. **Implement Authentication**: Add API authentication if required

## Getting Help

- **Documentation**: See README.md in the theme folder
- **Issues**: Report issues on GitHub
- **WordPress Codex**: https://codex.wordpress.org/

## Additional Resources

- [WordPress Theme Development](https://developer.wordpress.org/themes/)
- [WordPress REST API](https://developer.wordpress.org/rest-api/)
- [PHP cURL](https://www.php.net/manual/en/book.curl.php)
- [JSONPlaceholder](https://jsonplaceholder.typicode.com/) - Free API for testing

---

**Congratulations!** Your Centinela Theme with external API integration is now installed and ready to use.
