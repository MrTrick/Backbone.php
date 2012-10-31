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
require_once('common.php');

/**
 * Throws exceptions if called - used for testing error handling.  
 * @author Patrick Barnes
 */
class Test_Sync_DB_Table_MalAdapter extends Zend_Test_DbAdapter {
    public $sql, $bind;
    public function query($sql, $bind = array()) {
        $this->sql = (string)$sql;
        $this->bind = $bind;  
        throw new Zend_Db_Adapter_Exception("Query failed in maladapter"); 
    }
}

class Test_Sync_TestCase extends PHPUnit_Framework_TestCase {
    protected $name = "Backbone.Sync";
    
    public static function setUpBeforeClass() {
        try {
            $db = Zend_Db::factory('Pdo_Sqlite', array('dbname'=>'sync_test.db'));
            $db->query('CREATE TABLE IF NOT EXISTS "books" ( "id" INTEGER PRIMARY KEY AUTOINCREMENT, "title" NOT NULL, "author" TEXT, "length" INTEGER );');
            $db->query('CREATE TABLE IF NOT EXISTS "people" ( "id" INTEGER PRIMARY KEY, "name" NOT NULL, "height" INTEGER, "weight" INTEGER );');            
            Zend_Db_Table::setDefaultAdapter($db);
        } catch (Zend_Db_Adapter_Exception $ex) {
            echo "Error connecting to database: ".$ex->getMessage(); exit;
        }
    }

    public static function tearDownAfterClass() {
        @unlink('sync_test.db');
    }
    
    protected $attrs = array(
        'title' => "The Tempest",
        'author' => "Bill Shakespeare",
        'length' => 123
    );
    protected $id;
    
    protected function _setUpDB() {
        $table = new Zend_Db_Table('books');
        $table->delete('');
        $this->id = $table->insert($this->attrs);
        $table->insert(array('id'=>'1938273', 'title'=>"Moby Dick", 'author'=>"Herman Melville", 'length'=>321));
    }
    
    /**
     * DB Table: Sync Table
     * Test that the linked table object is correctly set, on override, construction, and set. 
     */
    public function testDBTableSyncTable() {
        $sync_class = Backbone::uniqueId('Sync_');
        eval('class '.$sync_class.' extends Backbone_Sync_DB_Table { protected $table = "books"; }');
        $this->assertTrue(class_exists($sync_class));
        
        //No table defined
        try {
            $sync = new Backbone_Sync_DB_Table();
            $this->fail("Shouldn't be able to create sync db object without defining table");
        } catch (InvalidArgumentException $e) {} 
            
        //Defined by override
        $sync = new $sync_class();
        $table = $sync->table();
        $this->assertTrue($table instanceof Zend_Db_Table);
        $this->assertEquals('books', $table->info(Zend_Db_Table::NAME));
        
        //Defined by construction
        $sync = new Backbone_Sync_DB_Table(array('table'=>'people'));
        $table = $sync->table();
        $this->assertTrue($table instanceof Zend_Db_Table);
        $this->assertEquals('people', $table->info(Zend_Db_Table::NAME));
                
        //Defined by override and construction
        $sync = new $sync_class(array('table'=>'people'));
        $table = $sync->table();
        $this->assertTrue($table instanceof Zend_Db_Table);
        $this->assertEquals('people', $table->info(Zend_Db_Table::NAME), "Expect the construction table name to override the class table name");

        //Defined by construction with object
        $table = new Zend_Db_Table('people');
        $sync = new Backbone_Sync_DB_Table(array('table'=>$table));
        $this->assertSame($table, $sync->table());
        
        //Manually set
        $sync = new $sync_class();
        $table = new Zend_Db_Table('people');
        $this->assertNotSame($table, $sync->table());
        $sync->table($table);
        $this->assertSame($table, $sync->table());
        
        $sync = new $sync_class();
        $sync->table('people');
        $this->assertTrue($sync->table() instanceof Zend_Db_Table);
        $this->assertEquals('people', $table->info(Zend_Db_Table::NAME));
        
        $sync = new $sync_class();
        try {
            $sync->table(array('foo'));
            $this->fail("Shouldn't be able to set an invalid table object");
        } catch (InvalidArgumentException $e) {}
    }
    
