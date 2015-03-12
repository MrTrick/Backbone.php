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

require_once('common.php');

class TestRouter extends Backbone_Router {
    protected $routes = array(
        'noCallback' => 'noCallback', //No callback exists for this route
        'counter' => 'counter',
        'search/:query' => 'search',
        'search/:query/p:page' => 'search',
        'contacts' => 'contacts',
        'contacts/new' => 'newContact',
        'contacts/:id' => 'loadContact',
        'splat/*args/end' => 'splat',
        '*first/complex-:part/*rest' => 'complex',
        ':entity?*args' => 'query',
        '*anything' => 'anything'
    );
    
    public $testing;
    public function initialize(array $options) { 
        $this->testing = $options['testing'];
        $this->route('implicit', 'implicit'); 
    }
    
    public $count = 0;
    public function counter() { $this->count++; }
    public function implicit() { $this->count++; }

    public $query, $page;
    public function search($query, $page=null) { $this->query = $query; $this->page = func_num_args()==3?$page:null; }
    
    public $contact;
    public function contacts() { $this->contact = 'index'; }
    public function newContact() { $this->contact = 'new'; }
    public function loadContact() { $this->contact = 'load'; }
    
    public $args;
    public function splat($args) { $this->args = $args; }
    
    public $first, $part, $rest;
    public function complex($first, $part, $rest) { $this->first = $first; $this->part = $part; $this->rest = $rest; }

    public $entity, $queryArgs;
    public function query($entity, $args) { $this->entity = $entity; $this->queryArgs = $args; }

    public $anything;
    public function anything($whatever) { $this->anything = $whatever; }
}

class Test_Router_Rest extends Backbone_Router_Rest {
	public $lastAction, $lastId, $lastOptions;
	public function index(array $options=array()) { $this->lastAction = "index"; $this->lastId = null; $this->lastOptions = $options; }
	public function read($id, array $options=array()) { $this->lastAction = "read"; $this->lastId = $id; $this->lastOptions = $options; }
	public function create(array $options=array()) { $this->lastAction = "create"; $this->lastId = null; }
	public function update($id, array $options=array()) { $this->lastAction = "update"; $this->lastId = $id; }    
	public function delete($id, array $options=array()) { $this->lastAction = "delete"; $this->lastId = $id; }
}

class Test_Router_TestCase extends PHPUnit_Framework_TestCase {
    /**
     * @var TestRouter
     */
    protected $router = null;
    /**
     * @var Zend_Controller_Request_Http
     */
    protected $request = null;
    
    
    protected $lastRoute = null, $lastArgs = array(); 
    public function onRoute($router, $route, $args) { $this->lastRoute = $route; $this->lastArgs = $args; }
    
    public function setUp() {
        $this->request = new Zend_Controller_Request_Http('http://example.com');
        $this->router = new TestRouter(array('testing'=>101));
        $this->router->request($this->request);
        $this->router->on('route', array($this,'onRoute'));
    }
    
    
    /**
     * Router: initialize
     */
    public function testRouterInitialize() {
        $this->assertEquals(101,$this->router->testing);
    }
    
    /**
     * Router: routes (simple)
     */
    public function testRouterRoutesSimple() {
        $router = $this->router;
        $this->assertTrue($router('/search/news'));
        $this->assertEquals('news', $router->query);
        $this->assertNull($router->page);
    }
    
    /**
     * Router: routes (two part)
     */
    public function testRouterRoutesTwoPart() {
        $router = $this->router;
        $this->assertTrue($router('/search/nyc/p10'));
        $this->assertEquals('nyc', $router->query);
        $this->assertEquals('10', $router->page);
    }
    
    /*
     * Tests not applicable to Backbone.php: 
     * Router: routes via navigate
     * Router: routes via navigate for backwards-compatibility
     * Router: loadUrl is not called for identical routes
     * Router: route precedence via navigate
     * Router: routes via navigate with {replace: true}
     * #1003 - History is started before navigate is called
     * #1185 - Use pathname when hashChange is not wanted.
     * #1206 - Strip leading slash before location.assign.
     * #1366 - History does not prepend root to fragment.
     * Router: Normalize root.
	 */
    
