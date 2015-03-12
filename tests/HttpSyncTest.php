<?php
require_once('common.php');

/**
 * Backbone_Sync_Http test case.
 */
class Backbone_Sync_Http_TestCase extends PHPUnit_Framework_TestCase {
    
    public $baseUrl = "http://example.com";
    
    private $records = array();
    /**
     * Prepares the environment before running a test.
     */
    protected function setUp() {
        parent::setUp();
        
        $this->records = array(
            'aa' => array(
                'id'=>'aa',
                'surname'=>'Aaronson',
                'history' => array((object)array("message"=>"Test message A","author"=>"testUser","date"=>"2014-05-01T15:00:00+00:00"))
            ),
            'bb' => array(
                'id'=>'bb',
                'surname'=>'Bailey',
                'history' => array((object)array("message"=>"Test message B","author"=>"testUser","date"=>"2014-05-01T15:00:00+00:00"))
            ),
            'cc' => array(
                'id'=>'cc',
                'surname'=>'Carlson',
                'history' => array((object)array("message"=>"Test message C","author"=>"testUser","date"=>"2014-05-01T15:00:00+00:00"))
            ),
            'dd' => array(
                'id'=>'dd',
                'surname'=>'Davidson',
                'history' => array((object)array("message"=>"Test message D","author"=>"testUser","date"=>"2014-05-01T15:00:00+00:00"))
            )
        );
        
        $this->sync_options = array();
        
        $this->model_options = array('urlRoot' => '/people/');
        $this->collection_options = array('url' => '/people/');
    }
    
    /**
     * Cleans up the environment after running a test.
     */
    protected function tearDown() {
        // TODO Auto-generated
        parent::tearDown();
    }
    
    /**
     * Build a mock of Zend_Http_Client that will return the specified response from a request() call.
     * @param $response The response to return from a call to request()
     * @param callable $validationCallback A callback that takes the client object during the call - can be used to validate the state of the client during the request
     * @return boolean|PHPUnit_Framework_MockObject_MockObject
     */
    protected function buildClientMock($response, $validationCallback = null, $expectedMethod = null) {
        $self = $this;
        $clientMock = $this->getMock('Zend_Http_Client', array('request'));
        $clientMock
            ->expects($this->once())
            ->method('request')
            ->with($this->callback(function($arg) use ($clientMock, $validationCallback, $expectedMethod, $self) {
                if ($validationCallback && is_callable($validationCallback)) $validationCallback($clientMock);
                if ($expectedMethod && is_string($expectedMethod)) $self->assertHttpClientMethod($clientMock, $expectedMethod);
                return true;
            }))
            ->will($this->returnValue($response));
        return $clientMock;
    }
    
    public function assertHttpClientMethod($client, $expectedMethod) {
        $clientReflection = new ReflectionClass($client);
        $property = $clientReflection->getProperty('method');
        $property->setAccessible(true);
        $method = $property->getValue($client);
        $this->assertEquals($expectedMethod, $method, "Client HTTP Method does not match");
    }
    
    /**
     * Provider for testing exceptions
     * HttpSync should translate status codes and server messages into appropriate exceptions
     */
    public function exceptionCodeMapProvider() {
        $methods = array('readModel', 'create', 'update', 'delete');
        $exceptions = array(
        	array('Backbone_Exception', 401, "Unauthorised"),
        	array('Backbone_Exception', 404, "Model not found"),
        	array('Backbone_Exception', 500, "Internal server error"),
        	array('Backbone_Exception', 418, "I'm a teapot"), //Any exceptions forwarded from the server should be reproduced on the client side
        );
        $combinations = array();
        foreach ($methods as $method) foreach ($exceptions as $exception) {
            $combinations[] = array_merge(array($method), $exception);
        }
        return $combinations;
    }
    
    /**
     * Check that error codes from the server translate correctly
     * @dataProvider exceptionCodeMapProvider
     */
    public function testMethodException($method, $exceptionClass, $exceptionCode, $exceptionMessage) {
        $this->setExpectedException($exceptionClass, $exceptionMessage, $exceptionCode);
        $attrs = $this->records['aa'];
        $model = new Backbone_Model($attrs, $this->model_options);
    
        // Client expects one call to $client->request, which will return the json_encoded attrs on success
        $client = $this->buildClientMock(new Zend_Http_Response($exceptionCode, array(), json_encode(array('code'=>$exceptionCode, 'message'=>$exceptionMessage))));
    
        // Build Sync object and call readModel
        $sync = new Backbone_Sync_Http(array('baseUrl'=>$this->baseUrl, 'client'=>$client));
        $response = $sync->$method($model, $this->sync_options);
        $this->fail("Exception expected");
    }
    
