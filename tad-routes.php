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
 * Support methods
 */
function _json_post( $request, $response ) {
	$post_id = $request->param( 'id' );
	$post    = get_post( $post_id );
	if ( empty( $post ) ) {
		echo json_encode( [ 'Error' => 'Not a valid post ID' ] );
	} else {
		echo( json_encode( $post ) );
	}
}

function _dispatch() {
	$found = dispatch( null, null, null, true );
	if ( $found ) {
		die( $found );
	}
}


/**
 * Routes
 */
function route_posts() {
	respond( 'GET', '/[i:id]/json', '_json_post' );
}

function route_who_am_i() {
	$user = wp_get_current_user();
	echo $user->ID > 0 ? sprintf( 'Hi %s', $user->display_name ) : 'You are not logged in';
}

/**
 * Parse request
 */
add_filter( 'do_parse_request', 'tad_routes_do_parse_request', 1, 3 );
function tad_routes_do_parse_request( $continue, WP $wp, $extra_query_vars ) {
	with( '/posts', 'route_posts' );
	respond( '/who-am-i', 'route_who_am_i' );
	_dispatch();

	return $continue;
}