    /**
     * Router: route precedence
     */
    public function testRouterRoutePrecedence() {
        $router = $this->router;
        
        $this->assertTrue($router('contacts'));
        $this->assertEquals('index', $router->contact);
        $this->assertTrue($router('contacts/new'));
        $this->assertEquals('new', $router->contact);
        $this->assertTrue($router('contacts/foo'));
        $this->assertEquals('load', $router->contact);
    }
    
    /**
     * Router: use implicit callback if none provided. (Through events) 
     */
    public function testRouterUseImplicitCallback() {
        $router = $this->router;
        $router->count = 0;
        $router('implicit');
        $this->assertEquals(1, $router->count);
    }
    
    /**
     * Router: routes (splats) 
     */
    public function testRouterRoutesSplats() {
        $router = $this->router;
        $this->assertTrue( $router('http://example.com/splat/long-list/of/splatted_99args/end') );
        $this->assertEquals('long-list/of/splatted_99args', $router->args);
    }
    
    /**
     * Router: routes (splats)
     */
    public function testRouterRoutesComplex() {
        $router = $this->router;
        $this->assertTrue( $router('http://example.com/one/two/three/complex-part/four/five/six/seven') );
        $this->assertEquals('one/two/three', $router->first);
        $this->assertEquals('part', $router->part);
        $this->assertEquals('four/five/six/seven', $router->rest);
    }
    
    /**
     * Router: routes (query)
     * PHP Only: Unlike Backbone.js, the php router ignores query parameters (as they're not well matched by a regex, order being arbitrary) 
     */
    public function testRouterRoutesQuery() {
        $router = $this->router;
        $this->assertTrue( $router('http://example.com/mandel?a=b&c=d') );
        $this->assertNotEquals('mandel', $router->entity);
    }
    
    /**
     * Router: routes (anything)
     */
    public function testRouterRoutesAnything() {
        $router = $this->router;
        $this->assertTrue( $router('http://example.com/doesn\'t-match-a-route') );
        $this->assertEquals('doesn\'t-match-a-route', $router->anything);
    }
    
    /**
     * Router: fires event when router doesn't have callback on it
     */
    public function testRouterFiresEventWithoutCallback() {
        $callcount = 0; 
        $router = $this->router;
        $router->on("route:noCallback", function() use (&$callcount) { $callcount++; });
        $this->assertTrue( $router('http://example.com/noCallback'), 'Matches route, even though without callback' );
        $this->assertEquals(1, $callcount);
    }
    
    /**
     * Router: #933, #908 - leading slash
     * TODO: $_SERVER, pathInfo vs basePath vs requestURI is all fairly complex. Check url() works properly in a webserver
     */
    public function testRouterLeadingSlash() {
        $router = $this->router;
        $router->request('http://example.com/root/foo');
        $this->assertEquals('root/foo', $router->url());
        
        $router = $this->router;
        $router->request('http://example.com/root/foo');
        $this->assertEquals('foo', $router->url(array('root'=>'/root')));
    }
    
    /**
     * Router: route callback gets passed decoded values
     */
    public function testRouteCallbackPassedDecodedValues() {
        $router = $this->router;
        $this->assertTrue( $router('http://example.com/has%2Fslash/complex-has%23hash/has%20space') );
        $this->assertEquals('has/slash', $router->first);
        $this->assertEquals('has#hash', $router->part);
        $this->assertEquals('has space', $router->rest);
    }
    
    /**
     * Router: correctly handles URLs with % (#868)
     */
    public function testRouterCorrectlyHandlesUrlsWithPercent() {
        $router = $this->router;
        $this->assertTrue( $router('http://example.com/search/fat%3A1.5%25') );
        $this->assertTrue( $router('http://example.com/search/fat') );
        $this->assertEquals('fat', $router->query);
        $this->assertEquals(null, $router->page);
        $this->assertEquals('search', $this->lastRoute);
    }
    
    /**
     * #1387 - Root fragment without trailing slash.
     */
    public function testRootFragmentWithoutTrailingSlash() {
        $router = $this->router;
        $router->request('http://example.com/root');
        $this->assertEquals('', $router->url(array('root'=>'/root/')));
    }
    