    /**
     * Check that readModel() queries against the correct URI, with the right HTTP method
     * @return boolean
     */
    public function testReadModel() {
        $attrs = $this->records['aa'];
        $model = new Backbone_Model($attrs, $this->model_options);
        $self = $this;
        
        // Check route used for retrieval
        $validationCallback = function($client) use ($self) {
            $self->assertEquals($self->baseUrl, $client->getUri()->getScheme()."://".$client->getUri()->getHost(), "Client should make calls against ".$self->baseUrl);
        	$self->assertEquals("/people/".urlencode('aa'), $client->getUri()->getPath(), "Client path should match /people/:id route");
        	return true;
        };
        
        // Client expects one call to $client->request, which will return the json_encoded attrs on success
        $client = $this->buildClientMock(new Zend_Http_Response('200', array(), json_encode($attrs)), $validationCallback, "GET");
        
        // Build Sync object and call readModel
        $sync = new Backbone_Sync_Http(array('baseUrl'=>$this->baseUrl, 'client'=>$client));
        $response = $sync->readModel($model, $this->sync_options);
        
        // Check that result is the original attributes, and that they have been correctly decoded
        $this->assertEquals($attrs, $response, "Http Sync should return retrieved attributes");
    }

    /**
     * Check that readCollection() queries against the correct URI, with the right HTTP method
     * @return boolean
     */
    public function testReadCollection() {
        $collection = new Backbone_Collection(null, $this->collection_options);
        $self = $this;
        
        // Check route used for retrieval
        $validationCallback = function($client) use ($self) {
            $self->assertEquals($self->baseUrl, $client->getUri()->getScheme()."://".$client->getUri()->getHost(), "Client should make calls against ".$self->baseUrl);
        	$self->assertEquals("/people/", $client->getUri()->getPath(), "Client path should match /people/ route");
        	return true;
        };
        
        // Client expects one call to $client->request, which will return the json_encoded attrs on success
        $client = $this->buildClientMock(new Zend_Http_Response('200', array(), json_encode($this->records)), $validationCallback, "GET");
        
        // Build Sync object and call readModel
        $sync = new Backbone_Sync_Http(array('baseUrl'=>$this->baseUrl, 'client'=>$client));
        $response = $sync->readCollection($collection, $this->sync_options);
        
        // Check that result is the original attributes, and that they have been correctly decoded
        $this->assertEquals($this->records, $response, "Http Sync should return retrieved array");
    }

    /**
     * Check that create() queries against the correct URI, with the right HTTP method
     * @return boolean
     */
    public function testCreate() {
        $attrs = array_diff_key($this->records['aa'], array('id'=>1));
        $model = new Backbone_Model($attrs, $this->model_options);
        $self = $this;
        
        // Check route used for retrieval
        $validationCallback = function($client) use ($self) {
            $self->assertEquals($self->baseUrl, $client->getUri()->getScheme()."://".$client->getUri()->getHost(), "Client should make calls against ".$self->baseUrl);
        	$self->assertEquals("/people/", $client->getUri()->getPath(), "Client path should match /people/ route");
        	return true;
        };
        
        // Client expects one call to $client->request, which will return the json_encoded attrs on success
        $client = $this->buildClientMock(new Zend_Http_Response('200', array(), json_encode(array_merge($attrs, array('id'=>'aa')))), $validationCallback, "POST");        
        // Build Sync object and call readModel
        $sync = new Backbone_Sync_Http(array('baseUrl'=>$this->baseUrl, 'client'=>$client));
        $response = $sync->create($model, $this->sync_options);
        
        // Check that result is the original attributes, along with the server-generated key, and that they have been correctly decoded
        $this->assertEquals($this->records['aa'], $response, "Http Sync should return retrieved attributes");
    }

