# Testing Guide for Centinela Theme

This guide explains how to test the Centinela WordPress theme with external API integration.

## Prerequisites

- WordPress 5.0+ installed and configured
- PHP 7.2+ 
- Server with internet access
- Theme installed and activated

## Test Categories

### 1. Installation Tests

#### Test 1.1: Theme Installation
**Objective**: Verify theme files are correctly installed

**Steps**:
1. Navigate to `Appearance > Themes` in WordPress admin
2. Verify "Centinela Theme" appears in the list
3. Check theme version is 1.0.0
4. Verify theme description is present

**Expected Result**: Theme appears with correct metadata

#### Test 1.2: Theme Activation
**Objective**: Ensure theme activates without errors

**Steps**:
1. Click "Activate" on Centinela Theme
2. Check for any error messages
3. Verify theme is marked as active

**Expected Result**: Theme activates successfully, no errors

#### Test 1.3: Admin Menu
**Objective**: Verify admin settings page appears

**Steps**:
1. With theme active, go to `Appearance` menu
2. Look for "API Settings" submenu

**Expected Result**: "API Settings" option appears under Appearance

### 2. API Configuration Tests

#### Test 2.1: Default API Configuration
**Objective**: Verify default API settings

**Steps**:
1. Go to `Appearance > API Settings`
2. Check default API URL
3. Check default cache time

**Expected Results**:
- API URL: `https://jsonplaceholder.typicode.com`
- Cache Time: `3600` seconds

#### Test 2.2: Update API Settings
**Objective**: Test saving API configuration

**Steps**:
1. Go to `Appearance > API Settings`
2. Change API URL to a test value
3. Change cache time to 7200
4. Click "Save Settings"
5. Refresh the page

**Expected Result**: Settings persist and display success message

#### Test 2.3: Cache Clearing
**Objective**: Verify cache clearing functionality

**Steps**:
1. Go to `Appearance > API Settings`
2. Click "Clear Cache" button
3. Check for success message

**Expected Result**: "Cache cleared successfully!" message appears

### 3. API Data Fetching Tests

#### Test 3.1: Homepage API Display
**Objective**: Verify API data displays on homepage

**Steps**:
1. Visit your site's homepage
2. Look for "Latest Posts from External API" section
3. Verify post titles and content appear

**Expected Result**: 5 posts from API displayed with titles and content

#### Test 3.2: API Error Handling
**Objective**: Test error handling with invalid API URL

**Steps**:
1. Go to `Appearance > API Settings`
2. Set API URL to `https://invalid-api-url-12345.com`
3. Save settings
4. Visit homepage

**Expected Result**: Error message displayed (not a white screen)

#### Test 3.3: Network Timeout
**Objective**: Verify graceful handling of timeouts

**Steps**:
1. Set API URL to a slow/unresponsive endpoint
2. Visit homepage
3. Wait for response

**Expected Result**: Timeout after 15 seconds with error message

### 4. Shortcode Tests

#### Test 4.1: Basic Shortcode
**Objective**: Test [centinela_api] shortcode

**Steps**:
1. Create a new post or page
2. Add shortcode: `[centinela_api endpoint="posts" limit="3"]`
3. Publish and view

**Expected Result**: 3 posts displayed from API

#### Test 4.2: Users Endpoint
**Objective**: Test users endpoint via shortcode

**Steps**:
1. Create a new post
2. Add shortcode: `[centinela_api endpoint="users" limit="5"]`
3. Publish and view

**Expected Result**: 5 users displayed with names and emails

#### Test 4.3: Invalid Endpoint
**Objective**: Test error handling with invalid endpoint

**Steps**:
1. Create a new post
2. Add shortcode: `[centinela_api endpoint="invalid" limit="5"]`
3. Publish and view

**Expected Result**: Error message displayed, not a crash

### 5. Caching Tests

#### Test 5.1: Cache Hit
**Objective**: Verify caching works

**Steps**:
1. Visit homepage (fresh cache)
2. Note the load time
3. Immediately refresh the page
4. Compare load time

**Expected Result**: Second load is faster (data from cache)

#### Test 5.2: Cache Expiration
**Objective**: Test cache expiration

**Steps**:
1. Set cache time to 60 seconds
2. Visit homepage
3. Wait 61 seconds
4. Visit homepage again

**Expected Result**: Fresh API call made after cache expires

#### Test 5.3: Multiple Endpoints Cached Separately
**Objective**: Verify different endpoints have separate cache

**Steps**:
1. Visit page with posts shortcode
2. Visit page with users shortcode
3. Clear posts cache manually (via database)
4. Refresh both pages

**Expected Result**: Posts refetch, users still cached

### 6. Template Tests

#### Test 6.1: Index Template
**Objective**: Verify homepage template works

**Steps**:
1. Visit site homepage
2. Check for header, content, footer
3. Verify API data section appears

**Expected Result**: Complete page with all sections

#### Test 6.2: Single Post Template
**Objective**: Test single post display

**Steps**:
1. Create a WordPress post
2. View the single post
3. Check layout and formatting

**Expected Result**: Post displays with proper styling

#### Test 6.3: Page Template
**Objective**: Test page template

**Steps**:
1. Create a WordPress page
2. Add some content
3. Publish and view

**Expected Result**: Page displays correctly with theme styling