    /**
     * Backbone_Router: Manually add routes
     */
    public function testBRManuallyAddedRoutes() {
        $router = new Backbone_Router();
        $router->route('foo', 'foo');
        $this->assertArrayHasKey('#^foo$#', $router->routes());
        
        $router->route('bar', 'bar');
        $this->assertEquals(array('#^bar$#', '#^foo$#'), array_keys($router->routes()), 'Later routes have higher precedence');
        
        $router = new Backbone_Router();
        $baz = function() {};
        $router->route('foo', 'bar', $baz);
        $this->assertEquals(array('#^foo$#' => array('name'=>'bar', 'callback'=>$baz)), $router->routes());
        
        $router = new Backbone_Router();
        Zend_Loader_Autoloader::getInstance()->suppressNotFoundWarnings(true);
        foreach(array(false, new stdClass(), array(), 'nonexistent_method') as $invalid_callback) {
            try { 
                $router->route('foo', 'bar', $invalid_callback);
                $this->fail("Can't add invalid callback format - ".json_encode($invalid_callback));
            } catch (InvalidArgumentException $e) { } 
            $this->assertEquals(array(), $router->routes(), 'Route not added');
        }
        Zend_Loader_Autoloader::getInstance()->suppressNotFoundWarnings(false);
        
        $router_class = Backbone::uniqueId('Router_');
        eval('class '.$router_class.' extends Backbone_Router { public function baz() {} }');
        $this->assertTrue(class_exists($router_class));
        
        $router = new $router_class();
        $router->route('foo', 'bar', 'baz');
        $this->assertEquals(array('#^foo$#' => array('name'=>'bar', 'callback'=>array($router, 'baz'))), $router->routes());
        
        try {
            $router = new Backbone_Router(array('routes'=>array(
                'foo'=>'foo',
                'invalid_cb'=>array()
            )));
            $this->fail("Shouldn't be able to build with invalid action/callback");
        } catch(InvalidArgumentException $e) {}
    }
    
    /**
     * Backbone_Router: Regex routes
     */
    public function testBRRegexRoutes() {
        $router = new Backbone_Router(array('routes'=>array(
            '#^HELLO_KITTY$#'=>'kawai',
        	'#^IgNoReCaSe$#i'=>'capital'
        )));
        $routes = array_keys($router->routes());
        $this->assertEquals('#^HELLO_KITTY$#', $routes[0], 'Regex routes are passed through as-is');
        $this->assertEquals('#^IgNoReCaSe$#i', $routes[1], 'Regex routes are passed through as-is');
    }
    
    /**
     * Backbone_Router: Can route to a nested router
     */
    public function testBRRouteToNestedRouter() {
        $inner_route = null; $outer_route = null; $inner_args = array(); $outer_args = array();
        $inner = new Backbone_Router(array(
        	'routes'=>array(
            	'foo/:id'=>'foo',
            	'*all'=>'all'        
            ),
            'on'=>array(
                'route'=>function($router, $route, $args) use (&$inner_route, &$inner_args) { $inner_route = $route; $inner_args = $args; }
            )
        ));
        $outer = new Backbone_Router(array(
        	'routes'=>array(
            	'outside' => 'outside',
            	'inside*capture' => array('name'=>'inside', 'callback'=>$inner)
            ),
            'on'=>array(
                'route'=>function($router, $route, $args) use (&$outer_route, &$outer_args) { $outer_route = $route; $outer_args = $args; }
            )
        ));
        
        $this->assertTrue( $outer('http://example.com/outside') );
        $this->assertEquals('outside', $outer_route);
        $outer_route = null;
        
        $this->assertTrue( $outer('http://example.com/inside/foo/1234') );
        $this->assertEquals('inside', $outer_route, 'Triggers route event on outer router');
        $this->assertEquals('foo', $inner_route);
        $this->assertEquals(array(1234), $inner_args);
        
        $this->assertTrue( $outer('http://example.com/inside') );
        $this->assertEquals('inside', $outer_route);
        $this->assertEquals('all', $inner_route);
        $this->assertEquals(array(''), $inner_args);
    }
    
