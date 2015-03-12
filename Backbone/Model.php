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
 * Backbone_Model
 * PHP Implementation of Backbone.js's Backbone.Model
 *
 * @author Patrick Barnes
 *
 */    
class Backbone_Model extends Backbone_Events
{
    /*************************************************************************\
     * Attributes and Accessors
    \*************************************************************************/
    
    /**
     * A map of the variables that have changed
     * @var array
     */
    protected $changed = array();
   
    /** 
     * A hash of attributes that have silently changed since the last time
     * `change` was called.  Will become pending attributes on the next call.
     * @var array
     */
    protected $_silent = array();

    /**
     * A hash of attributes that have changed since the last 'change' event began.
     */
    protected $_pending = array();
    
    /**
     * A map of the previous values of attributes
     * @var array
     */
    protected $_previousAttributes = array();

    /**
     * A map of cached escaped attributes 
     * @var array
     */
    protected $_escapedAttributes = array();
    
    /**
     * A flag to indicate that the model is in the middle of a change() operation
     * @var bool
     */
    protected $_changing = false;
    
    /**
     * The default name for the JSON `id` attribute is `"id"`. 
     * MongoDB and CouchDB users may want to set this to '_id'
     * 
     * Multi-field ids (like mysql compound keys) are not supported. 
     * @var string
     */
    protected $idAttribute = 'id';
    
    /**
     * Default attribute values map. 
     * @var array
     */
    protected $defaults = array();
    
    /**
     * Default attribute values map.
     * Override this function if you need to dynamically calculate the model defaults.
     * @return array
     */
    protected function defaults() { return $this->defaults; }
    
    /**
     * The id of the model. Corresponds to the attribute with the name $idAttribute
     * @var mixed
     */
    protected $id = null;

    /**
     * The id of the model. Corresponds to the attribute with the name $idAttribute
     * - id() : Get the id
     * - id($id) : Set the id - on failure return false.
     * @param mixed 
     * @return mixed
     */
    public function id($id=null) {
        if (func_num_args()) return $this->set($this->idAttribute(), $id) ? $this->id : false;
        else return $this->id; 
    }
    
    /**
     * The client id of the model. This identifies the model before it has been saved.
     * (Unique only for the duration of execution)  
     */
    protected $cid = null;
    
    /**
     * The client id of the model. This identifies the model before it has been saved.
     * (Unique only for the duration of execution)  
     */
    public function cid() { return $this->cid; }
    
    /**
     * A map of the model's attributes.
     * @var array
     */
    protected $attributes = array();
    
    /**
     * A map of the model's attributes.
     * @var array
     */
    public function attributes() { return $this->attributes; }

    /**
     * The collection reference, if the model is attached to one
     * @var Backbone_Collection
     */
    protected $collection = null;
    
    /**
     * The collection reference, if the model is attached to one
     * $model->collection() : Get the collection
     * $model->collection($collection) : Set the collection
     * @var Backbone_Collection $collection
     */
    public function collection(Backbone_Collection $collection=null) {
        if (func_num_args()) $this->collection = $collection;
        return $this->collection;
    }
    
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
     * If the collection it belongs to has a sync function, returns it.
     * Otherwise, returns the Backbone default sync
     * 
     * DIFFERS FROM Backbone.js - sync() is the function accessor, not the function itself. 
     * 
     * The gateway can be an implementation of Backbone_Sync_Interface 
     * or a function of signature; function($method, $model, $options)
     * 
     * @param Backbone_Sync_Interface|callable $gateway
     * @return Backbone_Sync_Interface The effective sync fuction
     */
    public function sync($sync=null) {
        if (func_num_args()) {
            if ($sync and !(is_callable($sync) || is_a($sync, 'Backbone_Sync_Interface'))) throw new InvalidArgumentException('Cannot set $sync - invalid');            
            $this->sync = $sync;
        }
<<<<<<< HEAD
        
        return $this->sync ? $this->sync 
             : ( ($this->collection && $this->collection->sync()) ? $this->collection->sync() 
             : Backbone::getDefaultSync(get_class($this)));
=======
        return $this->sync ? $this->sync : ($this->collection ? $this->collection->sync() : Backbone::getDefaultSync(get_class($this)));
>>>>>>> 857c8d5996fdee227c4d22994ba4e543bd7d4d40
    }
    
    /**
     * Define where the model comes from
     * Override to set a default urlRoot for that Model type
     * @var url
     */
    protected static $urlRoot = null;
    
    /**
     * An instance version of urlRoot, to allow urlRoot($x) to set 
     * a custom urlRoot in the object without modifying the class member 
     * @var url
     */
    protected $_urlRoot = null;
    
