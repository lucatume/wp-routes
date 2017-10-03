<?php
/*
 * (c) Chris O'Hara <cohara87@gmail.com> (MIT License)
 * http://github.com/chriso/klein.php
 * Modified to work in PHP 5.2 by Luca Tumedei <luca@theaveragedev.com>
 * https://github.com/lucatume/klein52
 */

$__klein_routes    = array();
$__klein_namespace = null;

/**
 * Registers a route as one handled by klein.
 *
 * @since 1.0.3
 *
 * @param        string $method   A supported HTTP method, e.g. `GET`, `POST`, `DELETE` and so on.
 * @param string        $route    The route matching pattern.
 * @param  callable     $callback The callback that should be used to handle the request.
 *
 * @return mixed|null
 */
function klein_respond( $method, $route = '*', $callback = null ) {
	global $__klein_routes, $__klein_namespace;

	$args     = func_get_args();
	$callback = array_pop( $args );
	$route    = array_pop( $args );
	$method   = array_pop( $args );

	if ( null === $route ) {
		$route = '*';
	}

	// only consider a request to be matched when not using matchall
	$count_match = ( $route !== '*' );

	if ( $__klein_namespace && $route[0] === '@' || ( $route[0] === '!' && $route[1] === '@' ) ) {
		if ( $route[0] === '!' ) {
			$negate = true;
			$route  = substr( $route, 2 );
		} else {
			$negate = false;
			$route  = substr( $route, 1 );
		}

		// regex anchored to front of string
		if ( $route[0] === '^' ) {
			$route = substr( $route, 1 );
		} else {
			$route = '.*' . $route;
		}

		if ( $negate ) {
			$route = '@^' . $__klein_namespace . '(?!' . $route . ')';
		} else {
			$route = '@^' . $__klein_namespace . $route;
		}
	} // empty route with namespace is a match-all
	elseif ( $__klein_namespace && ( '*' === $route ) ) {
		$route = '@^' . $__klein_namespace . '(/|$)';
	} else {
		$route = $__klein_namespace . $route;
	}

	$__klein_routes[] = array( $method, $route, $callback, $count_match );

	return $callback;
}

/**
 * Registers a group of routes with a common root namespace.
 *
 * @since 1.0.3
 *
 * @param string $namespace The namespace that for a group of routes, e.g. `/users` or `/api/admin`
 * @param string|callable $routes Either the path to a file defining a group of routes or a callable
 *                                registering a group of routes.
 */
function klein_with( $namespace, $routes ) {
	global $__klein_namespace;
	$previous          = $__klein_namespace;
	$__klein_namespace .= $namespace;
	if ( is_callable( $routes ) ) {
		$routes();
	} else {
		require $routes;
	}
	$__klein_namespace = $previous;
}

/**
 * Starts the session.
 *
 * @since 1.0.3
 */
function klein_start_session() {
	if ( session_id() === '' ) {
		session_start();
	}
}

/**
 * Dispatches a request to the appropriate route.
 *
 * @since 1.0.3
 *
 * @param null|string       $uri The request URI
 * @param null|string       $req_method The request method, e.g. `GET`, `POST` or `DELETE`
 * @param array|null $params An array of parameters for the request
 * @param bool       $capture Whether the matching route response output should be printed or not
 * @param bool       $passthru If `capture` is set to `true` when no route is matched the function will
 *                             return `false`
 *
 * @return bool|string `false` if the route was not matched and `capture` and `passthru` were `true`; the matched
 *                     route output otherwise.
 */
