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
		echo json_encode( array( 'Error' => 'Not a valid post ID' ) );
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
function route_create_post( $request ) {
	if ( wp_verify_nonce( $request->nonce, 'add-post' ) ) {
		if ( ! is_string( $_POST['post-title'] ) ) {
			echo "You only had one job. Enter a post title please.";
			return;
		}
		$id = wp_insert_post( array( 'post_title' => $_POST['post-title'] ) );
		if ( $id ) {
			echo json_encode( get_post( $id ) );
		} else {
			echo "Something went wrong in the random post creation.";
		}
	} else {
		echo "Not authorized to create random posts";
	}
}

function route_nonce() {
	echo wp_create_nonce( 'add-post' );
}

function route_posts() {
	respond( 'GET', '/[i:id]/json', '_json_post' );
	respond( 'POST', '/[:nonce]', 'route_create_post' );
}

function route_who_am_i() {
	$user = wp_get_current_user();
	echo $user->ID > 0 ? sprintf( 'Hi %s', $user->display_name ) : 'You are not logged in';
}


function route_admin() {
	wp_safe_redirect( admin_url() );
	die();
}

function route_login() {
	wp_safe_redirect( wp_login_url() );
	die();
}

/**
 * Parse request
 */
add_filter( 'do_parse_request', 'tad_routes_do_parse_request', 1, 3 );
function tad_routes_do_parse_request( $continue, WP $wp, $extra_query_vars ) {
	with( '/posts', 'route_posts' );
	respond( '/who-am-i', 'route_who_am_i' );
	respond( '/admin', 'route_admin' );
	respond( '/login', 'route_login' );
	respond( '/nonce', 'route_nonce' );
	_dispatch();

	return $continue;
}