    /**
     * Define where the model comes from
     * $model->urlRoot() : Get the urlRoot
     * $model->urlRoot($urlRoot) : Set the urlRoot
     * @param string $urlRoot 
     * @return string The urlRoot, or null.
     */
    public function urlRoot($urlRoot = null) {
        if (func_num_args()) $this->_urlRoot = $urlRoot;
        return $this->_urlRoot ? $this->_urlRoot : static::$urlRoot;
    }
    
    /**
     * If set, the model url.
     * @see Backbone_Model::url()
     * @var string
     */
    protected $_url = null;

    /**
     * What is the server's URL for this model?
     *
     * If a URL is set on the model, return it. Otherwise, conform
     * to the Backbone.js implementation;
     *  - if part of a collection, is [collection.url()]/id
     *  - if not part of a collection, is [urlRoot]/id
     *  - if isNew, omits the /id part.
     *
     * Override (and provide a javascript equivalent method) if a different convention is required.
     *
     * Get: $model->url();
     * Set: $model->url($url);
     * @param url string
     * @return string
     * @throws LogicException if url is not set and not part of a collection and rootUrl is not defined.
     */
    public function url($url = null) {
        if (func_num_args()) $this->_url = $url;
    
        if ($this->_url) return $this->_url;
        else {
            $base = $this->collection ? $this->collection->url() : $this->urlRoot();
            if (!$base and !is_string($base)) throw new LogicException("Model not part of a collection and urlRoot not defined, can't calculate model url!");
    
            if ($this->isNew())
                return $base;
            else
                return rtrim($base, '/').'/'.rawurlencode($this->id); //almost the same as javascript's encodeUriComponent, except for . ! ~ * ' ( )
        }
    }
   
    /*************************************************************************\
     * Construction/initialisation
    \*************************************************************************/

    /**
     * Build, fetch, and return an instance of the model with the given id
     * Options will be passed to the constructor (and initialize) and to fetch()
     * @param scalar $id
     * @param array $options
     * @return Backbone_Model|false The fetched model, or false on failure.
     * @see Backbone_Model::__construct
     * @see Backbone_Model::fetch 
     */
    public static function factory($id, array $options=array()) {
        $class = get_called_class();
        $model = new $class($id, $options); /* @var $model Backbone_Model */
        return $model->fetch($options);
    }
    
    /**
     * Create a new model, with the defined attributes and options
     * 
     * Options:
     *  - 'collection' : Store a reference to that collection 
     *  - 'sync' : Store a reference to that sync function
     *  - 'parse' : If true, runs $attrs through parse() before setting
     *    
     * @param array|string|mixed $attrs An array of attributes, an id, any parsable value if the 'parse' option is set, or empty for a default object.
     * @param array $options Any options to set on construction, or empty.
     */
    public function __construct($attrs=array(), array $options=array()) 
    {
        parent::__construct($options);
        
        //Assign cid
        $this->cid = Backbone::uniqueId();

        //Set collection or gateway, if given
        if (!empty($options['collection'])) $this->collection($options['collection']);
        if (!empty($options['sync'])) $this->sync($options['sync']);
        
        //Set url or urlRoot, if given
        if (!empty($options['url'])) $this->url($options['url']);
        if (!empty($options['urlRoot'])) $this->urlRoot($options['urlRoot']);
        
        //Parse and set 
        if (!empty($options['parse'])) 
            $attrs = $this->parse($attrs);
        else if (is_string($attrs))
            $attrs = array( $this->idAttribute() => $attrs );
        else if (is_object($attrs)) 
            $attrs = (array)$attrs;
        if ($attrs and !is_array($attrs)) 
            throw new InvalidArgumentException('$attrs must be an array or parsable into an array, '.gettype($attrs).' given.');
        $attrs = $attrs ? array_merge(static::defaults(), $attrs) : static::defaults();

        $this->set($attrs, array('silent'=>true));
        
        //Reset change tracking
        $this->changed = array();
        $this->_silent = array();
        $this->_pending = array();
        $this->_previousAttributes = $this->attributes; //copies - is an array

        $this->initialize($attrs, $options);
    }

    /**
     * Initialize is an empty function by default. Override it with your own initialization logic.
     * (You may also need to define an 'initialize' javascript method)
     *   
     * @param mixed $attrs The attributes/values passed on construction
     * @param array $options The options passed on construction
     */
    protected function initialize($attrs, array $options) 
    {
    }
    
