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
 * Provides a standard collection class for our sets of models, ordered
 * or unordered. If a `comparator` is specified, the Collection will maintain
 * its models in sort order, as they're added and removed.
 * @author Patrick Barnes
 */
class Backbone_Collection extends Backbone_Events implements Countable, Iterator 
{
    /*************************************************************************\
     * Attributes and Accessors
    \*************************************************************************/
    
    /**
     * The name of the model class to use for any constructed instances of the collection.
     * Override to set a default value, or pass the name in at collection construction.
     * Alternatively, a 'prototype' object to clone instances from
     * @var string|Backbone_Model
     */
    protected $model = 'Backbone_Model';
    
    /**
     * The name of the model class to use for any constructed instances of the collection.
     * Alternatively, a 'prototype' object to clone instances from
     * 
     * Get: $collection->model() 
     * Set: $collection->model($model) 
     * 
     * @param string|Backbone_Model $model
     * @return string|Backbone_Model
     */
    public function model($model=null) {
        if (func_num_args()) {
            if (!$model or !is_a($model, 'Backbone_Model', true)) //is_a: returns true for name or object
                throw new InvalidArgumentException(get_class($this).": Missing or invalid model name or prototype.");
            $this->model = $model;
            $this->idAttribute = null;
        }
        return $this->model;
    }
    
    /**
     * The default model type's ID attribute
     * @var string
     */
    protected $idAttribute = null;
    
    /**
     * The default model type's ID attribute
     * Picked from the model class when needed, and cached
     * @see Backbone_Model::idAttribute
     * @return string
     */    
    public function idAttribute() {
        if (!$this->idAttribute) {
            $name_or_prototype = $this->model();
            if (is_string($name_or_prototype)) {
                $model = new $name_or_prototype();
                $this->idAttribute = $model->idAttribute();
            } else {
                $this->idAttribute = $name_or_prototype->idAttribute();
            }
        }
        return $this->idAttribute; 
    }
    
    /**
     * The url of this collection, if defined.
     * Override to set a default value, or use the accessor
     * @var string
     */
    protected $url = null;
    
    /**
     * The url of this collection
     * Get: $collection->url();
     * Set: $collection->url($url);
     * @param url
     * @return url
     */
    public function url($url=null) {
        if (func_num_args()) $this->url = $url;
        return $this->url;
    }
    
    /**
     * The comparator to sort the collection by
     * @var callable
     */
    protected $comparator = null;
    
    /**
	 * Store the comparator type
     */
    protected $comparator_type;
    
    /**
     * The comparator to sort the collection by
     * Can be an iterator comparator (1 argument) or a compare comparator (2 arguments)
     * 
     * Get: $collection->comparator()
     * Set: $collection->comparator($comparator)
     * @param callable $comparator
     * @return callable 
     */
    public function comparator($comparator=null) {
        if (func_num_args()) {
            //Removing the comparator? - short-circuit back
            if (!$comparator) return $this->comparator=null;
            
            if (is_string($comparator) and method_exists($this, $comparator)) $comparator = array($this, $comparator);
            if (!is_callable($comparator)) throw new InvalidArgumentException("Comparator must be callable");
            
            //Find out; how many arguments does the comparator expect?
            $class = null; $method = null;
            if (is_array($comparator)) list($class, $method) = $comparator;
            if (is_string($comparator) and strpos($comparator, '::')) list($class, $method) = explode("::", $comparator);
            if (is_object($comparator) and method_exists($comparator, '__invoke')) { $class = $comparator; $method = '__invoke'; }
            else $reflector = new ReflectionFunction($comparator);
            if ($class) $reflector = new ReflectionMethod($class, $method);
            $params = $reflector->getNumberOfParameters();
            
            if ($params == 2) $this->comparator_type = 'compare';
            else if ($params == 1) $this->comparator_type = 'iterator';
            else throw new InvalidArgumentException("Comparator is unknown type - should expect 1 or 2 parameters");
            $this->comparator = $comparator;
        }
        return $this->comparator;
    }
    
    /**
     * The stored members of this collection, in whatever order.
     * @var array
     */
    protected $models;
    
    /**
     * The stored members of this collection
     * @return array
     */
    public function models() { return $this->models; }
    
    /**
     * A map of models by id
     * @var array
     */
    protected $_byId;
    
    /**
     * A map of models by cid
     * @var array
     */
    protected $_byCid;
    
