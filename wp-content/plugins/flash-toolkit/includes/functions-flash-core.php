<?php
/**
 * Flash Core Functions.
 *
 * General core functions available on both the front-end and admin.
 *
 * @author   ThemeGrill
 * @category Core
 * @package  FlashToolkit/Functions
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include core functions (available in both admin and frontend).
include( 'functions-flash-deprecated.php' );
include( 'functions-flash-formatting.php' );
include( 'functions-flash-portfolio.php' );

/**
 * is_flash_pro_active - Check if Flash Pro is active.
 * @return bool
 */
function is_flash_pro_active() {
	return false !== strpos( get_option( 'template' ), 'flash-pro' );
}

/**
 * Queue some JavaScript code to be output in the footer.
 * @param string $code
 */
function flash_toolkit_enqueue_js( $code ) {
	global $flash_toolkit_queued_js;

	if ( empty( $flash_toolkit_queued_js ) ) {
		$flash_toolkit_queued_js = '';
	}

	$flash_toolkit_queued_js .= "\n" . $code . "\n";
}

/**
 * Output any queued javascript code in the footer.
 */
function flash_toolkit_print_js() {
	global $flash_toolkit_queued_js;

	if ( ! empty( $flash_toolkit_queued_js ) ) {
		// Sanitize.
		$flash_toolkit_queued_js = wp_check_invalid_utf8( $flash_toolkit_queued_js );
		$flash_toolkit_queued_js = preg_replace( '/&#(x)?0*(?(1)27|39);?/i', "'", $flash_toolkit_queued_js );
		$flash_toolkit_queued_js = str_replace( "\r", '', $flash_toolkit_queued_js );

		$js = "<!-- Flash Toolkit JavaScript -->\n<script type=\"text/javascript\">\njQuery(function($) { $flash_toolkit_queued_js });\n</script>\n";

		/**
		 * social_icons_queued_js filter.
		 * @param string $js JavaScript code.
		 */
		echo apply_filters( 'flash_toolkit_queued_js', $js );

		unset( $flash_toolkit_queued_js );
	}
}

/**
 * Display a FlashToolkit help tip.
 *
 * @param  string $tip Help tip text
 * @param  bool   $allow_html Allow sanitized HTML if true or escape
 * @return string
 */
function flash_toolkit_help_tip( $tip, $allow_html = false ) {
	if ( $allow_html ) {
		$tip = flash_toolkit_sanitize_tooltip( $tip );
	} else {
		$tip = esc_attr( $tip );
	}

	return '<span class="flash-toolkit-help-tip" data-tip="' . $tip . '"></span>';
}

/**
 * Get all available sidebars.
 * @param  array $sidebars
 * @return array
 */
function flash_toolkit_get_sidebars( $sidebars = array() ) {
	global $wp_registered_sidebars;

	foreach ( $wp_registered_sidebars as $sidebar ) {
		if ( ! in_array( $sidebar['name'], apply_filters( 'flash_toolkit_sidebars_exclude', array( 'Display Everywhere' ) ) ) ) {
			$sidebars[ $sidebar['id'] ] = $sidebar['name'];
		}
	}

	return $sidebars;
}

/**
 * FlashToolkit Layout Supported Screens or Post types.
 * @return array
 */
function flash_toolkit_get_layout_supported_screens() {
	return (array) apply_filters( 'flash_toolkit_layout_supported_screens', array( 'post', 'page', 'portfolio', 'jetpack-portfolio' ) );
}

/**
 * Get and include template files.
 *
 * @param string $template_name
 * @param array  $args (default: array())
 * @param string $template_path (default: '')
 * @param string $default_path (default: '')
 */
function flash_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
	if ( ! empty( $args ) && is_array( $args ) ) {
		extract( $args );
	}

	$located = flash_locate_template( $template_name, $template_path, $default_path );

	if ( ! file_exists( $located ) ) {
		_doing_it_wrong( __FUNCTION__, sprintf( '<code>%s</code> does not exist.', $located ), '1.0' );
		return;
	}

	// Allow 3rd party plugin filter template file from their plugin.
	$located = apply_filters( 'flash_get_template', $located, $template_name, $args, $template_path, $default_path );

	do_action( 'flash_toolkit_before_template_part', $template_name, $template_path, $located, $args );

	include( $located );

	do_action( 'flash_toolkit_after_template_part', $template_name, $template_path, $located, $args );
}