    /**
     * DB Table: Init
     */
    public function testDBTableInit() {
        $sync_class = Backbone::uniqueId('Sync_');
        eval('class '.$sync_class.' extends Backbone_Sync_DB_Table { public $options=null; public function initialize($options) { $this->options = $options; } }');
        $this->assertTrue(class_exists($sync_class));
        
        $options = array('table'=>'books', '1'=>'one', '2'=>'two', '3'=>'three');
        $sync = new $sync_class($options);
        $this->assertEquals($options, $sync->options);
    }

    /**
     * DB Table: Reading directly from sync
     */
    public function testDBTableRead() {
        $_model = null; $_data = null; $_options = null; $error = false;
        
        $this->_setUpDB();
        $sync = new Backbone_Sync_DB_Table(array('table'=>'books'));
        $model = new Backbone_Model(array('id'=>$this->id));
        $options = array(
            'success' => function($model, $data, $options) use (&$_model, &$_data, &$_options) {
                $_model = $model;
                $_data = $data;
                $_options = $options;        
            },
            'error' => function($model, $error, $options) use (&$error) {
                $error = true;
            }
        );
        
        $data = $sync('read', $model, $options);
        $this->assertEquals($this->attrs['title'], $data['title']);
        $this->assertEquals($this->id, $data['id']);
        
        $this->assertFalse($error, 'Error callback not called');
        $this->assertEquals($model, $_model, 'Success callback passes model');
        $this->assertEquals($data, $_data, 'Success callback passes data');
        $this->assertEquals($options, $_options, 'Success callback passes options');
    }

    /**
     * DB Table: Reading through the model
     */
    public function testDBTableReadFromModel() {
        $this->_setUpDB();
        $sync = new Backbone_Sync_DB_Table(array('table'=>'books'));
        $model = new Backbone_Model(array('id'=>$this->id), array('sync'=>$sync));
        
        $this->assertSame($model, $model->fetch());
        $this->assertEquals($this->id, $model->id());
        $this->assertEquals($this->attrs['title'], $model->title);
    }
    
    /**
     * DB Table: Read from the collection
     */
    public function testDBTableReadFromCollection() {
        $this->_setUpDB();
        $sync = new Backbone_Sync_DB_Table(array('table'=>'books'));
        $collection = new Backbone_Collection(array(), array('sync'=>$sync));

        $this->assertEquals(0, $collection->length());
        $this->assertSame($collection, $collection->fetch());
        $this->assertEquals(2, $collection->length());
        $model = $collection->get($this->id);
        $this->assertEquals($this->attrs['title'], $model->title);
        $this->assertNotNull($collection->get('1938273'));
    }
    
    /**
     * DB Table: Create a record
     */
    public function testDBTableCreate() { 
        $this->_setUpDB();
        $sync = new Backbone_Sync_DB_Table(array('table'=>'people'));
        
        $model = new Backbone_Model(array('name'=>'Wide Wally', 'height'=>150, 'weight'=>300, 'other_attr'=>'not to be saved'));
        $model->sync($sync);
        $this->assertTrue($model->isNew());
        $res = $model->save();
        $this->assertSame($model, $res);
        $this->assertFalse($model->isNew());
        $id = $model->id();
        $this->assertNotNull($id);
        $this->assertTrue(is_numeric($id));
        
        $model = new Backbone_Model();
        $model->sync($sync);
        $model->id = $id;
        $model->fetch();
        $this->assertEquals('Wide Wally', $model->name, "Can fetch back saved model");
        $this->assertNull($model->other_attr, "Attributes not in the db aren't saved by Backbone_Sync_DB");
    }

    /**
     * DB Table: Update an existing record
     */
    public function testDBTableUpdate() {
        $this->_setUpDB();
        $sync = new Backbone_Sync_DB_Table(array('table'=>'books'));
        $model = new Backbone_Model(array('id'=>'1938273', 'title'=>"Moby Dick", 'author'=>"Herman Melville", 'length'=>321));
        $model->sync($sync);
        
        $res = $model->save('author', 'Engelbert Humperdink');
        $this->assertSame($model, $res);
        $this->assertEquals('Engelbert Humperdink', $model->author);
                
        $model = new Backbone_Model(array('id'=>'1938273'), array('sync'=>$sync));
        $model->fetch();
        $this->assertEquals('Engelbert Humperdink', $model->author);
    }
    