function klein_dispatch( $uri = null, $req_method = null, array $params = null, $capture = false, $passthru = false ) {
	global $__klein_routes;

	// Pass $request, $response, and a blank object for sharing scope through each callback
	$request  = new klein_Request;
	$response = new klein_Response;
	$app      = new klein_App;

	// Get/parse the request URI and method
	if ( null === $uri ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
	}
	if ( false !== strpos( $uri, '?' ) ) {
		$uri = str_replace( stristr( $uri, "?" ), "", $uri );
	}
	if ( null === $req_method ) {
		$req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET';

		// For legacy servers, override the HTTP method with the X-HTTP-Method-Override
		// header or _method parameter
		if ( isset( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ) ) {
			$req_method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
		} elseif ( isset( $_REQUEST['_method'] ) ) {
			$req_method = $_REQUEST['_method'];
		}
	}

	// Force request_order to be GP
	// http://www.mail-archive.com/internals@lists.php.net/msg33119.html
	$_REQUEST = array_merge( $_GET, $_POST );
	if ( null !== $params ) {
		$_REQUEST = array_merge( $_REQUEST, $params );
	}

	$matched         = 0;
	$methods_matched = array();

	ob_start();

	foreach ( $__klein_routes as $handler ) {
		list( $method, $_route, $callback, $count_match ) = $handler;

		$method_match = null;
		// Was a method specified? If so, check it against the current request method
		if ( is_array( $method ) ) {
			foreach ( $method as $test ) {
				if ( strcasecmp( $req_method, $test ) === 0 ) {
					$method_match = true;
				}
			}
			if ( null === $method_match ) {
				$method_match = false;
			}
		} elseif ( null !== $method && strcasecmp( $req_method, $method ) !== 0 ) {
			$method_match = false;
		} elseif ( null !== $method && strcasecmp( $req_method, $method ) === 0 ) {
			$method_match = true;
		}

		// If the method was matched or if it wasn't even passed (in the route callback)
		$possible_match = is_null( $method_match ) || $method_match;

		// ! is used to negate a match
		if ( isset( $_route[0] ) && $_route[0] === '!' ) {
			$negate = true;
			$i      = 1;
		} else {
			$negate = false;
			$i      = 0;
		}

		// Check for a wildcard (match all)
		if ( $_route === '*' ) {
			$match = true;

			// Easily handle 404's
		} elseif ( $_route === '404' && ! $matched && count( $methods_matched ) <= 0 ) {
			try {
				call_user_func( $callback, $request, $response, $app, $matched, $methods_matched );
			}
			catch ( Exception $e ) {
				$response->error( $e );
			}

			++ $matched;
			continue;

			// Easily handle 405's
		} elseif ( $_route === '405' && ! $matched && count( $methods_matched ) > 0 ) {
			try {
				call_user_func( $callback, $request, $response, $app, $matched, $methods_matched );
			}
			catch ( Exception $e ) {
				$response->error( $e );
			}

			++ $matched;
			continue;

			// @ is used to specify custom regex
		} elseif ( isset( $_route[ $i ] ) && $_route[ $i ] === '@' ) {
			$match = preg_match( '`' . substr( $_route, $i + 1 ) . '`', $uri, $params );

			// Compiling and matching regular expressions is relatively
			// expensive, so try and match by a substring first
		} else {
			$route = null;
			$regex = false;
			$j     = 0;
			$n     = isset( $_route[ $i ] ) ? $_route[ $i ] : null;

			// Find the longest non-regex substring and match it against the URI
			while ( true ) {
				if ( ! isset( $_route[ $i ] ) ) {
					break;
				} elseif ( false === $regex ) {
					$c     = $n;
					$regex = $c === '[' || $c === '(' || $c === '.';
					if ( false === $regex && false !== isset( $_route[ $i + 1 ] ) ) {
						$n     = $_route[ $i + 1 ];
						$regex = $n === '?' || $n === '+' || $n === '*' || $n === '{';
					}
					if ( false === $regex && $c !== '/' && ( ! isset( $uri[ $j ] ) || $c !== $uri[ $j ] ) ) {
						continue 2;
					}
					$j ++;
				}
				$route .= $_route[ $i ++ ];
			}

			// Check if there's a cached regex string
			$regex = wp_cache_get( "route:$route", 'klein' );
			if ( false === $regex ) {
				$regex = klein_compile_route( $route );
				wp_cache_set( "route:$route", $regex,'klein' );
			}

			$match = preg_match( $regex, $uri, $params );
		}

		if ( isset( $match ) && $match ^ $negate ) {
			// Keep track of possibly matched methods
			$methods_matched = array_merge( $methods_matched, (array) $method );
			$methods_matched = array_filter( $methods_matched );
			$methods_matched = array_unique( $methods_matched );

			if ( $possible_match ) {
				if ( null !== $params ) {
					$_REQUEST = array_merge( $_REQUEST, $params );
				}
				try {
					call_user_func( $callback, $request, $response, $app, $matched, $methods_matched );
				}
				catch ( Exception $e ) {
					$response->error( $e );
				}
				if ( $_route !== '*' ) {
					$count_match && ++ $matched;
				}
			}
		}
	}

	if ( ! $matched && count( $methods_matched ) > 0 ) {
		$response->code( 405 );
		$response->header( 'Allow', implode( ', ', $methods_matched ) );
	} elseif ( ! $matched ) {
		$response->code( 404 );
	}

	if ( $capture ) {
		if ( $passthru && $matched == 0 ) {
			ob_end_clean();

			return false;
		}

		return ob_get_clean();
	} elseif ( $response->chunked ) {
		$response->chunk();
	} else {
		ob_end_flush();
	}
}

