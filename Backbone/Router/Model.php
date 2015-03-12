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
        
        parent::__construct($options);
        
        //Add a special route to match '_js' - after construction, so not overriden by other routes 
        $this->route('_js', 'exportClasses');
        $this->route('_.js', 'exportClassesAsModule');
    }

    /*************************************************************************\
     * Request Helpers
    \*************************************************************************/
    
    /**
     * Instantiate a blank model, linked to the collection
     * @return Backbone_Model
     */
    protected function buildModel($id=null) {
        $collection = $this->collection();
        $class = $collection->model();
        $attrs = $id ? array( $collection->idAttribute() => $id ) : array();
        $model = new $class($attrs); /* @var $model Backbone_Model */
        $model->collection($collection);
        return $model;
    }
    
    /**
     * Update a model with new values
     * @return Backbone_Model|false False if set is unsuccessful
     */
    protected function modifyModel($model, $values, $options) {
        return $model->set($values, $options);
    }
    
    /**
     * Send a model or collection back to the client
     * @param Backbone_Collection|Backbone_Model $m_or_c
     * @param array $options
     */
    protected function output($m_or_c, array $options) {
        $this->response()
          ->setHeader('Content-Type', 'application/json')
          ->setBody( $m_or_c->exportJSON($options) );        
    }

    /*************************************************************************\
     * Actions
    \*************************************************************************/
    
    /**
     * Read the contents of the collection, according to the query parameters
     * @see Backbone_Router_Rest::index()
     */
    public function index(array $options=array()) {
        $options['params'] = $this->getParams($options);
        $this->collection()->fetch($options);
        $this->output($this->collection(), $options);
    }
    
    /**
     * Read the model with the given id
     * @see Backbone_Router_Rest::read()
     * @param string id
     */
    public function read($id, array $options=array()) {
        $options['params'] = $this->getParams($options);
        $modelClass = $this->collection()->model();
        $model = $modelClass::factory($id, $options);
        
        $this->output($model, $options);
    }
        
    /**
     * Create the given model
     * @see Backbone_Router_Rest::create()
     */
    public function create(array $options=array()) {
        $model = $this->buildModel();
        $options['params'] = $this->getParams($options);

        //Try and set the new attributes
        if (!$this->modifyModel($model, (array)$this->getData(), $options)) return;

        //If successful, save and return the created model
        if (!$model->save(null, $options)) return;

        $this->output($model, $options);
    }

    /**
     * Update the model with the given id
     * @see Backbone_Router_Rest::update()
     */
    public function update($id, array $options=array()) {
        $model = $this->buildModel($id);
        $options['params'] = $this->getParams($options);

        //Fetch the existing model
        if (!$model->fetch($options)) return;
        
        //Try and set the new attributes
        if (!$this->modifyModel($model, (array)$this->getData(), $options)) return;
        
        //If successful, save and return the updated model
        if (!$model->save(null, $options)) return;
        
        $this->output($model, $options);
    }
    
    /**
     * Delete the model with the given id
     * @see Backbone_Router_Rest::delete()
     */
    public function delete($id, array $options=array()) {
        $model = $this->buildModel($id);
        $options['params'] = $this->getParams($options);

        //Fetch the existing model
        if (!$model->fetch($options)) return;
        
        $model->destroy(array_merge($options, array('wait'=>true)));

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