    /**
     * DB Table: Remove an existing record
     */
    public function testDBTableDelete() {
        $this->_setUpDB();
        $sync = new Backbone_Sync_DB_Table(array('table'=>'books'));
        $model = new Backbone_Model(array('id'=>'1938273'), array('sync'=>$sync));
        $model->fetch();
        $res = $model->destroy();
        $this->assertSame($model, $res);
        
        $model = new Backbone_Model(array('id'=>'1938273'), array('sync'=>$sync));
        $res = $model->fetch();
        $this->assertFalse($res, "Once destroyed, the object is no longer in the table and can't be fetched");
    }
    
    /**
     * DB Table: Invalid sync usage
     */
    public function testDBTableInvalidSyncInvocation() {
        $this->_setUpDB();
        $sync = new Backbone_Sync_DB_Table(array('table'=>'books'));
        
        try {
            $sync('read', new stdClass(), array());
            $this->fail("Shouldn't be able to read on a non model/collection");
        } catch (InvalidArgumentException $e) {}
        
        try {
            $sync('snorkle', new Backbone_Model(), array());
            $this->fail("Shouldn't be able to 'snorkle'.");
        } catch (InvalidArgumentException $e) {}
    }
    
    /**
     * DB Table: Read empty object
     */
    public function testDBTableReadEmptyObject() {
        $this->_setUpDB();
        $sync = new Backbone_Sync_DB_Table(array('table'=>'books'));
        $model = new Backbone_Model();
        
        try {
            $sync('read', $model, array());
            $this->fail("Shouldn't be able to read a new model");
        } catch(InvalidArgumentException $e) {}
    }
    
    /**
     * DB Table: Read using params
     */
    public function testDBTableReadParams() {
        $this->_setUpDB();
        $sync = new Backbone_Sync_DB_Table(array('table'=>'books'));
        $sync->table()->insert(array('title'=>'The Cat In The Hat', 'author'=>'Dr Seuss', 'length'=>102));
        $sync->table()->insert(array('title'=>'The Cat In The Hat Comes Back', 'author'=>'Dr Seuss', 'length'=>105));
        $sync->table()->insert(array('title'=>'Food', 'author'=>'Wide Wally', 'length'=>102));
        $sync->table()->insert(array('title'=>'Food', 'author'=>'Slim Sally', 'length'=>11));
        
        $collection = new Backbone_Collection(array(), array('sync'=>$sync));
        $this->assertEquals(2, $collection->fetch(array('params'=>array('author'=>'Dr Seuss')))->length());
        $collection->reset();
        $this->assertEquals(2, $collection->fetch(array('params'=>array('title'=>'Food')))->length());
        $collection->reset();
        $this->assertEquals(2, $collection->fetch(array('params'=>array('length'=>'102')))->length());
        $collection->reset();
        $this->assertEquals(1, $collection->fetch(array('params'=>array('title'=>'The Cat In The Hat')))->length());
        $collection->reset();
        $this->assertEquals(6, $collection->fetch()->length());
    }
    
