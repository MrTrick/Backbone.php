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

class Test_Model_OverridableValidate extends Test_Model {
    protected $_validate = null;
    public function setValidate($function) { $this->_validate = $function; }
    public function validate(array $attrs, array $options = array()) { $func=$this->_validate; return $func ? $func($attrs, $options) : null; }
}

/**
 * Class definitions for testing overriden aspects of the model - used by the test function with that name. 
 */
class Test_ModelDefaults1_Model extends Test_Model {
    protected $defaults = array("one" => 1, "two" => 2);
}
class Test_ModelDefaults2_Model extends Test_Model {
    protected function defaults() { return array("one" => 3, "two" => 4); }
}
class Test_ModelInheritClassPropertiesParent_Model extends Test_Model {
    public function instancePropSame() { return 1; }
    public function instancePropDiff() { return 2; }
    public static function classProp() { return 3; }
}
class Test_ModelInheritClassPropertiesChild_Model extends Test_ModelInheritClassPropertiesParent_Model {
    public function instancePropDiff() { return 4; }
}        

/**
 * Test the Model class
 * @author Patrick Barnes
 *
 */
class Test_Model_TestCase extends PHPUnit_Framework_TestCase {
    protected $name = "Backbone.Model";
    
    protected $doc;
    protected $collection;
    
    protected $lastRequest;
    
    public function setUp() {
        //Create a sample model and collection
        $this->doc = new Test_Model(array(
            'id' => '1-the-tempest',
            'title' => 'The Tempest', 
            'author' => 'Bill Shakespeare',
            'length' => 123
        ));
        $this->collection = new Test_Collection();
        $this->collection->add($this->doc);
        
        //Override the default 'sync' method
        $this->lastRequest = $lastRequest = new stdClass();
        Backbone::setDefaultSync(function($method, Backbone_Model $model, $options) use (&$lastRequest) {
           $lastRequest->method = $method;
           $lastRequest->model = $model;
           $lastRequest->options = $options;
           
           $lastRequest->attributes = $model->attributes(); //Equivalent of the Backbone.js unit test's 'ajaxParams'
           
           //If 'async' mode set, let the test to call the success function itself.
           if (empty($options['async'])) call_user_func($options['success'], $model, array(), $options);
           return true; 
        });
    }
    
    /**
     * Internal: Quick sanity test to ensure that the internal classes work
     */
    public function testInternalTestClasses() {
        $test_model = new Test_Model();
        $subclass = Test_Model::buildClass(array('staticMember'=>'bar'));
        $test_model_extended = new $subclass();
        
        $this->assertNotEquals('Test_Model', $subclass);
        $this->assertTrue($test_model_extended instanceof Test_Model);
        
        $this->assertEquals('bar', $subclass::$staticMember);
        $this->assertEquals('foo', Test_Model::$staticMember);
    }
    
    /**
     * Model: Initialize
     * (due to magic __set / __get, actually stores in attributes) 
     */
    public function testModelInitialize() {
        $test = $this;
        $collection = new Backbone_Collection();
        $model = new Test_Model(array(), array(
        	'collection'=>$collection,
            'initialize'=>function($attrs, $options, $model) use ($test) { 
                $model->one = 1;
                $test->assertSame($model->collection(), $options['collection']);
            } 
        ));
        $this->assertEquals(1, $model->one);
        $this->assertSame($collection, $model->collection());
    }
    
    /**
     * Model: Initialize with attributes and options
     */
    public function testModelInitializeWithAttrs() {
        $model = new Test_Model(array(), array('one' => 1,
            'initialize' => function($attrs, $options, $model) { $model->one = $options['one']; }
        ));
        
        $this->assertEquals(1, $model->one);
    }
    
    /**
     * Model: Initialize with parsed attributes
     */
    public function testModelWithParsedAttrs() {
        $model = new Test_Model(array('value'=>1), array('parse'=>true,
            'parse'=> function($in) { $in['value']+=1; return $in; }
        ));
        
        $this->assertEquals($model->value, 2); 
    }
    
    /**
     * Model: url 
     */
    public function testModelUrl() {
        $this->doc->collection()->url('/collection');
        $this->assertEquals($this->doc->url(), '/collection/1-the-tempest');
        $this->doc->collection()->url('/collection/');
        $this->assertEquals($this->doc->url(), '/collection/1-the-tempest');
        $this->doc->collection(null);
        try {
            $this->doc->url();
            $this->fail();
        } catch (LogicException $e) { }
    }
    
    /**
     * Model: url when using urlRoot, and uri encoding
     */
    public function testModelUrlRootAndURIEncoding() {
        $model = new Backbone_Model();
        $model->urlRoot('/collection');
        $this->assertEquals('/collection', $model->url());
        $model->set(array('id'=>'+1+'));
        $this->assertEquals('/collection/%2B1%2B', $model->url());
    }
    
    /**
     * Model: url when using urlRoot as a function to determine urlRoot at runtime
     */
    public function testModelUrlRootAsFunction() {
        $model = new Test_Model(array('parent_id'=>1), array(
            'urlRoot' => function($urlRoot, $model) { return '/nested/' . $model->get('parent_id') . '/collection'; }
        ));
        $this->assertEquals('/nested/1/collection', $model->url());
        $model->set(array('id'=>2));
        $this->assertEquals('/nested/1/collection/2', $model->url());
    }
    
    /**
     * Model: clone
     */
    public function testModelClone() {
        $a = new Backbone_Model(array( 'foo' => 1, 'bar' => 2, 'baz' => 3));
        $b = clone $a;
        $this->assertEquals(1, $a->foo);
        $this->assertEquals(2, $a->bar);
        $this->assertEquals(3, $a->baz);
        $this->assertEquals($a->foo, $b->foo, "Foo should be the same on the clone.");
        $this->assertEquals($a->bar, $b->bar, "Bar should be the same on the clone.");
        $this->assertEquals($a->baz, $b->baz, "Baz should be the same on the clone.");
        $a->foo = 100;
        $this->assertEquals(100, $a->foo);
        $this->assertEquals(1, $b->foo, "Changing a parent attribute does not change the clone.");
    }
    
