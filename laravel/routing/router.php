<?php namespace Laravel\Routing;

use Closure;
use Laravel\Str;
use Laravel\Bundle;
use Laravel\Request;

class Router {

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
	 * All of the route names that have been matched with URIs.
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
	 * The number of URI segments allowed as method arguments.
	 *
	 * @var int
	 */
	public static $segments = 6;

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
	 * @param  string|array  $route
	 * @param  mixed         $action
	 * @return void
	 */
	public static function secure($route, $action)
	{
		static::register($route, $action, true);
	}

	/**
	 * Register a controller with the router.
	 *
	 * @param  string|array  $controller
	 * @return void
	 */
	public static function controller($controllers)
	{
		foreach ((array) $controllers as $controller)
		{
			list($bundle, $controller) = Bundle::parse($controller);

			// First we need to replace the dots with slashes in thte controller name
			// so that it is in directory format. The dots allow the developer to use
			// a clean syntax when specifying the controller.
			$path = str_replace('.', '/', $controller);

			// We also need to grab the root URI for the bundle. The "handles" option
			// on the bundle specifies which URIs the bundle responds to, and we need
			// to prefix the route with that value.
			if ($bundle !== DEFAULT_BUNDLE)
			{
				$root = Bundle::option($bundle, 'handles');
			}

			// The number of method arguments allowed for a controller is set by the
			// "segments" constant on this class, which allows for the developers to
			// increase or decrease the limit on total number of method arguments
			// that can be passed to a routable controller action.
			$segments = static::$segments + 1;

			$wildcards = implode('/', array_fill(0, $segments, '(:any?)'));

			$pattern = "* /{$root}{$path}/{$wildcards}";

			// Once we have the path and root URI we can generate a basic route for
			// the controller that should handle a typical, conventional controller
			// routing setup of controller/method/segment/segment that is used in
			// other popular MVC frameworks like CodeIgniter.
			static::register($pattern, array(
				
				'uses'     => "{$bundle}::{$controller}@(:1)",

				'defaults' => array_pad(array('index'), $segments, null),
			
			));
		}
	}

	/**
	 * Register a route with the router.
	 *
	 * <code>
	 *		// Register a route with the router
	 *		Router::register('GET /', function() {return 'Home!';});
	 *
	 *		// Register a route that handles multiple URIs with the router
	 *		Router::register(array('GET /', 'GET /home'), function() {return 'Home!';});
	 * </code>
	 *
	 * @param  string|array  $route
	 * @param  mixed         $action
	 * @param  bool          $https
	 * @return void
	 */
	public static function register($route, $action, $https = false)
	{
		if (is_string($route)) $route = explode(', ', $route);

		foreach ((array) $route as $uri)
		{
			// If the URI begins with a splat, we'll call the universal method, which
			// will register a route for each of the request methods supported by
			// the router. This is just a notational short-cut.
			if (starts_with($uri, '*'))
			{
				static::universal(substr($uri, 2), $action);

				continue;
			}

			// If the URI begins with a wildcard, we want to add this route to the
			// array of "fallback" routes. Fallback routes are always processed
			// last when parsing routes since they are very generic and could
			// overload bundle routes that are registered.
			if (str_contains($uri, ' /('))
			{
				$routes =& static::$fallback;
			}
			else
			{
				$routes =& static::$routes;
			}

			// If the action is a string, it is a pointer to a controller, so we
			// need to add it to the action array as a "uses" clause, which will
			// indicate to the route to call the controller when the route is
			// executed by the application.
			if (is_string($action))
			{
				$routes[$uri]['uses'] = $action;
			}
			// If the action is not a string, we can just simply cast it as an
			// array, then we will add all of the URIs to the action array as
			// the "handes" clause so we can easily check which URIs are
			// handled by the route instance.
			else
			{
				if ($action instanceof Closure) $action = array($action);

				$routes[$uri] = (array) $action;
			}

			// If the HTTPS option is not set on the action, we will use the
			// value given to the method. The "secure" method passes in the
			// HTTPS value in as a parameter short-cut, just so the dev
			// doesn't always have to add it to an array.
			if ( ! isset($routes[$uri]['https']))
			{
				$routes[$uri]['https'] = $https;
			}

			$routes[$uri]['handles'] = (array) $route;
		}
	}

	/**
	 * Register a route for all HTTP verbs.
	 *
	 * @param  string  $route
	 * @param  mixed   $action
	 * @return void
	 */
	protected static function universal($route, $action)
	{
		$count = count(static::$methods);

		$routes = array_fill(0, $count, $route);

		// When registering a universal route, we'll iterate through all of the
		// verbs supported by the router and prepend each one of the URIs with
		// one of the request verbs, then we'll register them.
		for ($i = 0; $i < $count; $i++)
		{
			$routes[$i] = static::$methods[$i].' '.$routes[$i];
		}

		static::register($routes, $action);
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
		// the bundles that are installed.
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
			if (isset($value['name']) and $value['name'] == $name)
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

		foreach (static::routes() as $uri => $route)
		{
			// To find the route, we'll simply spin through the routes looking
			// for a route with a "uses" key matching the action, then we'll
			// check the request method for a match.
			if (isset($route['uses']) and $route['uses'] == $action)
			{
				if (starts_with($uri, $method))
				{
					return static::$uses[$method.$action] = array($uri => $route);
				}
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
		// First we will make sure the bundle that handles the given URI has been
		// started for the current request. Bundles may handle any URI beginning
		// with their "handles" string.
		Bundle::start($bundle = Bundle::handles($uri));

		// All route URIs begin with the request method and have a leading slash
		// before the URI. We'll put the request method and URI in that format
		// so we can find matches easily.
		$destination = $method.' /'.trim($uri, '/');

		// Of course literal route matches are the quickest to find, so we will
		// check for those first. If the destination key exists in teh routes
		// array we can just return that route now.
		if (array_key_exists($destination, static::$routes))
		{
			$action = static::$routes[$destination];

			return new Route($destination, $action);
		}

		// If we can't find a literal match we'll iterate through all of the
		// registered routes to find a matching route based on the route's
		// regular expressions and wildcards.
		if ( ! is_null($route = static::match($destination)))
		{
			return $route;
		}
	}

	/**
	 * Iterate through every route to find a matching route.
	 *
	 * @param  string  $destination
	 * @return Route
	 */
	protected static function match($destination)
	{
		foreach (static::routes() as $route => $action)
		{
			// We only need to check routes with regular expressions since all other
			// would have been able to be caught by the check for literal matches
			// we just did before this loop.
			if (strpos($route, '(') !== false)
			{
				$pattern = '#^'.static::wildcards($route).'$#';

				// If we get a match, we'll return the route and slice off the first
				// parameter match, as preg_match sets the first array item to the
				// full-text match of the pattern.
				if (preg_match($pattern, $destination, $parameters))
				{
					return new Route($route, $action, array_slice($parameters, 1));
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
	 * @return array
	 */
	public static function routes()
	{
		return array_merge(static::$routes, static::$fallback);
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

}