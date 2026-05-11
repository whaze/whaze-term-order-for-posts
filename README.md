# Whaze Term Order for Posts

A WordPress plugin that enables per-post custom ordering of taxonomy terms, directly from the Gutenberg editor sidebar.

## Requirements

- PHP 8.2+
- WordPress 6.0+

## Installation

```bash
composer install
npm install
npm run build
```

## Usage

### Via the admin settings page

Go to **Settings → Term Order for Posts** to enable term ordering for any post type / taxonomy combination — no code required.

### Via code (optional)

Register a post type / taxonomy combination programmatically (on `init` or later):

```php
add_action( 'init', function () {
    whaze_term_order_for_posts_register( 'post', 'category' );
    whaze_term_order_for_posts_register( 'movie', 'genre' );
} );
```

Programmatic registrations are merged with admin-saved ones and shown as read-only in the settings page.

### Retrieve ordered terms

```php
$terms = whaze_term_order_for_posts_get_terms( get_the_ID(), 'category' );
```

Falls back to `wp_get_object_terms()` if no custom order is defined.

## REST API

The `term_order` field is added to the REST response for all registered post types:

```json
{
  "term_order": {
    "category": [3, 7, 12]
  }
}
```

Readable and writable via `PATCH /wp/v2/posts/:id`.

## Development

```bash
# PHP linting
composer run phpcs

# PHP auto-fix
composer run phpcbf

# JS build (production)
npm run build

# JS build (watch)
npm run start

# JS lint
npm run lint:js

# Generate .pot + update translations (requires WP-CLI)
npm run i18n
```

## Licence

GPL-2.0-or-later
