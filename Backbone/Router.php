<?php
/*
 * @license Simplified BSD
 * Copyright (c) 2012, Patrick Barnes, UTS
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 
 * 1. Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *    
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * Backbone_Router maps URLs to actions, and fires events/callbacks when routes are matched.
 * 
 * @author Patrick Barnes
 *
 */
class Backbone_Router extends Backbone_Events
{
    /*************************************************************************\
     * Attributes and Accessors
    \*************************************************************************/
        
    /**
     * Registered routes in the router, in the format route => action, in the matched order
     * action can be the name of a class method, or an array containing 'name' and 'callback' elements.
     * (The first form will be converted to the second)
     * @var array
     */
    protected $routes = array();
    
    /**
     * Registered routes in the router
     * $router->routes() : Get the routes
     * $router->routes($routes) : Set the routes, replacing any existing routes.
     * (analogous to _bindRoutes in Backbone.js)
     *  
     * @var array $routes
     * @return array
     */
    public function routes(array $routes=array()) {
        if (func_num_args()) {
            $this->routes = array();
            foreach(array_reverse($routes) as $route=>$action) {
                if (is_string($action))
                    $this->route($route, $action);
                else if (is_array($action) and !empty($action['name']) and !empty($action['callback']))
                    $this->route($route, $action['name'], $action['callback']);
                else
                    throw new InvalidArgumentException("Route '$route' couldn't be parsed, expecting a string or an array containing 'name' and 'callback' keys");
            }
        }
        return $this->routes;
    }
    
    /**
     * Store a reference to the routed request, so the router callbacks can access it 
     * @var Zend_Controller_Request_Http
     */
    protected $request = null;
    
    /**
     * Store a reference to the routed request, so the router callbacks can access it
     * $router->request() : Get the request
     * $router->request($request) : Set the request
     *
     * If a request is not locally set, fetching defers to the Backbone::getCurrentRequest() method.
     * @param Zend_Controller_Request_Http|string $request
     * @return Zend_Controller_Request_Http
     */
    public function request($request = null) {
        if (func_num_args()) {
            if (is_string($request)) {
                if (preg_match('#^https?://#',$request)) 
                    $_request = new Zend_Controller_Request_Http($request);
                else {
                    $_request = new Zend_Controller_Request_Http();
                    $_request->setPathInfo($request ? $request : '/');
                }
                $this->request = $_request;
            } else {
                $this->request = $request;
            }
        }
        return $this->request ? $this->request : Backbone::getCurrentRequest();
    }
    
    /**
     * Store a reference to the router's response   
     * @var Zend_Controller_Response_Http
     */
    protected $response = null;
    
    /**
     * Store a reference to the router's response   
     * $router->response() : Get the response
     * $router->response($response) : Set the response
     *
     * If a response is not locally set, fetching defers to the Backbone::getCurrentResponse() method.
     * @param Zend_Controller_Response_Http
     * @return Zend_Controller_Response_Http
     */
    public function response($response = null) {
        if (func_num_args()) $this->response = $response;
        return $this->response ? $this->response : Backbone::getCurrentResponse();
    }

    /**
     * If set, a whitelist of the query parameters that can be passed to the model/collection as options
     * @var array
     */
    protected $valid_client_params = null;
    
    /*************************************************************************\
     * Request Helpers
    \*************************************************************************/

    /**
     * Get the parameters for this request; they will be passed through to the
     * model/collection and the subsequent sync methods
     *
     * Router parameters come from two places;
     *  - Set in $options['params']. This can be done either directly by the application, or by a parent router.
     *  - Set in the request by the client.
     *
     * You can restrict which parameters the client may set by defining the $valid_client_params class attribute.
     * $options-defined parameters will *override* any client parameters.
     *
     * e.g. If the client requests "/asset/33?person=44";
     *  - The request parameters will contain 'person'=>44
     *  - If $valid_client_params is empty or contains 'person', the function returns array('person'=>44)
     *  - If $valid_client_params is not empty and doesn't contain 'person', the function returns array()
     *
     * e.g. If the client requests "/person/44/asset/33"; (and the 'asset' router is a sub-route of the 'person' router)
     *  - The 'person' parameter will already be set in $options['params'], the function returns array('person'=>44)
     *
     * e.g. If the client requests "/person/44/asset/33?person=11&otherparam=foo" (and $valid_client_params is empty)
     *  - As 'person' will be set by $options['params'], the request parameters cannot override it.
     *  - The function will return array('person'=>44, 'otherparam'=>'foo')
     *
     * @param array $options The invocation options, usually as passed to the route handler
     * @return array A map of parameters
     */
    public function getParams(array $options) {
    	//Fetch user parameters, filtering if specified
    	$client_params = $this->request()->getParams();
    	if ($this->valid_client_params !== null)
    		$client_params = array_intersect_key($client_params, array_flip($this->valid_client_params));
    
    	//Combine with server parameters
    	return empty($options['params']) ? $client_params : array_merge($client_params, $options['params']);
    }
    
