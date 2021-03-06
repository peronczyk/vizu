<?php

/**
 * =================================================================================
 *
 * VIZU CMS
 * Lib: Router
 *
 * =================================================================================
 */

class Router {

	/**
	 * Website protocol: http:// or https://
	 */
	public $protocol;

	/**
	 * Full actual URL
	 */
	public $url;

	/**
	 * Domain, eg: domain.com
	 */
	public $domain;

	/**
	 * Website path, eg: http://www.domain.com/website
	 */
	public $site_path;

	/**
	 * Reqested modules, eg: /admin/login
	 * @var Array
	 */
	public $request = [];

	/**
	 * Requested query, eg: ?foo=bar&baz=lorem
	 * @var Array
	 */
	public $query = [];


	/** ----------------------------------------------------------------------------
	 * Constructor
	 * Sets often used variables that describes user location
	 */

	public function __construct() {

		// Set website protocol
		$this->protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';

		// Set full actual URL, eg.: http://domain.com/website/request/?query=true
		$this->url = $this->protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		// Set website domain, eg.: domain.com
		$this->domain = $_SERVER['HTTP_HOST'];

		// Create website URL, eg.: http://domain.com/website
		$this->site_path = $this->protocol . $_SERVER['SERVER_NAME'];
		$script_dirname = dirname($_SERVER['SCRIPT_NAME']);
		if ($script_dirname != '/') {
			$this->site_path .= $script_dirname;
		}

		// Return string after domain from physical URL
		// eg.: 'website' from http://domain.com/website/request
		$dirname = basename($this->site_path);
		$requested_str = $_SERVER['REQUEST_URI'];

		// Check if website is in subdirectory.
		// If yes remove everything from start to occurence of subfolder name
		$pos = strpos(strtolower($requested_str), strtolower($dirname)); // Return occurence of 'website' in requested URI
		if ($pos !== false) {
			$requested_str = substr($requested_str, $pos + strlen($dirname));
		}

		// Divide string into params chunk and query string chunk
		$requested_str = explode('?', $requested_str);

		// Get request params
		$this->request = array_values(array_filter(explode('/', $requested_str[0])));

		// Get query params
		if (isset($requested_str[1])) {
			parse_str($requested_str[1], $this->query);
		}
	}


	/** ----------------------------------------------------------------------------
	 * Load module
	 */

	public function getModuleToLoad() {
		$first_request = $this->getFirstRequest();
		if ($first_request) {
			$module_file = Config::$APP_DIR . 'modules/' . $first_request . '/' . $first_request . '.php';
			if (file_exists($module_file)) {
				return $module_file;
			}
			else {
				$error404_module_file = Config::$APP_DIR . 'modules/404/404.php';
				if (file_exists($error404_module_file)) {
					return $error404_module_file;
				}
				else {
					Core::error('Requested module "' . Config::$DEFAULT_MODULE . '" and module "404" does not exist.', __FILE__, __LINE__, debug_backtrace());
				}
			}
		}
		else {
			$default_module_file = Config::$APP_DIR . 'modules/' . Config::$DEFAULT_MODULE . '/' . Config::$DEFAULT_MODULE . '.php';
			if (file_exists($default_module_file)) {
				return $default_module_file;
			}
			else {
				Core::error('Configured default module "' . Config::$DEFAULT_MODULE . '" does not exist', __FILE__, __LINE__, debug_backtrace());
			}
		}
		return false;
	}


	/** ----------------------------------------------------------------------------
	 * Get first request
	 */

	public function getFirstRequest() {
		return $this->getRequestChunk(0);
	}


	/** ----------------------------------------------------------------------------
	 * Get specified element of the request
	 */

	public function getRequestChunk(int $number) {
		return $this->request[$number] ?? null;
	}


	/** ----------------------------------------------------------------------------
	 * Request shift
	 * Remove first request from array
	 */

	public function requestShift() {
		array_shift($this->request);
		return $this->request;
	}


	/** ----------------------------------------------------------------------------
	 * Redirect to specified module
	 *
	 * @param Boolean $add_request
	 * @param Boolean $add_query
	 */

	public function redirect($path, $add_request = false, $add_query = false) {
		$redirect_url = trim($this->site_path, '/') . '/' . $path;

		if ($add_request && count($this->request) > 0) {
			if (substr($redirect_url, -1) != '/') {
				$redirect_url .= '/';
			}
			$redirect_url .= implode('/', $this->request);
		}
		if ($add_query && !empty($_SERVER['QUERY_STRING'])) {
			$redirect_url .= '?' . $_SERVER['QUERY_STRING'];
		}

		header('location: ' . $redirect_url);
	}


	/** ----------------------------------------------------------------------------
	 * Redirect to url address with WWW at the beginning
	 * if configuration requires it.
	 */

	public function redirectToWww() {
		$domain = explode('.', $this->domain);
		if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) && $domain[0] != 'www') {
			header('location: ' . str_replace($this->protocol, $this->protocol . 'www.', $this->url));
		}
	}

}