    /**
     * The length of the collection
     * @var integer
     */
    protected $length;
    
    /**
     * The length of the collection
     * @return integer
     */
    public function length() { return $this->length; }
    
    /**
     * The synchronization function / object
     * Performs the given synchronization method on the model. 
     * 
     * Unlike Backbone.js, the sync() method is expected to be synchronous.
     * Override this function for other behaviour.
     * 
     * @var Backbone_Sync_Interface|callable
     */
    protected $sync = null;
    
    /**
     * Synchronization function accessor
     * Get: $model->sync()
     * Set: $model->sync($sync)
     * 
     * If a sync function is/was set, returns it.
     * Otherwise, returns the Backbone default sync
     * 
     * DIFFERS FROM Backbone.js - sync() is the function accessor, not the function itself. 
     * 
     * The sync parameter can be an implementation of Backbone_Sync_Interface 
     * or a function of signature; function($method, $model, $options)
     * 
     * @param Backbone_Sync_Interface|callable $sync
     * @return Backbone_Sync_Interface The effective sync fuction
     */
    public function sync($sync=null) { 
        if (func_num_args()) {
            if ($sync and !is_callable($sync)) throw new InvalidArgumentException('Cannot set $sync - invalid');
            $this->sync = $sync;  
        }
        
        return $this->sync ? $this->sync : Backbone::getDefaultSync($this->model);
    }
    
    /*************************************************************************\
     * Construction/initialisation
    \*************************************************************************/

    /**
     * Create a new standard collection
     * 
     * Options:
     *  - 'model' : Specify the model class name
     *  - 'comparator' : Specify the comparator to use; keeps models sorted
     *  - 'parse' : If set, pass the $models param through parse()
     *  - 'sync' : If set, register as the collection's sync function/object.
     *  - 'url' : If set, set the collection url.
     *  
     * @param mixed $models Optionally, an initial set of models 
     * @param array $options
     * @throws InvalidArgumentException
     */
    public function __construct($models = array(), array $options = array()) {
        parent::__construct($options);
        
        //Check the model
        $this->model( !empty($options['model']) ? $options['model'] : $this->model );

        //Check the sync, if defined
        $sync = !empty($options['sync']) ? $options['sync'] : $this->sync;
        if (is_string($sync) and is_subclass_of($sync, 'Backbone_Sync_Interface', true)) $sync = new $sync($options);
        if ($sync) $this->sync($sync);
        
        //Check the comparator, if defined
        $comparator = !empty($options['comparator']) ? $options['comparator'] : $this->comparator;
        if ($comparator) $this->comparator($comparator);

        //Override url if given
        if (!empty($options['url'])) $this->url($options['url']);
        
        $this->_reset();
        $this->initialize($models, $options);
        if ($models) $this->reset($models, array_merge($options, array('silent'=>true, 'sync'=>null))); 
        //Weirdly:
        // in Backbone.js Collection, the models are added after initialize.
        // in Backbone.js Model, the attributes are set before initialize.
        //Known weirdness, leave it alone.
    }
    
	/**
     * Once an object is cloned, fix aspects that don't belong to the new model   
     * (Uses php's clone keyword)
     * __clone may not do what you think, make sure you read: http://php.net/manual/en/language.oop5.cloning.php
     *
     * The clone does not share event triggers with the original
     */
    public function __clone() {
       $this->off();
    }
        
    /**
     * Reset all internal state. Called when the collection is reset.
     */
    protected function _reset() {
        $this->length = 0;
        $this->models = array();
        $this->_byId = array();
        $this->_byCid = array();
    }  

    /**
     * Initialize is an empty function by default. Override it with your own initialization logic.
     * (You may also need to define an 'initialize' javascript method)
     *   
     * @param mixed $models The records/data passed on construction
     * @param array $options The options passed on construction
     */
    protected function initialize($models, array $options) {
    }
    
    /**
     * Convert a response into a list of models to be added to the collection. 
     * The default implementation just json_decodes the value if a string, and passes it along otherwise.
     * 
     * (Doesn't parse individual models, that's delegated to Model.parse(). Parses *collections* of models when calling fetch() )
     * 
     * @param mixed $in
     * @return array
     */
    public function parse($in) {
        return is_string($in) ? json_decode($in, true) : $in;
    }
    
    /*************************************************************************\
     * Collection modification
    \*************************************************************************/
    
