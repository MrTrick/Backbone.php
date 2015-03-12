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

class Test_Sync_Ldap_Collection extends Zend_Ldap_Collection {
	public function __construct(Iterator $iterator) { $this->_iterator = $iterator; }
	public function close() {}
}

class Test_Sync_TestCase extends PHPUnit_Framework_TestCase {
    protected $name = "Backbone.Sync";
    
    /**
     * Create a child class of the given parent, with the given static attributes.
     * @param unknown $parent
     * @param unknown $static
     * @return string
     */
    public static function buildClass($parent, $static, $class_name = null) {
    	$class_name = $class_name ? $class_name : Backbone::uniqueId('Test_'.$parent.'_');
    	$attrs = array();
    	$static_members = implode("\n", array_map(function($v) { return 'public static $'.$v.';'; }, array_keys($static)));
    	eval('class '.$class_name.' extends '.$parent.' {'.$static_members.'}');
    	foreach($static as $k=>$v) $class_name::$$k = $v;
    	return $class_name;
    }
    
    public static function setUpBeforeClass() {
        try {
            $db = Zend_Db::factory('Pdo_Sqlite', array('dbname'=>':memory:'));
            $db->query('CREATE TABLE IF NOT EXISTS "books" ( "id" INTEGER PRIMARY KEY AUTOINCREMENT, "title" NOT NULL, "author" TEXT, "length" INTEGER );');
            $db->query('CREATE TABLE IF NOT EXISTS "people" ( "id" INTEGER PRIMARY KEY, "name" NOT NULL, "height" INTEGER, "weight" INTEGER );');            
            Zend_Db_Table::setDefaultAdapter($db);
        } catch (Zend_Db_Adapter_Exception $ex) {
            echo "Error connecting to database: ".$ex->getMessage(); exit;
        }
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
    
    
    /**
     * Ensure that the LDAP filter builder works as the documentation says
     */
    public function testLdapSyncFilter() {
    	$collection = new Backbone_Collection();
    	$client = $this->getMock('Zend_Ldap');
    	$sync = new Backbone_Sync_Ldap(array('client'=>$client));
    	($Paramable = self::buildClass('Backbone_Sync_Ldap', array('filterable_attributes'=>array('one_id', 'two_id'))));
    	$sync_paramable = new $Paramable(array('client'=>$client));
    	($BaseFilter = self::buildClass('Backbone_Sync_Ldap', array('base_filters'=>array('(objectClass=LongHairedFreakyPeople)'), 'filterable_attributes'=>array('one_id', 'two_id'))));
    	$sync_base = new $BaseFilter(array('client'=>$client));
    	 
    	//Test every considered permutation of filters
    	$valid_filters = array(
    		//Empty
    	    "No filter"=>array($sync, "(objectClass=*)", array()),
    		"Empty filter"=>array($sync, "(objectClass=*)", array('filter'=>array())),
    		//Filter options and Zend_Ldap_Filter objects
    		"Expect Zend_Ldap_Filters to be passed straight through"=>array($sync, "(FOO)", array('filter'=>Zend_Ldap_Filter::string("FOO"))),
    		"Expect to work with ['params']['filter'] too" => array($sync, "(FOO)", array('params'=>array('filter'=>Zend_Ldap_Filter::string("FOO")))),
    		"Expect filters to be ANDed together if defined in options AND params"=>array($sync, "(&(FOO)(BAR))", array('filter'=>Zend_Ldap_Filter::string("FOO"), 'params'=>array('filter'=>Zend_Ldap_Filter::string("BAR")))),
    		//Accepting params
    		"If no filterable_attributes defined, ignores parameters."=>array($sync,"(objectClass=*)", array('params'=>array('person_id'=>'42'))),
    		"Ignores parameters that aren't filterable."=>array($sync_paramable, "(objectClass=*)", array('params'=>array('three_id'=>'42'))),
			"Accepts filterable parameters."=>array($sync_paramable, "(&(one_id=42)(two_id=22))", array('params'=>array('one_id'=>'42', 'three_id'=>array('Ooh', 'Invalid', 'Param'), 'two_id'=>'22'))),
    		"Combines params and option/param filters"=>array($sync_paramable, "(&(FOO)(BAR)(one_id=42))", array('params'=>array('one_id'=>'42', 'filter'=>Zend_Ldap_Filter::string("BAR")), 'filter'=>Zend_Ldap_Filter::string("FOO"))),
			//Accepting base_filters
			"Base filter only"=>array($sync_base, "(objectClass=LongHairedFreakyPeople)", array()),
    		"base_filters is combined with other filters"=>array($sync_base, "(&(objectClass=LongHairedFreakyPeople)(FOO))", array('filter'=>Zend_Ldap_Filter::string("FOO"))),
    		"base_filters is combined with params"=>array($sync_base, "(&(objectClass=LongHairedFreakyPeople)(one_id=42))", array('params'=>array('one_id'=>42))),
			//Parsing valid filter syntax
			"Simple filter is accepted"=>array($sync_paramable, "(one_id=42)", array('filter'=>array('attr'=>'one_id','value'=>42))),
			"Simple 1-element filter"=>array($sync_paramable, "(one_id=42)", array('filter'=>array('attr'=>'one_id','value'=>array(42)))),
			"Simple 2-element filter"=>array($sync_paramable, "(|(one_id=42)(one_id=22))", array('filter'=>array('attr'=>'one_id','value'=>array(42,22)))),
    		"Logical AND filter"=>array($sync_paramable, "(&(one_id=42)(two_id=22))", array('filter'=>array('&'=>array( array('attr'=>'one_id', 'value'=>42), array('attr'=>'two_id', 'value'=>22))))),
    		"Logical OR filter"=>array($sync_paramable, "(|(one_id=22)(two_id=44))", array('filter'=>array('|'=>array( array('attr'=>'one_id', 'value'=>22), array('attr'=>'two_id', 'value'=>44))))),
    		"Logical NOR filter."=>array($sync_paramable, "(!(|(one_id=12)(one_id=23)))", array('filter'=>array('!'=>array( array('attr'=>'one_id', 'value'=>12), array('attr'=>'one_id', 'value'=>23))))),
    		"Single element AND/OR collapses to self"=>array($sync_paramable, "(one_id=12)", array('filter'=>array('&'=>array( array('attr'=>'one_id', 'value'=>12))))),
    		"Single element NOR collapses to NOT"=>array($sync_paramable, "(!(one_id=12))", array('filter'=>array('!'=>array( array('attr'=>'one_id', 'value'=>12))))),
    		"Shortcut logical filter is expanded."=>array($sync_paramable, "(&(one_id=42)(two_id=22))", array('filter'=>array('&'=>array( 'one_id' => 42, 'two_id' => 22)))),
    		"Shortcut OR filter is expanded"=>array($sync_paramable, "(|(one_id=42)(one_id=22))", array('filter'=>array('attr'=>'one_id', 'value'=>array(42,22)))),
    		"Shortcut OR param is expanded"=>array($sync_paramable, "(|(one_id=42)(one_id=22))", array('params'=>array('one_id'=>array(42,22)))),
    		"Nested shortcut still works"=>array($sync_paramable, "(|(one_id=42)(one_id=22))", array('filter'=>array('&'=>array(array('attr'=>'one_id', 'value'=>array(42,22)))))),
    		"A 0-element filter is ignored"=>array($sync_paramable, "(one_id=42)", array('filter'=>array('&'=>array('one_id'=>42, 'two_id'=>array())))),
    		//Escaping control characters
    		"Special characters are escaped"=>array($sync_paramable, "(one_id=Escape! \\29\\28\\2a\\5c)", array('filter'=>array('attr'=>'one_id','value'=>"Escape! )(*\\"))),
    		"Leading wildcard allowed"=>array($sync_paramable, "(one_id=*FOO\\2aBAR)", array('filter'=>array('attr'=>'one_id','value'=>"*FOO*BAR"))),
    		"Trailing wildcard allowed"=>array($sync_paramable, "(one_id=FOO\\2aBAR*)", array('filter'=>array('attr'=>'one_id','value'=>"FOO*BAR*"))),
    		"Double wildcard allowed"=>array($sync_paramable, "(one_id=*FOO\\2aBAR*)", array('filter'=>array('attr'=>'one_id','value'=>"*FOO*BAR*"))),
    		"Wildcard allowed"=>array($sync_paramable, "(one_id=*)", array('filter'=>array('attr'=>'one_id', 'value'=>'*'))),
    		//Meaningless filters are ignored
    		"Empty filter is ignored"=>array($sync_paramable, "(objectClass=*)", array('filter'=>new Zend_Ldap_Filter_And(array()))),
    		"Empty sub-filter is ignored"=>array($sync_paramable, "(one_id=42)", array('filter'=>array('&'=>array(array("attr"=>"one_id", "value"=>42),array('&'=>array())))))
    	);
    	
    	foreach($valid_filters as $desc=>$row) {
    		list($_sync, $expected, $options) = $row;
    		$filter = $_sync->getSearchFilter($collection, $options);
    		$this->assertEquals($expected, $filter, $desc);
    	}
    	 
    	//Test invalid filters    		
    	$invalid_filters = array(
    	    'oops',
    		array('attr'=>'one_id'),
    		array('attr'=>'one_id', 'value'=>Zend_Ldap_Filter::any('objectClass')),
    		array('|', array('one_id'=>'bar')),
    		array('&'=>array('one_id'=>'bar', 'two_id'=>Zend_Ldap_Filter::any('objectClass'))),
    		array('&'=>array('one_id'=>'bar'), 'somethingelse'),
    		array('one_id'=>1, 'two_id'=>2),
    		array('attr'=>'three_id', 'value'=>3),
    		array('&'=>array('one_id'=>1, 'two_id'=>2, 'three_id'=>3)),
    		//array('attr'=>'one_id', 'value'=>array('assoc'=>'for','some'=>'reason')) Not currently checked
    		array('attr'=>'one_id', 'value'=>array( array('foo', 'foo') )) //Value is too deeply nested, wrong.
    	);
    	foreach($invalid_filters as $invalid) {
	    	try { 
	    		$filter = $sync_paramable->getSearchFilter($collection, array('filter'=>$invalid)); 
	    		$this->fail("Invalid filter type shouldn't be allowed: ".json_encode($invalid));
	    	}
	    	catch(PHPUnit_Framework_AssertionFailedError $e) { throw $e; } 
	    	catch(Exception $e) { $this->assertEquals("InvalidArgumentException", get_class($e)); }
    	}
	}
	
	/**
	 * Check that the right arguments are passed through to the LDAP client when reading a model,
	 * and that the model is populated correctly.
	 */
	public function testLdapSyncReadModel() {
		//Set up the expectations for how Zend_Ldap::search will be called, and the value it returns
		$client = $this->getMockBuilder('Zend_Ldap')->disableOriginalConstructor()->getMock();
		$client->expects($this->once())
		       ->method('search')
		       ->with(
					$this->equalTo('(objectClass=*)'),
		       		$this->equalTo('cn=Tiny Tim,ou=Lollypop Guild,o=Oz'),
		       		$this->equalTo(Zend_Ldap::SEARCH_SCOPE_BASE),
		       		$this->equalTo(array())
		       )
			   ->will($this->returnValue(new Test_Sync_Ldap_Collection(new ArrayIterator(array(
			   		array('dn'=>'cn=Tiny Tim,ou=Lollypop Guild,o=Oz', 'cn'=>array('Tiny Tim'), 'foo'=>array(1), 'bar'=>array(2))
			   )))));

		//Build and fetch a model
		$model = new Backbone_Model();
		$model->id('cn=Tiny Tim,ou=Lollypop Guild,o=Oz');
		$model->sync(new Backbone_Sync_Ldap(array('client'=>$client)));
		$success = $model->fetch();
		
		//Check the model...
		$this->assertTrue((bool)$success, "Fetched the model successfully");
		$this->assertEquals(array('Tiny Tim'), $model->get('cn'), "cn was populated in the model");
		$this->assertNull($model->get('dn'), "cn was not populated in the model");
	}
	
	/**
	 * Check that the right arguments are passed through to the LDAP client when reading a collection
	 */
	public function testLdapSyncReadCollection() {
		//Set up the expectations for how Zend_Ldap::search will be called, and the value it returns
		$client = $this->getMockBuilder('Zend_Ldap')->disableOriginalConstructor()->getMock();
		$client->expects($this->once())
		->method('search')
		->with(
				$this->equalTo('(&(objectClass=Munchkin)(givenName=Tiny))'),
				$this->equalTo('ou=Lollypop Guild,o=Oz'),
				$this->equalTo(Zend_Ldap::SEARCH_SCOPE_SUB),
				$this->equalTo(array('cn','givenName','sn','foo')),
				$this->equalTo("givenName")
		)
		->will($this->returnValue(new Test_Sync_Ldap_Collection(new ArrayIterator(array(
				array('dn'=>'cn=Tiny Tim,ou=Lollypop Guild,o=Oz', 'cn'=>array('Tiny Tim'), 'givenName'=>array('Tiny'), 'sn'=>array('Tim'), 'foo'=>array(1,2)),
				array('dn'=>'cn=Tiny Ted,ou=Lollypop Guild,o=Oz', 'cn'=>array('Tiny Ted'), 'givenName'=>array('Tiny'), 'sn'=>array('Ted'), 'foo'=>array(3,4))
		)))));
		
		//Build a sync class that's configured for the test 
		$Sync = self::buildClass('Backbone_Sync_Ldap', array(
		    'baseDn'=>'ou=Lollypop Guild,o=Oz',
			'base_filters'=>array('(objectClass=Munchkin)'),
			'filterable_attributes'=>array('cn','givenName','sn'),
			'returned_attributes'=>array('cn','givenName','sn','foo')
		));
		
		//Build and fetch a collection
		$collection = new Backbone_Collection();
		$collection->sync(new $Sync(array('client'=>$client)));
		$success = $collection->fetch(array('params'=>array('givenName'=>'Tiny','foo'=>1, 'sort'=>'givenName')));
		
		//Check the collection
		$this->assertTrue((bool)$success, "Fetched the collection successfully");
		$this->assertEquals(2, $collection->count(), "Found two results");
		$model = $collection->first();
		$this->assertInstanceOf('Backbone_Model', $model, "Retrieved first model");
		$this->assertEquals('cn=Tiny Tim,ou=Lollypop Guild,o=Oz', $model->id(), "Id set correctly");
		$this->assertNull($model->dn, "'dn' attribute stripped");
		$this->assertEquals(array('Tim'), $model->sn, "Returned attribute set");
	}
	
	/**
	 * Ticket #54 - If fetched attributes fail validation, should not 'blame' the sync function.   
	 */
	public function testFetchAndFailValidation() {
		$sync = new Backbone_Sync_Array(array('data'=>array(1=>array('id'=>1, 'name'=>'Foo!'))));
		
        $model_class = Backbone::uniqueId('Model_');
        eval('class '.$model_class.' extends Backbone_Model { 
        	public function validate(array $attrs, array $options=array()) {
        		return "You know what you did wrong...";
    		}
        }');
        $this->assertTrue(class_exists($model_class));
        
        $model = new $model_class(array('id'=>1)); /* @var $model Backbone_Model */
        $this->assertEquals(1, $model->id());
        $was_error = false;
        $self = $this;
        $result = $model->fetch(array('sync'=>$sync, 'error'=>function($model, $errors, $options) use (&$was_error, $self) {
        	$self->assertEquals("You know what you did wrong...", $errors, "Expected validation error occurs");
        	$was_error = true;
        }));
        $this->assertFalse((bool)$result, "Fetch failed - but didn't throw an exception (The bug)");
        $this->assertTrue($was_error, "Fetch failed - error handler called");        
	}
	
	
	public function setUp() {
	    $this->lastModel = null;
	    $this->lastBody = null;
	    $this->lastOptions = null;
	    $this->lastError = false;
	    $self = &$this;
	    
	    $this->defaultOptions = array(
	        'success' => function($model, $body, $options) use (&$self) {
	            $self->lastModel = $model;
	            $self->lastBody = $body;
	            $self->lastOptions = $options;
	            $self->lastError = false; 
	        },
	        'error' => function($model, $error, $options) use (&$self) {
	            $self->lastModel = $model;
	            $self->lastBody = null;
	            $self->lastOptions = $options;
	            $self->lastError = $error;	            
	        }
	    );
	}
	
	public function testArrayCreate() {
	    $sync = new Backbone_Sync_Array(array('data'=>array()));
	    $this->defaultOptions['sync'] = $sync;
	    
	    $model1 = new Backbone_Model(array('id'=>1, 'name'=>'Fred'));
	    $model1->save(null, $this->defaultOptions);
	    $this->assertFalse($this->lastError, 'Created model successfully');
	    $this->assertEquals($this->lastModel, $model1, 'Checking callback args');
	    $this->assertEquals($this->lastBody, array('id'=>1, 'name'=>'Fred'), 'Checking callback args');
	    $this->assertEquals(1, count($sync->data()), 'Exists in backend');
	    
	    $model2 = new Backbone_Model(array('id'=>2, 'name'=>'Joe'));
	    $model2->save(null, $this->defaultOptions);
	    $this->assertEquals(2, count($sync->data()), 'Exists in backend');
	    
	    $this->assertEquals(array(1=>array('id'=>1, 'name'=>'Fred'), 2=>array('id'=>2, 'name'=>'Joe')), $sync->data(), 'Stored properly in backend');
	}
	
	
	/**
	 * Test that the exception factory works properly 
	 */
	public function testBackboneExceptionFactory() {
	    //All args are passed through to constructor correctly
	    $ex = Backbone_Exception::factory('{"message":"Not found etc","code":404,"something_else":"blah blah blah"}', 404, new Exception("INNER"), array(1,2,3,4));
	    $this->assertEquals("Not found etc", $ex->getMessage());
	    $this->assertEquals(404, $ex->getCode());
	    $this->assertEquals("INNER", $ex->getPrevious()->getMessage());
	    $this->assertEquals(array(1,2,3,4), $ex->body);
	    
	    //Child classes are identified correctly
	    $ex = Backbone_Exception::factory("Foo!", 400);
	    $this->assertInstanceOf('Backbone_Exception', $ex);
	    $ex = Backbone_Exception::factory("Foo!", 401);
	    $this->assertInstanceOf('Backbone_Exception_Unauthorized', $ex);
	    $ex = Backbone_Exception::factory("Foo!", 403);
	    $this->assertInstanceOf('Backbone_Exception_Forbidden', $ex);	    
	    $ex = Backbone_Exception::factory("Foo!", 404);
	    $this->assertInstanceOf('Backbone_Exception_NotFound', $ex);
	    
	    //Code in the error overwrites code in the parameter
	    $ex = Backbone_Exception::factory('{"message":"Not found etc","code":404,"something_else":"blah blah blah"}', 200);
	    $this->assertInstanceOf('Backbone_Exception_NotFound', $ex);
	    $this->assertEquals(404, $ex->getCode());
	}
	
	/**
	 * Related to #390 - test the defer option
	 */
	public function testSyncDefer() {
	    $this->_setUpDB();
	    $sync = new Backbone_Sync_DB_Table(array('table'=>'books'));
	    $model = new Backbone_Model(array('id'=>'1938273', 'title'=>"Moby Dick", 'author'=>"Herman Melville", 'length'=>321));
	    $model->sync($sync);
	    
	    //Try some invalid uses first - defer should be checked
	    try {
	        $model->save('author', 'Engelbert Humperdink', array('defer'=>"Invalid value"));
	        $this->fail('Should require callable $options["defer"].');
	    } 
	    catch (InvalidArgumentException $e) { $this->assertEquals("Invalid 'defer' option, must be callable", $e->getMessage()); }
	    try {
	        $model->save('author', 'Engelbert Humperdink', array('defer'=>function() { }));
	        $this->fail('Will trip the "not async" save test.');
	    } 
	    catch (LogicException $e) { $this->assertEquals("Sync function did not invoke callbacks correctly", $e->getMessage()); }
	    
	    //Define the *correct* options
	    $triggerSuccess = null;
	    $count = 0;
	    $options = array(
            'async'=>true, //We will not be calling success right away.
            'defer'=>function($cl) use (&$triggerSuccess) { $triggerSuccess = $cl; } //Saves the closure
	    );
	    
	    //Register some 'success' listeners.
	    $options['success'] = function() use (&$count) { $count+=1; };
	    $sync->on('update', function() use (&$count) { $count+=2; });
	    $model->on('save', function() use (&$count) { $count+=4; });
	    
	    //Save the model!
	    $res = $model->save('author', 'Engelbert Humperdink', $options);
	    $this->assertSame($model, $res);
	    
	    //It should have saved successfully.
	    $this->assertEquals('Engelbert Humperdink', $model->author, "Saved successfully");
	    $model = new Backbone_Model(array('id'=>'1938273'), array('sync'=>$sync));
	    $model->fetch();
	    $this->assertEquals('Engelbert Humperdink', $model->author);
	    
	    //However, the listeners shouldn't have been called yet.
	    $this->assertEquals(0, $count, "No success listeners called yet");
	    $this->assertTrue(is_callable($triggerSuccess), "A closure was passed to 'defer'");
	    
	    //*Now* call them. 
	    $triggerSuccess();
	    $this->assertEquals(7, $count, "All success listeners called");
	}
	
	/**
	 * Related to #390
	 * Should not pass the 'defer' & 'async' options along to any callbacks
	 */
	public function testSyncDeferOptions() {
        $this->_setUpDB();
        $sync = new Backbone_Sync_DB_Table(array('table'=>'books'));
        $model = new Backbone_Model(array('id'=>'1938273', 'title'=>"Moby Dick", 'author'=>"Herman Melville", 'length'=>321));
        $model->sync($sync);
         
        //Define the *correct* options
        $triggerSuccess = null;
        $options = array(
            'async'=>true, //We will not be calling success right away.
            'defer'=>function($cl) use (&$triggerSuccess) { $triggerSuccess = $cl; }, //Saves the closure
            'bar'=>'foo'
        );
         
        $self = $this;
        $count = 0;
        $checkOptions = function() use ($self, &$count) {
            $options = func_get_arg(func_num_args()-1); //$options is the last arg, but depending on the channel the callback has 3 or 4 args
            $count++;
            $self->assertArrayHasKey('bar', $options);
            $self->assertEquals('foo',$options['bar']);
            $self->assertArrayNotHasKey('async',$options);
            $self->assertArrayNotHasKey('defer',$options);
        };
        
        //Register some 'success' listeners.
        $options['success'] = $checkOptions;
        $sync->on('before_update', $checkOptions);
        $sync->on('update', $checkOptions);
        $model->on('save', $checkOptions);
         
        //Save the model!
        $res = $model->save('author', 'Engelbert Humperdink', $options);
        $this->assertSame($model, $res);
        $this->assertEquals(1, $count, "before_update should have been called regardless");
         
        //It should have saved successfully.
        $this->assertEquals('Engelbert Humperdink', $model->author, "Saved successfully");
        $triggerSuccess();
        $this->assertEquals(4, $count, "Options checked four times");
    }


}
