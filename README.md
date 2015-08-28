#WP Routes

*Easy WordPress routing plugin with klein.php*

## Code Example
In my plugin or theme `functions.php` file:

	add_action('wp-routes/register_routes', function(){
	
		// easy to remember admin links
		respond('/admin', function(){
			wp_safe_redirect(admin_url());
			die();
		});
		respond('/login', function(){
			wp_safe_redirect(wp_login_url());
			die();
		});
		
		// an easy JSON route		
		with('/posts', function(){
			respond('GET', '/[i:id]', function($request){
				$post = get_post($request->id);
				if ($post) {echo json_encode($post);}
				else {echo json_encode(array('error' => 'Not a valid post ID'));}
			});
			respond('POST', '/new', function($request){
				if(!wp_verify_nonce($_POST['wp_nonce'], 'add-new-post')) {
					echo json_encode(array('error' => 'Not authorized to create new posts'));
					return;
				}	
				$id = wp_insert_post('post_title' => $request->title);
				if ($post) {echo json_encode(get_post($id));}
				else {echo json_encode(array('error' => 'There was an error creating the post'));}
			});
		});
	});
	
## Thanks Klein
The possibilities above are possible thanks to the [klein.php library](https://github.com/chriso/klein.php) by Chris O'Hara; I've merely ported v. 1.2.0 of the library to be back compatible with PHP 5.2 and used the goodness.  

## Installation
Download the `.zip` file and put in WordPress plugin folder.
	
## Usage
The plugin packs the [klein52 library](https://github.com/lucatume/klein52) and allow plugins and themes developers to use any of its methods.  
An action, `wp-routes/register_routes`, is fired before WordPress parses and handles the current request, see the code example above.  
If a route echoes something and it's not a catch-all route, one that matches on "*", then WordPress normal handling flow will be interrupted and the request will be responsibility of the route.  
The routing happens when WordPress is fully loaded along with its plugins and the current theme so any WordPress defined function will be available.