    /**
     * Add a model or list of models to the collection.
     * 
     * Options:
     *  - 'silent' : Don't trigger the 'add' event 
     *  - 'merge' : If set, merge in duplicates
     *  - 'parse' : If set, filter each element through the model's parse method.
     *  - 'at' : If set, add the new models at a particular offset in the collection
	 *
     * @param mixed $models A model, array of models, or some other value that can be parsed. (If not an array, assumed to represent a single model) 
     * @param array $options
     * @return Backbone_Model_Collection provides a fluent interface
     */
    public function add($models, array $options = array()) {
        if (is_array($models)) reset($models);
        if (!is_array($models) or $models and !is_numeric(key($models))) $models = array($models);
        $ids = $this->_byId; 
        $cids = $this->_byCid; 
        $new_cids = array();
        $dups = array(); 

        // Prepare/validate each element, and check if a duplicate of another element
        foreach($models as $i=>$model) {
            $model = $models[$i] = $this->_prepareModel($model, $options);
            if (!$model) throw new InvalidArgumentException("Can't add an invalid model to a collection");
            $cid = $model->cid();
            $id = $model->id();
            if (isset($cids[$cid]) or ($id and isset($ids[$id]))) { $dups[$i] = $model; continue; }
            $new_cids[$cid] = $cids[$cid] = $model;
            if ($id!==null) $ids[$id] = $model; //TODO: Backbone.js doesn't have a fence around this line; only one new item in a collection supported?
        }
        
        //Remove duplicates
        $models = array_diff_key($models, $dups);

        // Listen to added models' events, and index models for lookup by id and by cid 
        foreach($models as $model)
            $model->on('all', array($this, '_onModelEvent'));
        $this->_byCid = $cids;
        $this->_byId = $ids;

        //Insert models into the collection
        if (isset($options['at'])) {
            if (!is_numeric($options['at']) or $options['at'] < 0 or $options['at'] > $this->length) throw new InvalidArgumentException("Invalid 'at' offset given");
            $at = $options['at'];   
        } else {
            $at = $this->length;
        }

        array_splice($this->models, $at, 0, $models);
        $this->length = count($this->models);
        
        //Merge in duplicate models, by id.
        //TODO: This is the backbone.js behaviour, but seems like a bug. Investigate! (if more than one 'new' object in collection, might overwrite)
        if (!empty($options['merge'])) {
            foreach($dups as $dupe) if (isset($this->_byId[$dupe->id()]))
                $this->_byId[$dupe->id()]->set($dupe, $options);
        }
        
        // Sort the collection if appropriate
        if ($this->comparator() and !isset($options['at'])) $this->sort(array('silent'=>true));
        
        // Trigger 'add' events if not silent
        if (!empty($options['silent'])) return $this;
        foreach($this->models as $i=>$model) if (isset($new_cids[$model->cid()])) {
            $options['index'] = $i;
            $model->trigger('add', $model, $this, $options);
        }
                    
        return $this;   
    }
    

    /**
     * Remove a model or a list of models from the set.
     * 
     * Options:
     *  - 'silent' : Do not fire the 'remove' event for removed models
     *  
     * @param Backbone_Model|array $models array of Backbone_Model 
     * @param array $options
     */
    public function remove($models, array $options=array()) {
        if (!is_array($models)) $models = array($models);
        $silent = !empty($options['silent']);

        foreach($models as $_model) { /* @var $model Backbone_Model */
            $model=$this->getByCid($_model->cid()) or $model=$this->get($_model->id());
            if (!$model) continue;
            $index = $this->indexOf($model);
            unset($this->_byCid[$model->cid()]);
            unset($this->_byId[$model->id()]);
            array_splice($this->models, $index, 1);
            $this->length = $this->length - 1;
            if (!$silent) {
                $options['index'] = $index;
                $model->trigger('remove', $model, $this, $options);
            }
            $this->_removeReference($model);
        }
                    
        return $this;
    }
    
    /**
     * Add a model to the end of the collection
     * Delegates to add(), so supports the same options
     * 
     * @param Backbone_Model|mixed $model
     * @param array $options
     * @return Backbone_Model The added model
     */
    public function push($model, array $options = array()) {
        $model = $this->_prepareModel($model, $options);

        $this->add($model, $options);
        
        return $model; 
    }
    
