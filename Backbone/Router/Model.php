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
 * Backbone_Router_Model proxies REST calls through to an underlying collection and its model
 * 
 * This class is the natural opposite of the Backbone.js's Sync - it interprets the server
 * request and directs it to the collection/model, then returns the appropriate response
 * 
 * @author Patrick Barnes
 */
class Backbone_Router_Model extends Backbone_Router_Rest {
    /*************************************************************************\
     * Attributes and Accessors
    \*************************************************************************/
    
    /**
     * The proxied collection
     * If initially set to a string, will be converted to an instance 
     * on router construction (with the router options passed through to it)
     * @var Backbone_Collection
     */
    protected $collection = 'Backbone_Collection';
    
    /**
     * The proxied collection
     * - 'collection()' : Get
     * - 'collection($collection)' : Set
     * @param Backbone_Collection|string $collection 
     * @return Backbone_Collection
     */
    public function collection(Backbone_Collection $collection=null) {
        if (func_num_args()) { $this->collection = $collection; }
        return $this->collection;
    }
    
    /**
     * If set, a whitelist of the query parameters that can be passed to the model/collection as options
     */
    protected $valid_user_params = null;
    
    /*************************************************************************\
     * Initialize
    \*************************************************************************/

    /**
     * Constructor - sets up the collector.
     * To customise, override the 'initialize()' method.
     * (called by the parent constructor) 
     * @param array $options
     */
    public function __construct(array $options=array()) {
        //If collection is a string, build it.
        $collection = !empty($options['collection']) ? $options['collection'] : $this->collection;
        if (is_string($collection)) { $collection = new $collection(null, $options); }
        $this->collection($collection);
        
        //Override valid_user_params if given
        if (!empty($options['valid_user_params'])) $this->valid_user_params = $options['valid_user_params'];
        
        parent::__construct($options);
        
        //Add a special route to match '_js' - after construction, so not overriden by other routes 
        $this->route('_js', 'exportClasses');
        $this->route('_.js', 'exportClassesAsModule');
    }

    /*************************************************************************\
     * Request Helpers
    \*************************************************************************/
    
    /**
     * Get the options for this request; they will be passed through to the 
     * model/collection and the subsequent sync methods
     * 
     * You can restrict which options are able to be set from the request by
     * overriding/setting 'valid_user_params'.
     * 
     * @param array $options
     * @return array
     */
    protected function getOptions(array $options) {
        //Fetch user parameters, filtering if specified
        $params = $this->request()->getParams();
        if ($this->valid_user_params !== null) 
            $params = array_intersect_key($params, array_flip($this->valid_user_params));
            
        //Combine with the server options. 
        //(Already-set params overwrite query parameters)   
        return array_merge_recursive(array('params'=>$params), $options);
    }
    
    /**
     * Get the data for this request; usually this will be sent in the request body,
     * but it can be overriden by the 'data' query parameter.
     * JSON format is required
     * 
     * @return array
     * @throws InvalidArgumentException If the request data is invalid
     */
    protected function getData() {
        $request = $this->request();
        $raw = $request->has('data') ? $request->get('data') : $this->request()->getRawBody();
        if (!$raw) throw new InvalidArgumentException("No data received", 400);  
        $data = json_decode($raw, true);
        if ($data === null and json_last_error() != JSON_ERROR_NONE) 
            throw new InvalidArgumentException("Incorrect data format sent - should be JSON", 400);

        return $data;
    }
    
    /**
     * Instantiate a blank model, linked to the collection
     * @return Backbone_Model
     */
    protected function buildModel() {
        $collection = $this->collection();
        $class = $collection->model();
        $model = new $class(); /* @var $model Backbone_Model */
        $model->collection($collection);
        return $model;
    }

    /*************************************************************************\
     * Actions
    \*************************************************************************/
    
    /**
     * Read the contents of the collection, according to the query parameters
     * @see Backbone_Router_Rest::index()
     */
    public function index(array $options=array()) {
        $options = $this->getOptions($options);
        $this->collection()->fetch($options);
        
        $this->response()
            ->setHeader('Content-Type', 'application/json')
            ->setBody( $this->collection()->export($options) );
    }
    
