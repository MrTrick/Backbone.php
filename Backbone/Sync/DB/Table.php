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
 * Simple Sync implementation to a table backend
 * @author Patrick Barnes
 */
class Backbone_Sync_DB_Table extends Backbone_Sync_Abstract {
    /*************************************************************************\
     * Attributes and Accessors
    \*************************************************************************/
    
    /**
     * What kinds of exceptions are sync errors?
     * @see Backbone_Sync_Abstract::caught 
     * @var array
     */
    protected $caught = array('Zend_Db_Exception', 'Backbone_Exception_NotFound');
    
    /**
     * The Zend_Db_Table object
     * If initially set to a table name (string), will be converted to the equivalent Zend_Db_Table object on construction.
     * @var Zend_Db_Table
     */
    protected $table = null;
    
    /**
     * The Zend_Db_Table object
     * @param string|Zend_Db_Table $table
     */
    public function table($table=null) {
        if (func_num_args()) {
            if (is_string($table)) $this->table = new Zend_Db_Table($table);
            elseif ($table instanceof Zend_Db_Table) $this->table = $table;
            else throw new InvalidArgumentException("Zend_Db_Table or string expected, received ".gettype($table));
        }
        return $this->table;
    }

	/*************************************************************************\
     * Initialisation
    \*************************************************************************/
    
    /**
     * Build a sync instance.
     * Must either define the table in a subclass, or pass as an option.
     * @param array $options
     */
    public function __construct(array $options = array()) {
        //Construct the table instance, if defined as string
        $this->table(!empty($options['table']) ? $options['table'] : $this->table);
        
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
        $select = $this->getModelSelector($model, $options);
        $row = $this->table()->fetchRow($select);
        if (!$row) throw new Backbone_Exception_NotFound("Model could not be read, not found");
            
        return $this->convertRowToAttributes($row, $options);
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
        $table = $this->table();

        $select = $this->getCollectionSelector($collection, $options);
        $rowset = $table->fetchAll($select);
        $data = array();
        foreach($rowset as $row) $data[] = $this->convertRowToAttributes($row, $options);
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
        $table = $this->table();
        $data = $this->convertModelToRecord($model, $options);

        //Insert the model data
        $pkid = $table->insert($data);
                    
        //Read it back
        $pk = $table->info(Zend_Db_Table::PRIMARY);
        if (count($pk) != 1) throw new InvalidArgumentException("Multi primary keys not supported!");
        $select = $table->select()->where($table->getAdapter()->quoteIdentifier(reset($pk)).'=?',$pkid);
        $row = $this->table()->fetchRow($select);
        if (!$row) throw new Backbone_Exception_NotFound("Model could not be found after creating");
        return $this->convertRowToAttributes($row, $options);
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
        $table = $this->table();
        $data = $this->convertModelToRecord($model, $options);
        
        //Update the model data            
        $select = $this->getModelSelector($model, $options); 
        $updated = $table->update($data, $select->getPart(Zend_Db_Select::WHERE));
        if ($updated == 0) throw new Backbone_Exception_NotFound("Model could not be updated, not found.");
        elseif ($updated != 1) throw new LogicException("More than one model was updated, selector too broad.");
        
        //Read it back
        $row = $this->table()->fetchRow($select);
        if (!$row) throw new Backbone_Exception_NotFound("Model could not be found after updating");
        return $this->convertRowToAttributes($row, $options);
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
        $table = $this->table();
        //Remove the model
        $select = $this->getModelSelector($model, $options);
        $deleted = $table->delete($select->getPart(Zend_Db_Select::WHERE));
        if ($deleted == 0) throw new Backbone_Exception_NotFound("Model could not be deleted, not found.");        
        elseif ($deleted != 1) throw new LogicException("More than one model was deleted, selector too broad.");
        
        return true;
    }    

    /*************************************************************************\
     * Internal Conversion/Filter Functions
    \*************************************************************************/

    /**
     * Given a model, generate the table row selector 
     * (to identify its row in the table)
     * Override this function if your backend table uses a primary key other than 
     * the model's idAttribute()  
     * 
     * @param Backbone_Model $model
     * @param array $options
     */
    public function getModelSelector(Backbone_Model $model, array $options) {
        if ($model->id() === null) throw new InvalidArgumentException("Model is new, can't select it.");

        $table = $this->table();
        $select = $table->select();
        $cond = $select->getAdapter()->quoteIdentifier($model->idAttribute()).' = ?';
        $select->where($cond, $model->id() );
        
        //If parameters are set, apply as extra conditions 
        if (!empty($options['params'])) {
            if (!is_array($options['params'])) throw new InvalidArgumentException('Expected $options[\'params\'] to be an array'.gettype($options['params']).'given.');
            
            $columns = $table->info(Zend_Db_Table_Abstract::COLS);
            $params = array_intersect_key($options['params'], array_flip($columns));
            foreach($params as $col=>$val) 
                $select->where($select->getAdapter()->quoteIdentifier($col).' = ?', $val);
        }

        return $select;
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
    public function getCollectionSelector(Backbone_Collection $collection, array $options) {
        $table = $this->table();
        $select = $table->select();

        //Filter on any parameters given that exist in the table. Ignore others.
        if (!empty($options['params'])) {
            if (!is_array($options['params'])) throw new InvalidArgumentException('Expected $options[\'params\'] to be an array'.gettype($options['params']).'given.');
            
            $columns = $table->info(Zend_Db_Table_Abstract::COLS);
            $params = array_intersect_key($options['params'], array_flip($columns));
            foreach($params as $col=>$val) 
                $select->where($select->getAdapter()->quoteIdentifier($col).' = ?', $val);
        }
        
        return $select;
    }
        
    /**
     * Convert a Backbone_Model instance into a database record.
     * The default implementation returns the subset of attributes that are defined in the database table. 
     * TODO: If params are set, should check/restrict? (eg if 'author' is set, don't allow if model contains another value for 'author')
     * 
     * @param Backbone_Model $model
     * @return array 
     */
    public function convertModelToRecord(Backbone_Model $model, array $options) {
        $table = $this->table();
        $columns = $table->info(Zend_Db_Table_Abstract::COLS);
        return array_intersect_key($model->attributes(), array_flip($columns));        
    }
    
    /**
     * Convert a database record into an array of model attributes.
     * The default implementation simply returns the record. 
     * 
     * @param Zend_Db_Table_Row $row
     * @return array
     */
    public function convertRowToAttributes(Zend_Db_Table_Row $row, array $options) {
        return $row->toArray();
    }
}