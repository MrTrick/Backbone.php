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
 * Backbone_Sync_Ldap
 * A sync class to fetch/save against an LDAP directory
 *
 * @author Patrick Barnes
 */
class Backbone_Sync_Ldap extends Backbone_Sync_Abstract {
    /*************************************************************************\
     * Attributes and Accessors
    \*************************************************************************/
    
    /**
     * What fields are returned by the LDAP query?
     */
    protected static $search_attributes = array();
    
    /**
     * What kinds of exceptions are sync errors?
     * @see Backbone_Sync_Abstract::caught 
     * @var array
     */
    protected $caught = array('Zend_Ldap_Exception', 'Backbone_Exception_NotFound');
    
    /**
     * The Zend_Ldap client object
     * @var Zend_Ldap
     */
    protected $client = null;
    
    /**
     * The Zend_Ldap client object
     * - client() : Get
     * - client($client) : Set
     * On set, assumes an array is a list of options to pass to a newly constructed Zend_Ldap object.
     * On set, automatically binds to the default user if not already bound.
     * @param Zend_Ldap|array $client
     * @return Zend_Ldap
     */
    public function client($client=null) {
        if (func_num_args()) {
            if ($client instanceof Zend_Ldap) $this->client = $client;
            elseif (is_array($client)) $this->client = new Zend_Ldap($client);
            else throw new InvalidArgumentException("Zend_Ldap or array expected, received ".gettype($client));

            //Automatically bind, if not already bound.
            if ($this->client->getBoundUser() === false) $this->client->bind();
        }
        return $this->client;
    }
    
	/*************************************************************************\
     * Initialisation
    \*************************************************************************/
    
    /**
     * Build a sync instance.
     * Must either define the client in a subclass, or pass as an option.
     */
    public function __construct(array $options = array()) {
        //Construct or set the client instance, if defined
        $this->client(!empty($options['client']) ? $options['client'] : $this->client);
        
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
        $dn = $this->toDn($model, $options);
        $client = $this->client();

        $entry = $client->getEntry($dn, static::$search_attributes);
        if ($entry==null) throw new Backbone_Exception_NotFound("Model could not be read, not found");
        
        return $this->convertEntryToAttributes($entry, $options);
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
        $filter = $this->getSearchFilter($collection, $options);
        $client = $this->client();

        $entries = $client->search($filter, null, Zend_Ldap::SEARCH_SCOPE_SUB, static::$search_attributes);
        $data = array();
        foreach($entries as $entry) {
            //Convert the LDAP entry to its attributes, including the ID
            $attrs = $this->convertEntryToAttributes($entry, $options);
            $attrs[ $collection->idAttribute() ] = $this->toId($entry, $options);
            
            $data[] = $attrs;
        }
         
        return $data;
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
        $dn = $this->toDn($model, $options);
        $entry = $this->convertModelToEntry($model, $options);
        $client = $this->client();
        
        //Insert the model data
        $client->add($dn, $entry);
        
        //Read it back
        $entry = $client->getEntry($dn, static::$search_attributes);
        if ($entry==null) throw new Backbone_Exception_NotFound("Model could not be found after creating");
        
        return $this->convertEntryToAttributes($entry, $options);
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
        $dn = $this->toDn($model, $options);
        $entry = $this->convertModelToEntry($model, $options);
        $client = $this->client();
        
        //Update the model data
        $client->exists($dn) ? $client->update($dn, $entry) : $client->add($dn, $entry);
        
        //Read it back
        $entry = $client->getEntry($dn, static::$search_attributes);
        if ($entry==null) throw new Backbone_Exception_NotFound("Model could not be found after updating");
        
        return $this->convertEntryToAttributes($entry, $options);
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
        $dn = $this->toDn($model, $options);
        $client = $this->client();
                
        //Remove the entry
        $client->delete($dn);
        
        return true;
    }    

    /*************************************************************************\
     * Internal Conversion/Filter Functions
    \*************************************************************************/

    /**
     * Given a model, return the LDAP distinguished name
     * 
     * The default implementation assumes that the model id is the dn. 
     * 
     * @param Backbone_Model $model
     * @param array $options
     */
    public function toDn(Backbone_Model $model, array $options) {
        if ($model->id() === null) throw new InvalidArgumentException("Model is new, no DN.");
        
        return $model->id();
    }
    
    /**
     * Given an LDAP entry, return the model id
     * 
     * The default implementation assumes that the dn is the model id
     * 
     * @param array $entry
     * @param array $options
     */
    public function toId(array $entry, array $options) {
        return $entry['dn'];
    }
    
    /**
     * Given a collection to read, generate the table selector
     * (to figure out which rows to read)
     * The default implementation will select all rows by default, or allow a set of simple filters to be passed in $options['params']
     * 
     * Override this function to permit more complex selectors
     * 
     * @param Backbone_Collection $collection
     * @param array $options
     * @throws InvalidArgumentException
     */
    public function getSearchFilter(Backbone_Collection $collection, array $options) {
        //If no parameters given, select all objects
        if (empty($options['params'])) { 
            return '(objectClass=*)';
        
        //Otherwise, filter on the parameters given.
        } else {
            if (!is_array($options['params'])) throw new InvalidArgumentException('Expected $options[\'params\'] to be an array'.gettype($options['params']).'given.');
            
            $filters = array();
            foreach($options['params'] as $attr=>$val) {
                if ($val instanceof Zend_Ldap_Filter_Abstract) 
                    $filters[] = $val;
                else
                    $filters[] = Zend_Ldap_Filter::equals($attr, $val);
            }
            return new Zend_Ldap_Filter_And($filters);
        }
    }
        
    /**
     * Convert a Backbone_Model instance into an LDAP entry
     * The default implementation simply copies every model attribute except id,
     * delegating to Zend_Ldap_Attribute::setAttribute
     * 
     * @param Backbone_Model $model
     * @return array 
     */
    public function convertModelToEntry(Backbone_Model $model, array $options) {
        $entry = array();
        foreach($model->attributes() as $attr=>$val) if ($attr != $model->idAttribute())
            Zend_Ldap_Attribute::setAttribute($entry, $attr, $val);

        return $entry;
    }
    
    /**
     * Convert an LDAP entry into an array of model attributes.
     * The default implementation delegates to Zend_Ldap_Attribute::getAttribute, 
     * and skips the dn and any empty values. 
     * 
     * @param array $entry
     * @param array $options
     * @return array
     */
    public function convertEntryToAttributes(array $entry, array $options) {
        $attributes = array();
        foreach($entry as $attr=>$val) if ($attr!='dn') {
            $val = Zend_Ldap_Attribute::getAttribute($entry, $attr);
            if (!empty($val)) $attributes[$attr] = $val;
        }
        
        return $attributes;
    }
} 