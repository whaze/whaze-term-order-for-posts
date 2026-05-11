<?php
/**
 * Public API functions and plugin bootstrap.
 *
 * @package Whaze\TermOrderPerPost
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Whaze\TermOrderPerPost\Admin\SettingsPage;
use Whaze\TermOrderPerPost\Admin\SettingsRegistration;
use Whaze\TermOrderPerPost\BlockEditor\EditorAssets;
use Whaze\TermOrderPerPost\OrderCleaner;
use Whaze\TermOrderPerPost\OrderStorage;
use Whaze\TermOrderPerPost\Plugin;
use Whaze\TermOrderPerPost\Registry;
use Whaze\TermOrderPerPost\RestField;
use Whaze\TermOrderPerPost\TermsFilter;

// Shared references across the three bootstrap priority tiers.
$whaze_term_order_for_posts_registry = null;
$whaze_term_order_for_posts_storage  = null;

// Priority 1: instantiate core services and expose globals so the public API functions work.
add_action(
	'plugins_loaded',
	static function () use ( &$whaze_term_order_for_posts_registry, &$whaze_term_order_for_posts_storage ): void {
		$whaze_term_order_for_posts_registry = new Registry();
		$whaze_term_order_for_posts_storage  = new OrderStorage();

		$GLOBALS['whaze_term_order_for_posts_registry'] = $whaze_term_order_for_posts_registry;
		$GLOBALS['whaze_term_order_for_posts_storage']  = $whaze_term_order_for_posts_storage;
	},
	1
);

// Priority 5: populate the registry from the admin-saved option before developer hooks run at priority 10.
add_action(
	'plugins_loaded',
	static function () use ( &$whaze_term_order_for_posts_registry ): void {
		if ( null === $whaze_term_order_for_posts_registry ) {
			return;
		}

		$saved = get_option( SettingsRegistration::OPTION_KEY, [] );

		if ( ! is_array( $saved ) ) {
			return;
		}

		foreach ( $saved as $pair ) {
			if ( ! isset( $pair['postType'], $pair['taxonomy'] )
				|| ! is_string( $pair['postType'] )
				|| ! is_string( $pair['taxonomy'] )
			) {
				continue;
			}

			$whaze_term_order_for_posts_registry->register(
				sanitize_key( $pair['postType'] ),
				sanitize_key( $pair['taxonomy'] )
			);
		}
	},
	5
);

// Priority 20: wire all hooks now that the registry is fully populated.
add_action(
	'plugins_loaded',
	static function () use ( &$whaze_term_order_for_posts_registry, &$whaze_term_order_for_posts_storage ): void {
		if ( null === $whaze_term_order_for_posts_registry || null === $whaze_term_order_for_posts_storage ) {
			return;
		}

		$settings_registration = new SettingsRegistration();
		$settings_page         = new SettingsPage(
			$whaze_term_order_for_posts_registry,
			WHAZE_TERM_ORDER_FOR_POSTS_DIR,
			WHAZE_TERM_ORDER_FOR_POSTS_URL
		);

		$terms_filter = (bool) get_option( SettingsRegistration::AUTO_APPLY_KEY, false )
			? new TermsFilter( $whaze_term_order_for_posts_registry, $whaze_term_order_for_posts_storage )
			: null;

		$plugin = new Plugin(
			$whaze_term_order_for_posts_registry,
			$whaze_term_order_for_posts_storage,
			new OrderCleaner( $whaze_term_order_for_posts_storage, $whaze_term_order_for_posts_registry ),
			new RestField( $whaze_term_order_for_posts_registry, $whaze_term_order_for_posts_storage ),
			new EditorAssets(
				$whaze_term_order_for_posts_registry,
				WHAZE_TERM_ORDER_FOR_POSTS_DIR,
				WHAZE_TERM_ORDER_FOR_POSTS_URL
			),
			$settings_registration,
			$settings_page,
			$terms_filter,
		);

		$plugin->register();
	},
	20
);

/**
 * Register term ordering for a specific post type and taxonomy combination.
 *
 * Must be called on or after `plugins_loaded` (e.g. inside an `init` callback).
 *
 * @param string $post_type The post type slug.
 * @param string $taxonomy  The taxonomy slug.
 */
function whaze_term_order_for_posts_register( string $post_type, string $taxonomy ): void {
	if ( ! isset( $GLOBALS['whaze_term_order_for_posts_registry'] ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'whaze_term_order_for_posts_register() must be called after the plugins_loaded hook.', 'whaze-term-order-for-posts' ),
			'1.0.0'
		);

		return;
	}

	$GLOBALS['whaze_term_order_for_posts_registry']->register( $post_type, $taxonomy );
}

/**
 * Get the terms assigned to a post, sorted by the custom order defined in the editor.
 *
 * Falls back to wp_get_object_terms() if no custom order is defined for the post.
 *
 * @param int    $post_id  The post ID.
 * @param string $taxonomy The taxonomy slug.
 * @param array  $args     Optional. Arguments passed to wp_get_object_terms() as fallback.
 *
 * @return \WP_Term[]|\WP_Error Array of term objects, or WP_Error on failure.
 */
function whaze_term_order_for_posts_get_terms( int $post_id, string $taxonomy, array $args = [] ): array|\WP_Error {
	if ( ! isset( $GLOBALS['whaze_term_order_for_posts_storage'] ) ) {
		return wp_get_object_terms( $post_id, $taxonomy, $args );
	}

	/** @var OrderStorage $storage OrderStorage instance. */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
	$storage = $GLOBALS['whaze_term_order_for_posts_storage'];
	$order   = $storage->getOrder( $post_id, $taxonomy );

	if ( empty( $order ) ) {
		return wp_get_object_terms( $post_id, $taxonomy, $args );
	}

	$args['include'] = $order;
	$args['orderby'] = 'include';

	return wp_get_object_terms( $post_id, $taxonomy, $args );
}