/**
 * Dispatches the request to the first available matching routes and dies, or continues the script execution.
 *
 * @since 1.0.3
 *
 * @param null|string       $uri The request URI
 * @param null|string       $req_method The request method, e.g. `GET`, `POST` or `DELETE`
 * @param array|null $params An array of parameters for the request
 *
 * @return string|void|mixed|bool The route output if one was matched and the `dieCallback` is set to `true`;
 *                     otherwise the route output will be output before `die`ing; `false` if no matching route
 *                                was found.
 */
function klein_dispatch_or_continue( $uri = null, $req_method = null, array $params = null ) {
	$found = klein_dispatch( $uri, $req_method, $params, true, true );
	if ( $found ) {
		$dieCallback = null;

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters the die (output) handler to use when a route is matched.
			 *
			 * @since 1.0.3
			 *
			 * Use the special value "echo "To just echo the result and return, e.g.:
			 *
			 *        add_filter('klein_die_handler', function(){
			 *            return 'echo';
			 *        });
			 *
			 * @params callabe $handler The function that will be called to output the request; default `die`
			 */
			$dieCallback = apply_filters( 'klein_die_handler', null );
		}

		switch ( $dieCallback ) {
			case 'echo':
				echo $found;

				return;
			case null:
				die ( $found );
			default:
				return $dieCallback( $found );
		}
	}
}

/**
 * Compiles a route from the format used by klein to a regular expression.
 *
 * @since 1.0.3
 *
 * @param string $route The route in the format supported by klein.
 *
 * @return string The regular expression corresponding to the input route.
 */
