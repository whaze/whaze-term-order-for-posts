<?php
/**
 * SettingsPage class.
 *
 * @package Whaze\TermOrderPerPost
 */

declare(strict_types=1);

namespace Whaze\TermOrderPerPost\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Whaze\TermOrderPerPost\Registry;

/**
 * Registers the admin settings page and enqueues its React bundle.
 */
final class SettingsPage {

	/**
	 * Hook suffix returned by add_options_page() for this page.
	 */
	private const HOOK_SUFFIX = 'settings_page_whaze-term-order-for-posts';

	/**
	 * Script handle for the admin settings bundle.
	 */
	private const SCRIPT_HANDLE = 'whaze-term-order-for-posts-admin';

	/**
	 * Sets up the settings page.
	 *
	 * @param Registry $registry        The plugin registry (used to resolve programmatic registrations).
	 * @param string   $plugin_dir_path Absolute path to the plugin root (with trailing slash).
	 * @param string   $plugin_dir_url  URL to the plugin root (with trailing slash).
	 */
	public function __construct(
		private readonly Registry $registry,
		private readonly string $plugin_dir_path,
		private readonly string $plugin_dir_url,
	) {}

	/**
	 * Register the settings page under the Settings menu.
	 */
	public function addMenuPage(): void {
		add_options_page(
			__( 'Term Order for Posts', 'whaze-term-order-for-posts' ),
			__( 'Term Order for Posts', 'whaze-term-order-for-posts' ),
			'manage_options',
			'whaze-term-order-for-posts',
			[ $this, 'renderPage' ]
		);
	}

	/**
	 * Output the page shell into which React mounts.
	 */
	public function renderPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap"><div id="whaze-term-order-for-posts-settings"></div></div>';
	}

	/**
	 * Enqueue the admin bundle and its localised data on the plugin's settings page only.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueueAssets( string $hook_suffix ): void {
		if ( self::HOOK_SUFFIX !== $hook_suffix ) {
			return;
		}

		$asset_file = $this->plugin_dir_path . 'assets/build/admin.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			$this->plugin_dir_url . 'assets/build/admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'whazeTermOrderForPostsAdmin',
			[
				'availableTypes'            => ( new AvailableTypesProvider() )->get(),
				'savedRegistrations'        => get_option( SettingsRegistration::OPTION_KEY, [] ),
				'programmaticRegistrations' => $this->resolveProgrammaticRegistrations(),
				'autoApply'                 => (bool) get_option( SettingsRegistration::AUTO_APPLY_KEY, false ),
			]
		);

		// @wordpress/components styles are not loaded automatically outside the block editor.
		wp_enqueue_style( 'wp-components' );

		$this->loadScriptTranslations();
	}

	/**
	 * Derive which registrations come from PHP code rather than the admin option.
	 *
	 * Compares the fully merged registry against the persisted option so the UI
	 * can display code-driven pairs as read-only.
	 *
	 * @return array<int, array{postType: string, taxonomy: string}>
	 */
	private function resolveProgrammaticRegistrations(): array {
		$saved     = get_option( SettingsRegistration::OPTION_KEY, [] );
		$saved_set = [];

		if ( is_array( $saved ) ) {
			foreach ( $saved as $pair ) {
				if ( isset( $pair['postType'], $pair['taxonomy'] ) ) {
					$saved_set[ $pair['postType'] . '|' . $pair['taxonomy'] ] = true;
				}
			}
		}

		$programmatic = [];

		foreach ( $this->registry->all() as $post_type => $taxonomies ) {
			foreach ( $taxonomies as $taxonomy ) {
				if ( ! isset( $saved_set[ $post_type . '|' . $taxonomy ] ) ) {
					$programmatic[] = [
						'postType' => $post_type,
						'taxonomy' => $taxonomy,
					];
				}
			}
		}

		return $programmatic;
	}

	/**
	 * Inject JS translations via wp.i18n.setLocaleData().
	 *
	 * Uses glob() instead of wp_set_script_translations() because the latter resolves JSON files by hashing the script's full URL path,
	 * which differs per installation. Instead, we load all per-source JSON files produced by
	 * `wp i18n make-json` directly — setLocaleData() merges them additively.
	 */
	private function loadScriptTranslations(): void {
		$locale = determine_locale();
		$files  = glob( $this->plugin_dir_path . 'languages/whaze-term-order-for-posts-' . $locale . '-*.json' );

		if ( empty( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			$json = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local filesystem path, not a remote URL
			if ( false === $json ) {
				continue;
			}

			$data = json_decode( $json, true );
			if ( ! isset( $data['locale_data']['messages'] ) ) {
				continue;
			}

			wp_add_inline_script(
				self::SCRIPT_HANDLE,
				'wp.i18n.setLocaleData( ' . wp_json_encode( $data['locale_data']['messages'] ) . ', "whaze-term-order-for-posts" );',
				'before'
			);
		}
	}
}
