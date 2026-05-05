<?php
/**
 * Plugin Name:       Whaze Term Order for Posts
 * Plugin URI:        https://github.com/whaze/whaze-term-order-for-posts
 * Description:       Order taxonomy terms individually per post, directly from the Gutenberg editor sidebar.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Jérôme Buquet
 * Author URI:        https://profiles.wordpress.org/whaze/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       whaze-term-order-for-posts
 * Domain Path:       /languages
 *
 * @package Whaze\TermOrderPerPost
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WHAZE_TERM_ORDER_FOR_POSTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WHAZE_TERM_ORDER_FOR_POSTS_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/functions.php';