    /**
     * Check that update() queries against the correct URI, with the right HTTP method
     * @return boolean
     */
    public function testUpdate() {
        $attrs = $this->records['aa'];
        $model = new Backbone_Model($attrs, $this->model_options);
        $self = $this;
        
        // Check route used for retrieval
        $validationCallback = function($client) use ($self) {
            $self->assertEquals($self->baseUrl, $client->getUri()->getScheme()."://".$client->getUri()->getHost(), "Client should make calls against ".$self->baseUrl);
        	$self->assertEquals("/people/".urlencode('aa'), $client->getUri()->getPath(), "Client path should match /people/:id route");
        	return true;
        };
        
        // Client expects one call to $client->request, which will return the json_encoded attrs on success
        $client = $this->buildClientMock(new Zend_Http_Response('200', array(), json_encode($attrs)), $validationCallback, "PUT");
        
        // Build Sync object and call readModel
        $sync = new Backbone_Sync_Http(array('baseUrl'=>$this->baseUrl, 'client'=>$client));
        $response = $sync->update($model, $this->sync_options);
        
        // Check that result is the original attributes, and that they have been correctly decoded
        $this->assertEquals($attrs, $response, "Http Sync should return retrieved attributes");
    }

    /**
     * Check that delete() queries against the correct URI, with the right HTTP method
     * @return boolean
     */
    public function testDelete() {
        $attrs = $this->records['aa'];
        $model = new Backbone_Model($attrs, $this->model_options);
        $self = $this;
        
        // Check route used for retrieval
        $validationCallback = function($client) use ($self) {
            $self->assertEquals($self->baseUrl, $client->getUri()->getScheme()."://".$client->getUri()->getHost(), "Client should make calls against ".$self->baseUrl);
        	$self->assertEquals("/people/".urlencode('aa'), $client->getUri()->getPath(), "Client path should match /people/:id route");
        	return true;
        };
        
        // Client expects one call to $client->request, which will return a blank 200 response on success
        $client = $this->buildClientMock(new Zend_Http_Response('200', array(), ""), $validationCallback, "DELETE");
        
        // Build Sync object and call readModel
        $sync = new Backbone_Sync_Http(array('baseUrl'=>$this->baseUrl, 'client'=>$client));
        $response = $sync->delete($model, $this->sync_options);
        
        // Check that result is the original attributes, and that they have been correctly decoded
        $this->assertEquals(true, $response, "Http Sync should return retrieved attributes");
    }
    
    /**
     * #452 - Support configuring the HTTP client through the sync interface  
     */
    public function testClientConfig() {
        //Standard sync
        $client = new Zend_Http_Client();
        $adapter = new Zend_Http_Client_Adapter_Test();
        $client->setAdapter($adapter);
        $sync = new Backbone_Sync_Http(array('baseUrl'=>$this->baseUrl, 'client'=>$client));
        
        //Set up a canned response;
        $response = new Zend_Http_Response(200, array('Content-Type'=>'application/json'), json_encode($this->records['aa']));
        $adapter->setResponse($response);
        
        //Read a model - expect it to succeed
        $model = new Backbone_Model(array('id'=>'aa'), $this->model_options);
        $res = $sync->readModel($model, $this->sync_options);
        $this->assertEquals($this->records['aa'], $res, "Read model from canned response");

        //Expect default values;
        $this->assertEquals(10, $adapter->getConfig()['timeout'], "Default timeout");
        
        //----------------------------------------------
        
        //Set up a new sync with a longer timeout value
        $client = new Zend_Http_Client();
        $adapter = new Zend_Http_Client_Adapter_Test();
        $client->setAdapter($adapter);
        $sync = new Backbone_Sync_Http(array('baseUrl'=>$this->baseUrl, 'client'=>$client, 'client_config'=>array('timeout'=>60)));
        
        //Set up a canned response;
        $response = new Zend_Http_Response(200, array('Content-Type'=>'application/json'), json_encode($this->records['aa']));
        $adapter->setResponse($response);
        
        //Read a model - expect it to succeed
        $model = new Backbone_Model(array('id'=>'aa'), $this->model_options);
        $res = $sync->readModel($model, $this->sync_options);
        $this->assertEquals($this->records['aa'], $res, "Read model from canned response");
        
        //Expect the longer timeout to have been set on the adapter
        $this->assertEquals(60, $adapter->getConfig()['timeout'], "Longer timeout");
    }
    
}

