<?php
/**
 * Main plugin class.
 *
 * @package Whaze\TermOrderPerPost
 */

declare(strict_types=1);

namespace Whaze\TermOrderPerPost;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Whaze\TermOrderPerPost\Admin\SettingsPage;
use Whaze\TermOrderPerPost\Admin\SettingsRegistration;
use Whaze\TermOrderPerPost\BlockEditor\EditorAssets;
use Whaze\TermOrderPerPost\TermsFilter;

/**
 * Orchestrates all plugin hooks.
 */
final class Plugin {

	/**
	 * Injects dependencies.
	 *
	 * @param Registry             $registry               Stores post type / taxonomy registrations.
	 * @param OrderStorage         $storage                Reads and writes term order data.
	 * @param OrderCleaner         $cleaner                Keeps order in sync on term changes.
	 * @param RestField            $rest_field             Registers the REST API field.
	 * @param EditorAssets         $editor_assets          Enqueues the block editor script.
	 * @param SettingsRegistration $settings_registration  Registers the settings option.
	 * @param SettingsPage         $settings_page          Registers the admin settings page.
	 * @param TermsFilter|null     $terms_filter           Auto-apply filter; null when opt-in is disabled.
	 */
	public function __construct(
		private readonly Registry $registry,
		private readonly OrderStorage $storage,
		private readonly OrderCleaner $cleaner,
		private readonly RestField $rest_field,
		private readonly EditorAssets $editor_assets,
		private readonly SettingsRegistration $settings_registration,
		private readonly SettingsPage $settings_page,
		private readonly ?TermsFilter $terms_filter = null,
	) {}

	/**
	 * Register all plugin hooks.
	 */
	public function register(): void {
		// Priority 99: must run after theme/plugin whaze_term_order_for_posts_register() calls (priority 10).
		add_action( 'init', [ $this, 'registerPostMeta' ], 99 );
		add_action( 'rest_api_init', [ $this->rest_field, 'register' ] );
		add_action( 'set_object_terms', [ $this->cleaner, 'onSetObjectTerms' ], 10, 6 );
		add_action( 'enqueue_block_editor_assets', [ $this->editor_assets, 'enqueue' ] );

		add_action( 'admin_init', [ $this->settings_registration, 'registerSetting' ] );
		add_action( 'rest_api_init', [ $this->settings_registration, 'registerSetting' ] );
		add_action( 'admin_menu', [ $this->settings_page, 'addMenuPage' ] );
		add_action( 'admin_enqueue_scripts', [ $this->settings_page, 'enqueueAssets' ] );

		if ( null !== $this->terms_filter ) {
			$this->terms_filter->register();
		}
	}

	/**
	 * Register the post meta so it is accessible via the REST API and Gutenberg.
	 *
	 * Called on `init` (after `whaze_term_order_for_posts_register()` calls have run)
	 * so we can target only the relevant post types.
	 */
	public function registerPostMeta(): void {
		foreach ( array_keys( $this->registry->all() ) as $post_type ) {
			register_post_meta(
				$post_type,
				OrderStorage::META_KEY,
				[
					'type'          => 'string',
					'description'   => 'Serialised JSON map of taxonomy => ordered term IDs.',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
				]
			);
		}
	}
}
