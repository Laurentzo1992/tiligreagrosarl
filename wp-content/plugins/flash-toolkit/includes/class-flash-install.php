<?php
/**
 * Installation related functions and actions.
 *
 * @class    FT_Install
 * @version  1.0.0
 * @package  FlashToolkit/Classes
 * @category Admin
 * @author   ThemeGrill
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FT_Install Class.
 */
class FT_Install {

	/** @var array DB updates and callbacks that need to be run per version */
	private static $db_updates = array(
		'1.0.0' => array(
			'flash_update_100_db_version',
		),
	);

	/** @var object Background update class */
	private static $background_updater;

	/**
	 * Hook in tabs.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'check_version' ), 5 );
		add_action( 'init', array( __CLASS__, 'init_background_updater' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'install_actions' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Init background updates.
	 */
	public static function init_background_updater() {
		include_once( dirname( __FILE__ ) . '/class-flash-background-updater.php' );
		self::$background_updater = new FT_Background_Updater();
	}

	/**
	 * Check FlashToolkit version and run the updater is required.
	 *
	 * This check is done on all requests and runs if the versions do not match.
	 */
	public static function check_version() {
		if ( ! defined( 'IFRAME_REQUEST' ) && get_option( 'flash_toolkit_version' ) !== FT()->version ) {
			self::install();
			do_action( 'flash_toolkit_updated' );
		}
	}

	/**
	 * Install actions when a update button is clicked within the admin area.
	 *
	 * This function is hooked into admin_init to affect admin only.
	 */
	public static function install_actions() {
		if ( ! empty( $_GET['do_update_flash_toolkit'] ) ) {
			self::update();
			FT_Admin_Notices::add_notice( 'update' );
		}
		if ( ! empty( $_GET['force_update_flash_toolkit'] ) ) {
			do_action( 'wp_flash_updater_cron' );
			wp_safe_redirect( admin_url( 'themes.php' ) );
		}
	}

	/**
	 * Install FT.
	 */
	public static function install() {
		global $wpdb;

		// Check if we are not already running this routine.
		if ( 'yes' === get_transient( 'ft_installing' ) ) {
			return;
		}

		// If we made it till here nothing is running yet, lets set the transient now.
		set_transient( 'ft_installing', 'yes', MINUTE_IN_SECONDS * 10 );

		if ( ! defined( 'FT_INSTALLING' ) ) {
			define( 'FT_INSTALLING', true );
		}

		// Ensure needed classes are loaded.
		include_once( dirname( __FILE__ ) . '/admin/class-flash-admin-notices.php' );

		self::create_roles();

		// Register post types
		FT_Post_Types::register_post_types();
		FT_Post_Types::register_taxonomies();

		// Queue upgrades wizard
		$current_ft_version = get_option( 'flash_toolkit_version', null );
		$current_db_version = get_option( 'flash_toolkit_db_version', null );

		FT_Admin_Notices::remove_all_notices();

		// No versions? This is a new install :)
		if ( is_null( $current_ft_version ) && is_null( $current_db_version ) && apply_filters( 'flash_toolkit_enable_setup_wizard', true ) ) {
			set_transient( '_flash_activation_redirect', 1, 30 );
		}

		if ( ! is_null( $current_db_version ) && version_compare( $current_db_version, max( array_keys( self::$db_updates ) ), '<' ) ) {
			FT_Admin_Notices::add_notice( 'update' );
		} else {
			self::update_db_version();
		}

		self::update_ft_version();

		delete_transient( 'ft_installing' );
		// Flush rules after install
		do_action( 'flash_toolkit_flush_rewrite_rules' );

		/*
		 * Deletes all expired transients. The multi-table delete syntax is used
		 * to delete the transient record from table a, and the corresponding
		 * transient_timeout record from table b.
		 *
		 * Based on code inside core's upgrade_network() function.
		 */
		$sql = "DELETE a, b FROM $wpdb->options a, $wpdb->options b
			WHERE a.option_name LIKE %s
			AND a.option_name NOT LIKE %s
			AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
			AND b.option_value < %d";
		$wpdb->query( $wpdb->prepare( $sql, $wpdb->esc_like( '_transient_' ) . '%', $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) );

		// Trigger action.
		do_action( 'flash_toolkit_installed' );
	}

