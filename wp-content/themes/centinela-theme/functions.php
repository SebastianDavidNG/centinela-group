<?php
/**
 * Centinela Theme Functions
 * 
 * @package Centinela_Theme
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme Setup
 */
function centinela_theme_setup() {
    // Add default posts and comments RSS feed links to head
    add_theme_support('automatic-feed-links');
    
    // Let WordPress manage the document title
    add_theme_support('title-tag');
    
    // Enable support for Post Thumbnails
    add_theme_support('post-thumbnails');
    
    // Register navigation menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'centinela-theme'),
    ));
    
    // Add support for HTML5 markup
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));
}
add_action('after_setup_theme', 'centinela_theme_setup');

/**
 * Enqueue scripts and styles
 */
function centinela_theme_scripts() {
    wp_enqueue_style('centinela-style', get_stylesheet_uri(), array(), '1.0.0');
}
add_action('wp_enqueue_scripts', 'centinela_theme_scripts');

/**
 * API Integration Service Class
 */
class Centinela_API_Service {
    
    private $api_url;
    private $cache_time;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Default to JSONPlaceholder API for demonstration
        $this->api_url = get_option('centinela_api_url', 'https://jsonplaceholder.typicode.com');
        $this->cache_time = get_option('centinela_cache_time', 3600); // 1 hour default
    }
    
    /**
     * Fetch data from external API
     * 
     * @param string $endpoint API endpoint
     * @param array $args Additional arguments
     * @return array|WP_Error Response data or error
     */
    public function fetch_data($endpoint, $args = array()) {
        // Create cache key
        $cache_key = 'centinela_api_' . md5($endpoint . serialize($args));
        
        // Check cache first
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Build full URL
        $url = trailingslashit($this->api_url) . ltrim($endpoint, '/');
        
        // Add query parameters if provided
        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }
        
        // Make API request
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error(
                'api_error',
                sprintf(__('API returned error code: %d', 'centinela-theme'), $response_code)
            );
        }
        
        // Parse JSON response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'json_error',
                __('Failed to parse API response', 'centinela-theme')
            );
        }
        
        // Cache the result
        set_transient($cache_key, $data, $this->cache_time);
        
        return $data;
    }
    
    /**
     * Get posts from API
     * 
     * @param int $limit Number of posts to fetch
     * @return array|WP_Error
     */
    public function get_posts($limit = 10) {
        return $this->fetch_data('posts', array('_limit' => $limit));
    }
    
    /**
     * Get single post from API
     * 
     * @param int $post_id Post ID
     * @return array|WP_Error
     */
    public function get_post($post_id) {
        return $this->fetch_data('posts/' . intval($post_id));
    }
    
    /**
     * Get users from API
     * 
     * @param int $limit Number of users to fetch
     * @return array|WP_Error
     */
    public function get_users($limit = 10) {
        return $this->fetch_data('users', array('_limit' => $limit));
    }
    
    /**
     * Clear API cache
     */
    public function clear_cache() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_centinela_api_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_centinela_api_%'");
    }
}

/**
 * Get API Service instance
 * 
 * @return Centinela_API_Service
 */
function centinela_get_api_service() {
    static $instance = null;
    
    if ($instance === null) {
        $instance = new Centinela_API_Service();
    }
    
    return $instance;
}

/**
 * Shortcode to display API data
 * Usage: [centinela_api endpoint="posts" limit="5"]
 * 
 * @param array $atts Shortcode attributes
 * @return string
 */
function centinela_api_shortcode($atts) {
    $atts = shortcode_atts(array(
        'endpoint' => 'posts',
        'limit' => 5,
    ), $atts);
    
    $api_service = centinela_get_api_service();
    
    // Fetch data based on endpoint
    if ($atts['endpoint'] === 'posts') {
        $data = $api_service->get_posts($atts['limit']);
    } elseif ($atts['endpoint'] === 'users') {
        $data = $api_service->get_users($atts['limit']);
    } else {
        $data = $api_service->fetch_data($atts['endpoint']);
    }
    
    // Handle errors
    if (is_wp_error($data)) {
        return '<div class="api-error">' . esc_html($data->get_error_message()) . '</div>';
    }
    
    // Build output
    ob_start();
    ?>
    <div class="api-data-container">
        <?php if (empty($data)): ?>
            <p><?php _e('No data available.', 'centinela-theme'); ?></p>
        <?php else: ?>
            <?php foreach ($data as $item): ?>
                <div class="api-data-item">
                    <?php if (isset($item['title'])): ?>
                        <h3 class="api-data-title"><?php echo esc_html($item['title']); ?></h3>
                    <?php elseif (isset($item['name'])): ?>
                        <h3 class="api-data-title"><?php echo esc_html($item['name']); ?></h3>
                    <?php endif; ?>
                    
                    <?php if (isset($item['body'])): ?>
                        <div class="api-data-content"><?php echo esc_html($item['body']); ?></div>
                    <?php elseif (isset($item['email'])): ?>
                        <div class="api-data-content">
                            <strong><?php _e('Email:', 'centinela-theme'); ?></strong> <?php echo esc_html($item['email']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($item['id'])): ?>
                        <small class="api-data-meta"><?php printf(__('ID: %d', 'centinela-theme'), $item['id']); ?></small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('centinela_api', 'centinela_api_shortcode');

/**
 * Add admin menu for API settings
 */
function centinela_admin_menu() {
    add_theme_page(
        __('API Settings', 'centinela-theme'),
        __('API Settings', 'centinela-theme'),
        'manage_options',
        'centinela-api-settings',
        'centinela_api_settings_page'
    );
}
add_action('admin_menu', 'centinela_admin_menu');

/**
 * API Settings Page
 */
function centinela_api_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Handle form submission
    if (isset($_POST['centinela_api_settings_nonce']) && 
        wp_verify_nonce($_POST['centinela_api_settings_nonce'], 'centinela_api_settings')) {
        
        update_option('centinela_api_url', sanitize_text_field($_POST['api_url']));
        update_option('centinela_cache_time', intval($_POST['cache_time']));
        
        // Clear cache
        if (isset($_POST['clear_cache'])) {
            $api_service = centinela_get_api_service();
            $api_service->clear_cache();
            echo '<div class="notice notice-success"><p>' . __('Cache cleared successfully!', 'centinela-theme') . '</p></div>';
        }
        
        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'centinela-theme') . '</p></div>';
    }
    
    $api_url = get_option('centinela_api_url', 'https://jsonplaceholder.typicode.com');
    $cache_time = get_option('centinela_cache_time', 3600);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('centinela_api_settings', 'centinela_api_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="api_url"><?php _e('API URL', 'centinela-theme'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="api_url" name="api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text" required>
                        <p class="description"><?php _e('Base URL for the external API (e.g., https://api.example.com)', 'centinela-theme'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cache_time"><?php _e('Cache Duration (seconds)', 'centinela-theme'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="cache_time" name="cache_time" value="<?php echo esc_attr($cache_time); ?>" class="small-text" min="0">
                        <p class="description"><?php _e('How long to cache API responses (3600 = 1 hour)', 'centinela-theme'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Settings', 'centinela-theme'); ?>">
                <input type="submit" name="clear_cache" class="button" value="<?php _e('Clear Cache', 'centinela-theme'); ?>">
            </p>
        </form>
    </div>
    <?php
}
