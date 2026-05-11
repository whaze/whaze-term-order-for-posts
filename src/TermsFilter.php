<?php
/**
 * TermsFilter class.
 *
 * @package Whaze\TermOrderPerPost
 */

declare(strict_types=1);

namespace Whaze\TermOrderPerPost;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Automatically reorders get_the_terms() results using the stored custom order.
 *
 * Hooks into `get_the_terms` rather than `wp_get_object_terms` because WordPress primes
 * the term object cache via update_object_term_cache(), which issues a single bulk query
 * for all taxonomies at once. By the time per-taxonomy calls are made, the cache is already
 * populated with the unordered results and wp_get_object_terms is never called again.
 * The `get_the_terms` filter fires at consumption time — after the cache lookup — so it
 * consistently receives a single post + single taxonomy, regardless of caching.
 */
final class TermsFilter {

	/**
	 * Sets up the filter.
	 *
	 * @param Registry     $registry Registry of registered post type / taxonomy pairs.
	 * @param OrderStorage $storage  Reads stored term order from post meta.
	 */
	public function __construct(
		private readonly Registry $registry,
		private readonly OrderStorage $storage,
	) {}

	/**
	 * Register the get_the_terms filter.
	 */
	public function register(): void {
		add_filter( 'get_the_terms', [ $this, 'reorderTerms' ], 10, 3 );
	}

	/**
	 * Reorder terms for a post according to the stored custom order.
	 *
	 * Skips gracefully for non-object term results and unregistered pairs.
	 *
	 * @param \WP_Term[]|\WP_Error|false $terms    Terms attached to the post, or false/WP_Error on failure.
	 * @param int                        $post_id  Post ID.
	 * @param string                     $taxonomy Taxonomy slug.
	 *
	 * @return \WP_Term[]|\WP_Error|false
	 */
	public function reorderTerms( mixed $terms, int $post_id, string $taxonomy ): mixed {
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return $terms;
		}

		// Only reorder WP_Term objects; skip ID-only or name-only results.
		if ( ! ( $terms[0] instanceof \WP_Term ) ) {
			return $terms;
		}

		if ( ! $this->registry->isRegistered( get_post_type( $post_id ), $taxonomy ) ) {
			return $terms;
		}

		$order = $this->storage->getOrder( $post_id, $taxonomy );

		if ( empty( $order ) ) {
			return $terms;
		}

		return $this->applyOrder( $terms, $order );
	}

	/**
	 * Sort terms according to the stored order, appending unordered terms at the end.
	 *
	 * @param \WP_Term[] $terms Unsorted term objects.
	 * @param int[]      $order Ordered array of term IDs.
	 *
	 * @return \WP_Term[]
	 */
	private function applyOrder( array $terms, array $order ): array {
		$order_map = array_flip( $order );
		$ordered   = [];
		$rest      = [];

		foreach ( $terms as $term ) {
			if ( isset( $order_map[ $term->term_id ] ) ) {
				$ordered[ $order_map[ $term->term_id ] ] = $term;
			} else {
				$rest[] = $term;
			}
		}

		ksort( $ordered );

		return array_values( array_merge( $ordered, $rest ) );
	}
}