    /**
     * Backbone_Router: If routing to an inner router, returns the inner router's status 
     */
    public function testBRRouteToNestedRouterReturn() {
        $inner = new Backbone_Router(array('routes'=>array('foo'=>'foo','bar'=>'bar')));
        $outer = new Backbone_Router(array('routes'=>array('outside'=>'outside','inside/*innerurl'=>array('name'=>'inside','callback'=>$inner))));
        $this->assertTrue( $outer('http://example.com/outside') );
        $this->assertTrue( $outer('http://example.com/inside/foo') );
        $this->assertFalse( $outer('http://example.com/huh') );
        $this->assertFalse( $outer('http://example.com/inside/blah') );
    }
    
    /**
     * Backbone_Router: Valid 'root' option required
     */
    public function testBRValidRootOption() {
        $router = new Backbone_Router(array('request'=>'http://example.com/foo/bar/baz'));
        $this->assertEquals('foo/bar/baz', $router->url());
        $this->assertEquals('bar/baz', $router->url(array('root'=>'/foo')));
        
        $this->assertFalse($router->url(array('root'=>'/food')), 'Shouldn\'t be able to set root not matching request');
        $this->assertFalse($router(array('root'=>'/food')), 'Shouldn\'t be able to match if root doesn\'t match');
    } 
    
    /**
     * Backbone_Router: Routing the current request implicitly
     */
    public function testBRRoutingCurrentRequest() {
        Backbone::setCurrentRequest(new Zend_Controller_Request_Http('http://example.com/foo'));
        $this->assertEquals('/foo', Backbone::getCurrentRequest()->getPathInfo());
        Backbone::setCurrentRequest('http://exmaple.com/bar/baz');
        $this->assertEquals('/bar/baz', Backbone::getCurrentRequest()->getPathInfo());
        Backbone::setCurrentRequest('partial/url');
        $this->assertEquals('partial/url', Backbone::getCurrentRequest()->getPathInfo());
        
        Backbone::setCurrentRequest('/foo/45/');
        $router = new Backbone_Router(array(
            'routes'=>array('foo/:id'=>'foo'),
            'on'=>array('route'=>array($this, 'onRoute'))
        ));        
        $this->assertEquals('foo/45', $router->url(), 'Router fetches current request if not given');
        $this->assertTrue( $router() );
        $this->assertEquals('foo', $this->lastRoute);
    }
    
    /**
     * Backbone_Router: Routing requests with escaped delimiters
     */
    public function testBRRoutingRequestsWithEscapedDelimiters() {
        $router = $this->router;
        $this->assertTrue($router('/search/this%2Fthat/p10%2F%23%3F%2B'));
        $this->assertEquals('search', $this->lastRoute);
        $this->assertEquals('this/that', $router->query);
        $this->assertEquals('10/#?+', $router->page);
        
        $inner = new Backbone_Router(array('routes'=>array('foo/:bar/:baz'=>'foo'), 'on'=>array('route'=>array($this, 'onRoute'))));
        $outer = new Backbone_Router(array('routes'=>array('outside'=>'outside','inside/*innerurl'=>array('name'=>'inside','callback'=>$inner))));
        $this->assertTrue( $outer('http://example.com/inside/foo/50/17'), 'Routes simple case');
        $this->assertEquals('foo', $this->lastRoute);
        $this->assertEquals(array('50', '17'), $this->lastArgs, 'Captures args properly');
        $this->assertTrue( $outer('http://example.com/inside/foo/50%2F00/17'), 'Doesn\'t decode before routing' );
        $this->assertEquals('foo', $this->lastRoute);
        $this->assertEquals(array('50/00', '17'), $this->lastArgs);
        $this->assertTrue( $outer('http://example.com/inside/foo/50%252F00/17') );
        $this->assertEquals(array('50%2F00', '17'), $this->lastArgs, 'Decodes only once');
        $this->assertFalse( $outer('http://example.com/inside/foo%2F50%2F00/17'), 'Doesn\'t decode before routing' );
        $this->assertFalse( $outer('http://example.com/inside/foo/50/00/17'), 'No funny business like re-encoding' );
    }
    
