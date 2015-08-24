<?php
/**
 * Plugin Name: theAverageDev Routes
 * Plugin URI: http://theAverageDev.com
 * Description: Routing for WordPress
 * Version: 1.0
 * Author: theAverageDev
 * Author URI: http://theAverageDev.com
 * License: GPL 2.0
 */
global $_filters;
add_action( 'all', '_log_filter' );
add_filter( 'all', '_log_filter' );
$_filters = array();
function _log_filter() {
	global $_filters;
	$current_filter = current_filter();
	if ( $current_filter == end( $_filters ) ) {
		return;
	}
	$pattern = '/((pre_)*option_|get_user_|twentyfifteen_|(template|stylesheet)_directory_|sanitize_|wp_(audio|video)|post_type_|load_textdomain_|gettext|.*kses|theme_locale|salt).*$/';
	if (preg_match( $pattern,$current_filter)) {
		return;
	}
	if (in_array($current_filter,$_filters)) {
		return;
	}
	$_filters[] = $current_filter;
}

/**
 * `do_parse_request` filter description
 *
 * Filter whether to parse the request.
 *
 * @since 3.5.0
 *
 * @param bool         $bool             Whether or not to parse the request. Default true.
 * @param WP           $this             Current WordPress environment instance.
 * @param array|string $extra_query_vars Extra passed query variables.
 */
add_filter( 'do_parse_request', 'tad_routes_do_parse_request', 1, 3 );
function tad_routes_do_parse_request( $continue, WP $wp, $extra_query_vars ) {
	if ( preg_match( '~\\/my-route\\/?$~', $_SERVER['REQUEST_URI'] ) ) {
		global $_filters;
		array_pop($_filters);
		echo sprintf( "Filters/actions executed before <code>do_parse_request</code>:\n\n%s", "<ul><li>" . implode( '</li><li>', $_filters ) . "</li></ul>" );
		die();
	}

	return $continue;
}
