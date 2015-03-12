<?php

/**
 * Sync class that proxies to a HTTP Rest API
 * Supported paths:
 *  GET    / - readCollection
 *  
 *  GET    /:id - readModel
 *  POST   /    - create
 *  PUT    /:id - update
 *  DELETE /:id - delete
 * 
 * @author rmorriso
 */
class Backbone_Sync_Http extends Backbone_Sync_Abstract {

    static protected $baseUri = null;
    
    /**
     * The HTTP Client
     * @var Zend_Http_Client
     */
    protected $client = null;
    
    /**
     * Configuration to set on the HTTP client
     * @var array
     */
    protected $client_config = null;
    
    /**
     * Accessor method
     * @param Zend_Http_Client $client
     * @return Zend_Http_Client
     */
    public function client($client = null) {
        if ($client instanceof Zend_Http_Client) $this->client = $client;
        else if (!$this->client) $this->client = new Zend_Http_Client(); //Lazy initialization 
        
        return $this->client;
    }
    
    /**
     * Build a new sync object, configuring client, baseurl etc
     * @param array $options
     */
    function __construct($options = array()) {
        parent::__construct($options);
        
        //Set the baseUri from baseUrl
        if (empty($options['baseUrl']))
            throw new InvalidArgumentException("HTTP Sync requires baseUrl");
        static::$baseUri = rtrim($options['baseUrl'], '/');
        
        //Set a client if given
        if (!empty($options['client'])) $this->client($options['client']);
        
        //Store client configuratin if given
        if (!empty($options['client_config'])) $this->client_config = $options['client_config'];
    }
    
    /**
     * Retrieves model from /:model/:id
     * @see Backbone_Sync_Abstract::readModel()
     * @return array Associative array containing the retrieved model's attributes
     */
    public function readModel(Backbone_Model $model, array $options = array()) {
        // Configure the HTTP client (baseuri, path)
        $client = $this->configureHttpClient("GET", $model, $options);
        
        // GET request to $baseuri.$model->id
        //      - Translate HTTP error codes to appropriate exceptions
        $response = $client->request();
        
        if (!$response->isSuccessful()) throw Backbone_Exception::factory($response->getBody(), $response->getStatus());
        
        // json_decode returned object, as assoc array
        $attrs = $this->convertJsonToAttrs($response->getBody(), false);
        
        return $attrs;
    }
    
    /**
     * Retrieves collection from /:model
     * @see Backbone_Sync_Abstract::readCollection()
     * @return array Array of attribute arrays
     */
    public function readCollection(Backbone_Collection $collection, array $options = array()) {
        // Construct the HTTP client (baseuri, path)
        $client = $this->configureHttpClient("GET", $collection, $options);
        
        // GET request to $baseuri.'/'
        //      - Translate HTTP error codes to appropriate exceptions
        $response = $client->request();
        
        if (!$response->isSuccessful()) throw Backbone_Exception::factory($response->getBody(), $response->getStatus());
        
        // json_decode returned array, as array
        $attrs = $this->convertJsonToAttrs($response->getBody(), true);
        
        // return array
        return $attrs;
    }
    
    /**
     * @see Backbone_Sync_Abstract::create()
     * POST to /:model
     * @return array Attributes of the created model
     */
    public function create(Backbone_Model $model, array $options = array()) {
        // Construct the HTTP client (baseuri, path)
        $client = $this->configureHttpClient("POST", $model, $options);
        
        // json_encode $model->attributes
        $client->setRawData($this->convertAttrsToJson($model->attributes(), $model, $options), 'application/json');
        
        // POST attributes to $baseuri.$model->id
        //      - Translate HTTP error codes to appropriate exceptions
        $response = $client->request();
        
        if (!$response->isSuccessful()) throw Backbone_Exception::factory($response->getBody(), $response->getStatus());
        
        // json_decode returned object, as assoc array
        $attrs = $this->convertJsonToAttrs($response->getBody(), false);
        
        return $attrs;
    }
    
    /**
     * @see Backbone_Sync_Abstract::update()
     * PUT to /:model/:id
     * @return array Attributes of the updated model
     */
    public function update(Backbone_Model $model, array $options = array()) {
        // Construct the HTTP client (baseuri, path)
        $client = $this->configureHttpClient("PUT", $model, $options);
        
        // json_encode $model->attributes
        $client->setRawData($this->convertAttrsToJson($model->attributes(), $model));
        
        // PUT attributes to $baseuri.$model->id
        //      - Translate HTTP error codes to appropriate exceptions
        $response = $client->request();
        
        if (!$response->isSuccessful()) throw Backbone_Exception::factory($response->getBody(), $response->getStatus());
        
        // json_decode returned object, as assoc array
        $attrs = $this->convertJsonToAttrs($response->getBody(), false);
        
        return $attrs;
    }
    
    /**
     * @see Backbone_Sync_Abstract::delete()
     * DELETE to /:model/:id
     * @return bool True if successful, throws on error
     */
    public function delete(Backbone_Model $model, array $options = array()) {
        // Construct the HTTP client (baseuri, path)
        $client = $this->configureHttpClient("DELETE", $model, $options);
        
        // DELETE to $baseuri.$model->id
        //      - Translate HTTP error codes to appropriate exceptions
        $response = $client->request();

        if (!$response->isSuccessful()) throw Backbone_Exception::factory($response->getBody(), $response->getStatus());
        
        //Sync->delete should return (bool)success
        return true;
    }
    
    /*************************************************************************\
     * Internal Helper Functions
    \*************************************************************************/
    
    /**
     * Configure the Http Client, override here to sign requests.
     * @param string $method
     * @param string $url
     * @return Zend_Http_Client
     */
    protected function configureHttpClient($method, $model, array $options = array()) {
        $client = $this->client();
        
        // Clean the client completely
        $client->resetParameters(true);
        
        // Configure the client and underlying adapter
        if ($this->client_config) $client->setConfig($this->client_config);

        // Configure the client method and URL - add the baseUri if relative.
        $client->setMethod($method);
        
        $url = $model->url();
        if (strpos($url, '://') === false) { $url = static::$baseUri . $url; }
        
        $client->setUri( $url );
        
        return $client;
    }

    /**
     * For Models:
     * 		Decode JSON to objects, forcing the top level to an associative array
     * For Collections:
     * 		Decode JSON to array of objects, loop through using above method for each model
     * 
     * @param string $json
     * @param boolean $isCollection
     * @return array
     */
    protected function convertJsonToAttrs($json, $isCollection) {
		if ($isCollection) {
			$attrs = (array)json_decode($json);
			foreach ($attrs as &$o) {
				$o = (array)$o;
			}
			return $attrs;
		}
		else {
        	return (array)json_decode($json);
		}
    }

    /**
     * Map attributes if required
     * 
     * @param string $attrs
     * @param Backbone_Model $model
     * @return string
     */
    protected function convertAttrsToJson($attrs, $model) {
        return json_encode($attrs);
    }
}
