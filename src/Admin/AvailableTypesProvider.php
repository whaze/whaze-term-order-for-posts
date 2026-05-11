<?php
/**
 * AvailableTypesProvider class.
 *
 * @package Whaze\TermOrderPerPost
 */

declare(strict_types=1);

namespace Whaze\TermOrderPerPost\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the matrix of UI-visible post types and their compatible taxonomies.
 */
final class AvailableTypesProvider {

	/**
	 * Return all post types and their associated taxonomies eligible for term ordering.
	 *
	 * Filters post types to those with show_ui and show_in_rest enabled (block-editor
	 * compatible). Filters taxonomies to those with show_ui enabled. Excludes the
	 * built-in `attachment` post type, which is not editable via the block editor.
	 *
	 * @return array<int, array{postType: string, postTypeLabel: string, taxonomies: array<int, array{taxonomy: string, label: string}>}>
	 */
	public function get(): array {
		$post_types = get_post_types(
			[
				'show_ui'      => true,
				'show_in_rest' => true,
			],
			'objects'
		);

		$result = [];

		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			$taxonomies          = get_object_taxonomies( $post_type->name, 'objects' );
			$filtered_taxonomies = [];

			foreach ( $taxonomies as $taxonomy ) {
				if ( ! $taxonomy->show_ui ) {
					continue;
				}

				$filtered_taxonomies[] = [
					'taxonomy' => $taxonomy->name,
					'label'    => $taxonomy->labels->name,
				];
			}

			if ( empty( $filtered_taxonomies ) ) {
				continue;
			}

			$result[] = [
				'postType'      => $post_type->name,
				'postTypeLabel' => $post_type->labels->name,
				'taxonomies'    => $filtered_taxonomies,
			];
		}

		return $result;
	}
}
