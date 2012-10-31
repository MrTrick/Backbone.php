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
 * Abstract Sync class to provide some simple structure 
 * @author Patrick Barnes
 */
abstract class Backbone_Sync_Abstract implements Backbone_Sync_Interface {
    /*************************************************************************\
     * Attributes and Accessors
    \*************************************************************************/
    /**
     * Model class name
     * Models passed to this sync object must be an instance of this class.
     * @var string  
     */
    protected static $model = 'Backbone_Model';
    
    /**
     * Collection class name
     * Collections passed to this sync object must be an instance of this class.
     */
    protected static $collection = 'Backbone_Collection';
    
    /**
     * The exception types caught by sync
     * (and treated as normal sync errors, as opposed to an application error)
     *   
     * @var array Exception names, eg array('Zend_Db_Exception') or array('Zend_Db_Exception', 'MyWeirdException')
     */
    protected $caught = null;
    
    /**
     * The exception types caught by sync
     * (and treated as normal sync errors, as opposed to an application error)
     * - caught() : Get
     * - caught($caught) : Set
     *   
     * @var array Exception names, eg array('Zend_Db_Exception') or array('Zend_Db_Exception', 'MyWeirdException')
     * @return array
     */
    public function caught(array $caught = null) {
        if (func_num_args()) $this->caught = $caught;
        return $this->caught;
    }
    
	/*************************************************************************\
     * Initialisation
    \*************************************************************************/
    
    /**
     * Build a sync instance.
     */
    public function __construct(array $options = array()) {
        //Set the 'caught' exception types
        $this->caught(!empty($options['caught']) ? $options['caught'] : $this->caught);
        
        $this->initialize($options);
    }
   
    /**
     * Override for initialization logic
     * @param array $options
     */
    public function initialize($options) {
    }
    
    /*************************************************************************\
     * Check Functions
    \*************************************************************************/
    
    /**
     * Is the sync operation allowed by the server?
     *  
     * The default implementation will delegate to a model/collection's 
     * 'allowed($method, $options)' method, if they have defined one. 
     * Otherwise allows.
     * 
     * @param string $method
     * @param Backbone_Model|Backbone_Collection $m_c
     * @param array $options
     * @return bool TRUE if the operation is allowed, FALSE otherwise
     */
    public function allowed($method, $m_c, $options) {
        if (method_exists($m_c, 'allowed')) 
            return $m_c->allowed($method, $options);
        else
            return true;
    }
    
    /**
     * Should the sync operation catch this exception and treat it as a sync error?
     * 
     * The default implementation will catch if the exception class name exists in 
     * the 'caught' attribute.
     * @param Exception $e  
     * @param string $method
     * @param Backbone_Model|Backbone_Collection $m_c
     * @param array $options
     * @return bool TRUE if the exception should be caught, FALSE otherwise
     */
    public function catches(Exception $e, $method, $m_c, array $options) {
        foreach($this->caught as $c) 
            if ($e instanceof $c) 
                return true;
        return false;
    }
    
    /*************************************************************************\
     * Sync Functions
    \*************************************************************************/
   