    /**
     * Once an object is cloned, fix aspects that don't belong to the new model   
     * (Uses php's clone keyword)
     * __clone may not do what you think, make sure you read: http://php.net/manual/en/language.oop5.cloning.php
     * 
     */
    public function __clone() {
       $this->collection = null; //The clone isn't on the same collection
       $this->cid = Backbone::uniqueId(); //The clone has a different cid
       $this->off();             //Remove any event callbacks 
    }
    
    /**
     * Convert an input value into an array of attributes to pass to the set() method.
     * By default, passes arrays as-is, and assumes that strings are json_encoded attributes.
     * 
     * @param mixed $in
     * @return array
     */
    public function parse($in) 
    {
        return is_string($in) ? json_decode($in, true) : $in;
    }

    /*************************************************************************\
     * Attribute access/modification
    \*************************************************************************/

    /**
     * Get the value of an attribute
     * If the field does not exist, returns NULL. 
     * @param string $attr
     * @return mixed|null
     */
    public function get($attr) { return isset($this->attributes[$attr]) ? $this->attributes[$attr] : null; }
    
    /**
     * 'Magic' equivalent of get()
     * @param string $attr
     * @return mixed|null
     * @see get
     */
    public function __get($attr) { return isset($this->attributes[$attr]) ? $this->attributes[$attr] : null; }
    
    /**
     * Get the HTML-escaed value of an attribute
     * @param string $attr
     */
    public function escape($attr) {
        if (isset($this->_escapedAttributes[$attr])) return $this->_escapedAttributes[$attr];
        $val = $this->get($attr);
        return $this->_escapedAttributes[$attr] = htmlspecialchars( $val ? $val : '' );
    }
    
    /**
     * Get a number of attributes
     * Can be called as pick('attr', 'attr', 'attr')
     * or as pick( array('attr','attr','attr')  ) 
     * 
     * @param string|array $attr....
     * @return array Associative array of attributes
     */
    public function pick() {
        $attrs = array();
        foreach(func_get_args() as $attr) {
            if (is_string($attr)) $attrs[$attr] = $this->get($attr);
            else foreach($attr as $a) $attrs[$a] = $this->get($a); 
        };
        return $attrs;
    }

    /**
     * Returns true if the attribute contains a value that is not null
     * @param string $attr
     * @return bool
     */
    public function has($attr) { return isset($this->attributes[$attr]); }
    
    /**
     * 'Magic' equivalent of has()
     * @param string $attr
     * @return bool
     * @see has 
     */
    public function __isset($attr) { return isset($this->attributes[$attr]); }
    
    /**
     * Set a model attribute or attributes on the object, firing change() unless silenced.
     * 
     * Can be called as:
     *  set('key', $value, [$options])
     *  set(array('key'=>$value, 'key2'=>$value2), [$options])
     *  set($model, [$options]) //Where $model is an instance of Backbone_Model 
     *  
     * Options:
     *  - 'unset' : Remove any given attributes. (ignoring the actual values)
     *  - 'silent' : Do not validate or fire any change() event
     *  - 'error' : Callback if the attributes are not valid: function($model, $errors, $options). If omitted, instead calls trigger('error', $model, $errors, $options);  
     *  Options is passed through to _validate() and to change()
     * 
     * @param string|array $key Either the attribute name, or an array/object of name/value pairs 
     * @param mixed $value If $key is a name, the new value of that attribute. If $key is an array, the options to pass to the function
     * @param array $options If $key is a name, the options to pass to the function.
     * @return Backbone_Model|false provides a fluent interface, or false on failure
     */
    public function set($key, $value=null, $options=array()) 
    {
        //Which form of the function is being used? 'key',$value or array('key'=>$value)
        if ($key and is_scalar($key)) {
            $attrs = array($key=>$value); 
        } else {
            $attrs = $key;
            if ($attrs instanceof Backbone_Model) $attrs = $attrs->attributes(); //Model - get the attributes instead
            $options = $value ? $value : array();
        }
        
        //If no attributes to set - short-circuit return
        if (!$attrs or (is_object($attrs) and !get_object_vars($attrs))) return $this;
        
        $unsetting = !empty($options['unset']);
        $is_silent = !empty($options['silent']); 
        if ($unsetting) foreach($attrs as &$val) $val = null;
        
        //Run validation
        if (!$this->_validate($attrs, $options)) return false;
        
        //Check for changes of id
        if (array_key_exists($this->idAttribute(), $attrs)) {
            $this->id = $attrs[$this->idAttribute()];   
        }

        $options['changes'] = array();
        
        //For each 'set' attribute
        foreach($attrs as $attr=>$val) {
            //If the new and current value differ, record the change
            // Differing : Setting and doesn't exist, unsetting and exists, or is different 
            $exists = array_key_exists($attr, $this->attributes);
            if ( (!$unsetting and !$exists) or ($unsetting and $exists) or ($exists and $val !== $this->attributes[$attr]) ) {
                unset($this->_escapedAttributes[$attr]);
                if ($is_silent) $this->_silent[$attr] = true;
                else $options['changes'][$attr] = true;
            }
            
            //Update or delete the current value
            if ($unsetting) unset($this->attributes[$attr]);
            else $this->attributes[$attr] = $val;
            
            //If the new and _previous_ value differ, record the change. If not,
            //then remove changes for this attribute.
            // Differing : Setting and no previous, unsetting and previous, or different. 
            $had_previous = array_key_exists($attr, $this->_previousAttributes);
            if ((!$unsetting and !$had_previous) or ($unsetting and $had_previous) or ($had_previous and $val !== $this->_previousAttributes[$attr]) ) {
                $this->changed[$attr] = $val;
                if (!$is_silent) $this->_pending[$attr] = true;
            } else {
                unset($this->changed[$attr]);
                unset($this->_pending[$attr]);
            }
        }
        
        //Fire change()
        if (!$is_silent) $this->change($options);
        return $this;
    }
    
