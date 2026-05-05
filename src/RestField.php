<?php
/**
 * RestField class.
 *
 * @package Whaze\TermOrderPerPost
 */

declare(strict_types=1);

namespace Whaze\TermOrderPerPost;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;

/**
 * Registers and handles the `term_order` REST field on supported post types.
 */
final class RestField {

	/**
	 * Injects dependencies.
	 *
	 * @param Registry     $registry Determines which post types / taxonomies are managed.
	 * @param OrderStorage $storage  Reads and writes order data.
	 */
	public function __construct(
		private readonly Registry $registry,
		private readonly OrderStorage $storage,
	) {}

	/**
	 * Register the `term_order` REST field on all supported post types.
	 */
	public function register(): void {
		foreach ( array_keys( $this->registry->all() ) as $post_type ) {
			register_rest_field(
				$post_type,
				'term_order',
				[
					'get_callback'    => [ $this, 'get' ],
					'update_callback' => [ $this, 'update' ],
					'schema'          => [
						'description'          => __( 'Custom term order per taxonomy.', 'whaze-term-order-for-posts' ),
						'type'                 => 'object',
						'context'              => [ 'view', 'edit' ],
						'additionalProperties' => [
							'type'  => 'array',
							'items' => [ 'type' => 'integer' ],
						],
					],
				]
			);
		}
	}

	/**
	 * Return the stored term order for the post.
	 *
	 * @param array<string, mixed> $post     The prepared post data.
	 * @param string               $field    The field name.
	 * @param WP_REST_Request      $request  The current REST request.
	 *
	 * @return array<string, int[]>
	 */
	public function get( array $post, string $field, WP_REST_Request $request ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $field and $request required by register_rest_field callback signature
		$post_id = absint( $post['id'] );

		return $this->storage->getAllOrders( $post_id );
	}

	/**
	 * Update the stored term order from a REST request.
	 *
	 * @param mixed           $value   The value supplied for the field.
	 * @param \WP_Post        $post    The post object.
	 * @param string          $field   The field name.
	 * @param WP_REST_Request $request The current REST request.
	 *
	 * @return true|\WP_Error True on success, WP_Error on validation failure.
	 */
	public function update( mixed $value, \WP_Post $post, string $field, WP_REST_Request $request ): bool|\WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $field and $request required by register_rest_field callback signature
		if ( ! is_array( $value ) ) {
			return new \WP_Error(
				'term_order_invalid',
				__( 'term_order must be an object mapping taxonomy slugs to arrays of term IDs.', 'whaze-term-order-for-posts' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new \WP_Error(
				'term_order_forbidden',
				__( 'You do not have permission to edit this post.', 'whaze-term-order-for-posts' ),
				[ 'status' => 403 ]
			);
		}

		$post_type = $post->post_type;

		foreach ( $value as $taxonomy => $term_ids ) {
			$taxonomy = sanitize_key( $taxonomy );

			if ( ! $this->registry->isRegistered( $post_type, $taxonomy ) ) {
				continue;
			}

			if ( ! is_array( $term_ids ) ) {
				return new \WP_Error(
					'term_order_invalid_ids',
					sprintf(
						/* translators: %s: taxonomy slug */
						__( 'Term IDs for taxonomy "%s" must be an array.', 'whaze-term-order-for-posts' ),
						esc_html( $taxonomy )
					),
					[ 'status' => 400 ]
				);
			}

			$term_ids = array_map( 'absint', $term_ids );

			$validation = $this->validateTermIds( $post->ID, $taxonomy, $term_ids );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			$this->storage->setOrder( $post->ID, $taxonomy, $term_ids );
		}

		return true;
	}

	/**
	 * Validate that all given term IDs are assigned to the post and belong to the taxonomy.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $taxonomy The taxonomy slug.
	 * @param int[]  $term_ids Term IDs to validate.
	 *
	 * @return true|\WP_Error
	 */
	private function validateTermIds( int $post_id, string $taxonomy, array $term_ids ): bool|\WP_Error {
		$assigned = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );

		if ( is_wp_error( $assigned ) ) {
			return $assigned;
		}

		$assigned = array_map( 'absint', $assigned );
		$invalid  = array_diff( $term_ids, $assigned );

		if ( ! empty( $invalid ) ) {
			return new \WP_Error(
				'term_order_invalid_ids',
				sprintf(
					/* translators: 1: taxonomy slug, 2: comma-separated list of invalid IDs */
					__( 'The following term IDs are not assigned to this post in taxonomy "%1$s": %2$s', 'whaze-term-order-for-posts' ),
					esc_html( $taxonomy ),
					implode( ', ', array_map( 'absint', $invalid ) )
				),
				[ 'status' => 400 ]
			);
		}

		return true;
	}
}