#### Test 6.4: API Display Template
**Objective**: Test custom API template

**Steps**:
1. Create a new page
2. In Page Attributes, set Template to "API Data Display"
3. Publish and view

**Expected Result**: Multiple sections with different API data

### 7. Security Tests

#### Test 7.1: XSS Prevention
**Objective**: Verify output is escaped

**Steps**:
1. Using API that returns `<script>alert('xss')</script>` in content
2. View page with this content

**Expected Result**: Script tags displayed as text, not executed

#### Test 7.2: CSRF Protection
**Objective**: Verify nonce protection on settings

**Steps**:
1. Attempt to submit settings form without nonce
2. Try submitting with invalid nonce

**Expected Result**: Form submission rejected

#### Test 7.3: SQL Injection
**Objective**: Verify database queries are safe

**Steps**:
1. Set API endpoint with SQL-like syntax: `posts'; DROP TABLE--`
2. Submit form

**Expected Result**: Input sanitized, no database impact

### 8. Responsive Design Tests

#### Test 8.1: Mobile View
**Objective**: Verify mobile responsiveness

**Steps**:
1. Open site on mobile device or use browser dev tools
2. Resize to 375px width
3. Check layout and readability

**Expected Result**: Content displays properly, navigation usable

#### Test 8.2: Tablet View
**Objective**: Test tablet layouts

**Steps**:
1. Resize browser to 768px width
2. Check layout adjustments

**Expected Result**: Layout adapts appropriately

#### Test 8.3: Desktop View
**Objective**: Verify desktop display

**Steps**:
1. View at 1920px width
2. Check max-width containers

**Expected Result**: Content centered, proper spacing

### 9. Performance Tests

#### Test 9.1: Page Load Time
**Objective**: Measure initial page load

**Steps**:
1. Clear all caches
2. Use browser dev tools Network tab
3. Load homepage
4. Record load time

**Expected Result**: < 3 seconds on good connection

#### Test 9.2: Cached Load Time
**Objective**: Measure cached page performance

**Steps**:
1. Load page once (cache populated)
2. Reload and measure time

**Expected Result**: < 1 second with cache

#### Test 9.3: Multiple API Calls
**Objective**: Test performance with many shortcodes

**Steps**:
1. Create page with 10 different shortcodes
2. Measure load time

**Expected Result**: All requests cached efficiently

### 10. Browser Compatibility Tests

#### Test 10.1: Chrome
- [ ] Homepage loads correctly
- [ ] API data displays
- [ ] Admin settings work
- [ ] Shortcodes function

#### Test 10.2: Firefox
- [ ] Homepage loads correctly
- [ ] API data displays
- [ ] Admin settings work
- [ ] Shortcodes function

#### Test 10.3: Safari
- [ ] Homepage loads correctly
- [ ] API data displays
- [ ] Admin settings work
- [ ] Shortcodes function

#### Test 10.4: Edge
- [ ] Homepage loads correctly
- [ ] API data displays
- [ ] Admin settings work
- [ ] Shortcodes function

## Automated Testing

### PHP Syntax Validation

```bash
# Validate all PHP files
cd wp-content/themes/centinela-theme
for file in *.php; do php -l "$file"; done
```

### WordPress Coding Standards

```bash
# Using PHP_CodeSniffer (if installed)
phpcs --standard=WordPress wp-content/themes/centinela-theme/
```

### API Connectivity Test

```bash
# Test API endpoint accessibility
curl -I https://jsonplaceholder.typicode.com/posts
```

## Test Data

### Sample API URLs for Testing

1. **JSONPlaceholder** (default): `https://jsonplaceholder.typicode.com`
2. **WordPress REST API**: `https://yoursite.com/wp-json/wp/v2`
3. **ReqRes**: `https://reqres.in/api`

### Sample Shortcodes

```
[centinela_api endpoint="posts" limit="5"]
[centinela_api endpoint="users" limit="3"]
[centinela_api endpoint="comments" limit="10"]
```

## Troubleshooting Tests

### Debug Mode

Enable WordPress debug mode for detailed error messages:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Check Error Logs

```bash
# View PHP error log
tail -f /var/log/php-errors.log

# View WordPress debug log
tail -f wp-content/debug.log
```

## Test Results Checklist

Use this checklist to track test completion:

- [ ] Installation tests (3 tests)
- [ ] API configuration tests (3 tests)
- [ ] API data fetching tests (3 tests)
- [ ] Shortcode tests (3 tests)
- [ ] Caching tests (3 tests)
- [ ] Template tests (4 tests)
- [ ] Security tests (3 tests)
- [ ] Responsive design tests (3 tests)
- [ ] Performance tests (3 tests)
- [ ] Browser compatibility tests (4 browsers)

## Reporting Issues

When reporting issues, include:

1. WordPress version
2. PHP version
3. Theme version
4. Steps to reproduce
5. Expected vs actual behavior
6. Screenshots if applicable
7. Error logs

## Success Criteria

The theme passes testing if:

✓ All core functionality works
✓ No PHP errors or warnings
✓ API data fetches and displays correctly
✓ Caching functions properly
✓ Security measures prevent common attacks
✓ Responsive on all device sizes
✓ Works across major browsers
✓ Performance is acceptable

---

**Happy Testing!**