    /**
     * 'Magic' version of set(), for the simple set('key', $value) case.
     * @param string $attr
     * @param mixed $value
     * @return Backbone_Model|false provides a fluent interface, or false on failure
     * @see set 
     */
    public function __set($attr, $value) { return $this->set($attr, $value); }
    
    /**
     * Remove an attribute from the model.
     * (Uses php's unset keyword)
     * @param string $attr
     * @return Backbone_Model|false provides a fluent interface, or false on failure
     */
    public function __unset($attr) { return $this->set($attr, null, array('unset'=>true)); }

    /**
     * Remove an attribute from the model.
     * Allows custom options to be specified
     * @param string $attr
     * @param array $options
     * @return Backbone_Model|false provides a fluent interface, or false on failure
     */
    public function _unset($attr, array $options=array()) { return $this->set($attr, null, array_merge($options, array('unset'=>true))); }
    
    /**
     * Remove all attributes from the model.
     * @param array $options
     * @return Backbone_Model|false provides a fluent interface, or false on failure
     */
    public function clear(array $options=array()) { return $this->set($this->attributes, array_merge($options, array('unset'=>true))); }
   
    /*************************************************************************\
     * Validation / Inspection
    \*************************************************************************/
    
    /**
     * A model is new if it lacks an id.
     * @return bool
     */
    public function isNew() 
    {
        return $this->id === null;
    }
    
    /**
     * Get the attribute being used as the id
     */
    public function idAttribute() {
        return $this->idAttribute;   
    }
    
    /**
     * Check if the model is currently in a valid state.
     * It should normally only be possible to get into an invalid state if attributes
     * are changed using other than set(), or the validation mechanism / environment
     * changes.
     * 
     */
    public function isValid(array $options = array()) 
    {
        $errors = $this->validate($this->attributes, $options);
        
        //If no errors, valid!
        if (!$errors) return true;
<<<<<<< HEAD
=======
        
        //If an error callback has been registered, pass to it the errors 
        if (!empty($options['error']))
            call_user_func($options['error'], $this, $errors, $options);
            
        return false;
    }
    
    /**
     * What is the server's URL for this model?
     * Conforms to the Backbone.js implementation; 
     *  - if part of a collection, is [collection.url()]/id
     *  - if not part of a collection, is [urlRoot]/id
     *  - if isNew, omits the /id part.
     *  
     * Override (and provide a javascript equivalent method) if a different convention is required.
     * 
     * @return string
     * @throws LogicException if not part of a collection and rootUrl is not defined.
     */
    public function url() {
        $base = $this->collection ? $this->collection->url() : $this->urlRoot();
        if (!$base and !is_string($base)) throw new LogicException("Model not part of a collection and urlRoot not defined, can't calculate model url!");
>>>>>>> 857c8d5996fdee227c4d22994ba4e543bd7d4d40
        
        //If an error callback has been registered, pass to it the errors 
        if (!empty($options['error']))
            call_user_func($options['error'], $this, $errors, $options);
            
        return false;
    }
    
    /**
     * Validate the given attributes, and return any errors.
     * If no errors are found, the response will be empty.
     * (This may seem backwards, but matches the Backbone.js approach)
     * 
     * If called directly, does not trigger any error event or callback on error. 
     *  
     * @param array $attrs
     * @param array $options
     * @return array empty if no errors exist, error message(s) if errors exist
     */
    public function validate(array $attrs, array $options=array()) {
    }
    