    /**
     * Get the data for this request; usually this will be sent in the request body,
     * but it can be overriden by the 'data' query parameter.
     * JSON format is required
     *
     * @return array
     * @throws InvalidArgumentException If the request data is invalid
     */
    public function getData() {
    	$request = $this->request();
    	$raw = $request->has('data') ? $request->get('data') : $this->request()->getRawBody();
    	if (!$raw) throw new InvalidArgumentException("No data received", 400);
    	$data = json_decode($raw);
    	if ($data === null and json_last_error() != JSON_ERROR_NONE)
    		throw new InvalidArgumentException("Incorrect data format sent - should be JSON", 400);
    
    	return $data;
    }    
    
    /*************************************************************************\
     * Construction/initialisation
    \*************************************************************************/
        
    /**
     * Build a new Router object, processing the routes from the class definition
     * or the options.
     * 
     * Options:
     *  - 'routes' : Override the defined routes
     *  - 'request' : Set the given request
     *
     * @param array $options
     */
    public function __construct(array $options = array()) {
        parent::__construct($options);
        
        //Process routes
        $this->routes( !empty($options['routes']) ? $options['routes'] : $this->routes );
        
        //If request is given, set it.
        if (!empty($options['request'])) $this->request($options['request']);
        
        //If response is given, set it.
        if (!empty($options['response'])) $this->response($options['response']);

        //If valid_client_params is given, set it.
        if (!empty($options['valid_client_params'])) $this->valid_client_params = $options['valid_client_params'];
        
        //Initialize
        $this->initialize($options);
    }
    
    /**
     * Initialize is an empty function by default. Override it with your own initialization logic.
     *   
     * @param array $options The options passed on construction
     */
    protected function initialize(array $options) {
    }    
        
    /*************************************************************************\
     * Route setup
    \*************************************************************************/
    
    /**
     * Manually bind a single route to a callback.
     * 
     * For example:
     *  $router->route('search/:query/p:num', 'search', function($query, $num) {
     *     ...
     *  });
     *  
     * Note: 'route' here is a noun. For actually 'routing' the request, see __invoke
     * 
     * The most recent routes are checked first.
     *  
     * @param string $route The route to match; regex, or with :param and *splat params (a regex must be written in #^...$#flags form)
     * @param string $name The name of the route, used for triggering events
     * @param callable $callback The callback to call. If omitted, assumes that $name is a method to be called. Callback takes a number of arguments equal to the route's parameters.
     */
    public function route($route, $name, $callback = null) {
        //Checking
        if (!is_string($route)) throw new InvalidArgumentException("Invalid route given, must be string");
        if (!is_string($name)) throw new InvalidArgumentException("Invalid name given, must be string");

        //Is callback a method name? Convert to callable form
        if (is_string($callback) and method_exists($this, $callback)) $callback = array($this, $callback);
        //Is callback omitted, but name implied to be the method callback?
        elseif (func_num_args()==2 and method_exists($this, $name)) $callback = array($this, $name);
        //Is callback the name of a router class? Leave.
        elseif (is_a($callback, 'Backbone_Router', true));
        //Is the callback given, but invalid?
        elseif ($callback!==null and !is_callable($callback)) throw new InvalidArgumentException("Invalid callback given!");

        //Convert the route name to a regex for storage/use
        $route = $this->_routeToRegExp($route);

        $this->routes = array($route => array('name'=>$name, 'callback'=>$callback) ) + $this->routes; //Prepend
    }
    
    
    /**
     * Convert a route string into a regular expression, suitable for matching 
     * against the request URL.
     * 
     * If the input route is a recognised form of regular expression already, return it as-is. 
     * 
     * @param string $route
     * @return string
     */
    protected function _routeToRegExp($route) {
        if (preg_match(self::ISREGEXP, $route)) 
            return $route;
        else 
            return '#^'.preg_replace(
                array(
                    self::ESCAPEREGEXP, 
                    self::NAMEDPARAM, 
                    self::ENDSPLATPARAM,
                    self::SPLATPARAM
                ),
                array(
                    self::ESCAPEREGEXP_REPLACEMENT,
                    self::NAMEDPARAM_REPLACEMENT,
                    self::ENDSPLATPARAM_REPLACEMENT,
                    self::SPLATPARAM_REPLACEMENT
                ),
                $route
            ).'$#';
    }
    