    /**
     * Remove the model at the end of the collection and return it.
     * @param array $options
     * @return Backbone_Model|false The removed model, or false if the collection was empty.
     */
    public function pop(array $options = array()) {
        $model = $this->at($this->length - 1);

        if ($model) $this->remove($model, $options);
        return $model;
    }
    
    /**
     * Add a model to the beginning of the collection.
     *  
     * @param Backbone_Model|mixed $model
     * @param array $options
     */
    public function unshift($model, array $options = array()) {
        $model = $this->_prepareModel($model, $options);
        
        $this->add($model, array_merge(array('at'=>0), $options));
        
        return $model;
    }
    
    /**
     * Remove the model at the beginning of the collection and return it.
     * @param array $options
     * @return Backbone_Model|false The removed model, or false if the collection was empty.
     */
    public function shift(array $options = array()) {
        $model = reset($this->models);
        
        if ($model) $this->remove($model, $options);
        
        return $model;
    }
    
    /**
     * When you have more items than you want to add or remove individually, 
     * you can reset the entire set with a new list of models, without firing 
     * any add or remove events. Fires 'reset' when finished, unless the 'silent'
     * option is set.
     * 
     * If $models is not given, the collection will be emptied.
     * 
     * Options:
     *  - 'silent' : If set, don't trigger the 'reset' event.
     *   
     * @param array|mixed $models
     * @param array $options
     * @return Backbone_Model_Collection provides a fluent interface
     */
    public function reset($models = array(), array $options = array()) {
        if (is_array($models)) reset($models);
        if (!is_array($models) or $models and !is_numeric(key($models))) $models = array($models);
        
        //Ensure that no references to this collection from the existing models remain
        foreach($this->models as $model) $this->_removeReference($model);
        $this->_reset();
        $this->add($models, array_merge(array('silent'=>true), $options));

        if (empty($options['silent'])) $this->trigger('reset', $this, $options);
        return $this;
    }
    
    /**
     * Force the collection to re-sort itself. You don't need to call this under
     * normal circumstances, as the set will maintain sort order as each item
     * is added.
     */
    public function sort(array $options = array()) {
        if (!$this->comparator) throw new LogicException("Cannot sort a set without a comparator");
        if ($this->comparator_type == 'iterator') $this->sortBy($this->comparator);
        else usort($this->models, $this->comparator);
    }
    
    /*************************************************************************\
     * Collection access
    \*************************************************************************/
    
    /**
     * Slice out a sub-array of models from the collection
     * @param integer $begin The start index of the slice
     * @param integer $end The end index of the slice, up to but not including.
     * @return array Array of Backbone_Model objects
     */
    public function slice($begin, $end) {
        return array_slice($this->models, $begin, $end - $begin);        
    }
    
    /**
     * Get a model from the set by id.
     * If the model is not present, returns null.
     * @param Backbone_Model|string $id 
     * @return Backbone_Model
     */
    public function get($id) {
        if ($id === null) return null;
        if ($id instanceof Backbone_Model) $id = $id->id();
        return isset($this->_byId[$id]) ? $this->_byId[$id] : null;
    }

    /**
     * Get a model from the set by id.
     * If the model is not present, returns null.
     * @param Backbone_Model|string $id 
     * @return Backbone_Model
     */
    public function getByCid($cid) {
        if ($cid instanceof Backbone_Model) $cid = $cid->cid();
        return isset($this->_byCid[$cid]) ? $this->_byCid[$cid] : null;
    }
    
    /**
     * Get the model at the given index
     * @param integer $index
     * @return Backbone_Model
     */
    public function at($index) {
        return $this->models[$index];
    }
    
    /**
     * Given a model, return its index in the collection
     * @param Backbone_Model $model
     * @return int
     */
    public function indexOf(Backbone_Model $model) {
        if (!isset($this->_byCid[$model->cid()])) return null;
        else foreach($this->models as $i=>$_model) if ($model === $_model) return $i;
        throw new LogicException("Illegal state detected - in _byCid, should be in models");
    }
    
    
    /**
     * Return models with matching attributes. Useful for simple cases of filter().
     * If no attributes are specified, return an empty array.
     * 
     * @param array $attrs A map of attr=>value pairs.
     * @return array of Backbone_Model objects, keyed by id 
     */
    public function where(array $attrs) {
        if (!$attrs) return array();
        return array_filter(
            $this->models,
            function(Backbone_Model $model) use ($attrs) {
                foreach($attrs as $attr=>$value)
                    if ($model->get($attr) != $value) 
                        return false;
                return true;
            }
        );
    }
    
