=== WP Routes ===
Contributors: lucatume
Tags: routing
Requires at least: 3.5.0
Tested up to: 4.8.2
Requires PHP: 5.2.17
License: GPL-2.0
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html

Eeasy PHP 5.2 compatible custom routing thanks to klein.php library.
No need to tamper with rewrite rules and Apache .htaccess files to add custom routes.


== Description ==
In a plugin file or a theme `functions.php` file:

```php
function my_rest_api(){
	klein_with( \'/my-plugin/api\', \'src/routes.php\' );
}

function my_plugin_say_hi( klein_Request $request ){
	if( !empty( $request->name ) ){
		echo \"Hi {$request->name}!j\";
	} else {
		echo \"Hi there!\";
	}
}

function my_plugin_operation( klein_Request $request ){
	echo $request->first + $request->second;
}

function my_plugin_login( klein_Request $request, klein_Response $response  ){
	$response->redirect( wp_login_url() );
}

add_filter( \'wp-routes/register_routes\', \'my_rest_api\' );
```

In the `src/routes.php` file:

```php
// just a redirection
klein_respond( \'GET\', \'/login', \'my_plugin_login\' );

// API handling
klein_respond( \'GET\', \'/my-plugin/api/say-hi\', \'my_plugin_say_hi\' );
klein_respond( \'GET\', \'/my-plugin/api/say-hi/[a:name]\', \'my_plugin_say_hi\' );
klein_respond( \'GET\', \'/my-plugin/api/add/[i:first]/[i:second]\', \'my_plugin_operation\' );
```

While the example above uses PHP 5.2 compatible code route handlers can be defined using closures; see [examples on klein52 library README file](https://github.com/lucatume/klein52).

== Installation ==
Download the plugin zip file and install via the Plugin management screen.

== Frequently Asked Questions ==
*How to prevent \'die\' being called after a matching route was found?*

You can filter the \'klein_die_handler\' filter to instruct klein52 to simply echo the response output and continue returning \'echo\' or to use a custom callback to handle the output; see [here]()

== Changelog ==

= 1.0 =
* Initial version.