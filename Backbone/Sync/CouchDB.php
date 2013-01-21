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
 * Simple Sync implementation to a couchdb backend
 * @author Patrick Barnes
 */
class Backbone_Sync_CouchDB extends Backbone_Sync_Abstract {
    /*************************************************************************\
     * Attributes and Accessors
    \*************************************************************************/
    
    /**
     * What kinds of exceptions are sync errors?
     * @see Backbone_Sync_Abstract::caught 
     * @var array
     */
    protected $caught = array('couchException');
 
    
    /**
     * The couchClient object
     * If initially set to a DSN name (string), will be converted into a couchClient object on construction.
     * @var couchClient
     */
    protected $client = null;

    /**
     * The couchClient object
     * - client() : Get
     * - client($client) : Set - if a string, convert into a couchClient object with that DSN - eg http://user:password@server:port/path/db
     * @param couchClient|string $client
     * @return couchClient 
     */
    public function client($client=null) {
        if (func_num_args()) {
            if (is_string($client)) {
                if (!preg_match('#^(https?://.*)/([^/]+)/?$#', $client, $m)) 
                    throw new InvalidArgumentException("Invalid DSN - invalid format or doesn't include db");
                $dsn = $m[1];
                $db = $m[2]; 
                $this->client = new couchClient($dsn, $db);                
            } 
            elseif ($client instanceof couchClient) $this->client = $client;
            else throw new InvalidArgumentException("couchClient or string DSN expected, received ".gettype($client));
        }
        return $this->client;
    }
    
    /**
     * The name of the design document
     * Used when reading collections
     * NOTE: It is not currently expected that an application will need more than one design document
     * @var string
     */
    protected $design_doc = null;
    
    /**
     * The name of the design document
     * - design_doc() : Get
     * - design_doc($design_doc) : Set
	 * @param string $design_doc
	 * @return $design_doc
     */
    public function design_doc($design_doc = null) {
        if (func_num_args()) $this->design_doc = $design_doc;
        return $this->design_doc;
    }

	/*************************************************************************\
     * Initialisation
    \*************************************************************************/
    
    /**
     * Build a sync instance.
     * Must either define the client in a subclass, or pass as an option.
     */
    public function __construct(array $options = array()) {
        //Construct the client instance, if defined as string
        $this->client(!empty($options['client']) ? $options['client'] : $this->client);
        
        //Set the design doc
        if (!empty($options['design_doc'])) $this->design_doc($options['design_doc']);
        
        parent::__construct($options);
    }
    
    /*************************************************************************\
     * Sync Functions
    \*************************************************************************/

    /**
     * Given a Backbone_Model; fetch its attributes from the backend.
     * @see Backbone_Sync_Abstract::readModel
     * @param Backbone_Model $model
     * @param array $options Options, includes success/error callback
     * @throws couchException If an error occurs
     * @return array|false Returns the arr on success, false on failure. 
     */
    public function readModel(Backbone_Model $model, array $options = array()) {
        if ($model->idAttribute() != '_id') throw new InvalidArgumentException("Model is expected to have '_id' as its idAttribute.");

        $result = $this->client()->getDoc($model->id());
        return $this->convertDocumentToAttributes($result, $options);
    }
    
    /**
     * Given a Backbone_Collection; fetch its elements from the backend.
     * Options:
 	 *  'params' => A map of 'key' => 'value' view filters, to be passed through to the view query. See http://wiki.apache.org/couchdb/HTTP_view_API 
     * 
     * If the collection has its url set, it's assumed to be a view name
     * TODO: This is a bit cumbersome...
     *    
     * @see Backbone_Sync_Abstract::readCollection
     * @param Backbone_Model_Collection $collection  
     * @param array $options
     * @throws couchException If an error occurs
     * @return array|false Returns the array of models attributes, or false if an error occurs.
     */
    public function readCollection(Backbone_Collection $collection, array $options = array()) {
        list($view, $params) = $this->getCollectionSelector($collection, $options);
        $client = $this->client();
        $client->setQueryParameters($params);
        
        $result = $view  ?  $client->getView($this->design_doc, $view)  :  $client->getAllDocs();
             
        $data = array();
        foreach($result->rows as $row) $data[] = $this->convertDocumentToAttributes($row->doc, $options);
        return $data;
    }
    
