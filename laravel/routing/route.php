<?php namespace Laravel\Routing;

use Closure;
use Laravel\Bundle;
use Laravel\Request;
use Laravel\Response;

class Route {

	/**
	 * The URI the route response to.
	 *
	 * @var string
	 */
	public $uri;

	/**
	 * The request method the route responds to.
	 *
	 * @var string
	 */
	public $method;

	/**
	 * The bundle in which the route was registered.
	 *
	 * @var string
	 */
	public $bundle;

	/**
	 * The action that is assigned to the route.
	 *
	 * @var mixed
	 */
	public $action;

	/**
	 * The parameters that will passed to the route callback.
	 *
	 * @var array
	 */
	public $parameters;

	/**
	 * Create a new Route instance.
	 *
	 * @param  string   $method
	 * @param  string   $uri
	 * @param  array    $action
	 * @param  array    $parameters
	 * @return void
	 */
	public function __construct($method, $uri, $action, $parameters = array())
	{
		$this->uri = $uri;
		$this->method = $method;
		$this->action = $action;

		// Determine the bundle in which the route was registered. We will know
		// the bundle by using the bundle::handles method, which will return
		// the bundle assigned to that URI.
		$this->bundle = Bundle::handles($uri);

		// We'll need to set the parameters passed on the number parameters and
		// optional parameters compared to the parameters actually defined in
		// the URI string for the route.
		$this->parameters($uri, $action, $parameters);
	}

	/**
	 * Set the parameters array to the correct value.
	 *
	 * @param  string  $uri
	 * @param  array   $action
	 * @param  array   $parameters
	 * @return void
	 */
	protected function parameters($uri, $action, $parameters)
	{
		$wildcards = 0;

		$defaults = (array) array_get($action, 'defaults');

		// We need to determine how many of the default paramters should be merged
		// into the parameter array. First, we will count the number of wildcards
		// in the route URI and then merge the defaults.
		foreach (array_keys(Router::patterns()) as $wildcard)
		{
			$wildcards += substr_count($uri, $wildcard);
		}

		$needed = $wildcards - count($parameters);

		// If there are less parameters than wildcards, we will figure out how
		// many parameters we need to inject from the array of defaults and
		// merge them in into the main parameter array for the route.
		if ($needed > 0)
		{
			$defaults = array_slice($defaults, count($defaults) - $needed);

			$parameters = array_merge($parameters, $defaults);
		}

		$this->parameters = $parameters;
	}

	/**
	 * Call a given route and return the route's response.
	 *
	 * @return Response
	 */
	public function call()
	{
		// The route is responsible for running the global filters, and any
		// filters defined on the route itself, since all incoming requests
		// come through a route (either defined or ad-hoc).
		$response = Filter::run($this->filters('before'), array(), true);

		if (is_null($response))
		{
			$response = $this->response();
		}

		// We always return a Response instance from the route calls, so
		// we'll use the prepare method on the Response class to make
		// sure we have a valid Response isntance.
		$response = Response::prepare($response);

		Filter::run($this->filters('after'), array($response));

		return $response;
	}

	/**
	 * Execute the route action and return the response.
	 *
	 * Unlike the "call" method, none of the attached filters will be run.
	 *
	 * @return mixed
	 */
	public function response()
	{
		// If the action is a string, it is pointing the route to a controller
		// action, and we can just call the action and return its response.
		// We'll just pass the action off to the Controller class.
		$delegate = $this->delegate();

		if ( ! is_null($delegate))
		{
			return Controller::call($delegate, $this->parameters);
		}

		// If the route does not have a delegate, then it must be a Closure
		// instance or have a Closure in its action array, so we will try
		// to locate the Closure and call it directly.
		$handler = $this->handler();

		if ( ! is_null($handler))
		{
			return call_user_func_array($handler, $this->parameters);
		}
	}

	/**
	 * Get the filters that are attached to the route for a given event.
	 *
	 * @param  string  $event
	 * @return array
	 */
	protected function filters($event)
	{
		$global = Bundle::prefix($this->bundle).$event;

		$filters = array_unique(array($event, $global));

		// Next we will check to see if there are any filters attached to
		// the route for the given event. If there are, we'll merge them
		// in with the global filters for the event.
		if (isset($this->action[$event]))
		{
			$assigned = Filter::parse($this->action[$event]);

			$filters = array_merge($filters, $assigned);
		}

		return array(new Filter_Collection($filters));
	}

	/**
	 * Get the controller action delegate assigned to the route.
	 *
	 * If no delegate is assigned, null will be returned by the method.
	 *
	 * @return string
	 */
	protected function delegate()
	{
		return array_get($this->action, 'uses');
	}

	/**
	 * Get the anonymous function assigned to handle the route.
	 *
	 * If no anonymous function is assigned, null will be returned by the method.
	 *
	 * @return Closure
	 */
	protected function handler()
	{
		return array_first($this->action, function($key, $value)
		{
			return $value instanceof Closure;
		});
	}

	/**
	 * Determine if the route has a given name.
	 *
	 * <code>
	 *		// Determine if the route is the "login" route
	 *		$login = Request::route()->is('login');
	 * </code>
	 *
	 * @param  string  $name
	 * @return bool
	 */
	public function is($name)
	{
		return is_array($this->action) and array_get($this->action, 'name') === $name;
	}

	/**
	 * Register a GET route with the router.
	 *
	 * @param  string|array  $route
	 * @param  mixed         $action
	 * @return void
	 */
	public static function get($route, $action)
	{
		Router::register('GET', $route, $action);
	}

	/**
	 * Register a POST route with the router.
	 *
	 * @param  string|array  $route
	 * @param  mixed         $action
	 * @return void
	 */
	public static function post($route, $action)
	{
		Router::register('POST', $route, $action);
	}

	/**
	 * Register a PUT route with the router.
	 *
	 * @param  string|array  $route
	 * @param  mixed         $action
	 * @return void
	 */
	public static function put($route, $action)
	{
		Router::register('PUT', $route, $action);
	}

	/**
	 * Register a DELETE route with the router.
	 *
	 * @param  string|array  $route
	 * @param  mixed         $action
	 * @return void
	 */
	public static function delete($route, $action)
	{
		Router::register('DELETE', $route, $action);
	}

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
		Router::secure($method, $route, $action);
	}

	/**
	 * Register a controller with the router.
	 *
	 * @param  string|array  $controller
	 * @return void
	 */
	public static function controller($controller)
	{
		Router::controller($controller);
	}

}