    /**
     * Model: isNew
     */
    public function testModelIsNew() {
        $a = new Backbone_Model(array('foo' => 1, 'bar' => 2, 'baz' => 3));
        $this->assertTrue($a->isNew(), "Model should be new");
        $a = new Backbone_Model(array('foo' => 1, 'bar' => 2, 'baz' => 3, 'id' => -5));
        $this->assertFalse($a->isNew(), "Any defined ID is legal, negative or positive");
        $a = new Backbone_Model(array('foo' => 1, 'bar' => 2, 'baz' => 3, 'id' => 0));
        $this->assertFalse($a->isNew(), "Any defined ID is legal, including zero");
        $a = new Backbone_Model(array());
        $this->assertTrue($a->isNew(), "New when there is no id");
        $a = new Backbone_Model(array('id' => 2));
        $this->assertFalse($a->isNew(), "Not new for a positive integer");
        $a = new Backbone_Model(array('id' => -5));
        $this->assertFalse($a->isNew(), "Not new for a negative integer");
    }
    
    /**
     * Model: get
     */
    public function testModelGet() {
        $this->assertEquals('The Tempest', $this->doc->get('title'));
        $this->assertEquals('Bill Shakespeare', $this->doc->get('author'));
        $this->assertEquals('The Tempest', $this->doc->title);
        $this->assertEquals('Bill Shakespeare', $this->doc->author);    
    }
    
    /**
     * Model: escape
     */
    public function testModelEscape() {
        $this->assertEquals('The Tempest', $this->doc->escape('title'));
        $this->doc->set(array('audience' => 'Bill & Bob'));
        $this->assertEquals('Bill &amp; Bob', $this->doc->escape('audience'));
        $this->doc->set(array('audience' => 'Tim > Joan'));
        $this->assertEquals('Tim &gt; Joan', $this->doc->escape('audience'));
        $this->doc->set(array('audience' => 10101));
        $this->assertEquals('10101', $this->doc->escape('audience'));
        unset( $this->doc->audience );
        $this->assertEquals('', $this->doc->escape('audience'));
    }
    
    /**
     * Model: has
     */
    public function testModelHas() {
        $model = new Backbone_Model();
        $this->assertFalse($model->has('name'));
        $this->assertFalse(isset($model->name));
        
        $model->set(array(
            '0' => 0,
            '1' => 1,
            'true' => true,
            'false' => false,
            'empty' => '',
            'name' => 'name',
            'null' => null,
            //'undefined' => undefined No such PHP equivalent. 
        ));
        
        $this->assertTrue($model->has('0'));
        $this->assertTrue($model->has('1'));
        $this->assertTrue($model->has('true'));
        $this->assertTrue($model->has('false'));
        $this->assertTrue($model->has('empty'));
        $this->assertTrue($model->has('name'));
        
        unset($model->name);
        
        $this->assertFalse($model->has('name'));
        $this->assertFalse($model->has('null'));
    }
    
    /**
     * Model: set and unset
     */
    public function testModelSetUnset() {
        $a = new Test_Model(array('id' => 'id', 'foo' => 1, 'bar' => 2, 'baz' => 3));
        $changeCount = 0;
        $a->on("change:foo", function() use (&$changeCount) { $changeCount += 1; });
        $a->foo = 2;
        $this->assertEquals(2, $a->foo, "Foo should have changed");
        $this->assertEquals(1, $changeCount, "Change count should have incremented");
        $a->foo = 2; //set with value that is not new shouldn't fire change event
        $this->assertEquals(2, $a->foo, "Foo should NOT have changed, still 2");
        $this->assertEquals(1, $changeCount, "Change count should NOT have incremented");
        
        $test = $this;
        $a->setFunc('validate', function($attrs, $options=array()) use ($test) {
            $test->assertTrue(array_key_exists('foo', $attrs), "don't ignore values when unsetting");
            $test->assertEquals(null, $attrs['foo'], "don't ignore values when unsetting");
        });
        unset($a->foo);
        $this->assertEquals(null, $a->foo, "Foo should have changed");
        $this->assertEquals(2, $changeCount, "Change count should have incremented for unset");
        $a->setFunc('validate', null);
        
        unset($a->id);
        $this->assertEquals(null, $a->id(), "Unsetting the id attribute should remove the id");
    }
    
    /**
     * Model: multiple unsets
     */
    public function testModelMultipleUnsets() {
        $i = 0;
        $counter = function() use (&$i) { $i++; };
        $model = new Backbone_Model(array('a'=>1));
        $model->on('change:a', $counter);
        $model->a = 2;
        unset($model->a);
        unset($model->a);
        $this->assertEquals(2, $i, "Unset does not fire an event for missing attributes.");
    }
    
    /**
     * Model: unset and changedAttributes()
     */
    public function testModelUnsetAndChangedAttributes() {
        $model = new Backbone_Model(array('a'=>1));
        $model->set('a', null, array('unset'=>true, 'silent'=>true));
        $changedAttributes = $model->changedAttributes();
        $this->assertArrayHasKey('a', $changedAttributes, 'changedAttributes should contain unset properties');
        
        $changedAttributes = $model->changedAttributes();
        $this->assertArrayHasKey('a', $changedAttributes, 'changedAttributes should contain unset properties when running changedAttributes again after an unset');
    } 

    /**
     * Model: using a non-default id attribute.
     */
    public function testModelNonDefaultId() {
        $nondefault = Backbone::uniqueId('NonDefault_');
        eval('class '.$nondefault.' extends Backbone_Model { protected $idAttribute = "_id"; }');
        $this->assertTrue(class_exists($nondefault));
        
        $model = new $nondefault(array('id' => 'eye-dee', '_id' => 25, 'title' => 'Model'));
        $this->assertEquals('eye-dee', $model->id);
        $this->assertEquals(25, $model->id());
        $this->assertFalse($model->isNew());
        unset($model->_id);
        $this->assertEquals(null, $model->id());
        $this->assertTrue($model->isNew());
    }
    