    /**
     * Given a Backbone_Model; create it in the backend and return the current set of attributes.
     * @see Backbone_Sync_Abstract::create
     * @param Backbone_Model $model
     * @param array $options
     * @throws couchException if an error occurs
     * @return array|false Returns the latest attributes on success, false on failure.
     */
    public function create(Backbone_Model $model, array $options = array()) { return $this->_store($model, $options); }
    
    /**
     * Given a Backbone_Model; update it in the backend and return the current set of attributes.
     * @see Backbone_Sync_Abstract::update
     * @param Backbone_Model $model
     * @param array $options
     * @throws couchException if an error occurs
     * @return array|false Returns the latest attributes on success, false on failure. 
     */
    public function update(Backbone_Model $model, array $options = array()) { return $this->_store($model, $options); }
    
    /**
     * The actual implementation for create/update
     * @param Backbone_Model $model
     * @param array $options
     * @throws couchException if an error occurs
     * @return array|false Returns the latest attributes on success, false on failure. 
     */
    protected function _store(Backbone_Model $model, array $options = array()) {
        if ($model->idAttribute() != '_id') throw new InvalidArgumentException("Model is expected to have '_id' as its idAttribute.");

        $client = $this->client();
        $doc = $this->convertModelToDocument($model, $options); 
        
        //Store the document
        $result = $client->storeDoc($doc);
        
        //Set the returned parameters
        return array('_id'=>$result->id, '_rev'=>$result->rev); 
    }
    
    /**
     * Given a Backbone_Model; delete it from the backend
     * @see Backbone_Sync_Abstract::delete
     * @param Backbone_Model $model
     * @param array $options
     * @throws couchException if an error occurs
     * @return true|false Returns true on success, false on failure.
     */
    public function delete(Backbone_Model $model, array $options = array()) {
        if ($model->idAttribute() != '_id') throw new InvalidArgumentException("Model is expected to have '_id' as its idAttribute.");
        
        $doc = $this->convertModelToDocument($model, $options);
        $client = $this->client();
        $client->deleteDoc($doc);
        return true;    
    }    

    /*************************************************************************\
     * Internal Conversion/Filter Functions
    \*************************************************************************/
    
    /**
     * Given a collection to read, generate the view selector
     * 
     * The default implementation uses the collection's url as the view name,
     * and the 'params' option as the query parameters.
     *  
     * Override this function to permit more complex behaviour
     * TODO: Is the url parameter the most appropriate view naming mechanism?
     * 
     * @param Backbone_Collection $collection
     * @param array $options
     * @throws InvalidArgumentException
     */
    public function getCollectionSelector(Backbone_Collection $collection, array $options) {
        $params = !empty($options['params']) ? $options['params'] : array();
        
        //Is there some extra url info beyond the collection's own url?
        //Assume that the first part is the view name
        if (!empty($params['url'])) {
            $chunks = explode('/', trim($params['url'],'/'));
            $view = array_shift($chunks);
        } else {
            $view = null;
        }
        unset($params['url']);
        
        //Some parameters must be present
        $params['include_docs'] = true;
        $params['reduce'] = false;
        
        return array($view, $params);
    } 
        
    /**
     * Convert a Backbone_Model instance into a couchdb document
     * The default implementation simply converts the attributes into a stdclass object
     * 
     * @param Backbone_Model $model
     * @param array $options
     * @return stdClass 
     */
    public function convertModelToDocument(Backbone_Model $model, array $options) {
        return (object)$model->attributes();
    }
    
    /**
     * Convert a couchdb document into an attribute array.
     * The default implementation simply casts the document object to array. 
     * 
     * @param stdClass $document
     * @param array $options
     * @return array
     */
    public function convertDocumentToAttributes(stdClass $document, array $options) {
        return (array)$document;
    }
}