# USA Interactive Map Plugin

A flexible WordPress plugin that displays any post type data on an interactive USA map. Perfect for showing locations, offices, representatives, events, or any location-based content.

## Features

- 🗺️ **Interactive SVG Map** - Click any state to view associated content
- 📝 **Any Post Type** - Works with posts, pages, or custom post types
- 🎨 **Fully Customizable** - Colors, display modes, and content fields
- 📱 **Responsive Design** - Works on all devices
- ⚡ **Performance Optimized** - Built-in caching system
- 🔧 **Developer Friendly** - Hooks, filters, and template overrides
- 🌐 **Multiple Display Modes** - Tooltip, sidebar, modal, or below map
- 🔍 **Search Integration** - Optional search within state results
- 📊 **Count Badges** - Show post counts on each state
- 💾 **Import/Export** - Backup and share your settings

## Installation

1. Upload the `usa-interactive-map` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **USA Map** in your WordPress admin menu
4. Configure your settings and add the shortcode to any page

## Quick Start

### Basic Usage

Add the map to any page or post:

```
[usa_map]
```

### Shortcode Parameters

```
[usa_map 
  post_types="post,page,office"
  state_field="location_state"
  max_width="1000px"
  class="my-custom-map"
]
```

**Available Parameters:**
- `post_types` - Override selected post types (comma-separated)
- `state_field` - Override the state field meta key
- `max_width` - Set maximum width of the map
- `class` - Add custom CSS classes

## Configuration

### 1. General Settings

- **Post Types**: Select which post types to display on the map
- **State Field**: Choose how state data is stored (meta field, taxonomy, ACF, etc.)
- **State Format**: Full names (California) or abbreviations (CA)

### 2. Display Options

- **Display Mode**: 
  - Tooltip (popup on click)
  - Sidebar (fixed sidebar)
  - Modal (full modal window)
  - Below (results appear below map)
- **Results Per Page**: Number of posts to show per state
- **Show Count Badges**: Display post counts on states
- **Enable Search**: Add search box to results

### 3. Field Mapping

Add custom fields to display in results:
- Text fields
- URLs (automatically linked)
- Email addresses (mailto links)
- Images (from media library or URLs)

### 4. Colors & Styling

Customize all map colors:
- Default state color
- Hover state color
- Active state color
- Border colors
- Background colors
- Tooltip/modal colors

### 5. Advanced Settings

- **Caching**: Enable/disable and set cache duration
- **Debug Mode**: Show console logs for troubleshooting
- **Custom CSS**: Add your own styles

## Template Customization

Override default templates by copying them to your theme:

```
/your-theme/usa-map/tooltip-default.php
/your-theme/usa-map/result-item.php
```

## Hooks & Filters

### Actions

```php
// After map initialization
do_action('usa_map_init');

// After post fields in results
do_action('usa_map_after_post_fields', $post, $settings);

// Additional post actions
do_action('usa_map_post_actions', $post, $settings);

// When cache is cleared
do_action('usa_map_cache_cleared');
```

### Filters

```php
// Modify query arguments
add_filter('usa_map_state_query_args', function($args, $state, $post_types) {
    // Customize query
    return $args;
}, 10, 3);

// Modify state data
add_filter('usa_map_states_data', function($states) {
    // Add/modify states
    return $states;
});
```

## JavaScript Events

```javascript
// Map initialized
jQuery('.usa-map-wrapper').on('usa-map:initialized', function(e, mapInstance) {
    console.log('Map ready!');
});

// Content loaded
jQuery('.usa-map-wrapper').on('usa-map:content-loaded', function(e, stateName, content) {
    console.log('Loaded content for', stateName);
});

// Map closed
jQuery('.usa-map-wrapper').on('usa-map:closed', function() {
    console.log('Map display closed');
});
```

## Field Type Support

The plugin automatically detects and supports:

- **WordPress Custom Fields** (post meta)
- **Advanced Custom Fields (ACF)**
- **Toolset Types**
- **Pods**
- **Custom Field Suite**
- **Meta Box**
- **Custom Taxonomies**

## Performance Tips

1. **Enable Caching**: Reduces database queries
2. **Optimize Images**: Use appropriate image sizes
3. **Limit Results**: Don't show too many posts per state
4. **Use CDN**: For better asset delivery

## Troubleshooting

### Map not displaying?
- Check that jQuery is loaded
- Enable Debug Mode in Advanced settings
- Check browser console for errors

### States not clickable?
- Ensure state data exists in your posts
- Verify the state field is correctly mapped
- Check that post types are published

### Performance issues?
- Enable caching
- Reduce results per page
- Optimize your database

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- IE 11 (basic support)

## Accessibility

- Keyboard navigation (Tab/Enter to select states)
- Screen reader compatible
- ARIA labels
- High contrast mode support

## License

GPL v2 or later

## Credits

- SVG Map: SimpleMap (modified)
- Icons: Dashicons
- Fonts: Google Fonts (optional)

## Changelog

### Version 2.0.0
- Complete rewrite for flexibility
- Added admin configuration interface
- Multiple display modes
- Field mapping system
- Import/export functionality
- Template system
- Performance improvements

### Version 1.0.0
- Initial release
