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

require 'vendor/autoload_52.php';

/**
 * Allows theme and plugin developers to handle the routing before WordPress does.
 *
 * @param       $continue
 * @param    WP $wp
 * @param       $extra_query_vars
 *
 * @return mixed
 */
function tad_routes_do_parse_request( $continue, WP $wp, $extra_query_vars ) {
	/**
	 * Allow plugin and theme developers to register their own routes.
	 */
	do_action( 'tad/routes/register_routes' );

	// if no echo was produced or the only echo produced is from "*" routes
	// then continue and let WordPress handle the request; otherwise `die` the
	// route output.
	dispatch_or_continue();

	return $continue;
}

add_filter( 'do_parse_request', 'tad_routes_do_parse_request', 1, 3 );