    /**
     * Validate the given attributes according to the validate() function, and report/trigger any errors.
     * 
     * Options:
     *  - 'error' : Callback if the attributes are not valid: function($model, $errors, $options). If omitted, instead calls trigger('error', $model, $errors, $options);
     *  - 'silent' : Don't validate, just allow.
     * 
     * @param array $attrs
     * @param array $options
     * @return array true if no errors, false if any errors found.
     */
    public function _validate(array $attrs, array $options) {
        //Skip validation?
        if (!empty($options['silent'])) return true;
        
        //Merge the new attributes over the existing ones
        $attrs = array_merge($this->attributes, $attrs);
        
        $errors = $this->validate($attrs, $options);
        if (!$errors) return true;
        
        //If errors occur, report them
        if (!empty($options['error']))
            call_user_func($options['error'], $this, $errors, $options);
        else {
            $this->trigger('error', $this, $errors, $options); 
        }
        return false;
    }
    
    /**
     * Call this method to manually trigger a 'change' event on the model.
     * and a 'change:attribute' event for each changed attribute.
     * Calling this will cause all handlers observing the model to update.
     * 
     * Event signatures: 
     *  "change:$attr" : function($model, $value, $options)
     *  "change" : function($model, $options)
     * 
     * Options:
     *  - 'changes' : A map of fields to manually trigger changes on, in addition to detected changes. 
     * 
     * @param array $options
     * @return Backbone_Model provides a fluent interface 
     */
    public function change(array $options = array()) {
        $changing = $this->_changing;
        $this->_changing = true;
        
        //Silent changes become pending changes
        foreach($this->_silent as $attr=>$j) $this->_pending[$attr] = true;
        
        //Silent changes are triggered
        $changes = empty($options['changes']) ? $this->_silent : array_merge($options['changes'], $this->_silent);
        $this->_silent = array();
        foreach($changes as $attr=>$j) {
            $this->trigger('change:'.$attr, $this, $this->$attr, $options);
        }
        if ($changing) return $this;
        
        //Continue firing 'change' events while there are pending changes
        while(!empty($this->_pending)) {
            $this->_pending = array();
            $this->trigger('change', $this, $options);
            //Pending and silent changes still remain
            foreach($this->changed as $attr=>$j) {
                if (!empty($this->_pending[$attr]) || !empty($this->_silent[$attr])) continue;
                unset($this->changed[$attr]);
            }
            $this->_previousAttributes = $this->attributes;
        } 
        
        $this->_changing = false;
        return $this;
    }
    
    /**
     * Determine if the model has changed since the last 'change' event.
     * @param string $attr Optionally, determine if only that attribute has changed.
     * @return bool 
     */
    public function hasChanged($attr = null) {
        if ($attr !== null) return array_key_exists($attr, $this->changed);
        else return !empty($this->changed);
    }
    
    /**
     * Return an object containing all the attributes that have changed, or 
     * false if there are no changed attributes. Useful for determining what
     * parts of a view need to be updated and/or what attributes need to be 
     * persisted to storage. Unset attributes will be set to null.
     * You can also pass an attributes object to diff against the model,
     * determining if there *would be* a change.
     * @param array $diff Optionally, a set of attributes to generate a change against.
     * @return array A map of changed attributes
     */
    public function changedAttributes(array $diff = null) {
        if (!$diff) return !empty($this->changed) ? $this->changed : false;
        $changed = array();
        foreach($diff as $attr=>$val) {
            if (array_key_exists($attr, $this->_previousAttributes) and $val === $this->_previousAttributes[$attr]) continue;
            $changed[$attr] = $val;
        } 
        return empty($changed) ? false : $changed; 
    }
    
    /**
     * Get the previous value of an attribute, recorded at the time the last 
     * 'change' event was fired.
     * @param string $attr
     * @return mixed|null The previous value of the attribute, or null if one does not exist. 
     */
    public function previous($attr) {
        if (!array_key_exists($attr, $this->_previousAttributes)) return null;
        return $this->_previousAttributes[$attr];
    }
    
    /**
     * Get all of the attributes of the model at the time of the previous
     * 'change' event.
     * @return array A map of the previous attributes 
     */
    public function previousAttributes() {
        return $this->_previousAttributes;
    }
    
    
    /*************************************************************************\
     * Fetch / Save / Destroy
    \*************************************************************************/
    