    /**
     * Perform the given synchronization method on the object.
     * 
     * The sync() method is expected to be synchronous, unless $options['async'] is set.
     * 
     * Backbone_Sync is expected to:
     *  - Take parameters function($method, $model|$collection, $options)
     *  - Call $options['success']($model|$collection, $response, $options) if the sync was successful.
     *  - Call $options['error']($model|$collection, $response, $options) if the sync was successful.
     *  - Return true|false On success/failure
     *  
     * The default implementation:
     *  - Validates the method, object type
     *  - Checks whether the operation is allowed ( using allowed() )
     *  - Delegates the actual synchronisation task to one of the 'create', 'readModel', 'readCollection', 'update', 'delete' abstract methods
     *  - Catches any exceptions in the caught list.
     *  - Triggers the success/error callback, passing the appropriate data
     * 
     * @param string $method One of; 'create', 'update', 'delete', 'read'
     * @param Backbone_Model|Backbone_Collection $m_c The model to synchronize, typically $this.
     * @param array $options Any options that need to be set in the upstream sync method
     * @return array|false data if the operation was successful, FALSE if the operation encountered errors.
     * @see Backbone_Sync_Interface::__invoke()
     */
    public function __invoke($method, $m_c, array $options=array()) {
        //Check the passed object is valid 
        $is_m = is_a($m_c, static::$model);
        $is_c = is_a($m_c, static::$collection);
        if (!($is_m || $is_c)) 
            throw new InvalidArgumentException("Expected Model or Collection, not " . get_class($m_c));

        //Check the method is valid, and translate to a function name
        static $methods = array('create', 'readModel', 'readCollection', 'update', 'delete');
        $_method = ($method=='read')?($is_m?'readModel':'readCollection'):$method;
        if (!in_array($_method, $methods))
            throw new InvalidArgumentException("Unsupported method: ".$method);

        //Check the access control - allowed to sync?
        if (!$this->allowed($method, $m_c, $options))
            throw new Backbone_Exception_Forbidden("Forbidden, not permitted to '$method' this object");
        
        //Run sync operation
        $data = false; $error = false;
        try { 
            $data = $this->$_method($m_c, $options); 
        } catch (Exception $error) {}
        
        //If an exception was thrown, should it be caught?
        if ($error and !$this->catches($error, $method, $m_c, $options)) throw $error;
        
        //Was the operation successful? Notify any callbacks
        if ($data !== false) {
            if (!empty($options['success'])) call_user_func($options['success'], $m_c, $data, $options);
        } else {
            if (!empty($options['error'])) call_user_func($options['error'], $m_c, $error, $options);            
        }
        
        return $data;
    }
    
    /**
     * Given a Backbone_Model; fetch its attributes from the backend.
     * NOTE: Doesn't update the model object itself.
     * 
	 * @see Backbone_Model::fetch
     * @param Backbone_Model $model
     * @param array $options Options, includes success/error callback
     * @throws Exception if an error occurs, where exception type is implementation dependent 
     * @return array|false Returns the arr on success, false on failure. 
     */
    abstract public function readModel(Backbone_Model $model, array $options = array());
    
    /**
     * Given a Backbone_Collection; fetch its elements from the backend.
     * Options:
     *  'params' => A map of 'key' => 'value' where filters 
     * 
     * NOTE: Doesn't update the collection object itself.
     * @see Backbone_Collection::fetch
     * @param Backbone_Model_Collection $collection  
     * @param array $options
     * @throws Exception if an error occurs, where exception type is implementation dependent
     * @return array|false Returns the array of models attributes, or false if an error occurs.
     */
    abstract public function readCollection(Backbone_Collection $collection, array $options = array());
    
    /**
     * Given a Backbone_Model; create it in the backend and return the current set of attributes.
     *  
     * @see Backbone_Model::save()
     * @param Backbone_Model $model
     * @param array $options
     * @throws Exception if an error occurs, where exception type is implementation dependent 
     * @return array|false Returns the latest attributes on success, false on failure.
     */
    abstract public function create(Backbone_Model $model, array $options = array());
    
    /**
     * Given a Backbone_Model; update it in the backend and return the current set of attributes.
     *  
     * @see Backbone_Model::save()
     * @param Backbone_Model $model
     * @param array $options
     * @throws Exception if an error occurs, where exception type is implementation dependent 
     * @return array|false Returns the latest attributes on success, false on failure. 
     */
    abstract public function update(Backbone_Model $model, array $options = array());
    
    /**
     * Given a Backbone_Model; delete it from the backend
     *  
     * @see Backbone_Model::destroy()
     * @param Backbone_Model $model
     * @param array $options
     * @throws Exception if an error occurs, where exception type is implementation dependent 
     * @return true|false Returns true on success, false on failure.
     */
    abstract public function delete(Backbone_Model $model, array $options = array()); 
}