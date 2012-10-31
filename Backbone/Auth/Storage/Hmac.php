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
 * Provides a mechanism for retrieving identity from an HMAC-signed request,
 * and verifying the validity and currency of the request signature.
 * 
 * TODO: Have the class extend Backbone_Events to trigger on valid/invalid signatures?
 * 
 * (Doesn't 'store' the identity itself, requires that the client does)  
 * 
 * @author Patrick Barnes 
 */
class Backbone_Auth_Storage_Hmac implements Zend_Auth_Storage_Interface {
    /*************************************************************************\
     * Attributes and Accessors
    \*************************************************************************/
    
    /**
     * The number of seconds for which the generated client key should be valid
     * (Default 1 hr)
     * @var int
     */
    protected $lifetime = 3600;
        
    /**
     * The server secret key - must be set on construction
     * @var string
     */
    protected $server_key = null;    
    
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
     * Cache the generated client key
     * @var string
     */
    protected $client_key;
    
    /**
     * The expiry of the current or generated client key
     * @var integer
     */
    protected $expiry;
        
    /**
     * The current username, if the request was validly signed
     * Will be set to FALSE if the request is unsigned
     * Access with read()/write()
     * @var string
     */
    protected $identity = null;
    
    /*************************************************************************\
     * Construction/initialisation
    \*************************************************************************/
    
    /**
     * Build a new HMAC authentication 'storage' object.
     * Use it with Zend_Auth::setStorage
     * 
     * Options:
     * 	- 'request' : Specify the request containing the signature
     *  - 'lifetime' : Specify the lifetime of the generated client key, in seconds
     *  - 'server_key' : Specify the server's secret key 
     *  				 (Must be set on construction or be defined in child class)
     * 
     * @see Zend_Auth::setStorage
     * @param array $options
     * @throws InvalidArgumentException
     */    
    public function __construct(array $options=array()) {
        //If request is given, set it.
        if (!empty($options['request'])) $this->request($options['request']);
        
        //If lifetime is given, set it.
        if (!empty($options['lifetime']))
            $this->lifetime = $options['lifetime'];
        
        //Set/check the auth_secret
        if (!empty($options['server_key']))
            $this->server_key = $options['server_key'];
        else if (empty($this->server_key))
            throw new InvalidArgumentException("Must specify an authentication secret");
            
        //If logger is given, store a reference to it
        if (!empty($options['logger'])) 
            $this->logger = $options['logger'];
    }

    /*************************************************************************\
     * Utility methods
    \*************************************************************************/
    
    /**
     * Read and return the client credentials
     * If called after a write() operaion, is a newly-generated set of credentials
     * Otherwise reads from the request signature
     * @return array An associative array containing identity, expiry, and client_key
     * @throws Backbone_Exception_Unauthorized If the request is signed, but invalid
     */
    public function getClientCredentials() {
        if ($this->identity === null) $this->_readIdentity();
        
        return array(
        	'identity'=>$this->identity, 
        	'expiry'=>$this->expiry,
            'client_key'=>$this->client_key
        );
    }
    
    /**
     * Check whether the HMAC signature is valid
     * @param array $params
     * @param array $options
     * @return string An error if one occurs, null if successful
     */
    public function validateSignature(array $params) {
        foreach(array('identity','expiry','signature') as $required)
            if (empty($params[$required]))
                return "Missing '$required' parameter"; 
        $identity = $params['identity'];
                
        //The signature is only valid from time of issue to expiry
        $expiry = (int)$params['expiry']; 
        if (time() > $expiry or time() < $expiry - $this->lifetime) 
            return "Credentials expired";
        
        //Re-derive the client key from the identity/expiry and the server key
        $client_key = hash_hmac('sha256', $identity.$expiry, $this->server_key);
        
        //Check the signature has been signed with the valid client key        
        $request = $this->request();
        $method = $request->getMethod();
        $uri = $request->getScheme().'://'.$request->getHttpHost().$request->getRequestUri();
        $computed_signature = hash_hmac('sha256', $method.$uri, $client_key);
        if ($params['signature'] !== $computed_signature) {
            if (!empty($this->logger)) $this->logger->log("Forged signature: ".json_encode(compact('identity','expiry','client_key','uri','computed_signature')));
            return "Forged signature";
        }

        //Store the signature credentials in the object
        $this->expiry = $expiry;
        $this->identity = $params['identity'];
        $this->client_key = $client_key;
        
        return null;        
    }
    
    /**
     * Parse the request header, validate the request, and return the identity
     * Shouldn't be called more than once (respected by callers)
     * @return string The signature identity (username)
     * @throws Backbone_Exception_Unauthorized If the request is signed, but invalid
     */
    protected function _readIdentity() {
        //If no header, the request is unsigned
        $auth = $this->request()->getHeader('Authorization');
        if (!$auth) {
            $this->identity = false;
            return;
        }
        
        //Parse the header
        if ( !preg_match('/^HMAC (.*)$/', $auth, $m) )
            throw new Backbone_Exception_Unauthorized("Invalid Signature; Incorrect header format");    
        parse_str($m[1], $params);
        
        //Check the signature
        $error = $this->validateSignature($params);
        if ( $error )
            throw new Backbone_Exception_Unauthorized("Invalid Signature; $error");
            
        return $this->identity;
    }
    
    /*************************************************************************\
     * Zend_Auth_Storage_Interface Methods
    \*************************************************************************/
    
    /**
     * Returns true if the request is not signed
     * 
     * @see Zend_Auth_Storage_Interface::isEmpty()
     * @throws Backbone_Exception_Unauthorized If the request is signed, but invalid
     * @return boolean
     */
    public function isEmpty() {
        $identity = ($this->identity===null) ? $this->_readIdentity() : $this->identity;
        return ($identity===false);
    }
    
    /**
     * Return the identity of the signature
     * 
     * @see Zend_Auth_Storage_Interface::read()
     * @throws Backbone_Exception_Unauthorized If the request is signed, but invalid
     * @return string The identity, or false if the request is unsigned
     */
    public function read() {
        return ($this->identity===null) ? $this->_readIdentity() : $this->identity;
    }
    
	/**
	 * Upon authenticating, validate and store the identity, and generate a client key 
	 * 
	 * @param mixed $contents The authenticated identity
	 * @throws Zend_Auth_Storage_Exception If the identity can't be found
     * @see Zend_Auth_Storage_Interface::write()
     */
    public function write($contents) {
        //Some auth adaptors return an array - if so extract the identity/username
        if ( is_array($contents) and !empty($contents['username']) )
            $identity = $contents['username'];
        elseif ( is_string($contents))
            $identity = $contents;
        else 
            throw new Zend_Auth_Storage_Exception("Invalidly formatted identity");
        
        //Generate a time-limited key
        $expiry = time() + $this->lifetime;

        //Store the signature credentials in the object
        $this->expiry = $expiry;
        $this->identity = $identity;
        $this->client_key = hash_hmac('sha256', $identity.$expiry, $this->server_key);
    }
    
	/**
	 * Clear out the identity
	 * Does nothing beyond this request lifetime, the client is 
	 * expected to remove its own copy of the credentials
	 * 
     * @see Zend_Auth_Storage_Interface::clear()
     */
    public function clear() {
        $this->identity = false;
    }
} 