/**
 * Locate a template and return the path for inclusion.
 *
 * Note: FT_TEMPLATE_DEBUG_MODE will prevent overrides in themes from taking priority.
 *
 * This is the load order:
 *
 *      yourtheme       /   $template_path   /   $template_name
 *      yourtheme       /   $template_name
 *      $default_path   /   $template_name
 *
 * @param  string $template_name
 * @param  string $template_path (default: '')
 * @param  string $default_path (default: '')
 * @return string
 */
function flash_locate_template( $template_name, $template_path = '', $default_path = '' ) {
	if ( ! $template_path ) {
		$template_path = FT()->template_path();
	}

	if ( ! $default_path ) {
		$default_path = FT()->plugin_path() . '/templates/';
	}

	// Look within passed path within the theme - this is priority.
	$template = locate_template(
		array(
			trailingslashit( $template_path ) . $template_name,
			$template_name,
		)
	);

	// Get default template/
	if ( ! $template || FT_TEMPLATE_DEBUG_MODE ) {
		$template = $default_path . $template_name;
	}

	// Return what we found.
	return apply_filters( 'flash_toolkit_locate_template', $template, $template_name, $template_path );
}

/**
 * Get permalink settings for FlashToolkit independent of the user locale.
 *
 * @since  1.2.0
 * @return array
 */
function flash_get_permalink_structure() {
	if ( did_action( 'admin_init' ) ) {
		flash_switch_to_site_locale();
	}

	$permalinks = wp_parse_args( (array) get_option( 'flash_toolkit_permalinks', array() ), array(
		'portfolio_base'         => '',
		'category_base'          => '',
		'tag_base'               => '',
		'portfolio_has_archive'  => true,
		'use_verbose_page_rules' => false,
	) );

	// Ensure rewrite slugs are set.
	$permalinks['portfolio_rewrite_slug'] = untrailingslashit( empty( $permalinks['portfolio_base'] ) ? _x( 'portfolio', 'slug', 'flash-toolkit' )         : $permalinks['portfolio_base'] );
	$permalinks['category_rewrite_slug']  = untrailingslashit( empty( $permalinks['category_base'] ) ? _x( 'portfolio-category', 'slug', 'flash-toolkit' ) : $permalinks['category_base'] );
	$permalinks['tag_rewrite_slug']       = untrailingslashit( empty( $permalinks['tag_base'] ) ? _x( 'portfolio-tag', 'slug', 'flash-toolkit' )           : $permalinks['tag_base'] );

	if ( function_exists( 'restore_current_locale' ) && did_action( 'admin_init' ) ) {
		flash_restore_locale();
	}
	return $permalinks;
}

/**
 * Switch FlashToolkit to site language.
 *
 * @since 1.2.0
 */
function flash_switch_to_site_locale() {
	if ( function_exists( 'switch_to_locale' ) ) {
		switch_to_locale( get_locale() );

		// Filter on plugin_locale so load_plugin_textdomain loads the correct locale.
		add_filter( 'plugin_locale', 'get_locale' );

		// Init FT locale.
		FT()->load_plugin_textdomain();
	}
}
/**
 * Switch FlashToolkit language to original.
 *
 * @since 1.2.0
 */
function flash_restore_locale() {
	if ( function_exists( 'restore_previous_locale' ) ) {
		restore_previous_locale();

		// Remove filter.
		remove_filter( 'plugin_locale', 'get_locale' );

		// Init FT locale.
		FT()->load_plugin_textdomain();
	}
}

if ( ! function_exists( 'flash_get_google_fonts' ) ) {

	/**
	 * Get Google Font lists.
	 * @return array
	 */
	function flash_get_google_fonts() {
		return apply_filters( 'flash_get_google_fonts', include( FT()->plugin_path() . '/i18n/google-fonts.php' ) );
	}
}

if ( ! function_exists( 'flash_get_fontawesome_icons' ) ) {

	/**
	 * Get fontawesome icon lists.
	 * @return array
	 */
	function flash_get_fontawesome_icons() {
		return apply_filters( 'flash_get_fontawesome_icons', include( FT()->plugin_path() . '/i18n/fontawesome.php' ) );
	}
}

