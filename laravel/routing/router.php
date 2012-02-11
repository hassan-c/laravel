<?php namespace Laravel\Routing;

use Closure;
use Laravel\Str;
use Laravel\Bundle;
use Laravel\Request;

class Router {

	/**
	 * The route names that have been matched.
	 *
	 * @var array
	 */
	public static $names = array();

	/**
	 * The actions that have been reverse routed.
	 *
	 * @var array
	 */
	public static $uses = array();

	/**
	 * All of the routes that have been registered.
	 *
	 * @var array
	 */
	public static $routes = array();

	/**
	 * All of the "fallback" routes that have been registered.
	 *
	 * @var array
	 */
	public static $fallback = array();

	/**
	 * The "handes" clause for the bundle currently being routed.
	 *
	 * @var string
	 */
	public static $bundle;

	/**
	 * The number of URI segments allowed as method arguments.
	 *
	 * @var int
	 */
	public static $segments = 5;

	/**
	 * The wildcard patterns supported by the router.
	 *
	 * @var array
	 */
	public static $patterns = array(
		'(:num)' => '([0-9]+)',
		'(:any)' => '([a-zA-Z0-9\.\-_%]+)',
		'(:all)' => '(.*)',
	);

	/**
	 * The optional wildcard patterns supported by the router.
	 *
	 * @var array
	 */
	public static $optional = array(
		'/(:num?)' => '(?:/([0-9]+)',
		'/(:any?)' => '(?:/([a-zA-Z0-9\.\-_%]+)',
		'/(:all?)' => '(?:/(.*)',
	);

	/**
	 * An array of HTTP request methods.
	 *
	 * @var array
	 */
	public static $methods = array('GET', 'POST', 'PUT', 'DELETE');

	/**
	 * Register a HTTPS route with the router.
	 *
	 * @param  string        $method
	 * @param  string|array  $route
	 * @param  mixed         $action
	 * @return void
	 */
	public static function secure($method, $route, $action)
	{
		$action = static::action($action);

		$action['https'] = true;

		static::register($method, $route, $action);
	}

	/**
	 * Register a route with the router.
	 *
	 * <code>
	 *		// Register a route with the router
	 *		Router::register('GET' ,'/', function() {return 'Home!';});
	 *
	 *		// Register a route that handles multiple URIs with the router
	 *		Router::register(array('GET', '/', 'GET /home'), function() {return 'Home!';});
	 * </code>
	 *
	 * @param  string        $method
	 * @param  string|array  $route
	 * @param  mixed         $action
	 * @return void
	 */
	public static function register($method, $route, $action)
	{
		if (is_string($route)) $route = explode(', ', $route);

		foreach ((array) $route as $uri)
		{
			// If the URI begins with a splat, we'll call the universal method, which
			// will register a route for each of the request methods supported by
			// the router. This is just a notational short-cut.
			if ($method == '*')
			{
				foreach (static::$methods as $method)
				{
					static::register($method, $route, $action);
				}

				continue;
			}

			$uri = str_replace('(:bundle)', static::$bundle, $uri);

			// If the URI begins with a wildcard, we want to add this route to the
			// array of "fallback" routes. Fallback routes are always processed
			// last when parsing routes since they are very generic and could
			// overload bundle routes that are registered.
			if ($uri[0] == '(')
			{
				$routes =& static::$fallback;
			}
			else
			{
				$routes =& static::$routes;
			}

			// If the action is an array, we can simply add it to the array of
			// routes keyed by the URI. Otherwise, we will need to call into
			// the action method to get a valid action array.
			if (is_array($action))
			{
				$routes[$method][$uri] = $action;
			}
			else
			{
				$routes[$method][$uri] = static::action($action);
			}
			
			// If the HTTPS option is not set on the action, we will use the
			// value given to the method. The "secure" method passes in the
			// HTTPS value in as a parameter short-cut.
			if ( ! isset($routes[$method][$uri]['https']))
			{
				$routes[$method][$uri]['https'] = false;
			}
		}
	}

	/**
	 * Convert a route action to a valid action array.
	 *
	 * @param  mixed  $action
	 * @return array
	 */
	protected static function action($action)
	{
		// If the action is a string, it is a pointer to a controller, so we
		// need to add it to the action array as a "uses" clause, which will
		// indicate to the route to call the controller.
		if (is_string($action))
		{
			$action = array('uses' => $action);
		}
		// If the action is a Closure, we will manually put it in an array
		// to work around a bug in PHP 5.3.2 which causes Closures cast
		// as arrays to become null. We'll remove this.
		elseif ($action instanceof Closure)
		{
			$action = array($action);
		}

		return (array) $action;
	}

