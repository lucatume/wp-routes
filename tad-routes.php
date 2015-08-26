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
function _dispatch() {
	$found = dispatch( null, null, null, true );
	if ( $found ) {
		die( $found );
	}
}

/**
 * Parse request
 */
add_filter( 'do_parse_request', 'tad_routes_do_parse_request', 1, 3 );
function tad_routes_do_parse_request( $continue, WP $wp, $extra_query_vars ) {
	respond( '*', function () {
		get_header();
	} );

	respond( '/hello/[a:name]', function ( $request ) {
		?>
		<div id="primary" class="content-area">
			<main id="main" class="site-main" role="main">
				<article class="hentry">
					<header class="entry-header">
						<h2 class="entry-title">Hi <?= $request->name ?></h2></header>
					<div class="entry-content">
						<p>Lorem ipsum dolor sit amet</p>
					</div>
				</article>
			</main>
		</div>
		<?php
	} );

	respond( '*', function () {
		get_footer();
	} );

	_dispatch();

	return $continue;
}


