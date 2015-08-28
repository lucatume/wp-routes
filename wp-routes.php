<?php
/**
 * Plugin Name: WP Routes
 * Plugin URI: http://theAverageDev.com
 * Description: Routing for WordPress
 * Version: 1.0
 * Author: theAverageDev
 * Author URI: http://theAverageDev.com
 * License: GPL 2.0
 */

require 'vendor/autoload_52.php';

/**
 * Filters the request parsing process before WordPress does.
 *
 * @param bool         $continue
 * @param WP           $wp
 * @param string|array $extra_query_vars
 *
 * @return bool|void Either a bool for the `$continue` value or void if the parse request is stopped.
 */
function wp_routes_do_parse_request( $continue, WP $wp, $extra_query_vars ) {
	/**
	 * Allows plugin and theme developers to register custom routes to be handled before WordPress
	 * parses the request.
	 *
	 * @since 1.0.0
	 *
	 * @param bool         $bool             Whether or not to parse the request. Default true.
	 * @param WP           $this             Current WordPress environment instance.
	 * @param array|string $extra_query_vars Extra passed query variables.
	 */
	do_action( 'wp-routes/register_routes', $continue, $wp, $extra_query_vars );

	/**
	 * if no echo was produced or the only echo produced is from "*" routes
	 * then continue and let WordPress handle the request; otherwise `die` the
	 * route output.
	 */
	dispatch_or_continue();

	return $continue;
}

add_filter( 'do_parse_request', 'wp_routes_do_parse_request', 1, 3 );