	/**
	 * Register a controller with the router.
	 *
	 * @param  string|array  $controller
	 * @param  bool          $https
	 * @return void
	 */
	public static function controller($controllers, $https = false)
	{
		foreach ((array) $controllers as $identifier)
		{
			list($bundle, $controller) = Bundle::parse($identifier);

			// First we need to replace the dots with slashes in thte controller name
			// so that it is in directory format. The dots allow the developer to use
			// a cleaner syntax when specifying the controller. We will also grab the
			// root URI for the controller's bundle.
			$controller = str_replace('.', '/', $controller);

			$root = Bundle::option($bundle, 'handles');

			// If the controller is a "home" controller, we'll need to also build a
			// index method route for the controller. We'll remove "home" from the
			// route root and setup a route to point to the index method.
			if (ends_with($controller, 'home'))
			{
				$home = trim(substr($controller, 0, -4), '/');

				$uses = "{$identifier}@index";

				$pattern = trim($root.'/'.$home, '/') ?: '/';

				static::register('*', $pattern, $uses);
			}

			// The number of method arguments allowed for a controller is set by the
			// "segments" constant on this class, which allows for the developers to
			// increase or decrease the limit on method arguments.
			$wildcards = static::repeat('(:any?)', static::$segments);

			// Once we have the path and root URI we can generate a basic route for
			// the controller that should handle a typical, conventional controller
			// routing setup of controller/method/segment/segment, etc.
			$pattern = trim("{$root}/{$controller}/{$wildcards}", '/');

			static::register('*', $pattern, array(
				
				'uses'     => "{$identifier}@(:1)",

				'defaults' => array_pad(array('index'), static::$segments, null),

				'https'    => $https,
			));
		}
	}

	/**
	 * Find a route by the route's assigned name.
	 *
	 * @param  string  $name
	 * @return array
	 */
	public static function find($name)
	{
		if (isset(static::$names[$name])) return static::$names[$name];

		// If no route names have been found at all, we will assume no reverse
		// routing has been done, and we will load the routes file for all of
		// the bundles that are installed for the application.
		if (count(static::$names) == 0)
		{
			foreach (Bundle::names() as $bundle)
			{
				Bundle::routes($bundle);
			}
		}

		// To find a named route, we will iterate through every route defined
		// for the application. We will cache the routes by name so we can
		// load them very quickly the next time.
		foreach (static::routes() as $key => $value)
		{
			if (array_get($value, 'name') == $name)
			{
				return static::$names[$name] = array($key => $value);
			}
		}
	}

	/**
	 * Find the route that uses the given action and method.
	 *
	 * @param  string  $action
	 * @param  string  $method
	 * @return array
	 */
	public static function uses($action, $method = 'GET')
	{
		// If the action has already been reverse routed before, we'll just
		// grab the previously found route to save time. They are cached
		// in a static array on the class.
		if (isset(static::$uses[$method.$action]))
		{
			return static::$uses[$method.$action];
		}

		Bundle::routes(Bundle::name($action));

		// To find the route, we'll simply spin through the routes looking
		// for a route with a "uses" key matching the action, and if we
		// find one we cache and return it.
		foreach (static::routes($method) as $uri => $route)
		{
			if (array_get($route, 'uses') == $action)
			{
				return static::$uses[$method.$action] = array($uri => $route);
			}
		}
	}

	/**
	 * Search the routes for the route matching a method and URI.
	 *
	 * @param  string   $method
	 * @param  string   $uri
	 * @return Route
	 */
	public static function route($method, $uri)
	{
		Bundle::start($bundle = Bundle::handles($uri));

		// Of course literal route matches are the quickest to find, so we will
		// check for those first. If the destination key exists in teh routes
		// array we can just return that route now.
		if (array_key_exists($uri, static::$routes[$method]))
		{
			$action = static::$routes[$method][$uri];

			return new Route($method, $uri, $action);
		}

		// If we can't find a literal match we'll iterate through all of the
		// registered routes to find a matching route based on the route's
		// regular expressions and wildcards.
		if ( ! is_null($route = static::match($method, $uri)))
		{
			return $route;
		}
	}

	/**
	 * Iterate through every route to find a matching route.
	 *
	 * @param  string  $method
	 * @param  string  $uri
	 * @return Route
	 */
	protected static function match($method, $uri)
	{
		foreach (static::routes($method) as $route => $action)
		{
			// We only need to check routes with regular expression since all other
			// would have been able to be matched by the search for literal matches
			// we just did before we started searching.
			if (str_contains($route, '('))
			{
				$pattern = '#^'.static::wildcards($route).'$#';

				// If we get a match we'll return the route and slice off the first
				// parameter match, as preg_match sets the first array item to the
				// full-text match of the pattern.
				if (preg_match($pattern, $uri, $parameters))
				{
					return new Route($method, $route, $action, array_slice($parameters, 1));
				}
			}
		}
	}

	/**
	 * Translate route URI wildcards into regular expressions.
	 *
	 * @param  string  $key
	 * @return string
	 */
	protected static function wildcards($key)
	{
		list($search, $replace) = array_divide(static::$optional);

		// For optional parameters, first translate the wildcards to their
		// regex equivalent, sans the ")?" ending. We'll add the endings
		// back on when we know the replacement count.
		$key = str_replace($search, $replace, $key, $count);

		if ($count > 0)
		{
			$key .= str_repeat(')?', $count);
		}

		return strtr($key, static::$patterns);
	}

	/**
	 * Get all of the registered routes, with fallbacks at the end.
	 *
	 * @param  string  $method
	 * @return array
	 */
	public static function routes($method = null)
	{
		$routes = array_get(static::$routes, $method);

		return array_merge($routes, array_get(static::$fallback, $method));
	}

	/**
	 * Get all of the wildcard patterns
	 *
	 * @return array
	 */
	public static function patterns()
	{
		return array_merge(static::$patterns, static::$optional);
	}

	/**
	 * Get a string repeating a URI pattern any number of times.
	 *
	 * @param  string  $pattern
	 * @param  int     $times
	 * @return string
	 */
	protected static function repeat($pattern, $times)
	{
		return implode('/', array_fill(0, $times, $pattern));
	}

}