    /**
     * Pluck and return an attribute from each model in the collection.
     * Models missing that attribute will still be included, with value null.
     * 
     * @param string $attr
     * @return array An array of those values.
     */
    public function pluck($attr) {
        return array_map(function($model) use ($attr) { return $model->get($attr); }, $this->models);
    }
    
    /*************************************************************************\
     * Fetch / Create
    \*************************************************************************/
    
    /**
     * Fetch the set of models for this collection, resetting the 
     * collection when they are read.
     * 
     * Options:
     *  - 'sync' : Override the collection's sync function for this call 
     *  - 'add' : If set and true, appends the models to the collection instead of resetting
     *  - 'success' : If set, calls this function if the collection is successfully fetched
     *  - 'error' : If set, calls this function if an error occurs instead of triggering an error
     *  - 'parse' : Should the incoming models be parsed? Default to 'true'.
     *  
     *  (other options are passed through to the sync mechanism, and then to add/reset depending on 'add')
     *  @var array $options
     *  @return Backbone_Model_Collection implements a fluent interface, or returns false on failure.
     */
    public function fetch(array $options = array()) {
        $sync = !empty($options['sync']) ? $options['sync'] : $this->sync();
        if (!$sync) throw new BadFunctionCallException("No sync callback found, cannot fetch.");
        $on_success = !empty($options['success']) ? $this->parseCallback($options['success']) : false;
        $on_error = !empty($options['error']) ? $this->parseCallback($options['error']) : false;
        $was_success = false;
        $was_error = false;
        $collection = $this;
        
        if (!isset($options['parse'])) $options['parse'] = true;
        $options['success'] = function($_collection=null, $response=null, $_options=null) use ($collection, &$options, $on_success, &$was_success) {
            $models = $options['parse'] ? $collection->parse($response) : $response;
            
            //Set the collection
            if (!empty($options['add'])) 
                $collection->add($models, $options);
            else
                $collection->reset($models, $options);
            
            //Signal that the operation was successful
            if ($on_success) call_user_func($on_success, $collection, $response, $options);
            $collection->trigger('sync', $collection, $response, $options);
            $was_success = true; 
        };
        $options['error'] = function($_collection=null, $response=null, $_options=null) use ($collection, &$options, $on_error, &$was_error) {
            //Signal that the operation was unsuccessful
            if ($on_error) call_user_func($on_error, $collection, $response, $options); 
            else $collection->trigger('error', $collection, $response, $options);
            $was_error = true;
        };
        
        call_user_func($sync, 'read', $this, $options);
        if (!empty($options['async'])) return $this;
        elseif ($was_success != $was_error) return $was_success ? $this : false;
        else throw new LogicException("Sync function did not invoke callbacks correctly");
    }
    
    /**
     * Create a new instance of a model in this collection. Add the model to the 
     * collection immediately, unless wait: true.
     * 
     * Options:
     *  - 'wait' : If set, don't add the model to the collection *until* it saves successfully.
     *  - 'success' : If set, calls this function if the model is successfully created
     *  - 'error' : If set, calls this function if an error occurs instead of triggering an error  
     * (other options are passed through to prepare, the model's save method, the sync mechanism, and to add)
     * 
     * @param mixed $model
     * @param array $options
     * @return Backbone_Model
     */
    public function create($model, array $options=array()) {
        $collection = $this;
        $model = $this->_prepareModel($model, $options);
        if (!$model) return false; // Can't create an invalid model
        
        $waiting = !empty($options['wait']);
        if (!$waiting) $this->add($model, $options);
        $on_success = !empty($options['success']) ? $options['success'] : false;
        $options['success'] = function($model, $response, $_options) use ($collection, $options, $on_success, $waiting) {
            if ($waiting) $collection->add($model, $options);
            if ($on_success) call_user_func($on_success, $model, $response, $options);
        };
        $model->save(null, $options);
        return $model;
    }
        
    /*************************************************************************\
     * Methods for implementing PHP interfaces
    \*************************************************************************/

    //Iterator
    public function current() { return current($this->models); }
    public function next() { next($this->models); }
    public function key() { return key($this->models); }
    public function valid() { return (bool)current($this->models); }
    public function rewind() { reset($this->models); }

    //Countable
    public function count() { return $this->length; }

    /*************************************************************************\
     * Useful Underscore.js functions
    \*************************************************************************/
 
