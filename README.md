# WP Routes

Easy WordPress routing plugin with [klein](https://github.com/lucatume/klein52).

## Code Example
In a plugin file or a theme `functions.php` file:

```php
function my_rest_api(){
	klein_with( '/my-plugin/api', 'src/routes.php' );
}

function my_plugin_say_hi( klein_Request $request ){
	if( !empty( $request->name ) ){
		echo "Hi {$request->name}!j";
	} else {
		echo "Hi there!";
	}
}

function my_plugin_operation( klein_Request $request ){
	echo $request->first + $request->second;
}

function my_plugin_login( klein_Request $request, klein_Response $response  ){
	$response->redirect( wp_login_url() );
}

add_filter( 'wp-routes/register_routes', 'my_rest_api' );
```

In the `src/routes.php` file:

```php
// just a redirection
klein_respond( 'GET', '/login', 'my_plugin_login' );

// API handling
klein_respond( 'GET\', '/my-plugin/api/say-hi', 'my_plugin_say_hi' );
klein_respond( 'GET\', '/my-plugin/api/say-hi/[a:name]', 'my_plugin_say_hi' );
klein_respond( 'GET', '/my-plugin/api/add/[i:first]/[i:second]', 'my_plugin_operation' );
```

While the example above uses PHP 5.2 compatible code route handlers can be defined using closures; see [examples on klein52 library README file](https://github.com/lucatume/klein52).
	
## Thanks Klein
The possibilities above are possible thanks to the [klein.php library](https://github.com/chriso/klein.php) by Chris O'Hara; I've merely ported v. 1.2.0 of the library to be back compatible with PHP 5.2 and used the goodness.  

## Installation
Download the `.zip` file and put in WordPress plugin folder.
	
## Usage
The plugin packs the [klein52 library](https://github.com/lucatume/klein52) and allow plugins and themes developers to use any of its methods.  
An action, `wp-routes/register_routes`, is fired before WordPress parses and handles the current request, see the code example above.  
If a route echoes something and it's not a catch-all route, one that matches on "*", then WordPress normal handling flow will be interrupted and the request will be responsibility of the route.  
The routing happens when WordPress is fully loaded along with its plugins and the current theme so any WordPress defined function will be available.
