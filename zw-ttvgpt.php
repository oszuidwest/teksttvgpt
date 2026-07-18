<?php
/**
 * Plugin Name: ZuidWest Tekst TV GPT
 * Plugin URI: https://github.com/oszuidwest/zw-ttvgpt
 * Description: Genereert automatisch samenvattingen voor Tekst TV met behulp van OpenAI GPT-modellen
 * Version: 0.4.1
 * Author: Streekomroep ZuidWest
 * Author URI: https://www.zuidwesttv.nl
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: zw-ttvgpt
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Requires Plugins: advanced-custom-fields
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Read version from plugin header (single source of truth).
$zw_ttvgpt_plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
define( 'ZW_TTVGPT_VERSION', $zw_ttvgpt_plugin_data['Version'] );
unset( $zw_ttvgpt_plugin_data );

define( 'ZW_TTVGPT_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZW_TTVGPT_URL', plugin_dir_url( __FILE__ ) );

require_once ZW_TTVGPT_DIR . 'vendor/autoload.php';

use ZW_TTVGPT_Core\ApiHandler;
use ZW_TTVGPT_Core\Constants;
use ZW_TTVGPT_Core\Helper;
use ZW_TTVGPT_Core\Logger;
use ZW_TTVGPT_Core\SettingsManager;
use ZW_TTVGPT_Core\SummaryGenerator;
use ZW_TTVGPT_Core\Admin\AdminMenu;

/**
 * Initializes plugin components.
 *
 * @since 1.0.0
 */
function zw_ttvgpt_init(): void {
	$logger = new Logger( SettingsManager::is_debug_mode() );

	$api_handler = new ApiHandler(
		SettingsManager::get_api_key(),
		SettingsManager::get_model(),
		$logger
	);

	new SummaryGenerator(
		$api_handler,
		SettingsManager::get_word_limit(),
		$logger
	);

	if ( is_admin() ) {
		new AdminMenu( $logger );
	}
}
add_action( 'init', 'zw_ttvgpt_init' );

/**
 * Shows an admin notice when ACF is missing.
 *
 * This surfaces the dependency before AJAX generation fails with `acf_unavailable`.
 *
 * @since 1.0.0
 */
function zw_ttvgpt_acf_dependency_notice(): void {
	if ( function_exists( 'update_field' ) ) {
		return;
	}

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__(
		'ZuidWest Tekst TV GPT vereist de Advanced Custom Fields plugin. Activeer ACF om samenvattingen te kunnen genereren.',
		'zw-ttvgpt'
	);
	echo '</p></div>';
}
add_action( 'admin_notices', 'zw_ttvgpt_acf_dependency_notice' );

/**
 * Initializes plugin settings on activation.
 *
 * @since 1.0.0
 */
function zw_ttvgpt_activate(): void {
	if ( ! get_option( Constants::SETTINGS_OPTION_NAME ) ) {
		add_option(
			Constants::SETTINGS_OPTION_NAME,
			Constants::get_default_settings()
		);
	}
}
register_activation_hook( __FILE__, 'zw_ttvgpt_activate' );

/**
 * Removes temporary data on deactivation.
 *
 * @since 1.0.0
 */
function zw_ttvgpt_deactivate(): void {
	Helper::cleanup_transients();
}
register_deactivation_hook( __FILE__, 'zw_ttvgpt_deactivate' );
