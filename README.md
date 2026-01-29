# Centinela Group - WordPress Custom Theme Project

This repository contains a custom WordPress theme with external API integration capabilities.

## Project Overview

The **Centinela Theme** is a modern WordPress theme designed to fetch and display data from external API systems. It includes a powerful API service class, caching mechanisms, and easy-to-use shortcodes for displaying dynamic content.

## Features

- ✨ Custom WordPress theme with modern, responsive design
- 🔌 Built-in external API integration service
- 💾 Smart caching system for improved performance
- 🛠️ Admin panel for API configuration
- 📝 Shortcode support for easy content integration
- 🔒 Security best practices and error handling
- 📱 Mobile-friendly and responsive design

## Quick Start

1. **Install WordPress** on your server (if not already installed)

2. **Copy the theme** to your WordPress installation:
   ```bash
   cp -r wp-content/themes/centinela-theme /path/to/wordpress/wp-content/themes/
   ```

3. **Activate the theme** in WordPress admin:
   - Go to Appearance > Themes
   - Activate "Centinela Theme"

4. **Configure API settings**:
   - Go to Appearance > API Settings
   - Set your API URL (defaults to JSONPlaceholder for testing)
   - Configure cache duration

5. **Start using the theme**:
   - Use shortcodes: `[centinela_api endpoint="posts" limit="5"]`
   - View the homepage to see API data in action

## Theme Location

The WordPress theme is located at:
```
wp-content/themes/centinela-theme/
```

## Documentation

For detailed documentation, see:
- [Theme README](wp-content/themes/centinela-theme/README.md) - Complete theme documentation
- Theme files include inline documentation

## Theme Structure

```
wp-content/themes/centinela-theme/
├── style.css                    # Theme styles and metadata
├── functions.php                # Theme functions and API service
├── index.php                    # Main template
├── header.php                   # Header template
├── footer.php                   # Footer template
├── single.php                   # Single post template
├── page.php                     # Page template
├── template-api-display.php     # API display template
└── README.md                    # Theme documentation
```

## Usage Examples

### Using Shortcodes

Display API data in posts or pages:

```
[centinela_api endpoint="posts" limit="5"]
[centinela_api endpoint="users" limit="3"]
```

### Programmatic Usage

Use the API service in custom templates:

```php
$api_service = centinela_get_api_service();
$posts = $api_service->get_posts(10);

if (!is_wp_error($posts)) {
    foreach ($posts as $post) {
        echo $post['title'];
    }
}
```

## API Configuration

The theme includes an admin panel for API configuration:

1. Navigate to **Appearance > API Settings**
2. Configure:
   - **API URL**: Base URL for external API
   - **Cache Duration**: How long to cache responses (seconds)
3. Use the **Clear Cache** button to refresh cached data

## Default API

By default, the theme uses [JSONPlaceholder](https://jsonplaceholder.typicode.com) as a demonstration API. This free API provides:
- Posts
- Users
- Comments
- Albums
- Photos

Perfect for testing and development!

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Active internet connection for API calls

## Development

To customize the theme:

1. Create a child theme for customizations
2. Extend the `Centinela_API_Service` class for custom API methods
3. Override template files as needed
4. Follow WordPress coding standards

## Security

The theme implements WordPress security best practices:
- Input sanitization and validation
- Output escaping
- Nonce verification
- Capability checks
- Secure HTTP requests

## License

This project is licensed under the GNU General Public License v2 or later.

## Support

For issues or questions, please visit:
https://github.com/SebastianDavidNG/centinela-group

## Contributing

Contributions are welcome! Please feel free to submit pull requests or open issues.

---

**Built with ❤️ for the Centinela Group**