    /**
     * Return the first model
     * @param integer $n If given, return the first N models
     * @return Backbone_Model or array of Backbone_Models
     */
    public function first($n = 1) { return $n==1 ? reset($this->models) : array_slice($this->models, 0, $n); }
    
    /**
     * Return the last model
     * @param integer $n If given, return the last N models
     * @return Backbone_Model or array of Backbone_Models
     */
    public function last($n = 1) { return $n==1 ? end($this->models) : array_slice($this->models, -$n); }
    
	/**
     * Return all but the first model
     * @param integer $n If given, return all but the first N models 
     */
    public function rest($n = 1) { return array_slice($this->models, $n); }
    
    /**
     * Filter the collection by a given callback function
	 *
	 * The callback function takes one argument - a Backbone_Model object - and returns 
	 * true if that model should be part of the set, false if not.
	 * 
     * @param callable $callback
     * @return array of Backbone_Model objects, keyed by id
     */
    public function filter($callback) { return array_filter($this->models, $callback); }

    /**
     * Sort the collection by the return values of the iterator
     * @param callable $iterator
     */
    public function sortBy($iterator) {
        //Build and sort a map of i => iterator(model) 
        $sorted = array();
        foreach($this->models as $i=>$model) $sorted[$i] = call_user_func($iterator, $model);
        asort($sorted);
        //Rebuild the models array in the order of the above map
        $models = array();
        foreach($sorted as $i=>$j) $models[] = $this->models[$i];
        $this->models = $models; 
    }
    
    /**
     * Produces a new array of values by wrapping each model in list through an iterator.
     * @param callable $iterator
     * @return array
     */
    public function map($iterator) { return array_map($iterator, $this->models); }
    
    /**
     * Return true if any model passes the iterator truth test
     * @param callable $iterator
     * @return bool
     */
    public function any($iterator) { foreach($this->models as $model) { if ($iterator($model)) return true; } return false; }
    
    /**
     * Return true if all models pass the iterator truth test
     * @param callable $iterator
     * @return bool
     */
    public function all($iterator) { foreach($this->models as $model) { if (!$iterator($model)) return false; } return true; }
    
    /**
     * Return whether the collection is empty
     * @return bool
     */
    public function isEmpty() { return !$this->length; }
    
    /**
     * Return the models in the collection excluding any passed models
     * @param Backbone_Model|array $model A Backbone_Model, or an array of Backbone_Models 
     * @return array
     */
    public function without($models) { if (!is_array($models)) { $models = array($models); } return array_diff($this->models, $models); }
    
    /**
     * Return the model with the highest id. If iterator is given, return the model with the highest iterator return value.
     * @param iterator   
     * @return Backbone_Model
     */
    public function max($iterator=null) {
        if (!$this->length) return null;
        if (!$iterator) $iterator = function($m){ return $m->id(); };
        $max = $iterator( $max_model = reset($this->models ));
        while((bool)($m = next($this->models))) if ( ($_max=$iterator($m)) > $max) { $max_model = $m; $max = $_max; }
        return $max_model;
    }
    
    /**
     * Return the model with the lowest id. If iterator is given, return the model with the lowest iterator return value.
     * @param iterator
     * @return Backbone_Model    
     */
    public function min($iterator=null) {
        if (!$this->length) return null;
        if (!$iterator) $iterator = function($m){ return $m->id(); };
        $min = $iterator( $min_model = reset($this->models ));
        while((bool)($m = next($this->models))) if ( ($_min=$iterator($m)) < $min) { $min_model = $m; $min = $_min; }
        return $min_model;
    }
        
    //TODO: Implement more as needed.
    
    /*************************************************************************\
     * Internal utility functions
    \*************************************************************************/
    

    /**
     * Prepare a model or hash of attributes to be added to this collection.
     * 
	 * Options are passed as-is through to the model constructor. ('collection' is overriden)
     * @param mixed $model
     * @param array $options
     * @return Backbone_Model|false The model instance on success, false on failure.
     */
    protected function _prepareModel($model, array $options=array()) {
        //Model; set the collection if not already set, return.
        if ($model instanceof Backbone_Model) {
            if (!$model->collection()) $model->collection($this);
            return $model;
        }
        //Otherwise, try and construct one
        $attrs = $model;
        $name_or_prototype = $this->model();
        if (is_string($name_or_prototype))
            $model = new $name_or_prototype($attrs, array_merge($options,array('collection'=>$this))); /* @var $model Backbone_Model */
        else {
            $model = clone $name_or_prototype;
            $model->collection($this);
            $this->set($attrs, array('silent'=>true));
        }
        if (!$model->_validate($model->attributes(), $options)) return false;
        
        return $model;
    }
    