function klein_compile_route( $route ) {
	if ( preg_match_all( '`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $route, $matches, PREG_SET_ORDER ) ) {
		$match_types = array(
			'i'  => '[0-9]++',
			'a'  => '[0-9A-Za-z]++',
			'h'  => '[0-9A-Fa-f]++',
			'*'  => '.+?',
			'**' => '.++',
			''   => '[^/]+?',
		);
		foreach ( $matches as $match ) {
			list( $block, $pre, $type, $param, $optional ) = $match;

			if ( isset( $match_types[ $type ] ) ) {
				$type = $match_types[ $type ];
			}
			if ( $pre === '.' ) {
				$pre = '\.';
			}
			// Older versions of PCRE require the 'P' in (?P<named>)
			$pattern = '(?:' . ( $pre !== '' ? $pre : null ) . '(' . ( $param !== '' ? "?P<$param>" : null ) . $type . '))' . ( $optional !== '' ? '?' : null );

			$route = str_replace( $block, $pattern, $route );
		}
	}

	return "`^$route$`";
}

/**
 * Class klein_Request
 *
 * @since 1.0.3
 */
class klein_Request {

	public static $_headers = null;

	// HTTP headers helper
	protected $_id = null;
	protected $_body = null;

	// Returns all parameters (GET, POST, named) that match the mask
	public function params( $mask = null ) {
		$params = $_REQUEST;
		if ( null !== $mask ) {
			if ( ! is_array( $mask ) ) {
				$mask = func_get_args();
			}
			$params = array_intersect_key( $params, array_flip( $mask ) );
			// Make sure each key in $mask has at least a null value
			foreach ( $mask as $key ) {
				if ( ! isset( $params[ $key ] ) ) {
					$params[ $key ] = null;
				}
			}
		}

		return $params;
	}

	// Return a request parameter, or $default if it doesn't exist

	public function __isset( $param ) {
		return isset( $_REQUEST[ $param ] );
	}

	public function __get( $param ) {
		return isset( $_REQUEST[ $param ] ) ? $_REQUEST[ $param ] : null;
	}

	public function __set( $param, $value ) {
		$_REQUEST[ $param ] = $value;
	}

	public function __unset( $param ) {
		unset( $_REQUEST[ $param ] );
	}

	public function isSecure( $required = false ) {
		$secure = isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'];
		if ( ! $secure && $required ) {
			$url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			self::$_headers->header( 'Location: ' . $url );
		}

		return $secure;
	}

	// Is the request secure? If $required then redirect to the secure version of the URL

	public function header( $key, $default = null ) {
		$key = 'HTTP_' . strtoupper( str_replace( '-', '_', $key ) );

		return isset( $_SERVER[ $key ] ) ? $_SERVER[ $key ] : $default;
	}

	// Gets a request header

	public function cookie( $key, $default = null ) {
		return isset( $_COOKIE[ $key ] ) ? $_COOKIE[ $key ] : $default;
	}

	// Gets a request cookie

	public function method( $is = null ) {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET';
		if ( null !== $is ) {
			return strcasecmp( $method, $is ) === 0;
		}

		return $method;
	}

	// Gets the request method, or checks it against $is - e.g. method('post') => true

	public function validate( $param, $err = null ) {
		return new klein_Validator( $this->param( $param ), $err );
	}

	// Start a validator chain for the specified parameter

	public function param( $key, $default = null ) {
		return isset( $_REQUEST[ $key ] ) && $_REQUEST[ $key ] !== '' ? $_REQUEST[ $key ] : $default;
	}

	// Gets a unique ID for the request

	public function id() {
		if ( null === $this->_id ) {
			$this->_id = sha1( mt_rand() . microtime( true ) . mt_rand() );
		}

		return $this->_id;
	}

	// Gets a session variable associated with the request
	public function session( $key, $default = null ) {
		klein_start_session();

		return isset( $_SESSION[ $key ] ) ? $_SESSION[ $key ] : $default;
	}

	// Gets the request IP address
	public function ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : null;
	}

	// Gets the request user agent
	public function userAgent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;
	}

	// Gets the request URI
	public function uri() {
		return isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
	}

	// Gets the request body
	public function body() {
		if ( null === $this->_body ) {
			$this->_body = @file_get_contents( 'php://input' );
		}

		return $this->_body;
	}
}

/**
 * Class klein_Response
 *
 * @since 1.0.3
 */
class klein_Response extends StdClass {

	public static $_headers = null;
	public $chunked = false;
	protected $_errorCallbacks = array();
	protected $_layout = null;
	protected $_view = null;
	protected $_code = 200;

	// Enable response chunking. See: http://bit.ly/hg3gHb

	public function cookie( $key, $value = '', $expiry = null, $path = '/', $domain = null, $secure = false, $httponly = false ) {
		if ( null === $expiry ) {
			$expiry = time() + ( 3600 * 24 * 30 );
		}

		return setcookie( $key, $value, $expiry, $path, $domain, $secure, $httponly );
	}

	// Sets a response header