    /**
     * Fetch the model from its source. If the source's representation of the 
     * model differs from its current attributes, they will be overwritten,
     * triggering a "change" event.
     * 
     * Options:
     * - 'sync' : Override the model's sync function for this call
     * - 'success' : Callback on successfully fetching and updating the model: function($model, $response, $options)
     * - 'error' : Callback on failing to fetch or update the model: function($model, $response, $options). If omitted, instead calls trigger('error', $model, $response, $options);
     * - 'async' : (PHP Only) Don't require the sync function to call 'success' or 'error' before returning, though it may not support async mode.
     * 
     * @param array $options
     * @return Backbone_Model|false provides a fluent interface, or false on failure
     */
    public function fetch( array $options=array() )
    {
        $sync = !empty($options['sync']) ? $options['sync'] : $this->sync();
        if (!$sync) throw new BadFunctionCallException("No sync callback found, cannot fetch.");
        $on_success = !empty($options['success']) ? $this->parseCallback($options['success']) : false;
        $on_error = !empty($options['error']) ? $this->parseCallback($options['error']) : false;
        $was_success = false;
        $was_error = false;
        $model = $this;
        
        $_options = $options;
        $_options['success'] = function($_model=null, $response=null, $_options=null) use ($model, &$options, $on_success, &$was_success, &$was_error) {
            //Set the attributes
            $serverAttrs = $model->parse($response);
<<<<<<< HEAD
            if (!$model->set($serverAttrs, array_merge($options, array('on_sync'=>true)))) { $was_error=true; return; } //if can't set, calls error            
=======
            if (!$model->set($serverAttrs, array_merge($options, array('on_sync'=>true)))) return; //if can't set, calls error            
>>>>>>> 857c8d5996fdee227c4d22994ba4e543bd7d4d40
            
            //Signal that the operation was successful
            if ($on_success) call_user_func($on_success, $model, $response, $options);
            $model->trigger('fetch', $model, $response, $options);
            $model->trigger('sync', $model, $response, $options);
            $was_success = true; 
        };
        $_options['error'] = function($_model=null, $response=null, $_options=null) use ($model, &$options, $on_error, &$was_error) {
            //Signal that the operation was unsuccessful
            if ($on_error) call_user_func($on_error, $model, $response, $options); 
            else $model->trigger('error', $model, $response, $options);
            $was_error = true;
        };
        
        call_user_func($sync, 'read', $this, $_options);
        if (!empty($options['async'])) return $this;
        elseif ($was_success != $was_error) return $was_success ? $this : false;
        else throw new LogicException("Sync function did not invoke callbacks correctly");
    }
    