    /**
     * Model: set an empty string
     */
    public function testModelSetEmptyString() {
        $model = new Backbone_Model(array('name' => 'Model'));
        $model->name = '';
        $this->assertEquals('', $model->name);
    }
    
    /**
     * Model: clear
     */
    public function testModelClear() {
        $test = $this;
        $changed = false;
        $model = new Backbone_Model(array('id' => 1, 'name' => 'Model'));
        $model->on('change:name', function() use (&$changed) { $changed = true; });
        $model->on('change', function() use ($model, $test) {
           $changedAttrs = $model->changedAttributes();
           $test->assertArrayHasKey('name', $changedAttrs);
        });
        $model->clear();
        $this->assertTrue($changed);
        $this->assertNull($model->name);
    }
    
    /**
     * Model: defaults
     */
    public function testModelDefaults() {
        $model = new Test_ModelDefaults1_Model(array('two'=>null));
        $this->assertEquals(1, $model->one);
        $this->assertNull($model->two);
        $model = new Test_ModelDefaults2_Model(array('two'=>null));
        $this->assertEquals(3, $model->one);
        $this->assertNull($model->two);
    }
    
    /**
     * Model: change, hasChanged, changedAttributes, previous, previousAttributes
     */
    public function testModelChangedHasChangedAttributesPreviousAttributes() {
        $test = $this; /* @var $test Test_Model_TestCase */
        $changed = false;
        $model = new Backbone_Model(array('name' => 'Tim', 'age' => 10));
        $this->assertFalse($model->changedAttributes());
        $model->on('change', function() use ($test, $model, &$changed) {
            $test->assertTrue($model->hasChanged('name'), 'name changed');
            $test->assertFalse($model->hasChanged('age'), 'age did not');
            $test->assertEquals(array('name' => 'Rob'), $model->changedAttributes(), 'changedAttributes returns the changed attrs');
            $test->assertEquals('Tim', $model->previous('name'));
            $test->assertEquals(array('name' => 'Tim', 'age' => 10), $model->previousAttributes(), 'previousAttributes is correct');
            $changed = true;
        });
        $this->assertFalse($model->hasChanged());
        $this->assertFalse($model->hasChanged(null));
        $model->set('name', 'Rob', array('silent'=>true));
        $this->assertTrue($model->hasChanged());
        $this->assertTrue($model->hasChanged(null));
        $this->assertTrue($model->hasChanged('name'));
        $model->change();
        $this->assertTrue($changed, 'ran the expected assertions');
        
        $this->assertEquals('Rob', $model->name);
    }
    
    /**
     * Model: changedAttributes
     */
    public function testModelChangedAttributes() {
        $model = new Backbone_Model(array('a' => 'a', 'b' => 'b'));
        $this->assertFalse($model->changedAttributes());
        $this->assertFalse($model->changedAttributes(array('a' => 'a')));
        $changedAttributes = $model->changedAttributes(array('a' => 'b'));
        $this->assertArrayHasKey('a', $changedAttributes);
        $this->assertEquals('b', $changedAttributes['a']); 
    }
    
    /**
     * Model: change with options
     */
    public function testModelChangeWithOptions() {
        $value = 'foo';
        $model = new Backbone_Model(array('name' => 'Rob'));
        $model->on('change', function(Backbone_Model $model, $options) use (&$value) {
            $value = $options['prefix'] . $model->name;
        });
        $model->set('name', 'Bob', array('silent'=>true));
        $model->change(array('prefix' => 'Mr. '));
        $this->assertEquals('Mr. Bob', $value);
        $model->set('name', 'Sue', array('prefix'=> 'Ms. '));
        $this->assertEquals('Ms. Sue', $value);
    }
    
    /**
     * Model: change after initialize
     */
    public function testModelChangeAfterInitialize() {
        $changed = 0;
        $attrs = array('id' => 1, 'label' => 'c');
        $obj = new Backbone_Model($attrs);
        $obj->on('change', function() use (&$changed) { $changed += 1; });
        $obj->set($attrs);
        $this->assertEquals(0, $changed);
    }
    
    /**
     * Model: save within change event
     */
    public function testModelSaveWithinChangeEvent() {
        $lastModel = null;
        $model = new Backbone_Model(array('firstName' => 'Taylor', 'lastName' => 'Swift'));
        $model->on('change', function($_model) { $_model->save(); });
        $model->sync(function($method, $_model, $options) use (&$lastModel) {
            $lastModel = $_model;
            call_user_func($options['success'], $_model, array(), $options); //Signal success
            return true;
        });
        $model->lastName = 'Hicks';
        $this->assertSame($model, $lastModel);
    }
    
    /**
     * Model: validate after save
     */
    public function testModelValidateAfterSave() {
        $lastError = null;
        $model = new Test_Model(array(), array(
            'validate' => function($attrs) { if (isset($attrs['admin'])) return "Can't change admin status."; } 
        ));
        $model->sync(function($method, $_model, $options) {
            call_user_func($options['success'], $_model, array('admin'=>true), $options); //Signal success, setting an (invalid) attribute.
            return true;
        });
        $result = (bool)$model->save(null, array('error' => function($model, $error) use (&$lastError) {
            $lastError = $error;
        }));
        $this->assertFalse($result);
        $this->assertEquals("Can't change admin status.", $lastError);
    }
    
    /**
     * Model: isValid
     */
    public function testModelIsValid() {
        $model = new Test_Model(array('valid'=>true), array(
            'validate' => function($attrs, $options=array()) { if (empty($attrs['valid'])) return "invalid"; }
        ));
        $this->assertTrue($model->isValid());
        $this->assertFalse((bool)$model->set('valid', false));
        $this->assertTrue($model->get('valid'));
        $this->assertTrue($model->isValid());
        $this->assertTrue((bool)$model->set('valid', false, array('silent'=>true)));
        $this->assertFalse($model->isValid());
    }
    