/**
 * Check excerpt value in site origin page builder
 *
 * @param integer $id Post ID.
 * @return bool
 */
function flash_is_so_pagebuilder_active_page( $id ) {
	if ( function_exists( 'siteorigin_panels_is_home' ) ) {
		if ( siteorigin_panels_is_home() || get_post_meta( $id, 'panels_data', true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Get excerpt from the given post.
 *
 * @param  object $post Post Object.
 * @return string $excerpt
 */
function flash_so_pagebuilder_get_the_excerpt( $post ) {
	$excerpt = '';

	if ( flash_is_so_pagebuilder_active_page( $post->ID ) ) {
		if ( flash_has_manual_excerpt( $post ) ) {
			$excerpt = get_the_excerpt( $post );
		}
	} else {
		$excerpt = get_the_excerpt( $post );
	}

	return $excerpt;
}

/**
 * Checks if manual excerpt exists.
 *
 * @param  object $post Post Object.
 * @return bool
 */
function flash_has_manual_excerpt( $post ) {
	if ( '' === $post->post_excerpt ) {
		return false;
	}

	return true;
}

/**
 * Enqueue custom library files, which should be used for widgets.
 */
function flashtoolkit_enqueue_script() {
	global $post;
	$panels_data = get_post_meta( $post->ID, 'panels_data', true );

	if ( ! empty( $panels_data['widgets'] ) ) {

		foreach ( $panels_data['widgets'] as $widget ) {
			// For FT: Slider widget, For FT: Logo widget, For FT: Testimonial widget, For FT: Instagram Slider widget, For FT: Post Slider widget, For FT: WooCommerce Category Slider widget.
			if ( 'FT_Widget_Slider' == $widget['panels_info']['class'] || 'FT_Widget_Logo' == $widget['panels_info']['class'] || 'FT_Widget_Testimonial' == $widget['panels_info']['class'] || 'FT_Widget_InstagramSlider' == $widget['panels_info']['class'] || 'FT_Widget_PostSlider' == $widget['panels_info']['class'] || 'FT_Widget_WcCatSlider' == $widget['panels_info']['class'] ) {
				wp_enqueue_style( 'swiper' );
				wp_enqueue_script( 'swiper' );
			}

			// For FT: Animated Number Counter widget.
			if ( 'FT_Widget_Counter' == $widget['panels_info']['class'] ) {
				wp_enqueue_script( 'waypoints' );
				wp_enqueue_script( 'counterup' );
			}

			// For FT: Portfolio widget.
			if ( 'FT_Widget_Portfolio' == $widget['panels_info']['class'] ) {
				wp_enqueue_script( 'isotope' );
			}

			// For FT: Accordion widget.
			if ( 'FT_Accordion' == $widget['panels_info']['class'] ) {
				wp_enqueue_script( 'jquery-ui-accordion' );
			}

			// For FT: Countdown widget.
			if ( 'FT_Widget_Countdown' == $widget['panels_info']['class'] ) {
				wp_enqueue_script( 'jquery.plugin' );
				wp_enqueue_script( 'jquery.countdown' );
			}

			// For FT: Google Map widget.
			if ( 'FT_Widget_GoogleMap' == $widget['panels_info']['class'] ) {
				wp_enqueue_script( 'googlemap' );
			}

			// For FT: Progress Bar widget.
			if ( 'FT_Widget_ProgressBar' == $widget['panels_info']['class'] ) {
				wp_enqueue_script( 'waypoints' );
			}

			// For FT: Tabs widget, For FT: WooCommerce Product Tab widget.
			if ( 'FT_Tabs' == $widget['panels_info']['class'] || 'FT_Widget_WcProductTab' == $widget['panels_info']['class'] ) {
				wp_enqueue_script( 'jquery-ui-tabs' );
			}

			// For FT: Video Player widget.
			if ( 'FT_Video_Player' == $widget['panels_info']['class'] ) {
				wp_enqueue_style( 'jquery-swipebox' );
				wp_enqueue_script( 'jquery-swipebox' );
			}
		}
	}
}

add_action( 'wp_enqueue_scripts', 'flashtoolkit_enqueue_script', 20 );