	/**
	 * Removes Pro notice data from database.
	 *
	 * @since 1.2.0
	 */
	public static function deactivate() {

		$users = get_users();

		foreach ( $users as $user ) {
			if ( get_user_meta( $user->ID, 'flash_pro_notice_temporary_ignore', true ) ) {
				delete_user_meta( $user->ID, 'flash_pro_notice_temporary_ignore' );
			}

			if ( get_user_meta( $user->ID, 'flash_pro_notice_permanent_ignore', true ) ) {
				delete_user_meta( $user->ID, 'flash_pro_notice_permanent_ignore' );
			}
		}

		if ( get_option( 'flash_pro_notice_start_time' ) ) {
			delete_option( 'flash_pro_notice_start_time' );
		}

		// Trigger action.
		do_action( 'flash_toolkit_deactivate' );

	}

	/**
	 * Update FT version to current.
	 */
	private static function update_ft_version() {
		delete_option( 'flash_toolkit_version' );
		add_option( 'flash_toolkit_version', FT()->version );
	}

	/**
	 * Push all needed DB updates to the queue for processing.
	 */
	private static function update() {
		$current_db_version = get_option( 'flash_toolkit_db_version' );
		$update_queued      = false;

		foreach ( self::$db_updates as $version => $update_callbacks ) {
			if ( version_compare( $current_db_version, $version, '<' ) ) {
				foreach ( $update_callbacks as $update_callback ) {
					self::$background_updater->push_to_queue( $update_callback );
					$update_queued = true;
				}
			}
		}

		if ( $update_queued ) {
			self::$background_updater->save()->dispatch();
		}
	}

	/**
	 * Update DB version to current.
	 *
	 * @param string $version
	 */
	public static function update_db_version( $version = null ) {
		delete_option( 'flash_toolkit_db_version' );
		add_option( 'flash_toolkit_db_version', is_null( $version ) ? FT()->version : $version );
	}

	/**
	 * Create roles and capabilities.
	 */
	public static function create_roles() {
		global $wp_roles;

		if ( ! class_exists( 'WP_Roles' ) ) {
			return;
		}

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}

		$capabilities = self::get_core_capabilities();

		foreach ( $capabilities as $cap_group ) {
			foreach ( $cap_group as $cap ) {
				$wp_roles->add_cap( 'administrator', $cap );
			}
		}
	}

	/**
	 * Get capabilities for FlashToolkit.
	 *
	 * @return array
	 */
	private static function get_core_capabilities() {
		$capabilities = array();

		$capabilities['core'] = array(
			'manage_flash_toolkit',
		);

		$capability_types = array( 'portfolio' );

		foreach ( $capability_types as $capability_type ) {

			$capabilities[ $capability_type ] = array(
				// Post type
				"edit_{$capability_type}",
				"read_{$capability_type}",
				"delete_{$capability_type}",
				"edit_{$capability_type}s",
				"edit_others_{$capability_type}s",
				"publish_{$capability_type}s",
				"read_private_{$capability_type}s",
				"delete_{$capability_type}s",
				"delete_private_{$capability_type}s",
				"delete_published_{$capability_type}s",
				"delete_others_{$capability_type}s",
				"edit_private_{$capability_type}s",
				"edit_published_{$capability_type}s",

				// Terms
				"manage_{$capability_type}_terms",
				"edit_{$capability_type}_terms",
				"delete_{$capability_type}_terms",
				"assign_{$capability_type}_terms",
			);
		}

		return $capabilities;
	}

	/**
	 * Remove roles and capabilities.
	 */
	public static function remove_roles() {
		global $wp_roles;

		if ( ! class_exists( 'WP_Roles' ) ) {
			return;
		}

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}

		$capabilities = self::get_core_capabilities();

		foreach ( $capabilities as $cap_group ) {
			foreach ( $cap_group as $cap ) {
				$wp_roles->remove_cap( 'administrator', $cap );
			}
		}
	}

	/**
	 * Display row meta in the Plugins list table.
	 *
	 * @param array  $plugin_meta
	 * @param string $plugin_file
	 *
	 * @return array
	 */
	public static function plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( $plugin_file == FT_PLUGIN_BASENAME ) {
			$new_plugin_meta = array(
				'docs'    => '<a href="' . esc_url( apply_filters( 'flash_toolkit_docs_url', 'http://docs.themegrill.com/flash/' ) ) . '" title="' . esc_attr( __( 'View Flash Toolkit Documentation', 'flash-toolkit' ) ) . '">' . __( 'Docs', 'flash-toolkit' ) . '</a>',
				'support' => '<a href="' . esc_url( apply_filters( 'flash_toolkit_support_url', 'http://themegrill.com/support-forum/' ) ) . '" title="' . esc_attr( __( 'Visit Free Customer Support Forum', 'flash-toolkit' ) ) . '">' . __( 'Free Support', 'flash-toolkit' ) . '</a>',
			);

			return array_merge( $plugin_meta, $new_plugin_meta );
		}

		return (array) $plugin_meta;
	}
}

FT_Install::init();