    /**
     * Model: save
     */
    public function testModelSave() {
        $this->doc->save(array('title' => 'Henry V'));
        $this->assertEquals('update', $this->lastRequest->method);
        $this->assertSame($this->doc, $this->lastRequest->model);
    }
    
    /**
     * Model: save in positional style
     */
    public function testModelSavePositional() {
        $model = new Backbone_Model();
        $model->sync(function($method, $_model, $options) { 
            call_user_func($options['success'], $_model, array(), $options);
            return true;
        });
        $model->save('title', 'Twelfth Night');
        $this->assertEquals('Twelfth Night', $model->title);
    }
    
    /**
     * Model: fetch
     */
    public function testModelFetch() {
        $this->doc->fetch();
        $this->assertEquals('read', $this->lastRequest->method);
        $this->assertSame($this->doc, $this->lastRequest->model);
    }
    
    /**
     * Model: destroy
     */
    public function testModelDestroy() {
        $this->doc->destroy();
        $this->assertEquals('delete', $this->lastRequest->method);
        $this->assertSame($this->doc, $this->lastRequest->model);
        
        $newModel = new Backbone_Model();
        $this->assertFalse($newModel->destroy());
    }
    
    /**
     * Model: non-persisted destroy
     */
    public function testModelNonPersistedDestroy() {
        $a = new Backbone_Model(array('foo' => 1, 'bar' => 2, 'baz' => 3));
        $a->sync(function() { throw new Exception("Should not be called"); });
        $a->destroy();
        $this->assertTrue(true, "Non-persisted model should not call sync");
    }
    
    /**
     * Model: validate
     */
    public function testModelValidate() {
        $lastError = null;
        $model = new Test_Model(array(), array(
            'validate' => function($attrs, $options=array(), $model) { 
                if (array_key_exists('admin', $attrs) and $attrs['admin'] != $model->admin) return "Can't change admin status."; 
            } 
        ));
        $model->on('error', function($model, $error) use (&$lastError) {
            $lastError = $error;
        });
        $result = $model->set('a', 100);
        $this->assertSame($model, $result);
        $this->assertEquals(100, $model->a);
        $this->assertEquals(null, $lastError);
        $result = $model->set(array('admin' => true), array('silent' => true));
        $this->assertEquals(true, $model->admin);
        $result = $model->set(array('a' => 200, 'admin' => false));
        $this->assertFalse($result);
        $this->assertEquals(100, $model->a);
    }

    /**
     * Model: validate on unset and clear
     */
    public function testModelValidateOnUnsetAndClear() {
        $errorFlag = false;
        $model = new Test_Model(array('name' => 'One'), array(
            'validate' => function($attrs) use (&$errorFlag) {
                if (empty($attrs['name'])) { $errorFlag = true; return "No thanks"; }
            }
        ));
        $model->name = 'Two';
        $this->assertEquals('Two', $model->name);
        $this->assertFalse($errorFlag);
        unset($model->name);
        $this->assertTrue($errorFlag);
        $this->assertEquals('Two', $model->name);
        $model->clear();
        $this->assertEquals('Two', $model->name);
        $model = new Backbone_Model();
        $model->name = 'Two';
        $this->assertEquals('Two', $model->name);
        $model->clear();
        $this->assertEquals(null, $model->name);
    }
    
    /**
     * Model: validate with error callback
     */
    public function testModelValidateWithErrorCallback() {
        $lastError = null;
        $boundError = null;
        $model = new Test_Model_OverridableValidate();
        $model->setValidate(function($attrs) {
            if (!empty($attrs['admin'])) return "Can't change admin status.";
        });
        $callback = function($model, $error) use (&$lastError) {
            $lastError = $error;
        };
        $model->on('error', function($model, $error) use (&$boundError) {
            $boundError = true;
        });
        $result = $model->set('a', 100, array('error'=>$callback));
        $this->assertEquals($model, $result);
        $this->assertEquals(100, $model->a);
        $this->assertNull($lastError);
        $this->assertNull($boundError);
        $result = $model->set(array('a' => 200, 'admin' => true), array('error'=>$callback));
        $this->assertFalse($result);
        $this->assertEquals(100, $model->a);
        $this->assertEquals("Can't change admin status.", $lastError);
        $this->assertNull($boundError);
    }
    
    /**
     * Model: defaults always extend attrs (#459)
     */
    public function testModelDefaultsAlwaysExtendAttrs() {
        $test = $this;
        $model_class = Backbone::uniqueId('Model_');
        eval('class '.$model_class.' extends Test_Model { protected $defaults = array("one"=>1); }');
        $this->assertTrue(class_exists($model_class));
        
        $model = new $model_class(array(), array(
            'initialize' => function($attrs, $options, $model) use ($test) { $test->assertEquals(1, $model->one); }
        ));
        $model = new $model_class();
        $this->assertEquals(1, $model->one);
    }
    
    /**
     * Model: Inherit class properties
     */
    public function testModelInheritClassProperties() {
        $adult = new Test_ModelInheritClassPropertiesParent_Model();
        $kid = new Test_ModelInheritClassPropertiesChild_Model();
        
        $this->assertEquals( Test_ModelInheritClassPropertiesParent_Model::classProp(), Test_ModelInheritClassPropertiesChild_Model::classProp() );
        $this->assertNotNull( Test_ModelInheritClassPropertiesParent_Model::classProp() );
        
        $this->assertEquals( $kid->instancePropSame(), $adult->instancePropSame() );
        $this->assertNotNull( $kid->instancePropSame() );
        
        $this->assertNotEquals( $kid->instancePropDiff(), $adult->instancePropDiff() );
        $this->assertNotNull( $kid->instancePropDiff() );
    }
    
