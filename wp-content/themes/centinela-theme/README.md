# Centinela Theme - WordPress Custom Theme with External API Integration

A modern, responsive WordPress custom theme that integrates with external API systems to fetch and display data dynamically.

## Features

- 🎨 **Modern Design**: Clean, responsive layout that works on all devices
- 🔌 **External API Integration**: Built-in service class for fetching data from external APIs
- 💾 **Smart Caching**: Automatic caching of API responses to improve performance
- 🛠️ **Easy Configuration**: Admin panel for configuring API settings
- 📝 **Shortcode Support**: Easy-to-use shortcodes for displaying API data
- ⚡ **Performance Optimized**: Efficient caching and error handling
- 🔒 **Security**: Built-in sanitization and validation

## Installation

1. Copy the theme folder to your WordPress installation:
   ```
   wp-content/themes/centinela-theme/
   ```

2. Log in to your WordPress admin panel

3. Navigate to Appearance > Themes

4. Activate "Centinela Theme"

## Configuration

### API Settings

1. Go to Appearance > API Settings in your WordPress admin panel

2. Configure the following settings:
   - **API URL**: The base URL of your external API (e.g., `https://api.example.com`)
   - **Cache Duration**: How long to cache API responses in seconds (default: 3600 = 1 hour)

3. Click "Save Settings"

### Default API

By default, the theme uses [JSONPlaceholder](https://jsonplaceholder.typicode.com) as a demo API. This is perfect for testing and development.

## Usage

### Using the Shortcode

You can display API data anywhere in your posts or pages using the shortcode:

```
[centinela_api endpoint="posts" limit="5"]
```

**Shortcode Parameters:**
- `endpoint` - The API endpoint to fetch from (e.g., "posts", "users")
- `limit` - Number of items to display (default: 5)

**Examples:**

```
[centinela_api endpoint="posts" limit="10"]
[centinela_api endpoint="users" limit="3"]
```

### Programmatic Usage

You can also use the API service in your custom templates:

```php
<?php
// Get API service instance
$api_service = centinela_get_api_service();

// Fetch posts
$posts = $api_service->get_posts(10);

// Fetch users
$users = $api_service->get_users(5);

// Fetch custom endpoint
$data = $api_service->fetch_data('custom-endpoint', array('param' => 'value'));

// Handle errors
if (is_wp_error($data)) {
    echo 'Error: ' . $data->get_error_message();
} else {
    // Process data
    foreach ($data as $item) {
        // Display item
    }
}
?>
```

## API Service Class

The theme includes a powerful `Centinela_API_Service` class with the following methods:

### `fetch_data($endpoint, $args = array())`
Fetch data from any API endpoint with optional query parameters.

**Parameters:**
- `$endpoint` (string) - The API endpoint
- `$args` (array) - Optional query parameters

**Returns:** Array of data or WP_Error on failure

### `get_posts($limit = 10)`
Fetch posts from the API.

**Parameters:**
- `$limit` (int) - Number of posts to fetch

**Returns:** Array of posts or WP_Error on failure

### `get_post($post_id)`
Fetch a single post by ID.

**Parameters:**
- `$post_id` (int) - The post ID

**Returns:** Post data array or WP_Error on failure

### `get_users($limit = 10)`
Fetch users from the API.

**Parameters:**
- `$limit` (int) - Number of users to fetch

**Returns:** Array of users or WP_Error on failure

### `clear_cache()`
Clear all cached API responses.

## Template Files

The theme includes the following template files:

- `index.php` - Main template displaying API data
- `header.php` - Site header
- `footer.php` - Site footer
- `single.php` - Single post template
- `page.php` - Page template
- `style.css` - Theme styles
- `functions.php` - Theme functions and API integration

## Customization

### Changing the API URL

You can change the API URL in two ways:

1. **Via Admin Panel**: Appearance > API Settings
2. **Programmatically**: Use the `update_option()` function:
   ```php
   update_option('centinela_api_url', 'https://your-api.com');
   ```

### Adjusting Cache Duration

Control how long API responses are cached:

```php
update_option('centinela_cache_time', 7200); // 2 hours
```

### Clearing the Cache

Clear the API cache via:

1. **Admin Panel**: Appearance > API Settings > "Clear Cache" button
2. **Programmatically**:
   ```php
   $api_service = centinela_get_api_service();
   $api_service->clear_cache();
   ```

## Styling

The theme uses a modern, clean design with CSS variables for easy customization. Key classes:

- `.api-data-container` - Container for API data
- `.api-data-item` - Individual API data item
- `.api-data-title` - Title of API data item
- `.api-data-content` - Content of API data item
- `.api-error` - Error message styling
- `.api-success` - Success message styling

## Error Handling

The theme includes comprehensive error handling:

- Network errors
- Invalid JSON responses
- HTTP error codes
- Timeouts
- Invalid endpoints

All errors are displayed in a user-friendly format and logged for debugging.

## Performance

### Caching Strategy

- API responses are cached using WordPress transients
- Default cache duration: 1 hour (configurable)
- Cache keys are unique per endpoint and parameters
- Manual cache clearing available via admin panel

### Optimization Tips

1. Set appropriate cache duration based on how often your API data changes
2. Use pagination to limit the number of items fetched
3. Clear cache after API data updates
4. Monitor API rate limits

## Security

The theme implements WordPress security best practices:

- All user input is sanitized and validated
- Nonce verification for forms
- Capability checks for admin functions
- Escaping of output data
- Secure API communication via `wp_remote_get()`

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Active internet connection for API calls

## Troubleshooting

### API Data Not Displaying

1. Check API URL in settings (Appearance > API Settings)
2. Verify API is accessible from your server
3. Clear the cache
4. Check error messages in the output

### Cache Issues

If you're seeing stale data:
1. Go to Appearance > API Settings
2. Click "Clear Cache"
3. Refresh your page

### Network Errors

If you see network error messages:
1. Verify your server can make outbound HTTP requests
2. Check firewall settings
3. Verify API endpoint is correct
4. Check API rate limits

## Development

### File Structure

```
centinela-theme/
├── style.css           # Theme styles and metadata
├── functions.php       # Theme functions and API service
├── index.php          # Main template
├── header.php         # Header template
├── footer.php         # Footer template
├── single.php         # Single post template
└── page.php           # Page template
```

### Extending the API Service

To add custom API methods, extend the `Centinela_API_Service` class in your child theme:

```php
class My_Custom_API_Service extends Centinela_API_Service {
    public function get_custom_data() {
        return $this->fetch_data('custom-endpoint');
    }
}
```

## License

This theme is licensed under the GNU General Public License v2 or later.

## Support

For issues, questions, or contributions, please visit:
https://github.com/SebastianDavidNG/centinela-group

## Credits

- Built with WordPress
- Uses JSONPlaceholder for demo API
- Modern CSS design patterns
- WordPress coding standards

## Changelog

### Version 1.0.0
- Initial release
- External API integration
- Caching system
- Admin settings panel
- Shortcode support
- Responsive design
- Error handling