    /**
     * Create the given model
     * @see Backbone_Router_Rest::create()
     */
    public function create(array $options=array()) {
        $model = $this->buildModel();
        $options = $this->getOptions($options); 
        $model->save($this->getData(), $options);
        
        $this->response()
            ->setHeader('Content-Type', 'application/json')
            ->setBody( $model->export($options) );
    }
    
    /**
     * Read the model with the given id
     * @see Backbone_Router_Rest::read()
     * @param string id
     */
    public function read($id, array $options=array()) {
        $options = $this->getOptions($options);
        $modelClass = $this->collection()->model();
        $model = $modelClass::factory($id, $options);
        $this->response()
            ->setHeader('Content-Type', 'application/json')
            ->setBody( $model->export($options) );
    }

    /**
     * Update the model with the given id
     * @see Backbone_Router_Rest::update()
     */
    public function update($id, array $options=array()) {
        $model = $this->buildModel();
        if (!$model->id($id)) throw new InvalidArgumentException("Invalid id", 404);
        $options = $this->getOptions($options);

        $model->fetch($options);
        $model->save($this->getData(), $options);
        
        $this->response()
            ->setHeader('Content-Type', 'application/json')
            ->setBody( $model->export($options) );
    }
    
    /**
     * Delete the model with the given id
     * @see Backbone_Router_Rest::delete()
     */
    public function delete($id, array $options=array()) {
        $model = $this->buildModel();
        if (!$model->id($id)) throw new InvalidArgumentException("Invalid id", 400);
        $options = $this->getOptions($options); 
        
        $model->destroy($options);
        //No need to return anything - just a success HTTP code
    }

    /**
     * Export the model and collection javascript definition
     * (Needs the client to have loaded Backbone.js already)
     * TODO: Make more standards-compliant... require.js?
     */
    public function exportClasses(array $options=array()) {
        $collection = $this->collection();
        $modelClass = $collection->model();
        
        $exports = array(
            "//Exported definitions for ".get_class($this)
        );
        if ($modelClass != 'Backbone_Model')
            $exports[] = $modelClass::exportClass();
        if (get_class($collection) != 'Backbone_Collection') 
            $exports[] = $collection->exportClass();
            
        $this->response()
            ->setHeader('Content-Type', 'text/javascript')
            ->setBody( implode("\n\n", $exports) );
    } 

    /**
     * Export the model and collection javascript definition
     * (Needs the client to have loaded Backbone.js already)
     * Definitions are wrapped in a Universal Module Definition (UMD).
     * If requirejs is loaded, it will use requirejs's define(...) syntax;
     * otherwise, it will register the module in the global namespace.
     * Options:
     *      exportModuleName: The name of the module to export into.
     *      exportModuleDeps: An array of requirejs module dependencies
     *                  (such as 'backbone', 'underscore', or 'jquery')
     */
    public function exportClassesAsModule(array $options=array()) {
        $moduleName = $options['exportModuleName'];
        $moduleDeps = json_encode(is_array($options['exportModuleDeps']) ? $options['exportModuleDeps'] : array($options['exportModuleDeps']));
        $collection = $this->collection();
        $modelClass = $collection->model();
        
        $exports = array(
            "//Exported definitions for ".get_class($this)
        );
        if ($modelClass != 'Backbone_Model')
            $exports[] = $modelClass::exportClass();
        if (get_class($collection) != 'Backbone_Collection') 
            $exports[] = $collection->exportClass();
        
        $definitions = implode("\n\n", $exports);
        
        $out = <<<EOF
(function(root, factory) {
    if (typeof define === 'function' && define.amd) {
        define($moduleDeps, factory);
    }
    else {
        root.$moduleName = factory(root.$moduleName);
    }
}(this, function($moduleName) {
$definitions

return $moduleName;
}));
EOF;
        $this->response()
            ->setHeader('Content-Type', 'text/javascript')
            ->setBody( $out );
    }    
}