    /**
     * Model: Nested change events don't clobber previous attributes
     */
    public function testModelNestedChangesDontClobber() {
        $test = $this;
        $called_a = false;
        $called_b = false;
        $model = new Backbone_Model();
        $model->on('change:state', function($model, $newState) use (&$called_a, $test) {
            $called_a = true;
            $test->assertEquals(null, $model->previous('state'));
            $test->assertEquals('hello', $newState);
            //Fire a nested change event.
            $model->set('other', 'whatever');
        })
        ->on('change:state', function($model, $newState) use (&$called_b, $test) {
            $called_b = true;
            $test->assertEquals(null, $model->previous('state'));
            $test->assertEquals('hello', $newState);
        })
        ->set('state', 'hello');
        
        $this->assertTrue($called_a);
        $this->assertTrue($called_b);
    }
    
    /**
     * Model: hasChanged/set should use same comparison
     */
    public function testModelHasChangedSetShouldUseSameComparison() {
        $test = $this;
        $changed = 0;
        $changed_a = 0;
        $model = new Backbone_Model(array('a'=>null));
        $model->on('change', function() use ($test, $model, &$changed) {
            $test->assertTrue($model->hasChanged('a'));
            $changed++;
        })
        ->on('change:a', function() use (&$changed_a) {
            $changed_a++;
        })
        ->set('a', false);
        $this->assertEquals(1, $changed);
        $this->assertEquals(1, $changed_a);
    }
    
    /**
     * Model: #582, #425, change:attribute callbacks should fire after all changes have occurred
     */
    public function testModelChangeAttributeCallbacksAfterAllChanges() {
        $test = $this;
        $model = new Backbone_Model();
        $calls = 0;
        
        $assertion = function() use ($test, $model, &$calls) {
            $test->assertEquals('a', $model->a);
            $test->assertEquals('b', $model->b);
            $test->assertEquals('c', $model->c);
            $calls++;
        };
        
        $model->on('change:a', $assertion);
        $model->on('change:b', $assertion);
        $model->on('change:c', $assertion);
        
        $model->set(array('a'=>'a', 'b'=>'b', 'c'=>'c'));
        $this->assertEquals(3, $calls);
    }
    
    /**
     * Model: #871 set with attributes property
     */
    public function testModelSetWithAttributesProperty() {
        $model = new Backbone_Model();
        $model->set(array('attributes'=>true));
        $this->assertTrue($model->has('attributes'));
        $this->assertEquals(true, $model->get('attributes'));
        
        //Also test for magic setter - PHP only
        $model2 = new Backbone_Model();
        $model2->attributes = true;
        $this->assertTrue($model2->has('attributes'));
        $this->assertEquals(true, $model2->attributes);
    }
    
    /**
     * Model: set value regardless of equality/change
     */
    public function testModelSetValueRegardlessOfEquality() {
        $model = new Backbone_Model(array('x'=>new stdClass()));
        $a = new stdClass();
        $this->assertNotSame($a, $model->x);
        $model->x = $a;
        $this->assertSame($a, $model->x);
    }
    
    /**
     * Model: unset fires change for null attributes
     */
    public function testModelUnsetFiresChangeForNullAttributes() {
        $changed = false;
        $model = new Backbone_Model(array('x'=>null));
        $model->on('change:x', function() use (&$changed) { $changed = true; });
        unset($model->x);
        $this->assertTrue($changed);
    }
    
    /**
     * Model: set: null values
     */
    public function testModelSetNullValues() {
        $model = new Backbone_Model(array('x'=>null));
        $this->assertArrayHasKey('x', $model->attributes());
    }
    
    /**
     * Model: change fires change:attr
     */
    public function testModelChangeFiresChangeAttr() {
        $changed = false;
        $model = new Backbone_Model(array('x'=>1));
        $model->set('x', 2, array('silent'=>true));
        $model->on('change:x', function() use (&$changed) { $changed = true; });
        $model->change();
        $this->assertTrue($changed);
    }
    
    /**
     * Model: hasChanged is false after original values are set 
     */
    public function testModelHasChangedFalseAfterOriginalSet() {
        $triggered_change = false;
        $model = new Backbone_Model(array('x'=>1));
        $model->on('change:x', function() use (&$triggered_change) { $triggered_change = true; });
        $model->set('x', 2, array('silent'=>true));
        $this->assertTrue($model->hasChanged());
        $model->set('x', 1, array('silent'=>true));
        $this->assertFalse($model->hasChanged());
        $this->assertFalse($triggered_change);
    }
    
    /**
     * Model: Save with 'wait' succeeds without 'validate'
     */
    public function testModelSaveWithWaitWithoutValidate() {
        $model = new Backbone_Model();
        $model->save(array('x'=>1), array('wait'=>true));
        $this->assertSame($model, $this->lastRequest->model);
    }
    
    /**
     * Model: 'hasChanged' for falsey keys
     */
    public function testModelHasChangedForFalseyKeys() {
        $model = new Backbone_Model();
        $model->set(array('x' => true), array('silent'=>true));
        $this->assertFalse($model->hasChanged(0));
        $this->assertFalse($model->hasChanged(''));
    }
    
    /**
     * Model: 'previous' for falsey keys
     */
    public function testModelPreviousForFalseyKeys() {
        $model = new Backbone_Model(array(0=>true, ''=>true));
        $model->set(array(0=>false, ''=>false), array('silent'=>true));
        $this->assertEquals(true, $model->previous(0));
        $this->assertEquals(true, $model->previous(''));
    }
    
    /**
     * Model: `save` with `wait` sends correct attributes
     */
    public function testModelSaveWithWaitSendsCorrectAttributes() {
        $changed = 0;        
        $model = new Backbone_Model(array('x'=>1, 'y'=>2));
        $model->on('change:x', function() use (&$changed) { $changed++; });
        //Call save with wait in async mode, and it won't be changed until sync is successful
        //(PHP only: without 'async', requires the sync function to call error or success before returning.) 
        $model->save(array('x'=>3), array('wait'=>true, 'async'=>true));
        $this->assertEquals(array('x'=>3, 'y'=>2), $this->lastRequest->attributes);
        $this->assertEquals(1, $model->x, 'Model has not changed yet - waiting for success');
        $this->assertEquals(0, $changed);
        //Trigger the success callback, and the model will change
        call_user_func($this->lastRequest->options['success'], $model, array(), $this->lastRequest->options);
        $this->assertEquals(3, $model->x);
        $this->assertEquals(1, $changed);
    }
    