    /**
     * Remove any internal references to this collection from the given model 
     * @param Backbone_Model $model
     */
    protected function _removeReference(Backbone_Model $model) {
        if ($this == $model->collection()) 
            $model->collection(null);
        $model->off('all', array($this, '_onModelEvent'));
    }
    
    /**
     * Internal method called every time a model in the set fires an event.
     * Sets need to update their indexes when models change ids. All other
     * events simply proxy through. "add" and "remove" events that originate
     * in other collections are ignored.
     */
    public function _onModelEvent($event, $model=null, $collection=null, $options=null) {
        //Ignore add/remove events from other collections
        if (($event=='add' or $event=='remove') and $collection != $this) 
            return;
        //Destroyed models are removed
        if ($event=='destroy')
            $this->remove($model, $options);
        //If a model id changes, update the id index
        if ($model and $event == 'change:'.$model->idAttribute()) {
            unset($this->_byId[$model->previous($model->idAttribute())]);
            if ($model->id() !== null) $this->_byId[$model->id()] = $model;
        }
        //Pass event through to listeners on this collection
        $this->trigger($event, $model, $collection, $options);
    }
    
    /*************************************************************************\
     * Export functions
    \*************************************************************************/
    
    /**
     * The javascript name for the class, if set
     * By default will use the PHP class name.
     * @var string
     */
    protected static $exported_classname = null;
        
    /**
     * Javascript attributes that belong in the class definition.
     * 
     * @var array Map of 'name' => 'function(args) { stuff }'
     */
    protected static $exported_functions = array();
    
    /**
     * Static attributes that need to be exported to the class definition
     * @var array List of attribute names
     */
    protected static $exported_fields = array('url');

    /**
     * Return the JSON represention of the collection.
     * Delegates to each model's `toJSON` method.
     * @return string the JSON-encoded representation of the collection
     */
    public function toJSON() {
        return '['.implode(',', array_map(function($model) { return $model->toJSON(); }, $this->models)).']';
    }
    
    /**
     * @return string
     */
    public function __toString() {
        return $this->toJSON();
    }
    
    /**
     * Fetch the javascript class this object is an instance of
     */
    public static function getExportedClassName() {
        $classname = get_called_class();
        if ($classname == 'Backbone_Collection') return 'Backbone.Collection';
        elseif (static::$exported_classname) return static::$exported_classname;
        else return $classname;
    }

    /**
     * Export the collection instance for sending to the client's collection fetch call.
     * Can be overriden to filter out models that shouldn't be shown to clients.
     * If specific attributes shouldn't be shown, it would be better to override the model exportObject method
     * 
     * The default implementation simply delegates to 'toJSON'
     * 
     * @param array $options Any options the method might need, eg the authenticated user 
     * @see Backbone_Model::exportObject
     * @see Backbone_Collection::toJSON
     * @see Backbone_Router_Model::index
     * @return string A JSON-encoded array of exported Model objects.
     */
    public function export(array $options = array()) {
        return $this->toJSON();
    }
    
    /**
     * Export this model's class definition in javascript form
     * Assumes that Backbone_Model corresponds to Backbone.Model, and
     * won't export the top-level class.   
     * eg 'var MyClass = Backbone.Model.extend({ ... });
     */
    public static function exportClass() {
        $_class = get_called_class();
        $_parent = get_parent_class($_class);
        if ($_class == 'Backbone_Collection') throw new LogicException("Can't export Backbone_Collection, only subclasses");
        
        $class = $_class::getExportedClassName();
        $parent = $_parent::getExportedClassName();
        
        $reflector = new ReflectionClass($class);
        $class_values = $reflector->getDefaultProperties();
        
        $members = array();
        $members[] = "model: ".$class_values['model'];
        foreach(static::$exported_fields as $field) $members[] = "$field: ".(isset($class_values[$field])? json_encode($class_values[$field]) : 'null');
        foreach(static::$exported_functions as $name=>$func) $members[] = "$name: $func";

        return "var $class = $parent.extend({\n\t".implode(",\n\t",$members)."\n})";
    }
}