    /**
     * Backbone_Router: Routing splats, more edge cases
     */
    public function testBRRoutingMoreSplats() {
        $router = new Backbone_Router();
        $router->on('route',array($this, 'onRoute'));
        $router->route('foo/*bar', 'foo');
        
        $this->assertTrue( $router('foo/bar/baz') );
        $this->assertEquals(array('bar/baz'), $this->lastArgs, 'Doesn\'t pick up an extra / from somewhere');
        $this->assertTrue( $router('foo/bar/baz/') );
        $this->assertEquals(array('bar/baz'), $this->lastArgs, 'Loses the trailing slash if present');
        
        $this->assertTrue( $router('foo/') );
        $this->assertEquals(array(''), $this->lastArgs, 'Matches empty url');
        $this->assertTrue( $router('foo') );
        $this->assertEquals(array(''), $this->lastArgs, 'Matches empty even without /');
        
        $this->assertFalse( $router('food'), 'Won\'t match if the preceding component is extended' );
        $this->assertFalse( $router('fo'), 'Won\'t match if the preceding component is shortened' );
        
        $router = new Backbone_Router();
        $router->on('route',array($this, 'onRoute'));
        $router->route('foo*bar', 'foo');
        
        $this->assertTrue( $router('foo/bar/baz') );
        $this->assertEquals(array('/bar/baz'), $this->lastArgs, 'Picks up slash if starting rest');
        $this->assertTrue( $router('foo/') );
        $this->assertEquals(array(''), $this->lastArgs, 'Won\'t pick up trailing slash at end (will be trimmed)');
        $this->assertTrue( $router('food') );
        $this->assertEquals(array('d'), $this->lastArgs, 'Will match if the preceding component is extended');
        $this->assertFalse( $router('fo'), 'Won\'t match if the preceding component is shortened' );
        
        $router = new Backbone_Router();
        $router->on('route',array($this, 'onRoute'));
        $router->route('foo/*bar/suffix', 'foo');
        
        $this->assertTrue( $router('foo/bar/baz/suffix') );
        $this->assertEquals(array('bar/baz'), $this->lastArgs, 'Keeps suffix');
    }
    
    /**
     * Backbone_Router: Routing empty URL to nested router
     */
    public function testBRRoutingEmptyURLToNested() {
        $inner = new Backbone_Router(array('routes'=>array('foo'=>'foo','bar'=>'bar',''=>'index')));
        $outer = new Backbone_Router(array('routes'=>array('outside'=>'outside','inside/*url'=>array('name'=>'inside','callback'=>$inner))));
        $inner->on('route', array($this, 'onRoute'));
        
        $this->assertTrue( $outer('/outside') );
        $this->assertEquals(null, $this->lastRoute, 'Not reached' );
        
        $_SERVER['REQUEST_URI'] = '/inside/';
        $this->assertTrue( $outer('/inside/foo') );
        $this->assertEquals('foo', $this->lastRoute);
        $this->assertTrue( $outer('/inside/') );
        $this->assertEquals('index', $this->lastRoute);
        $this->assertTrue( $outer('/inside') );
        $this->assertEquals('index', $this->lastRoute);
    }
    
    /**
     * Backbone_Router: Passing options through to actions
     */
    public function testBRPassingOptions() {
        $options = null;
        $router = new Backbone_Router();
        $router->route('book/:id', 'read', function($id, $_options) use (&$options) { $options = $_options; });
        $this->assertTrue( $router(array('request'=>'/book/123', 'user'=>'Fred')) );
        $this->assertEquals( array('request'=>'/book/123', 'user'=>'Fred'), $options );
        
        $inner = new Backbone_Router();
        $inner->route('foo', 'foo', function($_options) use (&$options) { $options = $_options; });
        $outer = new Backbone_Router(array('routes'=>array('inside/*url'=>array('name'=>'inside','callback'=>$inner))));
        $this->assertTrue( $outer(array('request'=>'/inside/foo', 'user'=>'Phil')) );
        $this->assertEquals( array('request'=>'foo', 'params'=>array(), 'user'=>'Phil'), $options, 'url param is stripped from router params' );
        
        $outer->route('other/:other_id/inside/*url', 'filtered', $inner);
        $this->assertTrue( $outer(array('request'=>'/other/32/inside/foo', 'user'=>'Phil')) );
        $this->assertEquals( array('request'=>'foo', 'params'=>array('other_id'=>'32'), 'user'=>'Phil'), $options, 'Previous parameters passed to inner router' );
    }
    
