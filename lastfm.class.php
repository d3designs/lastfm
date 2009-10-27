<?php
/**
 * File: api-lastfm
 * 	Handle the Last.fm API.
 *
 * Version:
 * 	2009.10.26
 *
 * Copyright:
 * 	2009 Ryan Parman
 *
 * License:
 * 	Simplified BSD License - http://opensource.org/licenses/bsd-license.php
 */


/*%******************************************************************************************%*/
// CORE DEPENDENCIES

// Include the config file
if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.inc.php'))
{
	include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.inc.php';
}


/*%******************************************************************************************%*/
// CONSTANTS

/**
 * Constant: LASTFM_NAME
 * 	Name of the software.
 */
define('LASTFM_NAME', 'api-lastfm');

/**
 * Constant: LASTFM_VERSION
 * 	Version of the software.
 */
define('LASTFM_VERSION', '1.0');

/**
 * Constant: LASTFM_BUILD
 * 	Build ID of the software.
 */
define('LASTFM_BUILD', gmdate('YmdHis', strtotime(substr('$Date$', 7, 25)) ? strtotime(substr('$Date$', 7, 25)) : filemtime(__FILE__)));

/**
 * Constant: LASTFM_URL
 * 	URL to learn more about the software.
 */
define('LASTFM_URL', 'http://github.com/skyzyx/lastfm/');

/**
 * Constant: LASTFM_USERAGENT
 * 	User agent string used to identify the software
 */
define('LASTFM_USERAGENT', LASTFM_NAME . '/' . LASTFM_VERSION . ' (Last.fm Toolkit; ' . LASTFM_URL . ') Build/' . LASTFM_BUILD);


/*%******************************************************************************************%*/
// CLASS

/**
 * Class: LastFM
 */
class LastFM
{
	/**
	 * Property: key
	 * 	The Last.fm API Key. This is inherited by all service-specific classes.
	 */
	var $key;

	/**
	 * Property: secret_key
	 * 	The Last.fm API Secret Key. This is inherited by all service-specific classes.
	 */
	var $secret_key;

	/**
	 * Property: subclass
	 * 	The API subclass (e.g. album, artist, user) to point the request to.
	 */
	var $subclass;

	/**
	 * Property: api_version
	 * The supported API version. This is inherited by all service-specific classes.
	 */
	var $api_version = null;

	/**
	 * Property: set_hostname
	 * 	Stores the alternate hostname to use, if any. This is inherited by all service-specific classes.
	 */
	var $hostname = null;


	/*%******************************************************************************************%*/
	// CONSTRUCTOR

	/**
	 * Method: __construct()
	 * 	The constructor.
	 *
	 * Access:
	 * 	public
	 *
	 * Parameters:
	 * 	key - _string_ (Optional) Your Amazon API Key. If blank, it will look for the <AWS_KEY> constant.
	 * 	secret_key - _string_ (Optional) Your Amazon API Secret Key. If blank, it will look for the <AWS_SECRET_KEY> constant.
	 * 	subclass - _string_ (Optional) Don't use this. This is an internal parameter.
	 *
	 * Returns:
	 * 	boolean FALSE if no valid values are set, otherwise true.
	 */
	public function __construct($key = null, $secret_key = null, $subclass = null)
	{
		// Instantiate the utilities class.
		// $this->util = new $this->utilities_class();

		// Set default values
		$this->key = null;
		$this->secret_key = null;
		$this->subclass = $subclass;
		$this->api_version = '2.0';
		$this->hostname = 'ws.audioscrobbler.com';

		// If both a key and secret key are passed in, use those.
		if ($key && $secret_key)
		{
			$this->key = $key;
			$this->secret_key = $secret_key;
			return true;
		}
		// If neither are passed in, look for the constants instead.
		else if (defined('LASTFM_KEY') && defined('LASTFM_SECRET_KEY'))
		{
			$this->key = LASTFM_KEY;
			$this->secret_key = LASTFM_SECRET_KEY;
			return true;
		}

		// Otherwise set the values to blank and return false.
		else
		{
			throw new LastFM_Exception('No valid credentials were used to authenticate with Last.fm.');
		}
	}


	/*%******************************************************************************************%*/
	// SET OVERRIDE VALUES

	/**
	 * Method: set_hostname()
	 * 	Assigns a new hostname to use for an API-compatible web service.
	 *
	 * Parameters:
	 * 	hostname - _string_ (Required) The hostname to make requests to.
	 *
	 * Returns:
	 * 	void
	 */
	public function set_hostname($hostname)
	{
		$this->hostname = $hostname;
	}

	/**
	 * Method: set_api_version()
	 * 	Sets a new API version to use in the request.
	 *
	 * Parameters:
	 * 	api_version - _string_ (Required) The version to use (e.g. 2.0).
	 *
	 * Returns:
	 * 	void
	 */
	public function set_api_version($api_version)
	{
		$this->api_version = $api_version;
	}


	/*%******************************************************************************************%*/
	// MAGIC METHODS

	/**
	 * Handle requests to properties
	 */
	public function __get($var)
	{
		// Determine the name of this class
		$class_name = get_class($this);

		// Re-instantiate this class, passing in the subclass value
		return new $class_name($this->key, $this->secret_key, strtolower($var));
	}

	/**
	 * Handle requests to methods
	 */
	public function __call($name, $args)
	{
		// Change the names of the methods to match what the API expects
		$name = strtolower(str_replace('_', '', $name));

		// Construct the rest of the query parameters with what was passed to the method
		$fields = http_build_query((count($args) > 0) ? $args[0] : array(), '', '&');

		// Put together the name of the API method to call
		$method = (isset($this->subclass)) ? sprintf('%s.%s', $this->subclass, $name) : $name;

		// Construct the URL to request
		$api_call = sprintf('http://' . $this->hostname . '/' . $this->api_version . '/?api_key=' . $this->key . '&method=%s&%s', $method, $fields);

		// Return the value
		return $this->request($api_call);
	}


	/*%******************************************************************************************%*/
	// REQUEST/RESPONSE

	/**
	 * Method: request()
	 * 	Requests the data, parses it, and returns it. Requires RequestCore and SimpleXML.
	 *
	 * Parameters:
	 * 	url - _string_ (Required) The web service URL to request.
	 *
	 * Returns:
	 * 	ResponseCore object
	 */
	public function request($url)
	{
		if (class_exists('RequestCore'))
		{
			$http = new RequestCore($url);
			$http->send_request();

			$response = new stdClass();
			$response->header = $http->get_response_header();
			$response->body = new SimpleXMLElement($http->get_response_body(), LIBXML_NOCDATA);
			$response->status = $http->get_response_code();

			return $response;
		}

		throw new Exception('This class requires RequestCore. http://requestcore.googlecode.com');
	}
}
