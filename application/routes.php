<?php

/*
|--------------------------------------------------------------------------
| Bundle Routing
|--------------------------------------------------------------------------
|
| Want to setup convention based controller routing for a bundle? It is
| simple as pie to get started. The Route::bundle method will set up a
| great route to get you started. 
|
| The first and second segments will specify the controller and method,
| and the rest will be passed to the method as parameters. A route is
| also generated to point the root URI to the home@index method.
|
*/

Route::bundle();

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Simply tell Laravel the HTTP verbs and URIs it should respond to. It is a
| breeze to setup your applications using Laravel's RESTful routing, and it
| is perfectly suited for building both large applications and simple APIs.
| Enjoy the fresh air and simplicity of the framework.
|
| Let's respond to a simple GET request to http://example.com/hello:
|
|		Route::to('GET /hello', function()
|		{
|			return 'Hello World!';
|		});
|
| You can even respond to more than one URI:
|
|		Route::to('GET /hello, GET /world', function()
|		{
|			return 'Hello World!';
|		});
|
| It's easy to allow URI wildcards using (:num) or (:any):
|
|		Route::to('GET /hello/(:any)', function($name)
|		{
|			return "Welcome, $name.";
|		});
|
*/

Route::to('* /', function()
{
	return View::make('home.index');
});

Route::to('* /(:all)', function()
{
	return Response::error('404');
});

/*
|--------------------------------------------------------------------------
| Route Filters
|--------------------------------------------------------------------------
|
| Filters provide a convenient method for attaching functionality to your
| routes. The built-in "before" and "after" filters are called before and
| after every request to your application, and you may even create other
| filters that can be attached to individual routes.
|
| Let's walk through an example...
|
| First, define a filter:
|
|		Filter::register('filter', function()
|		{
|			return 'Filtered!';
|		});
|
| Next, attach the filter to a route:
|
|		Router::register('GET /', array('before' => 'filter', function()
|		{
|			return 'Hello World!';
|		}));
|
*/

Filter::register('before', function()
{
	// Do stuff before every request to your application...
});

Filter::register('after', function()
{
	// Do stuff after every request to your application...
});

Filter::register('csrf', function()
{
	if (Request::forged()) return Response::error('500');
});

Filter::register('auth', function()
{
	if (Auth::guest()) return Redirect::to('login');
});