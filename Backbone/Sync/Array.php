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
 * Backbone_Sync_Array
 * A sync class to fetch/save against an internal array
 * Changes will only persist for the lifetime of the object.
 * 
 * This class is intended to be used primarily for development and testing
 *
 * @author Patrick Barnes
 */
class Backbone_Sync_Array extends Backbone_Sync_Abstract {
    /*************************************************************************\
     * Attributes and Accessors
    \*************************************************************************/
    
    /**
     * The internal array storing model data by id, eg: '(id)' => array('attr'=>'foo', 'otherattr'=>'bar' ...),
     * The data element should also include the id attribute.  
     * @var array
     */
    public $data = null;
        
    /**
     * The internal array storing model data by id, eg: '(id)' => array('attr'=>'foo', 'otherattr'=>'bar' ...),
     * - data() : Get
     * - data($data) : Set
     * @param array $data
     * @return array
     */
    public function data(array $data=null) {
        if (func_num_args()) {
            if ($data === null || !is_array($data)) throw new InvalidArgumentException("Must define model data");
            $this->data = $data;
        }
        return $this->data;
    }
    
	/*************************************************************************\
     * Initialisation
    \*************************************************************************/
    
    /**
     * Build a sync instance.
     * Must define the internal data, or pass it as an option.
     */
    public function __construct(array $options = array()) {
        //Set the data, if defined
        $this->data(array_key_exists('data', $options) ? $options['data'] : $this->data);
        
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
     * @throws Zend_Db_Exception if an error occurs
     * @return array|false Returns the arr on success, false on failure. 
     */
    public function readModel(Backbone_Model $model, array $options = array()) {
        $id = $model->id();
        if (!isset($this->data[$id])) throw new Backbone_Exception_NotFound("Model could not be read, not found");

        return $this->data[$id];
    }
    
    /**
     * Given a Backbone_Collection; fetch its elements from the backend.
     * Options:
     *  'params' => A map of 'key' => 'value' where filters 
     * @see Backbone_Sync_Abstract::readCollection
     * @param Backbone_Model_Collection $collection  
     * @param array $options
     * @throws Zend_Db_Exception if an error occurs
     * @return array|false Returns the array of models attributes, or false if an error occurs.
     */
    public function readCollection(Backbone_Collection $collection, array $options = array()) {
        if (empty($options['params'])) return array_values($this->data);

        return array_filter(array_values($this->data), function($r) use($options) {
            foreach($options['params'] as $field=>$value) {
                //If field doesn't exist, fail.
                if (!isset($r[$field])) return false;                        
                //If field in the value list, good.
                elseif (array_intersect((array)$value, (array)$r[$field])) continue;
                //Neither, fail.
                else return false;
            }
            return true; //Matched every filter, pass.
        });
    } 
        
    /**
     * Given a Backbone_Model; create it in the backend and return the current set of attributes.
     * @see Backbone_Sync_Abstract::create
     * @param Backbone_Model $model
     * @param array $options
     * @throws Zend_Db_Exception if an error occurs
     * @return array|false Returns the latest attributes on success, false on failure.
     */
    public function create(Backbone_Model $model, array $options = array()) {
        $id = $model->id();
        if (!$id) throw new Backbone_Exception_Forbidden("Cannot create a model without an id");
        if (isset($this->data[$id])) throw new Backbone_Exception_Forbidden("Cannot create model - already exists");
        return $this->data[$id] = $this->convertModelToData($model, $options); 
    }
    
    /**
     * Given a Backbone_Model; update it in the backend and return the current set of attributes.
     *  TODO: Define; what happens if the PK of a model is modified? 
     * @see Backbone_Sync_Abstract::update
     * @param Backbone_Model $model
     * @param array $options
     * @throws Zend_Db_Exception if an error occurs
     * @return array|false Returns the latest attributes on success, false on failure. 
     */
    public function update(Backbone_Model $model, array $options = array()) {
        $id = $model->id();
        if (!$id) throw new Backbone_Exception_Forbidden("Cannot update a model without an id");
        return $this->data[$id] = $this->convertModelToData($model, $options);
    }
    
    /**
     * Given a Backbone_Model; delete it from the backend
     * @see Backbone_Sync_Abstract::delete
     * @param Backbone_Model $model
     * @param array $options
     * @throws Zend_Db_Exception if an error occurs
     * @return true|false Returns true on success, false on failure.
     */
    public function delete(Backbone_Model $model, array $options = array()) {
        $id = $model->id();
        if (!$id) throw new Backbone_Exception_Forbidden("Cannot delete a model without an id");
        if (!isset($this->data[$id])) throw new Backbone_Exception_NotFound("Cannot find model to delete");
        
        unset($this->data[$id]);
        return true;
    }
    
    /**
     * Convert a Backbone_Model instance into an array
     * The default implementation simply returns the attributes
     *
     * @param Backbone_Model $model
     * @param array $options
     * @return array
     */
    public function convertModelToData(Backbone_Model $model, array $options) {
        return $model->attributes();
    }
} 