    /**
     * DB Table: Error handling
     */
    public function testDBTableErrorHandling() {
        $this->_setUpDB();
        
        //Construct an adapter that will throw errors on attempting to read/modify the db 
        $maladapter = new Test_Sync_DB_Table_MalAdapter();
        $fields = array('SCHEMA_NAME','TABLE_NAME','COLUMN_NAME','COLUMN_POSITION','DATA_TYPE','DEFAULT','NULLABLE','LENGTH','SCALE','PRECISION','UNSIGNED','PRIMARY','PRIMARY_POSITION','IDENTITY');
        $maladapter->setDescribeTable('books', array(
            'id'=>array_combine($fields, array('','books','id',1,'INTEGER',null,true,null,null,null,null,true,1,1)),
            'title'=>array_combine($fields, array('','books','title',2,'',null,false,null,null,null,null,false,null,false)),
        	'author'=>array_combine($fields, array('','books','author',3,'TEXT',null,true,null,null,null,null,false,null,false)),
        	'length'=>array_combine($fields, array('','books','length',4,'INTEGER',null,true,null,null,null,null,false,null,false))
        ));
        
        $error = false;
        $sync = new Backbone_Sync_DB_Table(array('table'=>'books'));
        $sync->table()->setOptions(array( Zend_Db_Table::ADAPTER => $maladapter ));
        $options = array('error'=>function($model, $_error, $options) use (&$error) { $error = $_error; });
        
        $collection = new Backbone_Collection(array(), array('sync'=>$sync));
        $res = $collection->fetch($options);
        $this->assertEquals('SELECT books.* FROM books', $maladapter->sql);
        $this->assertFalse($res, "Fetch collection failed");
        $this->assertTrue($error instanceof Zend_Db_Adapter_Exception, "Error set to exception");
        $this->assertEquals('Query failed in maladapter', $error->getMessage());
        $error = false;

        $model = new Backbone_Model(array('id'=>'1938273'), array('sync'=>$sync));
        $res = $model->fetch($options);
        $this->assertEquals("SELECT books.* FROM books WHERE (id = '1938273') LIMIT 0,1", $maladapter->sql);
        $this->assertFalse($res, "Fetch collection failed");
        $this->assertTrue($error instanceof Zend_Db_Adapter_Exception, "Error set to exception");
        $this->assertEquals('Query failed in maladapter', $error->getMessage());
        $error = false;
                
        $model = new Backbone_Model(array('title'=>'The Cat In The Hat', 'author'=>'Dr Seuss', 'length'=>102), array('sync'=>$sync));
        $res = $model->save(null, $options);
        $this->assertEquals("INSERT INTO books (title, author, length) VALUES (?, ?, ?)", $maladapter->sql);
        $this->assertEquals(array('The Cat In The Hat', 'Dr Seuss', 102), $maladapter->bind);
        $this->assertFalse($res, "Create model failed");
        $this->assertTrue($error instanceof Zend_Db_Adapter_Exception, "Error set to exception");
        $this->assertEquals('Query failed in maladapter', $error->getMessage());
        $error = false;

        $model = new Backbone_Model(array('id'=>'1938273', 'title'=>"Moby Dick", 'author'=>"Herman Melville", 'length'=>321), array('sync'=>$sync));
        $res = $model->save(null, $options);
        $this->assertEquals("UPDATE books SET id = ?, title = ?, author = ?, length = ? WHERE ((id = '1938273'))", $maladapter->sql);
        $this->assertEquals(array('1938273', 'Moby Dick', 'Herman Melville', 321), $maladapter->bind);
        $this->assertFalse($res, "Update model failed");
        $this->assertTrue($error instanceof Zend_Db_Adapter_Exception, "Error set to exception");
        $this->assertEquals('Query failed in maladapter', $error->getMessage());
        $error = false;
        
        $model = new Backbone_Model(array('id'=>'1938273', 'title'=>"Moby Dick", 'author'=>"Herman Melville", 'length'=>321), array('sync'=>$sync));
        $res = $model->destroy($options);
        $this->assertEquals("DELETE FROM books WHERE ((id = '1938273'))", $maladapter->sql);
        $this->assertFalse($res, "Destroy model failed");
        $this->assertTrue($error instanceof Zend_Db_Adapter_Exception, "Error set to exception");
        $this->assertEquals('Query failed in maladapter', $error->getMessage());
    }
    
    /**
     * DB Table: Error in selector functions are caught
     */
    public function testDBTableErrorInSelector() {
        $error = false;
        $options = array('error'=>function($model, $_error, $options) use (&$error) { $error = $_error; });
        
        $sync = new Backbone_Sync_DB_Table(array('table'=>'nonexistent_table'));
        $model = new Backbone_Model(array('id'=>3), array('sync'=>$sync));
        $res = $model->fetch($options);
        $this->assertFalse($res, "Fetch from nonexistent table failed");
        $this->assertTrue($error instanceof Zend_Db_Table_Exception, "Failed to build the proper query");
    }
}