    /**
     * Backbone_Router: client and options parameters are handled properly
     */
    public function testBRParams() {
    	$params = null;
    	
    	$router = new Backbone_Router();
    	$router->route('book/:id', 'read', function($id, $options) use (&$params, $router) { 
    		$params = $router->getParams($options);
    	});
    	
    	$request = new Zend_Controller_Request_Http('http://example.com/book/123');
    	$request->setParam('page', 5);
    	Backbone::setCurrentRequest($request);
    	
    	$this->assertTrue( $router() );
    	$this->assertEquals( array('page'=>5), $params, "Client parameter passed into route" );
    	
    	$this->assertTrue( $router(array('params'=>array('page'=>2, 'foo'=>'bar'))) );
    	$this->assertEquals( array('page'=>2, 'foo'=>'bar'), $params, "Options parameters override request parameters");
    }

    /**
     * Backbone_Router: whitelisting of user parameters
     */
    public function testBRParamsWhiteList() {
    	$params = null;
    	 
    	$router = new Backbone_Router( array('valid_client_params'=>array('page','sort') ));
    	$router->route('book/:id', 'read', function($id, $options) use (&$params, $router) {
    		$params = $router->getParams($options);
    	});
    		 
    	$request = new Zend_Controller_Request_Http('http://example.com/book/123');
    	$request->setParams(array('page'=>5, 'foo'=>'bar'));
    	Backbone::setCurrentRequest($request);
    		 
    	$this->assertTrue( $router() );
    	$this->assertEquals( array('page'=>5), $params, "Only the page parameter accepted" );
    	
    	$this->assertTrue( $router(array('params'=>array('foo'=>'Froggy'))) );
    	$this->assertEquals( array('page'=>5, 'foo'=>'Froggy'), $params, "valid_client_params does not affect \$options['param']" );
    	
    	$this->assertTrue( $router(array('params'=>array('foo'=>'Froggy', 'page'=>7))) );
    	$this->assertEquals( array('page'=>7, 'foo'=>'Froggy'), $params, "and options parameters still override request parameters" );
    }    
    
    //-------------------------------------------------------------------------------------------------------
    
    /**
     * Backbone_Router_Rest: Routing
     */
    public function testBRRRouting() {
        $router = new Test_Router_Rest();
        $router->on('route', array($this, 'onRoute'));
        
        //Index
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('GET');
        $request->setRequestUri('');
        $this->assertTrue( $router($request) );
        $this->assertEquals('index', $this->lastRoute );
        $this->assertEquals('index', $router->lastAction);
        
        //Read
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('GET');
        $request->setRequestUri('asdf');
        $this->assertTrue( $router($request) );
        $this->assertEquals('read', $this->lastRoute );
        $this->assertEquals(array('asdf'), $this->lastArgs);
        $this->assertEquals('read', $router->lastAction);
        $this->assertEquals('asdf', $router->lastId);
        
        //Create
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST');
        $request->setRequestUri('');
        $this->assertTrue( $router($request) );
        $this->assertEquals('create', $this->lastRoute );
        $this->assertEquals('create', $router->lastAction);
        
        //Update
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('PUT');
        $request->setRequestUri('foo');
        $this->assertTrue( $router($request) );
        $this->assertEquals('update', $this->lastRoute );
        $this->assertEquals(array('foo'), $this->lastArgs);
        $this->assertEquals('update', $router->lastAction);
        $this->assertEquals('foo', $router->lastId);
        
        //Delete
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('DELETE');
        $request->setRequestUri('1239');
        $this->assertTrue( $router($request) );
        $this->assertEquals('delete', $this->lastRoute );
        $this->assertEquals(array('1239'), $this->lastArgs);
        $this->assertEquals('delete', $router->lastAction);
        $this->assertEquals('1239', $router->lastId);        
        
        //NON-matching requests
        foreach(array(
            array('POST', 'foo'),
            array('GET', 'foo/id'),
            array('DELETE', ''),
            array('PUT', ''),
            array('PUT', 'foo/id'),
            array('HEAD', ''), //Unknown method. TODO: HEAD support to implement later.
        ) as $r) { list($method, $uri) = $r;
            $request = new Zend_Controller_Request_HttpTestCase();
            $request->setMethod($method);
            $request->setRequestUri($uri);
            $this->assertFalse( $router($request), "$method $uri shouldn't be routable." );
        }
    }
    
