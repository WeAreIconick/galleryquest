=== Gallery Quest ===
Contributors: iconick
Tags: gallery, images, filter, block
Requires at least: 6.1
Tested up to: 6.8
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A filterable gallery block plugin for WordPress. Each gallery post contains multiple images, and each image can have its own taxonomies for filtering.

## Features

- Custom post type for gallery collections
- Block editor sidebar panel for managing gallery images
- Taxonomy-based filtering (character, artist, rarity)
- Server-side filtering via REST API
- GLightbox integration for image viewing
- Responsive grid layout
- Mobile-first design with touch-friendly controls

## Installation

1. Upload the plugin to `/wp-content/plugins/gallery-quest/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Install dependencies: `npm install`
4. Build assets: `npm run build`

## Development

```bash
# Install dependencies
npm install

# Start development mode with hot reload
npm start

# Build for production
npm run build

# Create plugin zip
npm run plugin-zip
```

## Usage

1. Create a new "Gallery" post
2. Use the "Gallery Images" panel in the sidebar to add images
3. Assign taxonomies to images in the Media Library
4. Insert the "Gallery Quest" block on any page/post
5. Select your gallery and configure display options

## Requirements

- WordPress 6.1+
- PHP 7.4+
- Node.js 16+ (for development)

## License

GPL-2.0-or-later
