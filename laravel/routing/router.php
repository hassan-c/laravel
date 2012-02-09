<?php namespace Laravel\Routing; use Closure, Laravel\Str, Laravel\Bundle;

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
		foreach ((array) $route as $uri)
		{
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

			$routes[$uri]['https'] = $https;

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
		foreach (static::routes() as $uri => $route)
		{
			// To find the route, we'll simply spin through the routes looking
			// for a route with a "uses" key matching the action, then we'll
			// check the request method for a match.
			if (isset($route['uses']) and $route['uses'] == $action)
			{
				if (starts_with($uri, $method))
				{
					return array($uri => $route);
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
		// First we will make sure the bundle that handles the given URI has
		// been started for the current request. Bundles may handle any URI
		// beginning with their specified "handles" string.
		Bundle::start($bundle = Bundle::handles($uri));

		// All route URIs begin with the request method and have a leading
		// slash before the URI. We'll put the request method and URI in
		// that format so we can find matches.
		$destination = $method.' /'.trim($uri, '/');

		if (array_key_exists($destination, static::$routes))
		{
			$action = static::$routes[$destination];

			return new Route($destination, $action);
		}

		// If we can't find a literal match, we'll iterate through all of
		// the registered routes to find a matching route that uses some
		// regular expressions or wildcards.
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