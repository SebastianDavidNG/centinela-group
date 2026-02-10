# Centinela Theme - Quick Reference

## Installation
```bash
# Copy theme to WordPress
cp -r wp-content/themes/centinela-theme /path/to/wordpress/wp-content/themes/

# Activate via WordPress Admin
WordPress Admin > Appearance > Themes > Activate "Centinela Theme"
```

## Configuration
**Location**: WordPress Admin > Appearance > API Settings

- **API URL**: Base URL for external API (default: `https://jsonplaceholder.typicode.com`)
- **Cache Duration**: Seconds to cache API responses (default: `3600`)
- **Clear Cache**: Button to manually clear all cached API data

## Shortcodes

### Basic Usage
```
[centinela_api endpoint="posts" limit="5"]
[centinela_api endpoint="users" limit="3"]
```

### Parameters
- `endpoint` - API endpoint to fetch from (e.g., "posts", "users")
- `limit` - Number of items to display (default: 5)

## Programmatic API Usage

### Get API Service Instance
```php
$api_service = centinela_get_api_service();
```

### Fetch Posts
```php
$posts = $api_service->get_posts(10);
if (!is_wp_error($posts)) {
    foreach ($posts as $post) {
        echo esc_html($post['title']);
    }
}
```

### Fetch Users
```php
$users = $api_service->get_users(5);
```

### Fetch Single Post
```php
$post = $api_service->get_post(1);
```

### Custom Endpoint
```php
$data = $api_service->fetch_data('custom-endpoint', [
    'param1' => 'value1',
    'param2' => 'value2'
]);
```

### Clear Cache
```php
$api_service->clear_cache();
```

## Template Files

- `index.php` - Homepage with API data display
- `header.php` - Site header
- `footer.php` - Site footer
- `single.php` - Single post view
- `page.php` - Page view
- `template-api-display.php` - Custom API display template

## CSS Classes

### API Data Display
- `.api-data-container` - Container for API data
- `.api-data-item` - Individual data item
- `.api-data-title` - Item title
- `.api-data-content` - Item content
- `.api-data-meta` - Metadata text

### Messages
- `.api-error` - Error message (red)
- `.api-success` - Success message (green)
- `.loading` - Loading state

## Common Tasks

### Change Default API
```php
update_option('centinela_api_url', 'https://your-api.com');
```

### Change Cache Duration
```php
update_option('centinela_cache_time', 7200); // 2 hours
```

### Add Custom API Method
```php
// In child theme functions.php
class My_API_Service extends Centinela_API_Service {
    public function get_custom_data() {
        return $this->fetch_data('my-endpoint');
    }
}
```

### Create Custom Shortcode
```php
function my_api_shortcode($atts) {
    $api = centinela_get_api_service();
    $data = $api->fetch_data('my-endpoint');
    // Process and return HTML
}
add_shortcode('my_api', 'my_api_shortcode');
```

## Error Handling

### Check for Errors
```php
if (is_wp_error($data)) {
    echo $data->get_error_message();
} else {
    // Process data
}
```

### Common Error Types
- `api_error` - API returned non-200 status code
- `json_error` - Failed to parse JSON response
- `WP_Error` - Network/connection errors

## Debugging

### Enable WordPress Debug Mode
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Check Error Logs
```bash
tail -f wp-content/debug.log
```

### Test API Connectivity
```bash
curl -I https://jsonplaceholder.typicode.com/posts
```

## Performance Tips

1. **Cache Duration**: Set based on data update frequency
   - Fast-changing: 300-900s (5-15 min)
   - Hourly updates: 3600s (1 hour)
   - Daily updates: 86400s (24 hours)

2. **Limit Results**: Use `limit` parameter to reduce data volume

3. **Monitor Cache**: Clear cache after API structure changes

## Security Best Practices

1. **Always Escape Output**:
   ```php
   echo esc_html($data['title']);
   echo esc_url($data['url']);
   echo esc_attr($data['attribute']);
   ```

2. **Sanitize Input**:
   ```php
   $url = esc_url_raw($_POST['api_url']);
   $text = sanitize_text_field($_POST['text']);
   ```

3. **Verify Nonces**:
   ```php
   wp_verify_nonce($_POST['nonce'], 'action_name');
   ```

## File Structure
```
centinela-theme/
├── style.css
├── functions.php
├── index.php
├── header.php
├── footer.php
├── single.php
├── page.php
├── template-api-display.php
└── README.md
```

## Requirements
- WordPress 5.0+
- PHP 7.2+
- Internet access for API calls

## Support Resources
- Theme README: `wp-content/themes/centinela-theme/README.md`
- Installation Guide: `INSTALLATION.md`
- Testing Guide: `TESTING.md`
- Implementation Details: `IMPLEMENTATION_SUMMARY.md`

## Quick Troubleshooting

| Issue | Solution |
|-------|----------|
| No API data showing | Check API URL in settings |
| Stale data | Clear cache via admin panel |
| White screen | Check PHP error logs |
| Slow loading | Increase cache duration |
| API errors | Verify API endpoint and connectivity |

---

**Version**: 1.0.0  
**License**: GPL v2 or later  
**Repository**: https://github.com/SebastianDavidNG/centinela-group