    /**
     * Optionally set a map of model attributes, and sync the model to storage.
     * If sync returns attributes that differ, the model's state will be 'set'
     * again.
     *
     * Options:
     * - 'sync' : Override the model's sync function for this call
     * - 'wait' : Wait for the save to succeed before setting the new attributes on the model.
     * - 'success' : Callback on successfully fetching and updating the model: function($model, $response, $options)
     * - 'error' : Callback on failing to fetch or update the model: function($model, $response, $options). If omitted, instead calls trigger('error', $model, $response, $options);
     * - 'async' : (PHP Only) Don't require the sync function to call 'success' or 'error' before returning.
     * Options are additionally passed through to set, and to the success or error callbacks. 
     *
     * Can be called:
     * - save([null,$options]) : Save the model as-is.
     * - save('key', $value, [$options]) : update the given attribute, then save.
     * - save(array('key'=>$value, 'key2'=>$value, ...), [$options]) : update the given attributes, then save.
     * - save($model, [$options]) : copy the attributes out of the given model into this one, then save.
     * 
     * @param string|array $key Either the attribute name, or an array/object of name/value pairs, or blank
     * @param mixed $value If $key is a name, the new value of that attribute. If $key is an array or blank, the options to pass to the function.
     * @param array $options If $key is a name, the options to pass to the function.
     * @return Backbone_Model|false provides a fluent interface, or false on failure
     */
    public function save($key=null, $value=null, $options=array()) 
    {
        //Idiot check - it's save(null, options) not save(options)
        if (func_num_args() == 1 && is_array($key) && (isset($key['success']) || isset($key['error'])))
            throw new BadFunctionCallException("Bad call - suspect save was called incorrectly");
        
        //Handle both ('key', $value) and (array('key'=>$value)) calls.
        if (is_scalar($key)) {
            $attrs = array($key=>$value); 
        } else {
            $attrs = $key;
            if ($attrs instanceof Backbone_Model) $attrs = $attrs->attributes(); //Model - get the attributes instead
            $options = $value ? $value : array();
        }
        
        $sync = !empty($options['sync']) ? $options['sync'] : $this->sync();
        if (!$sync) throw new BadFunctionCallException("No sync callback found, cannot save.");
        $current = null; $silentOptions = null;
        $model = $this;
        
        $waiting = !empty($options['wait']);
        //If we're 'wait'-ing to set changed attributes until persisted, 
        //keep a copy of the old attributes for reverting
        if ($waiting) {
            $current = $this->attributes;
            $silentOptions = array_merge($options, array('silent'=>true));
            if (!$this->set($attrs, $silentOptions)) return false;
        }
        //Try to set attributes before persisting
        elseif ($attrs) {
            if (!$this->set($attrs, $options)) return false;
        }
        //No attrs to set - check model is valid. 
        else {
            if (!$this->isValid()) return false;
        }
        
        $on_success = !empty($options['success']) ? $this->parseCallback($options['success']) : false;
        $on_error = !empty($options['error']) ? $this->parseCallback($options['error']) : false;
        $was_success = false;
        $was_error = false;
        
        $_options = $options;
        $_options['success'] = function($_model=null, $response=null, $__options=null) use ($model, &$options, &$_options, $on_success, $waiting, $attrs, &$was_success) {
            //Stop async/defer options from propagating
            unset($options['async']); 
            unset($options['defer']);
            
            //Saved successfully, set any new attributes
            $serverAttrs = $model->parse($response);
            if ($waiting and $attrs) $serverAttrs = array_merge($attrs, $serverAttrs);
<<<<<<< HEAD
            if (!$model->set($serverAttrs, array_merge($_options, array('on_sync'=>true)))) return; //if can't set, calls error 
=======
            if (!$model->set($serverAttrs, array_merge($options, array('on_sync'=>true)))) return; //if can't set, calls error 
>>>>>>> 857c8d5996fdee227c4d22994ba4e543bd7d4d40
            
            //Signal that the operation was successful
            if ($on_success) call_user_func($on_success, $model, $response, $options);
            $model->trigger('save', $model, $response, $options);            
            $model->trigger('sync', $model, $response, $options);
            $was_success = true; 
        };
        $_options['error'] = function($_model=null, $response=null, $_options=null) use ($model, &$options, $on_error, &$was_error) {
            //Signal that the operation was unsuccessful
            if ($on_error) call_user_func($on_error, $model, $response, $options); 
            else $model->trigger('error', $model, $response, $options);
            $was_error = true;
        };
        
        call_user_func($sync, $this->isNew() ? 'create' : 'update', $this, $_options);
        
        //Revert the changes, if we are waiting for a successful response first
        if ($waiting and !$was_success) {
            $this->clear($silentOptions);
            $this->set($current, $silentOptions);
        }
        
        if ($was_success != $was_error) return $was_success ? $this : false;
        else if (!empty($options['async'])) return $this;
        else throw new LogicException("Sync function did not invoke callbacks correctly");
    }
    
    /**
     * Destroy this model if it was already saved.
     * Optimistically removes the model from its collection, if it has one.
     * 
     * Options:
     * - 'sync' : Override the model's sync function for this call 
     * - 'wait' : Wait for success before removing it from the collection or triggering the destroy event.
     * - 'success' : Callback on successfully destroying the model: function($model, $response, $options)
     * - 'error' : Callback on failing to destroy the model: function($model, $response, $options). If omitted, instead calls trigger('error', $model, $response, $options);
     * - 'async' : (PHP Only) Don't require the sync function to call 'success' or 'error' before returning.
     * Options are additionally passed through to the destroy event, and the success or error callbacks. 
     * 
     * @param array $options
     * @return Backbone_Mode|false provides a fluent interface, or returns false on error.
     */
    public function destroy(array $options=array()) 
    {
        $sync = !empty($options['sync']) ? $options['sync'] : $this->sync();
        if (!$sync) throw new BadFunctionCallException("No sync callback found, cannot destroy.");
        $waiting = !empty($options['wait']);
        $model = $this;

        $on_success = !empty($options['success']) ? $this->parseCallback($options['success']) : false;
        $on_error = !empty($options['error']) ? $this->parseCallback($options['error']) : false;
        $was_success = false;
        $was_error = false;
        
        $_options = $options;
        $success = $_options['success'] = function($_model=null, $response=null, $_options=null) use ($model, &$options, $on_success, $waiting, &$was_success) {
            //If not done yet, remove from the collection.
            if ($waiting || $model->isNew()) $model->trigger('destroy', $model, $model->collection(), $options);
            
            //Signal that the operation was successful
            if ($on_success) call_user_func($on_success, $model, $response, $options);
            if (!$model->isNew()) $model->trigger('sync', $model, $response, $options);
            $was_success = true; 
        };
        $_options['error'] = function($_model=null, $response=null, $_options=null) use ($model, &$options, $on_error, &$was_error) {
            //Signal that the operation was unsuccessful
            if ($on_error) call_user_func($on_error, $model, $response, $options); 
            else $model->trigger('error', $model, $response, $options);
            $was_error = true;
        };
                
        //If model is new, no need to sync - just count as destroyed.
        if ($this->isNew()) {
            $success($this, null, $options);
            return false;
        }
        
        //Optimistically remove from the collection.
        if (!$waiting) $this->trigger('destroy', $this, $this->collection(), $options);
        
        call_user_func($sync, 'delete', $this, $_options);
        if (!empty($options['async'])) return $this;
        elseif ($was_success != $was_error) return $was_success ? $this : false;
        else throw new LogicException("Sync function did not invoke callbacks correctly");
    }
     