    //How to classify whether a route is already in regex form
    const ISREGEXP = '/^#\^.*\$#\w?$/'; //Matches the #^...$#flags form
    //How to escape any regex chars
    const ESCAPEREGEXP = '/[-[\]{}()+?.,\\^$|#\s]/';
    const ESCAPEREGEXP_REPLACEMENT = '\\\\\\0';
    //How to convert a named parameter (:name) to regex form
    const NAMEDPARAM = '/:(\w+)/';
    const NAMEDPARAM_REPLACEMENT = '(?<\\1>[^\/]+)';
    //How to convert a slash-following end splat parameter to regex form
    const ENDSPLATPARAM = '#/\*(\w+)$#';
    const ENDSPLATPARAM_REPLACEMENT = '(?:/|$)(?<\\1>.*?)';
    //How to convert a splat parameter (*rest) to regex form
    const SPLATPARAM = '/\*(\w+)/';
    const SPLATPARAM_REPLACEMENT = '(?<\\1>.*?)';
    
    /*************************************************************************\
     * Route invocation
    \*************************************************************************/

    /**
     * Fetch the url that will be routed for the current request
     * 
     * Options:
     *  - 'root' : Set a prefix that will be removed from any url
     * 
     * @param array $options
     */
    public function url(array $options=array()) {
        $request = $this->request();
        $url = $request->getPathInfo();
        if (!empty($options['root'])) {
            $root = rtrim($options['root'], '/');
            if (!strncmp($url, $root, strlen($root))) $url = substr($url, strlen($root));
            else return false; //If the root doesn't match, not routable
        }
        return trim($url, '/');
    }

    /**
     * Fetch the method used by the router's request
     * Allows it to be overriden by manually setting method in
     * the _method parameter or the custom method override header  
     */
    public function getMethod() {
        $request = $this->request();
        
        ($method = $request->getParam('_method')) or 
        ($method = $request->getHeader('X-HTTP-Method-Override')) or
        ($method = $request->getMethod());
        
        return strtoupper($method);
    }    
    
    /**
     * Call the appropriate route handler.
     * 
     * Options:
     *  - 'request' : If present, set in the router and use for routing  
     * 
     * @param array|Zend_Controler_Request_Http|string $options Option array, or the request 
     * @return bool Whether any route handler was matched and invoked
     */
    public function __invoke($options = array()) {
        //If given, set the request
        if (!is_array($options)) $options = array('request'=>$options);
        if (isset($options['request'])) $this->request($options['request']);
        
        //Fetch the URL to route
        $url = $this->url($options);
        if ($url===false) return false; //If the URL isn't valid for this router short-circuit out

        //Find the first route that matches, and trigger it
        foreach($this->routes as $route=>&$action) {
            if (preg_match($route, $url, $m)) {
                //Build the parameters list - (preg_match sets both numeric and named indices, we only want 1,2,3,... 
                $params = array();
                for($i=1;isset($m[$i]);$i++) $params[] = rawurldecode($m[$i]);
                $matched = true;
                $callback =& $action['callback'];
                
                //Trigger an event before routing the request
                $this->trigger('before_route', $this, $action['name'], $params, $options);
                
                //If the callback is a nested router, forward the request onwards
                if (is_a($callback, 'Backbone_Router', true)) {
                    //Build if necessary
                    if (is_string($callback)) $callback = new $callback($options);

                    //Fetch the suburl out of the matches
                    $url = array_key_exists('url', $m) ? $m['url'] : end($m);
                    if ($url===null) throw new InvalidArgumentException("Can't route to sub-router, no 'url' parameter given in route");
                    $options['request'] = $url;
                    
                    //Add the params to any existing ones in the options (eg from a router outside of this one) by name
                    $named_params = !empty($options['params']) ? $options['params'] : array();
                    foreach($m as $k=>$v) if (!is_numeric($k) && $k!=='url') $named_params[$k] = rawurldecode($v); 
                    $options['params'] = $named_params;
                    
                    //Forward the request to the nested router
                    $matched = ($callback($options)!==false);
                }
                
                //If the callback is a regular action, invoke it with the matched parameters
                elseif ($callback) {
                    //Invoke the request
                    $matched = (call_user_func_array($callback, array_merge($params, array($options)))!==false);
                } 
                
                //Trigger the named and global routing events, passing the matched parameters (numeric)
                //TODO: Should the events be triggered regardless, or only if the callback doesn't veto?
                call_user_func_array(array($this, 'trigger'), array_merge(array('route:'.$action['name'], $this), $params, array($options)));
                $this->trigger('route', $this, $action['name'], $params, $options);
                
                return $matched;
            }
        }
        
        //No matches
        return false;
    }
}
