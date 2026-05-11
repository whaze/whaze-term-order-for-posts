<?php
/**
 * SettingsRegistration class.
 *
 * @package Whaze\TermOrderPerPost
 */

declare(strict_types=1);

namespace Whaze\TermOrderPerPost\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin option with the WordPress Settings API and REST API.
 */
final class SettingsRegistration {

	/**
	 * WordPress option key for stored registrations.
	 */
	public const OPTION_KEY = 'whaze_term_order_for_posts_registrations';

	/**
	 * Register the option with the Settings API and expose it via the REST API.
	 *
	 * Must be called on both `admin_init` (Settings form / UI) and `rest_api_init`
	 * (so the option appears in and is writable via wp/v2/settings).
	 */
	public function registerSetting(): void {
		register_setting(
			'whaze_term_order_for_posts',
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'description'       => __( 'Post type / taxonomy pairs with custom term ordering enabled.', 'whaze-term-order-for-posts' ),
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => [],
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'                 => 'object',
							'properties'           => [
								'postType' => [ 'type' => 'string' ],
								'taxonomy' => [ 'type' => 'string' ],
							],
							'additionalProperties' => false,
						],
					],
				],
			]
		);
	}

	/**
	 * Validate and sanitize the option value before it is persisted.
	 *
	 * Invalid pairs are silently discarded; a settings error is added for each one
	 * so the admin UI can surface feedback.
	 *
	 * @param mixed $value Raw value from the Settings API or REST request.
	 *
	 * @return array<int, array{postType: string, taxonomy: string}>
	 */
	public function sanitize( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$valid = [];
		$seen  = [];

		foreach ( $value as $pair ) {
			if ( ! isset( $pair['postType'], $pair['taxonomy'] )
				|| ! is_string( $pair['postType'] )
				|| ! is_string( $pair['taxonomy'] )
			) {
				continue;
			}

			$post_type = sanitize_key( $pair['postType'] );
			$taxonomy  = sanitize_key( $pair['taxonomy'] );

			if ( '' === $post_type || '' === $taxonomy ) {
				continue;
			}

			if ( ! post_type_exists( $post_type ) || ! taxonomy_exists( $taxonomy ) ) {
				add_settings_error(
					self::OPTION_KEY,
					'whaze_term_order_for_posts_invalid_pair',
					sprintf(
						/* translators: 1: post type slug, 2: taxonomy slug */
						__( 'Invalid post type or taxonomy: "%1$s" / "%2$s". The pair was not saved.', 'whaze-term-order-for-posts' ),
						esc_html( $post_type ),
						esc_html( $taxonomy )
					)
				);
				continue;
			}

			if ( ! in_array( $taxonomy, get_object_taxonomies( $post_type ), true ) ) {
				add_settings_error(
					self::OPTION_KEY,
					'whaze_term_order_for_posts_incompatible_pair',
					sprintf(
						/* translators: 1: taxonomy slug, 2: post type slug */
						__( 'Taxonomy "%1$s" is not registered for post type "%2$s". The pair was not saved.', 'whaze-term-order-for-posts' ),
						esc_html( $taxonomy ),
						esc_html( $post_type )
					)
				);
				continue;
			}

			$key = $post_type . '|' . $taxonomy;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$valid[]      = [
				'postType' => $post_type,
				'taxonomy' => $taxonomy,
			];
		}

		return $valid;
	}
}