    /**
     * Model: a failed `save` with `wait` doesn't leave attributes behind
     */
    public function testModelFailedSaveWaitDoesntLeaveAttributes() {
        $model = new Backbone_Model();
        Backbone::setDefaultSync(function($method, $model, $options) { call_user_func($options['error'], $model, array(), $options); });
        $model->save(array('x'=>1), array('wait'=>true));
        $this->assertFalse($model->has('x'));
    }
    
    /**
     * Model: #1030 - `save` with `wait` results in correct attributes if success is called during sync
     */
    public function testModelSaveWithWaitCorrectIfSuccessDuringSync() {
        //Not really relevant for php, but included for completeness.
        $changed = 0;        
        $model = new Backbone_Model(array('x'=>1, 'y'=>2));
        Backbone::setDefaultSync(function($method, $model, $options) { call_user_func($options['success'], $model, array(), $options); });
        $model->on('change:x', function() use (&$changed) { $changed++; });
        $model->save(array('x'=>3), array('wait'=>true));
        $this->assertEquals(3, $model->x);
        $this->assertEquals(1, $changed);
    }
    
    /**
     * Model: save with wait validates attributes
     */
    public function testModelSaveWithWaitValidatesAttributes() {
        $validated = 0;
        $model = new Test_Model_OverridableValidate();
        $model->setValidate(function() use (&$validated) { $validated++; });
        $model->save(array('x'=>1), array('wait'=>true));
        $this->assertEquals(1, $validated);
    }
    
    /**
     * Model: nested `set` during `'change:attr'`
     */
    public function testModelNestedSetDuringChangeAttr() {
        $events = array();
        $model = new Backbone_Model();
        $model->on('all', function($event) use (&$events) { $events[] = $event; });
        $model->on('change', function() use ($model) {
            $model->set('z', true, array('silent'=>true));
        });
        $model->on('change:x', function() use ($model) {
            $model->set('y', true);
        });
        $model->set('x', true);
        $this->assertEquals(array('change:y', 'change:x', 'change'), $events);
        $events = array();
        $model->change();
        $this->assertEquals(array('change:z', 'change'), $events);
    }
    
    /**
     * Model: nested `change` only fires once
     */
    public function testModelNestedChangeOnlyFiresOnce() {
        $changes = 0;
        $model = new Backbone_Model();
        $model->on('change', function() use (&$changes, $model) {
            $changes++;
            $model->change();
        });
        $model->x = true;
        $this->assertEquals(1, $changes);
    }
    
    /**
     * Model: no `'change'` event if no changes
     */
    public function testModelNoChangeEventIfNoChanges() {
        $test = $this;
        $model = new Backbone_Model();
        $model->on('change', function() use ($test) { $test->fail(); });
        $model->change();
    }
    
    /**
     * Model: nested `set` during `'change'`
     */
    public function testNestedSetDuringChange() {
        $count = 0; $test = $this;
        $model = new Backbone_Model();
        //Should be called 3 times.
        $model->on('change', function() use (&$count, $test, $model) {
            switch($count++) {
                case 0:
                  $test->assertEquals(array('x'=>true), $model->changedAttributes());
                  $test->assertEquals(null, $model->previous('x'));
                  $model->y = true;
                  break;
                case 1:
                  $test->assertEquals(array('y'=>true), $model->changedAttributes());
                  $test->assertEquals(true, $model->previous('x'));
                  $model->z = true;
                  break;
                case 2:
                  $test->assertEquals(array('z'=>true), $model->changedAttributes());
                  $test->assertEquals(true, $model->previous('y'));
                  break;
                default:
                  $test->fail();
            }
        });
        $model->x = true;
        $this->assertEquals(3, $count);
    }
    
    /**
     * Model: nested `'change'` with silent
     */
    public function testModelNestedChangeWithSilent() {
        $count = 0; $test = $this;
        $model = new Backbone_Model();
        $model->on('change', function() use (&$count, $test, $model) {
           switch($count++) {
             case 0:
               $test->assertEquals(array('x'=>true), $model->changedAttributes());
               $model->set('y', true, array('silent'=>true));
               break;
             case 1:
               $test->assertEquals(array('y'=>true, 'z'=>true), $model->changedAttributes());
               break;
             default:
               $test->fail();
           } 
        });
        $model->x = true;
        $model->z = true;
        $this->assertEquals(2, $count);
    }
    
    /**
     * Model: nested `'change:attr'` with silent
     */
    public function testModelNestedChangeAttrWithSilent() {
        $changes = 0;
        $model = new Backbone_Model();
        $model->on('change:y', function() use (&$changes) { $changes++; });
        $model->on('change', function() use ($model) {
            $model->set('y', true, array('silent'=>true));
            $model->set('z', true);
        });
        $model->set('x',true);
        $this->assertEquals(1, $changes);
    }
    
    /**
     * Model: multiple nested changes with silent
     */
    public function testModelMultipleNestedChangesWithSilent() {
        $test = $this; $count=0;
        $model = new Backbone_Model();
        $model->on('change:x', function() use ($model) {
            $model->set('y', 1, array('silent'=>true));
            $model->set('y', 2);
        });
        $model->on('change:y', function($model, $val) use ($test, &$count) {
            $test->assertEquals(2, $val);
            $count++;
        });
        $model->x = true;
        $model->change();
        $this->assertEquals(1, $count);
    }

    /**
     * Model: multiple nested changes with silent 2
     */
    public function testModelMultipleNestedChangesWithSilent2() {
        $changes = array();
        $model = new Backbone_Model();
        $model->on('change:b', function($model, $val) use (&$changes) { $changes[] = $val; });
        $model->on('change', function() use ($model) {
            $model->set('b', 1);
            $model->set('b', 2, array('silent'=>true));
        });
        $model->set('b', 0);
        $this->assertEquals(array(0,1,1), $changes);
        $model->change();
        $this->assertEquals(array(0,1,1,2,1), $changes);
    }
    