    /*************************************************************************\
     * Export
    \*************************************************************************/
    /**
     * The javascript name for the class, if set
     * By default will use the PHP class name.
     * @var string
     */
    protected static $exported_classname = null;
        
    /**
     * Javascript methods that belong in the object definition.
     * 
     * For example; if a function 'foo' is exported, then
     * in javascript; "var model = new My.Model(); model.foo();" 
     * 
     * @var array Map of 'name' => 'function(args) { stuff }'
     */
    protected static $exported_functions = array();
    
    /**
     * Javascript static methods that belong in the class definition.
     * 
     * For exmaple; if a function 'foo' is exported, then
     * in javascript; My.Model.foo()
     */
    protected static $exported_static_functions = array();
    
    /**
     * Static class attributes that need to be exported to the class definition
     * @var array List of attribute names
     */
    protected static $exported_fields = array('idAttribute', 'defaults', 'urlRoot');
        
    /**
     * Output this model as a JSON-encoded string
     * @return string
     */
    public function toJSON() {
        return json_encode(empty($this->attributes) ? (object)null : $this->attributes);
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
        if ($classname == 'Backbone_Model') return 'Backbone.Model';
        elseif (static::$exported_classname) return static::$exported_classname;
        else return $classname;
    }
    
    /**
     * Export the model attributes for outputting to the client.
     * Can be overriden to filter out attributes that shouldn't be visible to clients.
     * 
     * The default implementation simply delegates to toJSON
     * 
     * @param array $options Any options the method might need, eg the authenticated user 
     * @see Backbone_Router_Model::read
     * @return string A JSON-encoded object of model attributes for export
     */
    public function exportJSON(array $options = array()) {
        return $this->toJSON();
    }
    
    /**
     * Export the model attributes as an associative array for processing and client output.
     * 
     * The default implementation simply returns the current attributes.
     * 
     * @param $options array
     * @return array An associative array of attributes
     */
    public function export(array $options=array()) {
        return $this->attributes;
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
        if ($_class == 'Backbone_Model') throw new LogicException("Can't export Backbone_Model, only subclasses");
        
        $class = $_class::getExportedClassName();
        $parent = $_parent::getExportedClassName();
        
        $members = array();
        $static_members = array();
        
        $reflector = new ReflectionClass($_class);
        $class_values = $reflector->getDefaultProperties();
        
        foreach(static::$exported_fields as $field) $members[] = "$field: ".(isset($class_values[$field])? json_encode($class_values[$field]) : 'null');
        foreach(static::$exported_functions as $name=>$func) $members[] = "$name: $func";
        foreach(static::$exported_static_functions as $name=>$func) $static_members[] = "$class.$name = $func;";
        
<<<<<<< HEAD
        //Export any constants defined by the class
        foreach($reflector->getConstants() as $name=>$field) { 
            if (empty(static::$exported_static_functions[$name])) 
                $static_members[] = "$class.$name = ".json_encode($field).";";
        }
        
        // If class isn't being defined as part of a module, declare it with var.
        if (strpos($class, '.') === false) $class = "var $class";
        
        return "$class = $parent.extend({\n  ".implode(",\n  ",$members)."\n});\n".implode("\n", $static_members);
=======
        // If class isn't being defined as part of a module, declare it with var.
        if (strpos($class, '.') === false) $class = "var $class";
        
        return "$class = $parent.extend({\n\t".implode(",\n\t",$members)."\n});\n".implode("\n", $static_members);
>>>>>>> 857c8d5996fdee227c4d22994ba4e543bd7d4d40
    }
}