	public function file( $path, $filename = null, $mimetype = null ) {
		$this->discard();
		$this->noCache();
		set_time_limit( 1200 );
		if ( null === $filename ) {
			$filename = basename( $path );
		}
		if ( null === $mimetype ) {
			$mimetype = finfo_file( finfo_open( FILEINFO_MIME_TYPE ), $path );
		}
		$this->header( 'Content-type: ' . $mimetype );
		$this->header( 'Content-length: ' . filesize( $path ) );
		$this->header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		readfile( $path );
	}

	// Sets a response cookie

	public function discard( $restart_buffer = false ) {
		$cleaned = ob_end_clean();

		if ( $restart_buffer ) {
			ob_start();
		}

		return $cleaned;
	}

	// Stores a flash message of $type

	public function noCache() {
		$this->header( "Pragma: no-cache" );
		$this->header( 'Cache-Control: no-store, no-cache' );
	}

	// Support basic markdown syntax

	public function header( $key, $value = null ) {
		self::$_headers->header( $key, $value );
	}

	// Tell the browser not to cache the response

	public function json( $object, $jsonp_prefix = null ) {
		$this->discard( true );
		$this->noCache();
		set_time_limit( 1200 );
		$json = json_encode( $object );
		if ( null !== $jsonp_prefix ) {
			$this->header( 'Content-Type: text/javascript' ); // should ideally be application/json-p once adopted
			echo "$jsonp_prefix($json);";
		} else {
			$this->header( 'Content-Type: application/json' );
			echo $json;
		}
	}

	// Sends a file