    /**
     * Model: nested set multiple times
     */
    public function testModelNestedSetMultipleTimes() {
        $count = 0;
        $model = new Backbone_Model();
        $model->on('change:b', function() use (&$count) { $count++; });
        $model->on('change:a', function() use ($model) {
            $model->b = true;
            $model->b = true;
        });
        $model->a = true;
        $this->assertEquals(1, $count);
    }
    
    /**
     * Model: Backbone.wrapError triggers 'error'
     * PHP Only: wrapError isn't used, so test the functions that use sync()
     *  Check that fetch(), save(), destroy() will trigger callback / event appropriately.
     */
    public function testModelSyncTriggersCallbackOrError() {
        $test = $this;
        $triggered = $called = false;
        
        $model = new Backbone_Model(array('id'=>0));
        $resp = new stdClass();
        
        //Call error on sync()
        $model->sync(function($method, $model, $options) use ($resp) {
            call_user_func($options['error'], $model, $resp, $options);
        });

        //Error trigger handler
        $model->on('error', function($_model, $_resp, $options) use ($test, &$model, &$resp, &$triggered) { 
            $triggered = true; 
            $test->assertSame($model, $_model);
            $test->assertSame($resp, $_resp);
            $test->assertArrayHasKey('foo', $options);
        });
        
        //Error options handler
        $err_handler = function($_model, $_resp, $options) use ($test, &$model, &$resp, &$called) { 
            $called = true; 
            $test->assertSame($model, $_model);
            $test->assertSame($resp, $_resp);
            $test->assertArrayHasKey('foo', $options);
        };
        
        //Expect triggering:
        $options = array('foo'=>'bar');
        $triggered = $called = false;
        $model->fetch($options);
        $this->assertTrue($triggered and !$called);
        $triggered = $called = false;
        $model->save(null, $options);
        $this->assertTrue($triggered and !$called);
        $triggered = $called = false;
        $model->destroy($options);
        $this->assertTrue($triggered and !$called);
        
        //Expect calling
        $options = array('foo'=>'bar', 'error'=>$err_handler);
        $triggered = $called = false;
        $model->fetch($options);
        $this->assertTrue($called and !$triggered);
        $triggered = $called = false;
        $model->save(null, $options);
        $this->assertTrue($called and !$triggered);
        $triggered = $called = false;
        $model->destroy($options);
        $this->assertTrue($called and !$triggered);
    }

    /**
     * Model: #1179 - isValid returns true in the absence of validate
     * PHP Only: Can't _remove_ the default empty validate function.
     */
    public function testModel1179isValidEmptyValidate() {
        $model = new Backbone_Model();
        $this->assertTrue($model->isValid());
    }
    
    /**
     * Model: #1122 - clear does not alter options
     * PHP Only: $options won't be modified anyway, it's passed by value and is an array.
     */
    public function testModel1122ClearDoesNotAlterOptions() {
        $model = new Backbone_Model();
        $options = array();
        $model->clear($options);
        $this->assertArrayNotHasKey('unset', $options);
    }
    
    /**
     * Model: #1122 - unset does not alter options
     * PHP Only: $options won't be modified anyway, it's passed by value and is an array.
     */
    public function testModel1122UnsetDoesNotAlterOptions() {
        $model = new Backbone_Model();
        $options = array();
        $model->_unset('x', $options);
        $this->assertArrayNotHasKey('unset', $options);
    }
    
    /**
     * Model: #1355 - `options` is passed to success callbacks
     */
    public function testModel1355OptionsIsPassedToSuccessCallbacks() {
        $test = $this;
        $model = new Backbone_Model();
        $count = 0;
        $options = array(
            'foo' => 'bar',
        	'success'=>function($model,$response,$options) use ($test, &$count) { 
                $count++;
                $test->assertEquals('bar', $options['foo']);
            }
        );
        $model->sync(function($method, $model, $options) { call_user_func($options['success']); });
        $model->save(array('id', 1), $options);
        $model->fetch($options);
        $model->destroy($options);
        $this->assertEquals(3, $count);
    }
    
    /**
     * Model: #1412 - Trigger 'sync' event.
     */
    public function testModel1412TriggerSyncEvent() {
        $count = 0;
        $model = new Backbone_Model(array('id'=>1));
        $model->sync(function($method, $model, $options) { call_user_func($options['success']); });
        $model->on('sync', function() use (&$count) { $count++; });
        $model->fetch();
        $model->save();
        $model->destroy();
        $this->assertEquals(3, $count);
    }
    
    /**
     * Model: #1365 - Destroy: New models execute success callback.
     */
    public function testModel1365DestroyNewModelExecuteSuccessCallback() {
        $test = $this;
        $count = 0;
        $model = new Backbone_Model();
        $model->on('sync', function() use ($test) { $test->fail(); })
        ->on('destroy', function() use (&$count) { $count++; })
        ->destroy(array('success'=>function() use (&$count) { $count++; }));
        $this->assertEquals(2, $count);
    }
    
    /**
     * Model: #1433 - Save: An invalid model cannot be persisted.
     */
    public function testModel1433SaveInvalidNotPersisted() {
        $test = $this;
        $model = new Test_Model(array(), array(
        	'validate'=>function() { return 'invalid'; }
        ));
        $model->sync(function() use ($test) { $test->fail(); });
        $this->assertFalse($model->save());
    }
    
    //----------------------------------------------------------------------------
    //Backbone.php-specific tests

    /**
     * Backbone_Model: cid()
     */
    public function testBMCid() {
        $model = new Backbone_Model();
        $a = $model->cid();
        $_a = $model->cid();
        $model = new Backbone_Model();
        $b = $model->cid();
        $this->assertEquals($a, $_a);
        $this->assertNotEquals($a, $b);
    }
    
