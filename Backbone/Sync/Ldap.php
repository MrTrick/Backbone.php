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
     * If not set, returns all.
     */
    protected static $returned_attributes = array();
    
    /**
     * What attributes can be filtered?
     * If not set, accepts none.
     */
    protected static $filterable_attributes = array();
    
    /**
     * Are there base-level filters that are applied to all searches?
     * @var array of string or Zend_Ldap_Filter
     */
    protected static $base_filters = array();
    
    /**
     * Specify a base dn or 'location' for these models
     * @var string
     */
    protected static $baseDn = null;
    
    /**
     * What kinds of exceptions are sync errors?
     * @see Backbone_Sync_Abstract::caught 
     * @var array
     */
    protected $caught = array('Zend_Ldap_Exception', 'Backbone_Exception_NotFound', 'Backbone_Exception_Forbidden');
    
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
     * 
     * Uses any filters defined in the class or the options, to enforce further restrictions.
     * eg; Calling readModel($contract, array('params'=>array('owner'=>$person_id)))
     *  - Fetches the contract.
     *  - Only works if the contract has that owner.
     * 
     * @see Backbone_Sync_Abstract::readModel
     * @param Backbone_Model $model
     * @param array $options Options, includes success/error callback
     * @throws Zend_Ldap_Exception if an error occurs
     * @return array|false Returns the arr on success, false on failure. 
     */
    public function readModel(Backbone_Model $model, array $options = array()) {
        $dn = $this->toDn($model, $options);
        $filter = $this->getSearchFilter($model, $options);
        $client = $this->client();
        
        try { $result = $client->search($filter, $dn, Zend_Ldap::SEARCH_SCOPE_BASE, static::$returned_attributes); }
        catch (Zend_Ldap_Exception $e) { throw new Backbone_Exception_NotFound("Model could not be read, not found"); }
        
        if ($result->count() == 0) { throw new Backbone_Exception_NotFound("Model could not be read, not found"); }
        else if ($result->count() > 1) { throw new UnexpectedValueException("Expected zero or one result, got ".$result->count()); }
        
        return $this->convertEntryToAttributes($result->getFirst(), $options);
    }
    
    /**
     * Given a Backbone_Collection; fetch its elements from the backend.
     * Options:
     *  'params' => A map of 'key' => 'value' attributes to filter on. If a 'filter' attribute is defined, added to 'filter'.  
     *  'filter' => A filter (see getSearchFilter) for the LDAP search
     * @see Backbone_Sync_Abstract::readCollection
     * @param Backbone_Model_Collection $collection  
     * @param array $options
     * @throws Zend_Ldap_Exception if an error occurs
     * @return array|false Returns the array of models attributes, or false if an error occurs.
     */
    public function readCollection(Backbone_Collection $collection, array $options = array()) {
        $filter = $this->getSearchFilter($collection, $options);
        $sort = $this->getSortFilter($collection, $options);
        $sizelimit = $this->getSizeLimit($collection, $options);
        $client = $this->client();
        
        //$time = microtime(true);
        $entries = $client->search($filter, static::$baseDn, Zend_Ldap::SEARCH_SCOPE_SUB, static::$returned_attributes, $sort, null, $sizelimit);
        //property_exists($this, 'log') && $this->log && $this->log->warn(sprintf("%s. Took: %0.3f seconds, %d results", $filter, (microtime(true)-$time), $entries->count()));
        
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
     * @throws Zend_Ldap_Exception if an error occurs
     * @return array|false Returns the latest attributes on success, false on failure.
     */
    public function create(Backbone_Model $model, array $options = array()) {
        $dn = $this->toDn($model, $options);
        $entry = $this->convertModelToEntry($model, $options);
        $client = $this->client();
        
        //Insert the model data
        $client->add($dn, $entry);
        
        //Read it back
        $entry = $client->getEntry($dn, static::$returned_attributes);
        if ($entry==null) throw new Backbone_Exception_NotFound("Model could not be found after creating");
        
        return $this->convertEntryToAttributes($entry, $options);
    }
    
    /**
     * Given a Backbone_Model; update it in the backend and return the current set of attributes.
     *  TODO: Define; what happens if the PK of a model is modified? 
     * @see Backbone_Sync_Abstract::update
     * @param Backbone_Model $model
     * @param array $options
     * @throws Zend_Ldap_Exception if an error occurs
     * @return array|false Returns the latest attributes on success, false on failure. 
     */
    public function update(Backbone_Model $model, array $options = array()) {
        $dn = $this->toDn($model, $options);
        $entry = $this->convertModelToEntry($model, $options);
        $client = $this->client();
        
        //Update the model data
        $client->exists($dn) ? $client->update($dn, $entry) : $client->add($dn, $entry);
        
        //Read it back
        $entry = $client->getEntry($dn, static::$returned_attributes);
        if ($entry==null) throw new Backbone_Exception_NotFound("Model could not be found after updating");
        
        return $this->convertEntryToAttributes($entry, $options);
    }
    
    /**
     * Given a Backbone_Model; delete it from the backend
     * @see Backbone_Sync_Abstract::delete
     * @param Backbone_Model $model
     * @param array $options
     * @throws Zend_Ldap_Exception if an error occurs
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
     * Given a collection or model to read, generate the ldap selector
     * (to figure out which entries to search for)
     * The default implementation will select (any row / all rows) by default.
     * 
     * If base_filters are defined, will use them.
     * If an $options['filter'] is defined, will use it.
     * If an $options['params']['filter'] is defined, will use it.
     * If filterable_attributes are defined in $options['params'], will use them.
     * Any defined filters are combined into a logical AND filter.
     * 
     * Override this function to permit custom selectors
     * 
     * @param Backbone_Model|Backbone_Collection $m_or_c
     * @param array $options
     * @throws InvalidArgumentException
     * @return Zend_Ldap_Filter|string
     */
    public function getSearchFilter($m_or_c, array $options) {
        //Filter on the parameters given, and the 'filter' attribute if given...
       	$params = empty($options['params']) ? array() : $options['params'];
       	if (!is_array($params)) throw new InvalidArgumentException('Expected $options[\'params\'] to be an array, '.gettype($options['params']).'given.');
       	 
		//Are there base filters?
		$filters = $this->getBaseFilters($m_or_c, $options);
		foreach($filters as &$filter) if (!is_string($filter)) {
			$filter = $this->parseFilter($filter);
		}
      	
       	//Are there any filters? Parse and incorporate them
      	if (!empty($options['filter'])) {
      		$f = $this->parseFilter($options['filter']);
      		if ($f) $filters[] = $f;
      	}
       	if (!empty($params['filter'])) {
       		$f = $this->parseFilter($params['filter']);
       		if ($f) $filters[] = $f;
       		unset($params['filter']);
       	}
       	
		//Push the parameters into the filter too
		foreach ($this->getFilterableParameters($params, $options) as $attr=>$value) {
			$f = $this->makeFilter($attr, $value);
			if ($f) $filters[] = $f;
		}	

		//Were there any filters at all?
		if (!$filters) return Zend_Ldap_Filter::any('objectClass');
		//If only one filter, use it directly. 
		if (count($filters)==1) $filter = $filters[0];
		//If multiple filters, combine them.
		else $filter = '(&'.implode($filters).')';
		
		//If the filter is functionally empty, (eg. "(|(&(|(|))))" contains no letters or numbers), ignore it
		if (preg_match('/^[^a-zA-Z0-9]*$/', (string)$filter)) 
			return Zend_Ldap_Filter::any('objectClass');

		return $filter;
    }
    
    /**
     * Fetch the filters that should apply to every request.
     * Will be ANDed with other filters by getSearchFilter  
     * 
     * @param Backbone_Model|Backbone_Collection $m_or_c
     * @param array $options
     * @return array 
     */
    protected function getBaseFilters($m_or_c, array $options) {
    	return static::$base_filters;
    }
    
	/**
	 * How should search results be sorted?
	 * (Typically an attribute name, optionally prefixed by '-' for reverse) 
	 * @param Backbone_Collection $collection
	 * @param array $options
	 * @return string|null
	 */   
    protected function getSortFilter(Backbone_Collection $collection, array $options) {
    	if (!empty($options['sort'])) return $options['sort'];
    	elseif (!empty($options['params']) && !empty($options['params']['sort'])) return $options['params']['sort']; 
    	else return null;
    }
    
    /**
     * How many results should be returned?
     * @param Backbone_Collection $collection
     * @param array $options
     * @return int|null
     */
    protected function getSizeLimit(Backbone_Collection $collection, array $options) {
    	if (!empty($options['limit'])) return $options['limit'];
    	if (!empty($options['params']) && !empty($options['params']['limit'])) return $options['params']['limit'];
    	else return null;
    }
    
    /**
     * Try to parse the given filter into a ldap-compatible form.
     * Filters can be:
     *   
	 * A Zend_Ldap_Filter object (To be set/added internally) 
     *
     * A simple test:
	 *
	 *   {attr:'Foo', value:'Bar'}
	 *
	 * A logical combination of tests:
	 *
	 *   {'&': [filter, filter, ...]}
	 *   {'|': [filter, filter, ...]}
	 *   {'!': [filter, filter, ...]}
	 *
	 * A short-cut combination: (cannot contain nested filters or repeated attributes)
	 *
	 *   {'&': {Foo:'Bar', Bar:'Baz', Baz:'Foo'} }
	 *   {'|': {Foo:'Bar', Bar:'Baz', Baz:'Foo'} }
	 *   //Is equivalent to;
	 *   {'&': [{attr:'Foo', value:'Bar'}, {attr:'Bar', value:'Baz'}, {attr:'Baz', value:'Foo'}]}
	 *   {'|': [{attr:'Foo', value:'Bar'}, {attr:'Bar', value:'Baz'}, {attr:'Baz', value:'Foo'}]}
	 *   
     * @param array|Zend_Ldap_Filter_Abstract $filter
     * @return string
     * @throws InvalidArgumentException
     */
    protected function parseFilter($filter) {
    	//Short-circuit - just passthrough actual filters.
    	if ($filter instanceof Zend_Ldap_Filter_Abstract) 
    		return $filter;
    	
    	//Simple filter?
    	elseif (is_array($filter) and count($filter) == 2 and array_key_exists('attr', $filter) and array_key_exists('value', $filter))
    		return $this->makeFilter($filter['attr'], $filter['value']);

    	elseif (is_array($filter) and count($filter) == 3 and array_key_exists('attr', $filter) and array_key_exists('value', $filter) and array_key_exists('type', $filter))
    	return $this->makeFilter($filter['attr'], $filter['value'], $filter['type']);
    	 
    	//Combination filter?
    	elseif (is_array($filter) and count($filter) == 1 and is_array( $inner=reset($filter) ) ) {
    		//Check the operator type
    		$operator = key($filter);
    		if ($operator == '&') { $pre='(&'; $suf=')'; }
    		elseif ($operator == '|') { $pre='(|'; $suf=')'; }
    		elseif ($operator == '!')  { $pre='(!(|'; $suf='))'; }
    		else throw new InvalidArgumentException("Invalid filter operator '".$operator."'");

    		$filters = array();

    		//Proper form? Recurse.
    		if (is_array(reset($inner)) and is_int(key($inner))) foreach($inner as $value) {
    			$f = $this->parseFilter($value);
    			if ($f) $filters[] = $f;
    		}
    		//Shortcut form? Make a simple filter from each key-value pair.
    		else foreach($inner as $attr=>$value) {
    			$f = $this->makeFilter($attr, $value);
    			if ($f) $filters[] = $f;
    		}
    		
    		//Ignore empty filters
    		if (!$filters) return null;
			//Combine and return for multiple filters
			if (count($filters)>1) return $pre.implode($filters).$suf;
			//Just return for single filters, with negation for !
			else return ($operator=='!') ? '(!'.$filters[0].')' : $filters[0];
    	}  
    	
    	//Or something else?
    	else throw new InvalidArgumentException("Invalid filter syntax; ".json_encode($filter));
    }
    
	/**
	 * Make an LDAP filter out of the given attribute and value
	 * If values is an array, filter matches any
	 * 
	 *   {Foo: ['Bar', 'Baz']}
	 *   //Is equivalent to;
	 *   {'|': [{attr:'Foo', value:'Bar'}, {attr:'Foo', value:'Baz'}]}
	 *   
	 * NOTE: Doesn't check whether assoc or real array
	 * 
	 * @param string $attr
	 * @param mixed $value
	 * @return string
	 * @throws InvalidArgumentException If the attribute is not listed as being filterable
	 */			
	public function makeFilter($attr, $value, $type=null) {
		if (!in_array($attr, static::$filterable_attributes)) throw new InvalidArgumentException("Cannot filter on attribute $attr");
		if (!is_array($value)) $value = array($value);
		if (!count($value)) return null;
		
		$filters = array();
		foreach($value as $v) {
			if (!is_scalar($v)) throw new InvalidArgumentException("Cannot filter $attr, invalid value");
			//If the value is prefixed or suffixed with '*' wildcards, keep them. 
			if (substr($v,0,1) === '*') { $pre='*'; $v=substr($v,1); } else $pre='';
			if (substr($v,-1) === '*') { $suf='*'; $v=substr($v,0,-1); } else $suf='';
			
			$filters[] = '('.$attr . '=' . $pre . Zend_Ldap_Filter::escapeValue($v) . $suf .')';
		}
		return (count($filters)==1)?$filters[0]:'(|'.implode($filters).')';
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
    
    /**
     * Given a map of parameters, return only the ones that can be filtered.
     * Useful for ensuring that **external** parameters meet the whitelist.
     * NOTE! DO NOT USE FOR INTERNAL PARAMETERS THAT SHOULD ALWAYS BE FILTERED, USE $options['filter'] INSTEAD  
     * 
     * @param array $params
     * @param array $options
     * @return array
     */
    protected function getFilterableParameters(array $params, array $options) {
    	return static::$filterable_attributes ? array_intersect_key($params, array_flip(static::$filterable_attributes)) : array();
    }
} 