	public function back() {
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$this->redirect( $_SERVER['HTTP_REFERER'] );
		}
		$this->refresh();
	}

	// Sends an object as json or jsonp by providing the padding prefix

	public function redirect( $url, $code = 302, $exit_after_redirect = true ) {
		$this->code( $code );
		$this->header( "Location: $url" );
		if ( $exit_after_redirect ) {
			exit;
		}
	}

	// Sends a HTTP response code

	public function code( $code = null ) {
		if ( null !== $code ) {
			$this->_code = $code;

			// Do we have the PHP 5.4 "http_response_code" function?
			if ( function_exists( 'http_response_code' ) ) {
				// Have PHP automatically create our HTTP Status header from our code
				http_response_code( $code );
			} else {
				// Manually create the HTTP Status header
				$protocol = isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
				$this->header( "$protocol $code" );
			}
		}

		return $this->_code;
	}

	// Redirects the request to another URL

	public function refresh() {
		$this->redirect( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/' );
	}

	// Redirects the request to the current URL

	public function query( $key, $value = null ) {
		$query = array();
		if ( isset( $_SERVER['QUERY_STRING'] ) ) {
			parse_str( $_SERVER['QUERY_STRING'], $query );
		}
		if ( is_array( $key ) ) {
			$query = array_merge( $query, $key );
		} else {
			$query[ $key ] = $value;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
		if ( strpos( $request_uri, '?' ) !== false ) {
			$request_uri = str_replace( stristr( $request_uri, "?" ), "", $request_uri );
		}

		return $request_uri . ( ! empty( $query ) ? '?' . http_build_query( $query ) : null );
	}

	// Redirects the request back to the referrer

	public function layout( $layout ) {
		$this->_layout = $layout;
	}

	// Sets response properties/helpers

	public function partial( $view, array $data = array() ) {
		$layout        = $this->_layout;
		$this->_layout = null;
		$this->render( $view, $data );
		$this->_layout = $layout;
	}

	// Adds to or modifies the current query string

	public function render( $view, array $data = array() ) {
		$original_view = $this->_view;

		if ( ! empty( $data ) ) {
			$this->set( $data );
		}
		$this->_view = $view;
		if ( null === $this->_layout ) {
			$this->yieldView();
		} else {
			require $this->_layout;
		}
		if ( false !== $this->chunked ) {
			$this->chunk();
		}

		// restore state for parent render()
		$this->_view = $original_view;
	}

	// Set the view layout

	public function set( $key, $value = null ) {
		if ( ! is_array( $key ) ) {
			return $this->$key = $value;
		}
		foreach ( $key as $k => $value ) {
			$this->$k = $value;
		}
	}

	// Renders the current view

	public function yieldView() {
		require $this->_view;
	}

	// Renders a view + optional layout

	public function chunk( $str = null ) {
		if ( false === $this->chunked ) {
			$this->chunked = true;
			self::$_headers->header( 'Transfer-encoding: chunked' );
			flush();
		}
		if ( null !== $str ) {
			printf( "%x\r\n", strlen( $str ) );
			echo "$str\r\n";
			flush();
		} elseif ( ( $ob_length = ob_get_length() ) > 0 ) {
			printf( "%x\r\n", $ob_length );
			ob_flush();
			echo "\r\n";
			flush();
		}
	}

	// Renders a view without a layout

	public function session( $key, $value = null ) {
		klein_start_session();

		return $_SESSION[ $key ] = $value;
	}

	// Sets a session variable

	public function onError( $callback ) {
		$this->_errorCallbacks[] = $callback;
	}

	// Adds an error callback to the stack of error handlers

	public function error( Exception $err ) {
		$type = get_class( $err );
		$msg  = $err->getMessage();

		if ( count( $this->_errorCallbacks ) > 0 ) {
			foreach ( array_reverse( $this->_errorCallbacks ) as $callback ) {
				if ( is_callable( $callback ) ) {
					if ( $callback( $this, $msg, $type, $err ) ) {
						return;
					}
				} else {
					$this->flash( $err );
					$this->redirect( $callback );
				}
			}
		} else {
			$this->code( 500 );
			throw new ErrorException( $err );
		}
	}

	// Routes an exception through the error callbacks

	public function flash( $msg, $type = 'info', $params = null ) {
		klein_start_session();
		if ( is_array( $type ) ) {
			$params = $type;
			$type   = 'info';
		}
		if ( ! isset( $_SESSION['__flashes'] ) ) {
			$_SESSION['__flashes'] = array( $type => array() );
		} elseif ( ! isset( $_SESSION['__flashes'][ $type ] ) ) {
			$_SESSION['__flashes'][ $type ] = array();
		}
		$_SESSION['__flashes'][ $type ][] = $this->markdown( $msg, $params );
	}

	// Returns an escaped request paramater

	public function markdown( $str, $args = null ) {
		$args = func_get_args();
		$md   = array(
			'/\[([^\]]++)\]\(([^\)]++)\)/' => '<a href="$2">$1</a>',
			'/\*\*([^\*]++)\*\*/'          => '<strong>$1</strong>',
			'/\*([^\*]++)\*/'              => '<em>$1</em>',
		);
		$str  = array_shift( $args );
		if ( is_array( $args[0] ) ) {
			$args = $args[0];
		}
		foreach ( $args as &$arg ) {
			$arg = htmlentities( $arg, ENT_QUOTES );
		}

		return vsprintf( preg_replace( array_keys( $md ), $md, $str ), $args );
	}

	// Returns and clears all flashes of optional $type

	public function param( $param, $default = null ) {
		return isset( $_REQUEST[ $param ] ) ? htmlentities( $_REQUEST[ $param ], ENT_QUOTES ) : $default;
	}

	// Escapes a string

	public function flashes( $type = null ) {
		klein_start_session();
		if ( ! isset( $_SESSION['__flashes'] ) ) {
			return array();
		}
		if ( null === $type ) {
			$flashes = $_SESSION['__flashes'];
			unset( $_SESSION['__flashes'] );
		} elseif ( null !== $type ) {
			$flashes = array();
			if ( isset( $_SESSION['__flashes'][ $type ] ) ) {
				$flashes = $_SESSION['__flashes'][ $type ];
				unset( $_SESSION['__flashes'][ $type ] );
			}
		}

		return $flashes;
	}

	// Discards the current output buffer and restarts it if passed a true boolean

	public function escape( $str ) {
		return htmlentities( $str, ENT_QUOTES );
	}

	// Flushes the current output buffer

	public function flush() {
		ob_end_flush();
	}

	// Return the current output buffer as a string
	public function buffer() {
		return ob_get_contents();
	}

	// Dump a variable
	public function dump( $obj ) {
		if ( is_array( $obj ) || is_object( $obj ) ) {
			$obj = print_r( $obj, true );
		}
		echo '<pre>' . htmlentities( $obj, ENT_QUOTES ) . "</pre><br />\n";
	}

	// Allow callbacks to be assigned as properties and called like normal methods
	public function __call( $method, $args ) {
		if ( ! isset( $this->$method ) || ! is_callable( $this->$method ) ) {
			throw new ErrorException( "Unknown method $method()" );
		}
		$callback = $this->$method;
		switch ( count( $args ) ) {
			case 1:
				return $callback( $args[0] );
			case 2:
				return $callback( $args[0], $args[1] );
			case 3:
				return $callback( $args[0], $args[1], $args[2] );
			case 4:
				return $callback( $args[0], $args[1], $args[2], $args[3] );
			default:
				return call_user_func_array( $callback, $args );
		}
	}
}

/**
 * Adds a custom route validation callback.
 *
 * Validators registered with this method will be available using the `is<method>` and `not<method>` chained
 * on the `$request->validate(<key>)` method; see the README.md file.
 *
 * @since 1.0.3
 *
 * @param string $method The validation pattern slug; e.g. `hex` or `userId`
 * @param callable $callback The function that will be called to validate the pattern; should return a boolean value
 */
function klein_addValidator( $method, $callback ) {
	klein_Validator::$_methods[ strtolower( $method ) ] = $callback;
}

/**
 * Class klein_ValidatorException
 *
 * The base class of the exceptions that will be thrown when a validation callback fails.
 *
 * @since 1.0.3
 */
class klein_ValidatorException extends Exception {}

/**
 * Class klein_Validator
 *
 * @since 1.0.3
 */
class klein_Validator {

	public static $_methods = array();

	protected $_str = null;
	protected $_err = null;

	// Sets up the validator chain with the string and optional error message
	public function __construct( $str, $err = null ) {
		$this->_str = $str;
		$this->_err = $err;
		if ( empty( self::$_defaultAdded ) ) {
			self::addDefault();
		}
	}

	// Adds default validators on first use. See README for usage details
	public static function addDefault() {
		self::$_methods['null']     = array( self, 'validateNull' );
		self::$_methods['len']      = array( self, 'validateLen' );
		self::$_methods['int']      = array( self, 'validateInt' );
		self::$_methods['float']    = array( self, 'validateFloat' );
		self::$_methods['email']    = array( self, 'validateEmail' );
		self::$_methods['url']      = array( self, 'validateUrl' );
		self::$_methods['ip']       = array( self, 'validateIp' );
		self::$_methods['alnum']    = array( self, 'validateAlnum' );
		self::$_methods['alpha']    = array( self, 'validateAlpha' );
		self::$_methods['contains'] = array( self, 'validateContains' );
		self::$_methods['regex']    = array( self, 'validateRegex' );
		self::$_methods['chars']    = array( self, 'validateChars' );
	}

	/**
	 * @return Closure
	 */
	private static function validateNull( $str ) {
		return $str === null || $str === '';
	}

	/**
	 * @return Closure
	 */
	private static function validateLen( $str, $min, $max = null ) {
		$len = strlen( $str );

		return null === $max ? $len === $min : $len >= $min && $len <= $max;
	}

	/**
	 * @return Closure
	 */
	private static function validateInt( $str ) {
		return (string) $str === ( (string) (int) $str );
	}

	/**
	 * @return Closure
	 */
	private static function validateFloat( $str ) {
		return (string) $str === ( (string) (float) $str );
	}

	/**
	 * @return Closure
	 */
	private static function validateEmail( $str ) {
		return filter_var( $str, FILTER_VALIDATE_EMAIL ) !== false;
	}

	/**
	 * @return Closure
	 */
	private static function validateUrl( $str ) {
		return filter_var( $str, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * @return Closure
	 */
	private static function validateIp( $str ) {
		return filter_var( $str, FILTER_VALIDATE_IP ) !== false;
	}

	/**
	 * @return Closure
	 */
	private static function validateAlnum( $str ) {
		return ctype_alnum( $str );
	}

	/**
	 * @return Closure
	 */
	private static function validateAlpha( $str ) {
		return ctype_alpha( $str );
	}

	/**
	 * @return Closure
	 */
	private static function validateContains( $str, $needle ) {
		return strpos( $str, $needle ) !== false;
	}

	/**
	 * @return Closure
	 */
	private static function validateRegex( $str, $pattern ) {
		return preg_match( $pattern, $str );
	}

	/**
	 * @return Closure
	 */
	private static function validateChars( $str, $chars ) {
		return preg_match( "`^[$chars]++$`i", $str );
	}

	public function __call( $method, $args ) {
		$reverse       = false;
		$validator     = $method;
		$method_substr = substr( $method, 0, 2 );

		if ( $method_substr === 'is' ) {       // is<$validator>()
			$validator = substr( $method, 2 );
		} elseif ( $method_substr === 'no' ) { // not<$validator>()
			$validator = substr( $method, 3 );
			$reverse   = true;
		}
		$validator = strtolower( $validator );

		if ( ! $validator || ! isset( self::$_methods[ $validator ] ) ) {
			throw new ErrorException( "Unknown method $method()" );
		}
		$validator = self::$_methods[ $validator ];
		array_unshift( $args, $this->_str );

		switch ( count( $args ) ) {
			case 1:
				$result = $validator( $args[0] );
				break;
			case 2:
				$result = $validator( $args[0], $args[1] );
				break;
			case 3:
				$result = $validator( $args[0], $args[1], $args[2] );
				break;
			case 4:
				$result = $validator( $args[0], $args[1], $args[2], $args[3] );
				break;
			default:
				$result = call_user_func_array( $validator, $args );
				break;
		}

		$result = (bool) ( $result ^ $reverse );
		if ( false === $this->_err ) {
			return $result;
		} elseif ( false === $result ) {
			throw new klein_ValidatorException( $this->_err );
		}

		return $this;
	}
}

/**
 * Class klein_App
 *
 * @since 1.0.3
 */
class klein_App {

	protected $services = array();
	protected $serviceInstances = array();

	// Check for a lazy service
	public function __get( $name ) {
		if ( ! isset( $this->services[ $name ] ) ) {
			throw new InvalidArgumentException( "Unknown service $name" );
		}
		$service = $this->services[ $name ];

		return $service();
	}

	// Call a class property like a method
	public function __call( $method, $args ) {
		if ( ! isset( $this->$method ) || ! is_callable( $this->$method ) ) {
			throw new ErrorException( "Unknown method $method()" );
		}

		return call_user_func_array( $this->$method, $args );
	}

	// Register a lazy service
	public function register( $name, $callable ) {
		if ( isset( $this->services[ $name ] ) ) {
			throw new Exception( "A service is already registered under $name" );
		}
		if ( null === $this->serviceInstances[ $name ] ) {
			$this->serviceInstances[ $name ] = call_user_func( $callable );
		}

		return $this->serviceInstances[ $name ];
	}
}

/**
 * Class klein_Headers
 *
 * @since 1.0.3
 */
class klein_Headers {

	public function header( $key, $value = null ) {
		header( $this->_header( $key, $value ) );
	}

	/**
	 * Output an HTTP header. If $value is null, $key is
	 * assume to be the HTTP response code, and the ":"
	 * separator will be omitted.
	 */
	public function _header( $key, $value = null ) {
		if ( null === $value ) {
			return $key;
		}

		$key = str_replace( ' ', '-', ucwords( str_replace( ' - ', ' ', $key ) ) );

		return "$key: $value";
	}
}


klein_Request::$_headers = klein_Response::$_headers = new klein_Headers;