    /**
     * Backbone_Model: misbehaving sync function is detected by fetch/save/destroy
     */
    public function testBMMisbehavingSync() {
        $model = new Backbone_Model(array('id'=>1));
        $model->sync(function() { /* Do nothing */ });
        try { $model->fetch(); $this->fail(); } 
        catch (LogicException $e) {}
        try { $model->save(); $this->fail(); } 
        catch (LogicException $e) {}
        try { $model->destroy(); $this->fail(); } 
        catch (LogicException $e) {}
    }
    
    /**
     * Backbone_Model: Ensure that toJSON returns a json-encoded object string
     */
    public function testBMToJSON() {
        $model = new Backbone_Model(array('a'=>1, 'b'=>"two", 'c'=>array(3,4,5), 'd'=>array('six'=>6,'seven'=>'seven')));
        $this->assertEquals('{"a":1,"b":"two","c":[3,4,5],"d":{"six":6,"seven":"seven"}}', $model->toJSON());
        
        $model = new Backbone_Model();
        $this->assertEquals('{}', $model->toJSON());
    }
    
    /**
     * Backbone_Model: Ensure that parse can handle both array and string inputs.
     */
    public function testBMParse() {
        $model = new Backbone_Model('{"a":1,"b":"two","c":[3,4,5],"d":{"six":6,"seven":"seven"}}', array('parse'=>true));
        $this->assertEquals(array('a'=>1, 'b'=>"two", 'c'=>array(3,4,5), 'd'=>array('six'=>6,'seven'=>'seven')), $model->attributes());
        
        $model = new Backbone_Model(array('a'=>1, 'b'=>"two", 'c'=>array(3,4,5), 'd'=>array('six'=>6,'seven'=>'seven')), array('parse'=>true));
        $this->assertEquals(array('a'=>1, 'b'=>"two", 'c'=>array(3,4,5), 'd'=>array('six'=>6,'seven'=>'seven')), $model->attributes());
    }
    
    /**
     * Backbone_Model: Exported class name is correct for the original model, and implicit/explicit
     */
    public function testBMExportClassName() {
        $this->assertEquals('Backbone.Model', Backbone_Model::getExportedClassName());
        $this->assertEquals('Test_Model', Test_Model::getExportedClassName());
        $class = Test_Model::buildClass(array('exported_classname'=>'MyClass'));
        $this->assertEquals('MyClass', $class::getExportedClassName());
    }
    
    /**
     * Backbone_Model: Exported object contains expected attributes
     */
    public function testBMExportedObject() {
        $model = new Backbone_Model(array('x'=>true));
        $this->assertEquals('{"x":true}', $model->export());
        
        $class = Test_Model::buildClass(array());
        $model = new $class(array('a'=>1, 'b'=>"two", 'c'=>array(3,4,5), 'd'=>array('six'=>6,'seven'=>'seven')));
        $this->assertEquals(
        	'{"a":1,"b":"two","c":[3,4,5],"d":{"six":6,"seven":"seven"}}', 
            $model->export()
        );
        
        $model = new Backbone_Model();
        $this->assertEquals('{}', $model->export());
    }
    
    /**
     * Backbone_Model : Can't export the class for Backbone_Model
     */
    public function testBMCantExportBMClass() {
        try {
            Backbone_Model::exportClass();
            $this->fail();
        } catch (LogicException $e) { 
        }
    }
    
    /**
     * Backbone_Model : Exported class contains expected definition
     */
    public function testBMExportClass() {
        $definition = Test_Model::exportClass();
        $this->assertStringStartsWith('var Test_Model = Backbone.Model.extend({', $definition);
    }
    
    /**
     * Backbone_Model : Exported subclass extends from the parent's exported class name
     */
    public function testBMExportClassParent() {
        $class = Test_Model::buildClass(array());
        $definition = $class::exportClass();
        $this->assertStringStartsWith('var '.$class.' = Test_Model.extend({', $definition);
    }
    
    /**
     * Backbone_Model: Exported class contains nominated members
     */
    public function testBMExportClassMembers() {
        $class = Test_Model::buildClass(array());
        $definition = $class::exportClass();
        $this->assertContains('idAttribute: "id"', $definition, "Exported by default" );
        
        $class = Test_Model::buildClass(array('foo'=>array("a" => true)));
        $definition = $class::exportClass();
        $this->assertNotContains('foo: {"a":true}', $definition, "Not named, so not exported" );
        
        $class = Test_Model::buildClass(array('foo'=>array("a" => true), 'exported_fields' => array('foo')));
        $definition = $class::exportClass();
        $this->assertContains('foo: {"a":true}', $definition, "Named, so exported" );
    }
    
    /**
     * Backbone_Model: Exported functions aren't escaped
     */
    public function testBMExportClassFunctions() {
        $class = Test_Model::buildClass(array('exported_functions'=>array('foo' => 'function() { alert("hi!"); }')));
        $definition = $class::exportClass();
        $this->assertContains('foo: function() { alert("hi!"); }', $definition);
    }
    
    /**
     * Backbone_Model: Clone copies urlRoot, sync - not collection or triggers
     */
    public function testBMClone() {
        $test = $this;
        $sync = function() {};
        $collection = new Backbone_Collection();
        $model = new Backbone_Model();
        $model->set(array('id'=>1, 'name'=>'Fred'));
        $model->on('custom', function() use ($test) { $test->fail(); } );
        $model->sync($sync);
        $model->collection($collection);
        $model->urlRoot('/foo');
        
        $model2 = clone $model;
        $this->assertEquals(array('id'=>1, 'name'=>'Fred'), $model2->attributes());
        $this->assertSame($sync, $model2->sync());
        $this->assertEquals('/foo', $model2->urlRoot());
        $this->assertNull($model2->collection());
        $model2->trigger('custom');
    }
    
    /**
     * Backbone_Model: Event registration passed on construction
     */
    public function testBMEventOnConstruction() {
        $called = false;
        $col = new Backbone_Model(array(), array('on'=>array('custom'=>function() use (&$called) { $called = true; })));
        $col->trigger('custom');
        $this->assertTrue($called);
    }
}


