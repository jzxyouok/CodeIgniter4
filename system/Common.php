<?php
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014 - 2017, British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package CodeIgniter
 * @author  CodeIgniter Dev Team
 * @copyright   Copyright (c) 2014 - 2017, British Columbia Institute of Technology (http://bcit.ca/)
 * @license https://opensource.org/licenses/MIT  MIT License
 * @link    https://codeigniter.com
 * @since   Version 3.0.0
 * @filesource
 */

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\RedirectException;
use CodeIgniter\Services;

/**
 * Common Functions
 *
 * Several application-wide utility methods.
 *
 * @package  CodeIgniter
 * @category Common Functions
 */

//--------------------------------------------------------------------
// Services Convenience Functions
//--------------------------------------------------------------------

if (! function_exists('cache'))
{
	/**
	 * A convenience method that provides access to the Cache
	 * object. If no parameter is provided, will return the object,
	 * otherwise, will attempt to return the cached value.
	 *
	 * Examples:
	 *    cache()->save('foo', 'bar');
	 *    $foo = cache('bar');
	 *
	 * @param string|null $key
	 *
	 * @return mixed
	 */
	function cache(string $key = null)
	{
		$cache = \Config\Services::cache();

		// No params - return cache object
		if (is_null($key))
		{
			return $cache;
		}

		// Still here? Retrieve the value.
		return $cache->get($key);
	}
}

//--------------------------------------------------------------------

if ( ! function_exists('view'))
{
	/**
	 * Grabs the current RendererInterface-compatible class
	 * and tells it to render the specified view. Simply provides
	 * a convenience method that can be used in Controllers,
	 * libraries, and routed closures.
	 *
	 * NOTE: Does not provide any escaping of the data, so that must
	 * all be handled manually by the developer.
	 *
	 * @param string $name
	 * @param array  $data
	 * @param array  $options Unused - reserved for third-party extensions.
	 *
	 * @return string
	 */
	function view(string $name, array $data = [], array $options = [])
	{
		/**
		 * @var CodeIgniter\View\View $renderer
		 */
		$renderer = Services::renderer();

		$saveData = null;
		if (array_key_exists('saveData', $options) && $options['saveData'] === true)
		{
			$saveData = (bool)$options['saveData'];
			unset($options['saveData']);
		}

		return $renderer->setData($data, 'raw')
			->render($name, $options, $saveData);
	}
}

//--------------------------------------------------------------------

if (! function_exists('view_cell'))
{
	/**
	 * View cells are used within views to insert HTML chunks that are managed
	 * by other classes.
	 *
	 * @param string      $library
	 * @param null        $params
	 * @param int         $ttl
	 * @param string|null $cacheName
	 *
	 * @return string
	 */
	function view_cell(string $library, $params = null, int $ttl = 0, string $cacheName = null)
	{
		return Services::viewcell()->render($library, $params, $ttl, $cacheName);
	}
}

//--------------------------------------------------------------------

if ( ! function_exists('env'))
{
	/**
	 * Allows user to retrieve values from the environment
	 * variables that have been set. Especially useful for
	 * retrieving values set from the .env file for
	 * use in config files.
	 *
	 * @param string $key
	 * @param null   $default
	 *
	 * @return array|bool|false|null|string|void
	 */
	function env(string $key, $default = null)
	{
		$value = getenv($key);
		if ($value === false)
		{
			$value = $_ENV[$key] ?? $_SERVER[$key] ?? false;
		}

		// Not found? Return the default value
		if ($value === false)
		{
			return $default;
		}

		// Handle any boolean values
		switch (strtolower($value))
		{
			case 'true':
				return true;
			case 'false':
				return false;
			case 'empty':
				return '';
			case 'null':
				return;
		}

		return $value;
	}
}

//--------------------------------------------------------------------

if ( ! function_exists('esc'))
{
	/**
	 * Performs simple auto-escaping of data for security reasons.
	 * Might consider making this more complex at a later date.
	 *
	 * If $data is a string, then it simply escapes and returns it.
	 * If $data is an array, then it loops over it, escaping each
	 * 'value' of the key/value pairs.
	 *
	 * Valid context values: html, js, css, url, attr, raw, null
	 *
	 * @param string|array $data
	 * @param string       $context
	 * @param string       $encoding
	 *
	 * @return $data
	 */
	function esc($data, $context = 'html', $encoding=null)
	{
		if (is_array($data))
		{
			foreach ($data as $key => &$value)
			{
				$value = esc($value, $context);
			}
		}

		if (is_string($data))
		{
			$context = strtolower($context);

			// Provide a way to NOT escape data since
			// this could be called automatically by
			// the View library.
			if (empty($context) || $context == 'raw')
			{
				return $data;
			}

			if ( ! in_array($context, ['html', 'js', 'css', 'url', 'attr']))
			{
				throw new \InvalidArgumentException('Invalid escape context provided.');
			}

			if ($context == 'attr')
			{
				$method = 'escapeHtmlAttr';
			}
			else
			{
				$method = 'escape'.ucfirst($context);
			}

			// @todo Optimize this to only load a single instance during page request.
			$escaper = new \Zend\Escaper\Escaper($encoding);

			$data   = $escaper->$method($data);
		}

		return $data;
	}
}

//--------------------------------------------------------------------

if (! function_exists('session'))
{
	/**
	 * A convenience method for accessing the session instance,
	 * or an item that has been set in the session.
	 *
	 * Examples:
	 *    session()->set('foo', 'bar');
	 *    $foo = session('bar');
	 *
	 * @param null $val
	 *
	 * @return \CodeIgniter\Session\Session|null|void
	 */
	function session($val = null)
	{
		// Returning a single item?
		if (is_string($val))
		{
			return $_SESSION[$val] ?? null;
		}

		return \Config\Services::session();
	}
}

//--------------------------------------------------------------------

if (! function_exists('timer'))
{
	/**
	 * A convenience method for working with the timer.
	 * If no parameter is passed, it will return the timer instance,
	 * otherwise will start or stop the timer intelligently.
	 *
	 * @param string|null $name
	 *
	 * @return $this|\CodeIgniter\Debug\Timer|mixed
	 */
	function timer(string $name = null)
	{
		$timer = \Config\Services::timer();

		if (empty($name))
		{
			return $timer;
		}

		if ($timer->has($name))
		{
			return $timer->stop($name);
		}

		return $timer->start($name);
	}
}

//--------------------------------------------------------------------

if (! function_exists('service'))
{
	/**
	 * Allows cleaner access to the Services Config file.
	 * Always returns a SHARED instance of the class, so
	 * calling the function multiple times should always
	 * return the same instance.
	 *
	 * These are equal:
	 *  - $timer = service('timer')
	 *  - $timer = \CodeIgniter\Services::timer();
	 *
	 * @param string $name
	 * @param array  ...$params
	 *
	 * @return mixed
	 */
	function service(string $name, ...$params)
	{
		// Ensure it IS a shared instance
		array_push($params, true);

		return Services::$name(...$params);
	}
}

//--------------------------------------------------------------------

if (! function_exists('single_service'))
{
	/**
	 * Allow cleaner access to a Service.
	 * Always returns a new instance of the class.
	 *
	 * @param string $name
	 * @param array|null $params
	 */
	function single_service(string $name, ...$params)
	{
		// Ensure it's NOT a shared instance
		array_push($params, false);

		return Services::$name(...$params);
	}
}

//--------------------------------------------------------------------

if (! function_exists('lang'))
{
	/**
	 * A convenience method to translate a string and format it
	 * with the intl extension's MessageFormatter object.
	 *
	 * @param string $line
	 * @param array  $args
	 *
	 * @return string
	 */
	function lang(string $line, array $args=[])
	{
		return Services::language()->getLine($line, $args);
	}
}

//--------------------------------------------------------------------



if ( ! function_exists('log_message'))
{
	/**
	 * A convenience/compatibility method for logging events through
	 * the Log system.
	 *
	 * Allowed log levels are:
	 *  - emergency
	 *  - alert
	 *  - critical
	 *  - error
	 *  - warning
	 *  - notice
	 *  - info
	 *  - debug
	 *
	 * @param string $level
	 * @param string $message
	 * @param array|null  $context
	 *
	 * @return mixed
	 */
	function log_message(string $level, string $message, array $context = [])
	{
		// When running tests, we want to always ensure that the
		// TestLogger is running, which provides utilities for
		// for asserting that logs were called in the test code.
		if (ENVIRONMENT == 'testing')
		{
			$logger = new \CodeIgniter\Log\TestLogger(new \Config\Logger());
			return $logger->log($level, $message, $context);
		}

		return Services::logger(true)
			->log($level, $message, $context);
	}
}

//--------------------------------------------------------------------

if ( ! function_exists('is_cli'))
{

	/**
	 * Is CLI?
	 *
	 * Test to see if a request was made from the command line.
	 *
	 * @return    bool
	 */
	function is_cli()
	{
		return (PHP_SAPI === 'cli' || defined('STDIN'));
	}
}

//--------------------------------------------------------------------

if ( ! function_exists('route_to'))
{
	/**
	 * Given a controller/method string and any params,
	 * will attempt to build the relative URL to the
	 * matching route.
	 *
	 * NOTE: This requires the controller/method to
	 * have a route defined in the routes Config file.
	 *
	 * @param string $method
	 * @param        ...$params
	 *
	 * @return \CodeIgniter\Router\string
	 */
	function route_to(string $method, ...$params): string
	{
		$routes = Services::routes();

		return $routes->reverseRoute($method, ...$params);
	}
}

//--------------------------------------------------------------------

if ( ! function_exists('remove_invisible_characters'))
{
	/**
	 * Remove Invisible Characters
	 *
	 * This prevents sandwiching null characters
	 * between ascii characters, like Java\0script.
	 *
	 * @param   string
	 * @param   bool
	 * @return  string
	 */
	function remove_invisible_characters($str, $url_encoded = TRUE)
	{
		$non_displayables = array();

		// every control character except newline (dec 10),
		// carriage return (dec 13) and horizontal tab (dec 09)
		if ($url_encoded)
		{
			$non_displayables[] = '/%0[0-8bcef]/';  // url encoded 00-08, 11, 12, 14, 15
			$non_displayables[] = '/%1[0-9a-f]/';   // url encoded 16-31
		}

		$non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';   // 00-08, 11, 12, 14-31, 127

		do
		{
			$str = preg_replace($non_displayables, '', $str, -1, $count);
		}
		while ($count);

		return $str;
	}
}

//--------------------------------------------------------------------

if (! function_exists('helper'))
{
	/**
	 * Loads a helper file into memory. Supports namespaced helpers,
	 * both in and out of the 'helpers' directory of a namespaced directory.
	 *
	 * @param string|array $filenames
	 *
	 * @return string
	 */
	function helper($filenames)//: string
	{
		$loader = Services::locator(true);

		if (! is_array($filenames))
		{
			$filenames = [$filenames];
		}

		foreach ($filenames as $filename)
		{
			if (strpos($filename, '_helper') === false)
			{
				$filename .= '_helper';
			}

			$path = $loader->locateFile($filename, 'Helpers');

			if (! empty($path))
			{
				include $path;
			}
		}
	}
}

//--------------------------------------------------------------------

if (! function_exists('app_timezone'))
{
	/**
	 * Returns the timezone the application has been set to display
	 * dates in. This might be different than the timezone set
	 * at the server level, as you often want to stores dates in UTC
	 * and convert them on the fly for the user.
	 */
	function app_timezone()
	{
		$config = new \Config\App();

		return $config->appTimezone;
	}
}

//--------------------------------------------------------------------

if (! function_exists('csrf_token'))
{
	/**
	 * Returns the CSRF token name.
	 * Can be used in Views when building hidden inputs manually,
	 * or used in javascript vars when using APIs.
	 *
	 * @return string
	 */
	function csrf_token()
	{
		$config = new \Config\App();

		return $config->CSRFTokenName;
	}
}

//--------------------------------------------------------------------

if (! function_exists('csrf_hash'))
{
	/**
	 * Returns the current hash value for the CSRF protection.
	 * Can be used in Views when building hidden inputs manually,
	 * or used in javascript vars for API usage.
	 *
	 * @return string
	 */
	function csrf_hash()
	{
		$security = Services::security(null, true);

		return $security->getCSRFHash();
	}
}

//--------------------------------------------------------------------

if (! function_exists('csrf_field'))
{
	/**
	 * Generates a hidden input field for use within manually generated forms.
	 *
	 * @return string
	 */
	function csrf_field()
	{
		return '<input type="hidden" name="'. csrf_token() .'" value="'. csrf_hash() .'">';
	}
}

//--------------------------------------------------------------------

if (! function_exists('force_https'))
{
	/**
	 * Used to force a page to be accessed in via HTTPS.
	 * Uses a standard redirect, plus will set the HSTS header
	 * for modern browsers that support, which gives best
	 * protection against man-in-the-middle attacks.
	 *
	 * @see https://en.wikipedia.org/wiki/HTTP_Strict_Transport_Security
	 *
	 * @param int $duration How long should the SSL header be set for? (in seconds)
	 *                      Defaults to 1 year.
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 */
	function force_https(int $duration = 31536000, RequestInterface $request = null, ResponseInterface $response = null)
	{
		if (is_null($request)) $request = Services::request(null, true);
		if (is_null($response)) $response = Services::response(null, true);

		if ($request->isSecure())
		{
			return;
		}

		// If the session library is loaded, we should regenerate
		// the session ID for safety sake.
		if (class_exists('Session', false))
		{
			Services::session(null, true)->regenerate();
		}

		$uri = $request->uri;
		$uri->setScheme('https');

		$uri = \CodeIgniter\HTTP\URI::createURIString(
				$uri->getScheme(),
				$uri->getAuthority(true),
				$uri->getPath(), // Absolute URIs should use a "/" for an empty path
				$uri->getQuery(),
				$uri->getFragment()
				);

		// Set an HSTS header
		$response->setHeader('Strict-Transport-Security', 'max-age='.$duration);
		$response->redirect($uri);
		exit();
	}
}

//--------------------------------------------------------------------

if (! function_exists('redirect'))
{
	/**
	 * Convenience method that works with the current global $request and
	 * $router instances to redirect using named/reverse-routed routes
	 * to determine the URL to go to. If nothing is found, will treat
	 * as a traditional redirect and pass the string in, letting
	 * $response->redirect() determine the correct method and code.
	 *
	 * If more control is needed, you must use $response->redirect explicitly.
	 *
	 * @param string   $uri
	 * @param $params
	 */
	function redirect(string $uri, ...$params)
	{
		$response = Services::response(null, true);
		$routes   = Services::routes(true);

		if ($route = $routes->reverseRoute($uri, ...$params))
		{
			$uri = $route;
		}

		$response->redirect($uri);
	}
}

//--------------------------------------------------------------------

if (! function_exists('redirect_with_input'))
{
	/**
	 * Identical to the redirect() method, except that this will
	 * send the current $_GET and $_POST contents in a _ci_old_input
	 * variable flashed to the session, which can then be retrieved
	 * via the old() method.
	 *
	 * @param string $uri
	 * @param array  ...$params
	 */
	function redirect_with_input(string $uri, ...$params)
	{
		$session = Services::session();

		// Ensure we have the session started up.
		if (! isset($_SESSION))
		{
			$session->start();
		}

		$input = [
			'get' => $_GET ?? [],
			'post' => $_POST ?? []
		];

			$session->setFlashdata('_ci_old_input', $input);

			redirect($uri, ...$params);
	}
}

//--------------------------------------------------------------------

if ( ! function_exists('stringify_attributes'))
{
	/**
	 * Stringify attributes for use in HTML tags.
	 *
	 * Helper function used to convert a string, array, or object
	 * of attributes to a string.
	 *
	 * @param   mixed   string, array, object
	 * @param   bool
	 * @return  string
	 */
	function stringify_attributes($attributes, $js = FALSE) : string
	{
		$atts = '';

		if (empty($attributes))
		{
			return $atts;
		}

		if (is_string($attributes))
		{
			return ' '.$attributes;
		}

		$attributes = (array) $attributes;

		foreach ($attributes as $key => $val)
		{
			$atts .= ($js)
				? $key.'='.esc($val, 'js').','
				: ' '.$key.'="'.esc($val, 'attr').'"';
		}

		return rtrim($atts, ',');
	}
}

//--------------------------------------------------------------------

if ( ! function_exists('is_really_writable'))
{
	/**
	 * Tests for file writability
	 *
	 * is_writable() returns TRUE on Windows servers when you really can't write to
	 * the file, based on the read-only attribute. is_writable() is also unreliable
	 * on Unix servers if safe_mode is on.
	 *
	 * @link    https://bugs.php.net/bug.php?id=54709
	 * @param   string
	 * @return  bool
	 */
	function is_really_writable($file)
	{
		// If we're on a Unix server with safe_mode off we call is_writable
		if (DIRECTORY_SEPARATOR === '/' || ! ini_get('safe_mode'))
		{
			return is_writable($file);
		}

		/* For Windows servers and safe_mode "on" installations we'll actually
		 * write a file then read it. Bah...
		 */
		if (is_dir($file))
		{
			$file = rtrim($file, '/').'/'.md5(mt_rand());
			if (($fp = @fopen($file, 'ab')) === FALSE)
			{
				return FALSE;
			}

			fclose($fp);
			@chmod($file, 0777);
			@unlink($file);
			return TRUE;
		}
		elseif ( ! is_file($file) OR ($fp = @fopen($file, 'ab')) === FALSE)
		{
			return FALSE;
		}

		fclose($fp);
		return TRUE;
	}
}

//--------------------------------------------------------------------

if ( ! function_exists('slash_item'))
{
	//Unlike CI3, this function is placed here because
	//it's not a config, or part of a config.
	/**
	 * Fetch a config file item with slash appended (if not empty)
	 *
	 * @param   string      $item   Config item name
	 * @return  string|null The configuration item or NULL if
	 * the item doesn't exist
	 */
	function slash_item($item)
	{
		$config     = new \Config\App();
		$configItem = $config->{$item};

		if ( ! isset($configItem) || empty(trim($configItem)))
		{
			return $configItem;
		}

		return rtrim($configItem, '/') . '/';
	}
}
//--------------------------------------------------------------------