    /**
     * Backbone_Router_Rest: Options are passed correctly
     */
    public function testBRROptionPassing() {
        $router = new Test_Router_Rest();
        $router->on('route', array($this, 'onRoute'));
        
        //Index
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('GET');
        $request->setRequestUri('');
        $options = array('request'=>$request, 'foo'=>'BAR');
        $this->assertTrue( $router($options) );
        $this->assertEquals( $options, $router->lastOptions );
        
        //Read
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('GET');
        $request->setRequestUri('asdf');
        $options = array('request'=>$request, 'params'=>array('id'=>'Di'));
        $this->assertTrue( $router($options) );
        $this->assertEquals( 'asdf', $router->lastId );
        $this->assertEquals( $options, $router->lastOptions );
    }

    /**
     * Backbone_Router_Rest: Defined Routes are routed before the REST routes
     */
    public function testBRRDefinedRoutes() {
        $router = new Backbone_Router_Rest();
        $router->on('route', array($this, 'onRoute'));
        
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('GET');
        $request->setRequestUri('');
        $router($request);
        $this->assertEquals('index', $this->lastRoute);
        $router->route('', 'newindex');
        $router($request);
        $this->assertEquals('newindex', $this->lastRoute, 'Higher priority than the index route');
        
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('GET');
        $request->setRequestUri('static');
        $router($request);
        $this->assertEquals('read', $this->lastRoute);
        $this->assertEquals(array('static'), $this->lastArgs);
        $router->route('static', 'static');
        $router($request);
        $this->assertEquals('static', $this->lastRoute, 'Higher priority than the index route');
        $this->assertEquals(array(), $this->lastArgs);

        //Other non-suborned routes won't match, by default?
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('GET');
        $request->setRequestUri('dynamic');
        $this->assertFalse( $router($request) );
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('PUT');
        $request->setRequestUri('1');
        $this->assertFalse( $router($request) );
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('DELETE');
        $request->setRequestUri('1');
        $this->assertFalse( $router($request) );
        
        //Not post, because the 'newindex' regular route matches regardless of method.
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST');
        $request->setRequestUri('');
        $this->assertTrue( $router($request) );
        $this->assertNotEquals('create', $this->lastRoute);
        $router->routes(array());
    }
    
    /**
     * Backbone_Router_Rest: If the override callback rejects the invocation, falls back to the REST routes
     */
    public function testBRRRejectRoute() {
        $count = 0;
        $router_class = Backbone::uniqueId('Router_');
        eval('class '.$router_class.' extends Backbone_Router_Rest { 
        	public function index(array $options=array()) { return true; }
        	public function create(array $options=array()) { return true; }
        }');
        $this->assertTrue(class_exists($router_class)); 
        $router = new $router_class();
        
        $router->route('', 'newindex', function($options) use (&$count) { 
            if ($options['request']->isPost()) return false; //Reject if post
            $count++;
        });
        $router->on('route', array($this, 'onRoute'));
        
        //GET '' matches newindex
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('GET');
        $request->setRequestUri('');
        $this->assertTrue( $router($request) );
        $this->assertEquals(1, $count);
        $this->assertEquals('newindex', $this->lastRoute);
        
        //POST '' matches create
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST');
        $request->setRequestUri('');
        $this->assertTrue( $router($request) );
        $this->assertEquals(1, $count);
        $this->assertEquals('create', $this->lastRoute);
    }

    //-------------------------------------------------------------------------------------------------------
    
    /**
     * Backbone_Router_Model: Routing
     */
    public function testBRMRouting() {
        $router = new Backbone_Router_Model();
        //TODO: Write tests        
    }
    
}