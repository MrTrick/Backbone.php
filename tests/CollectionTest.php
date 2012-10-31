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

require_once('Backbone.php');
require_once('Backbone/Events.php');
require_once('Backbone/Model.php');
require_once('Backbone/Collection.php');
require_once('common.php');

function debugModelArray($prefix, $arr) {
    echo $prefix.': [';
    foreach($arr as $k=>$v) echo $k.':'.$v.',';
    echo "]\n";
}

/**
 * Test the Collection class
 * @author Patrick Barnes
 *
 */
class Test_Collection_TestCase extends PHPUnit_Framework_TestCase {
    /**
     * @var Backbone_Model
     */
    public $a, $b, $c, $d, $e;
    /**
     * @var Backbone_Collection
     */
    public $col, $otherCol;
    
    protected $lastRequest;
    
    public function setUp() {
        $this->a = new Backbone_Model(array('id'=>3, 'label'=>'a'));
        $this->b = new Backbone_Model(array('id'=>2, 'label'=>'b'));
        $this->c = new Backbone_Model(array('id'=>1, 'label'=>'c'));
        $this->d = new Backbone_Model(array('id'=>0, 'label'=>'d'));
        $this->e = null;
        
        $this->col = new Backbone_Collection(array($this->a, $this->b, $this->c, $this->d));
        $this->otherCol = new Backbone_Collection();
        
        //Override the default 'sync' method
        $this->lastRequest = $lastRequest = new stdClass();
        Backbone::setDefaultSync(function($method, $model, $options) use (&$lastRequest) {
           $lastRequest->method = $method;
           $lastRequest->model = $model;
           $lastRequest->options = $options;
           
           //If 'async' mode set, let the test call the success function itself.
           if (empty($options['async'])) call_user_func($options['success'], $model, array(), $options);
           return true; 
        });
    }
    
    /**
     * Collection: new and sort
     */
    public function testCollectionNewAndSort() {
        //echo '$models: ['.implode(",", array_map(function($m){return (string)$m;},$models))."]\n";
        $this->assertSame($this->a, $this->col->first(), "a should be first.");
        $this->assertSame($this->d, $this->col->last(), "d should be last.");
        $this->col->comparator(function(Backbone_Model $a, Backbone_Model $b) {
            return $a->id() > $b->id() ? -1 : 1;
        });
        $this->col->sort();
        $this->assertSame($this->a, $this->col->first(), "a should be first.");
        $this->assertSame($this->d, $this->col->last(), "d should be last.");
        $this->col->comparator(function(Backbone_Model $m) { return $m->id(); });
        $this->col->sort();
        $this->assertSame($this->d, $this->col->first(), "d should be first.");
        $this->assertSame($this->a, $this->col->last(), "a should be last.");        
        $this->assertEquals(4, $this->col->length());
    }
    
    /**
     * Collection: get, getByCid
     */
    public function testCollectionGetGetByCid() {
        $col = $this->col;
        $col->get(0);
        $this->assertSame($this->d, $col->get(0));
        $this->assertSame($this->b, $col->get(2));
        $this->assertSame($col->first(), $col->getByCid($col->first()->cid()));
        $this->assertSame($col->first(), $col->getByCid($col->first()), "casts from model");
    }
    
    /**
     * Collection: get with non-default ids
     */
    public function testCollectionGetWithNonDefaultId() {
        $col = new Backbone_Collection();
        $MongoModel = Backbone::uniqueId('MongoModel_');
        eval('class '.$MongoModel.' extends Backbone_Model { protected $idAttribute = "_id"; }');
        $this->assertTrue(class_exists($MongoModel));
        $model = new $MongoModel(array('_id'=>100));
        $col->push($model);
        $this->assertSame($model, $col->get(100));
        $model->_id = 101;
        $this->assertSame($model, $col->get(101));
        $this->assertNull($col->get(100));
    }
    
    /**
     * Collection: update index when id changes
     */
    public function testCollectionUpdateIndexWhenIdChanges() {
        $col = new Backbone_Collection();
        $col->add(array(
            array('id'=>0, 'name'=>'one'),
            array('id'=>1, 'name'=>'two')
        ));
        $one = $col->get(0);
        $this->assertEquals('one', $one->name);
        $one->id = 101;
        $this->assertEquals(null, $col->get(0));
        $this->assertEquals('one', $col->get(101)->name);
    }
    
    /**
     * Collection: at
     */
    public function testCollectionAt() {
        $this->assertSame($this->c, $this->col->at(2));
    }
    
    /**
     * Collection: pluck
     */
    public function testCollectionPluck() {
        $this->assertEquals('a b c d', implode(' ', $this->col->pluck('label')));
    }
    
    /**
     * Collection: add
     */
    public function testCollectionAdd() {
        $test = $this;
        $e = new Backbone_Model(array('id'=>10, 'label'=>'e'));
        
        $othercoladd_count = 0;
        $this->otherCol->on('add', function() use (&$othercoladd_count) { $othercoladd_count++; });
        $this->otherCol->add($e);
        
        $coladd_label = null;
        $coladd_options = null;
        $this->col->on('add', function($model, $collection, $options) use ($test, &$coladd_label, &$coladd_options) {
            $coladd_label = $model->label;
            $coladd_options = $options;
            $test->assertEquals(4, $options['index']);
        });
        $this->col->add($e, array('amazing'=>true));
        $this->assertEquals('e', $coladd_label);
        $this->assertEquals(5, $this->col->length());
        $this->assertSame($e, $this->col->last());
        $this->assertEquals(1, $this->otherCol->length());
        $this->assertEquals(1, $othercoladd_count);
        $this->assertArrayHasKey('amazing', $coladd_options);
       
        $f = new Backbone_Model(array('id'=>20, 'label'=>'f'));
        $g = new Backbone_Model(array('id'=>21, 'label'=>'g'));
        $h = new Backbone_Model(array('id'=>22, 'label'=>'h'));
        $atCol = new Backbone_Collection(array($f, $g, $h));
        $this->assertEquals(3, $atCol->length());
        $this->assertEquals(3, count($atCol));
        $atCol->add($e, array('at'=>1));
        $this->assertEquals(4, $atCol->length());
        $this->assertSame($e, $atCol->at(1));
        $this->assertSame($h, $atCol->last());
    }

    /**
     * Collection: add multiple models
     */
    public function testCollectionAddMultipleModels() {
        $col = new Backbone_Collection(array(array('i'=>0), array('i'=>1), array('i'=>9)));
        $col->add(array(array('i'=>2),array('i'=>3),array('i'=>4),array('i'=>5),array('i'=>6),array('i'=>7),array('i'=>8)), array('at'=>2));
        $this->assertEquals(10, $col->length());
        for($i=0; $i<=9; $i++)
            $this->assertEquals($i, $col->at($i)->i);
    }
    
    /**
     * Collection: add ; at should have preference over comparator
     */
    public function testCollectionAddAtShouldHavePreferenceOverComparator() {
        $col = new Backbone_Collection();
        $col->comparator(function($a, $b) { return ($a->id() > $b->id()) ? -1 : 1; });
        
        $col->reset(array(array('id'=>2), array('id'=>3)));
        $col->add(new Backbone_Model(array('id'=>1)), array('at'=>1));

        $this->assertEquals('3 1 2', implode(' ', $col->pluck('id')));
    }
    
    /**
     * Collection: can't add model to collection twice
     */
    public function testCollectionCantAddModelToCollectionTwice() {
        $col = new Backbone_Collection(array(array('id'=>1), array('id'=>2), array('id'=>1), array('id'=>2), array('id'=>3)));
        $this->assertEquals('1 2 3', implode(' ', $col->pluck('id')));
    }
    
    /**
     * Collection: can't add different model with same id to collection twice
     */
    public function testCollectionCantAddDifferentModelToCollectionTwice() {
        $col = new Backbone_Collection();
        $col->unshift(array('id'=>101));
        $col->add(array('id'=>101));
        $this->assertEquals(1, $col->length());
    }
    
    /**
     * Collection: merge in duplicate models with {merge: true}
     */
    public function testCollectionMergeDuplicateModelsWithMergeTrue() {
        $col = new Backbone_Collection();
        $col->add(array(array('id'=>1,'name'=>'Moe'), array('id'=>2,'name'=>'Curly'), array('id'=>3,'name'=>'Larry')));
        $col->add(array('id'=>1,'name'=>'Moses'));
        $this->assertEquals('Moe', $col->first()->name);
        $col->add(array('id'=>1,'name'=>'Moses'), array('merge'=>true));
        $this->assertEquals('Moses', $col->first()->name);
        $col->add(array('id'=>1,'name'=>'Tim'), array('merge'=>true, 'silent'=>true));
        $this->assertEquals('Tim', $col->first()->name);
    }
    
    /**
     * Collection: add model to multiple collections
     */
    public function testCollectionAddModelToMultipleCollections() {
        $test = $this;
        $counter = 0;
        $e_add = 0; $f_add = 0;
        $e = new Backbone_Model(array('id'=>10, 'label'=>'e'));
        $colE = new Backbone_Collection();
        $colF = new Backbone_Collection();
        $e->on('add', function($model, $collection) use (&$counter, $test, $e, $colE, $colF) {
            $counter++;
            $test->assertSame($model, $e);
            if ($counter>1) {
                $test->assertSame($colF, $collection);
            } else {
                $test->assertSame($colE, $collection);
            }
        });
        $colE->on('add', function($model, $collection) use ($test, $e, $colE, &$e_add) {
            $test->assertSame($e, $model);
            $test->assertSame($colE, $collection);
            $e_add++;
        });
        $colF->on('add', function($model, $collection) use ($test, $e, $colF, &$f_add) {
            $test->assertSame($e, $model);
            $test->assertSame($colF, $collection);
            $f_add++;
        });
        $this->assertNull($e->collection());
        $colE->add($e);
        $this->assertSame($colE, $e->collection());
        $colF->add($e);
        $this->assertSame($colE, $e->collection(), "collection not overriden once set");

        $this->assertEquals(2, $counter);
        $this->assertEquals(1, $e_add);
        $this->assertEquals(1, $f_add);
    }
    
    /**
     * Collection: add model with parse
     */
    public function testCollectionAddModelWithParse() {
        $m_class = Backbone::uniqueId('Model_');
        eval('class '.$m_class.' extends Backbone_Model { public function parse($in) { $in["value"]++; return $in; } }');
        $this->assertTrue(class_exists($m_class));

        $col = new Backbone_Collection();
        $col->model($m_class);
        $col->add(array('value'=>1), array('parse'=>true));
        $this->assertEquals(2, $col->at(0)->value);
    }
    
    /**
     * Collection: add model to collection with sort()-style comparator 
     */
    public function testCollectionAddWithCompareSort() {
        $col = new Backbone_Collection();
        $col->comparator(function($a, $b) { return $a->name < $b->name ? -1 : 1; });
        $tom = new Backbone_Model(array('name'=>'Tom'));
        $rob = new Backbone_Model(array('name'=>'Rob'));
        $tim = new Backbone_Model(array('name'=>'Tim'));
        $col->add($tom);
        $col->add($rob);
        $col->add($tim);
        $this->assertEquals(0, $col->indexOf($rob));
        $this->assertEquals(1, $col->indexOf($tim));
        $this->assertEquals(2, $col->indexOf($tom));
    }
    
    /**
     * Collection: comparator that depends on `this`
     * PHP Only: No 'this' inside closures for PHP 5.3 - just test iterator sort.
     */
    public function testCollectionAddWithIteratorSort() {
        $col = new Backbone_Collection();
        $negative = function($num) { return -$num; };
        $col->comparator(function($a) use ($negative) { return $negative($a->id); });
        $col->add(array( array('id'=>1), array('id'=>2), array('id'=>3) ));
        $this->assertEquals('3 2 1', implode(' ', $col->pluck('id')));
    }
    
    /**
     * Collection: remove
     */
    public function testCollectionRemove() {
        $test = $this;
        $col = $this->col; $otherCol = $this->otherCol; $d = $this->d; $a = $this->a;
        $removed_label = null;
        $col->on('remove', function($model, $col, $options) use ($test, &$removed_label) {
            $removed_label = $model->label;
            $test->assertEquals(3, $options['index']);
        });
        $otherCol->on('remove', function($model, $col, $options) use ($test) {
            $test->fail("shouldn't trigger remove on other collection");
        });

        $col->remove($d);
        $this->assertEquals('d', $removed_label);
        $this->assertEquals(3, $col->length());
        $this->assertSame($a, $col->first());
    }
    
    /**
     * Collection: shift and pop
     */
    public function testCollectionShiftAndPop() {
        $col = new Backbone_Collection(array( array('a'=>'a'), array('b'=>'b'), array('c'=>'c') ));
        $this->assertEquals('a', $col->shift()->a);
        $this->assertEquals('c', $col->pop()->c);
    }
    
    /**
     * Collection: slice
     */
    public function testCollectionSlice() {
        $col = new Backbone_Collection(array( array('a'=>'a'), array('b'=>'b'), array('c'=>'c') ));
        $array = $col->slice(1, 3);
        $this->assertEquals(2, count($array));
        $this->assertEquals('b', $array[0]->b);
    }
    
    /**
     * Collection: events are unbound on remove
     */
    public function testCollectionEventsAreUnboundOnRemove() {
        $counter = 0;
        $dj = new Backbone_Model();
        $emcees = new Backbone_Collection(array($dj));
        $emcees->on('change', function() use (&$counter) { $counter++; });
        $dj->name = 'Kool';
        $this->assertEquals(1, $counter);
        $emcees->reset();
        $this->assertEquals(null, $dj->collection());
        $dj->name = 'Shadow';
        $this->assertEquals(1, $counter);
    }
    
    /**
     * Collection: remove in multiple collections
     */    
    public function testCollectionRemoveInMultipleCollections() {
        $modelData = array('id'=>5, 'title'=>'Othello');
        $f_removed = false;
        $e = new Backbone_Model($modelData);
        $f = new Backbone_Model($modelData);
        $f->on('remove', function() use (&$f_removed) { $f_removed = true; });
        $colE = new Backbone_Collection(array($e));
        $colF = new Backbone_Collection(array($f));
        $this->assertNotSame($e, $f);
        $this->assertEquals(1, $colE->length());
        $this->assertEquals(1, $colF->length());
        $colE->remove($e);
        $this->assertFalse($f_removed);
        $this->assertEquals(0, $colE->length());
        $colF->remove($e);
        $this->assertEquals(0, $colF->length(), '"$e" ($f) will be removed from $colF, because has the same id');
        $this->assertTrue($f_removed);
    }
    
    /**
     * Collection: remove same model in multiple collection
     */
    public function testCollectionRemoveSameModelInMultipleCollections() {
        $test = $this;
        $counter = 0; $colE_remove = false; $colF_remove = false;
        $m = new Backbone_Model(array('id'=>5, 'title'=>'Othello'));
        $colE = new Backbone_Collection(array($m));
        $colF = new Backbone_Collection(array($m));
        $m->on('remove', function($model, $collection) use ($test, &$counter, $m, $colE, $colF) {
            $counter++;
            $test->assertSame($m, $model);
            if ($counter>1) {
                $test->assertSame($colE, $collection);
            } else {
                $test->assertSame($colF, $collection);
            }
        });
        $colE->on('remove', function($model, $collection) use ($test, $m, $colE, &$colE_remove) {
            $test->assertSame($m, $model);
            $test->assertSame($colE, $collection);
            $colE_remove=true;
        });
        $colF->on('remove', function($model, $collection) use ($test, $m, $colF, &$colF_remove) {
            $test->assertSame($m, $model);
            $test->assertSame($colF, $collection);
            $colF_remove=true;
        });
        //Remove from the F collection, won't be removed from the E collection
        $colF->remove($m);
        $this->assertEquals(0, $colF->length());
        $this->assertEquals(1, $colE->length());
        $this->assertTrue($colF_remove and !$colE_remove);
        $this->assertEquals(1, $counter);
        $this->assertSame($colE, $m->collection(), 'Was added to colE first, so won\'t be unregistered by removing from colF');
        //Remove from the E collection
        $colE->remove($m);
        $this->assertEquals(null, $m->collection());
        $this->assertEquals(0, $colE->length());
        $this->assertEquals(2, $counter);
        $this->assertTrue($colE_remove);
    }
    
    /**
     * Collection: model destroy removes from all collections
     */
    public function testCollectionModelDestroyRemovesFromAllCollections() {
        $m = new Backbone_Model(array('id'=>5, 'title'=>'Othello'));
        $m->sync(function($method, $model, $options) { call_user_func($options['success']); });
        $colE = new Backbone_Collection(array($m));
        $colF = new Backbone_Collection(array($m));
        $m->destroy();
        $this->assertEquals(0, $colE->length());
        $this->assertEquals(0, $colF->length());
        $this->assertEquals(null, $m->collection);
    }
    
    /**
     * Collection: non-persisted model destroy removes from all collections
     */
    public function testCollectionNonPersistedModelDestroyRemovesFromAllCollections() {
        $test = $this;
        $m = new Backbone_Model(array('title'=>'Othello'));
        $m->sync(function($method, $model, $options) use ($test) { $test->fail("should not be called"); });
        $colE = new Backbone_Collection(array($m));
        $colF = new Backbone_Collection(array($m));
        $m->destroy();
        $this->assertEquals(0, $colE->length());
        $this->assertEquals(0, $colF->length());
        $this->assertEquals(null, $m->collection);
    }
    
    /**
     * Collection: fetch
     */
    public function testCollectionFetch() {
        $this->col->fetch();
        $this->assertEquals('read', $this->lastRequest->method);
        $this->assertSame($this->col, $this->lastRequest->model);
        $this->assertEquals(true, $this->lastRequest->options['parse']);
        
        $this->col->fetch(array('parse'=>false));
        $this->assertEquals(false, $this->lastRequest->options['parse']);
    }
    
    /**
     * Collection: create
     */
    public function testCollectionCreate() {
        $model = $this->col->create(array('label'=>'f'), array('wait'=>true));
        $this->assertEquals('create', $this->lastRequest->method);
        $this->assertSame($model, $this->lastRequest->model);
        $this->assertEquals('f', $model->label);
        $this->assertSame($this->col, $model->collection());
    }
    
    /**
     * Collection: create enforces validation
     */
    public function testCollectionCreateEnforcesValidation() {
        $ValidatingModel = Backbone::uniqueId('Model_');
        eval('class '.$ValidatingModel.' extends Backbone_Model { public function validate(array $attrs, array $options=array()) { return "fail"; } }');
        $this->assertTrue(class_exists($ValidatingModel));
        $col = new Backbone_Collection(array(), array('model'=>$ValidatingModel));
        $this->assertEquals(false, $col->create(array('foo'=>'bar')));
        $this->assertEquals(0, $col->length());
    }
    
    /**
     * Collection: a failing create runs the error callback
     */
    public function testFailingCreateRunsError() {
        $ValidatingModel = Backbone::uniqueId('Model_');
        eval('class '.$ValidatingModel.' extends Backbone_Model { public function validate(array $attrs, array $options=array()) { return "fail"; } }');
        $this->assertTrue(class_exists($ValidatingModel));
        $flag = false;
        $callback = function($model, $error) use (&$flag) { $flag = true; };
        $col = new Backbone_Collection(array(), array('model'=>$ValidatingModel));
        $col->create(array('foo'=>'bar'), array('error'=>$callback));
        $this->assertTrue($flag);
    }
    
    /**
     * Collection: initialize
     */
    public function testCollectionInitialize() {
        $Collection = Backbone::uniqueId('Collection_');
        eval('class '.$Collection.' extends Backbone_Collection { public $one=null; public function initialize($attributes, array $options) { $this->one = 1; } }');
        $this->assertTrue(class_exists($Collection));
        $col = new $Collection();
        $this->assertEquals(1, $col->one);
    }
    
    /**
     * Collection: toJSON
     * PHP Only: Expected to return the encoded string directly, not an array  
     */
    public function testCollectionToJSON() {
        $this->assertEquals('[{"id":3,"label":"a"},{"id":2,"label":"b"},{"id":1,"label":"c"},{"id":0,"label":"d"}]', $this->col->toJSON());
    }
    
    /**
     * Collection: where
     */
    public function testCollectionWhere() {
        $col = new Backbone_Collection(array(
            array('a'=>1),
            array('a'=>1),
            array('a'=>1,'b'=>2),
            array('a'=>2,'b'=>2),
            array('a'=>3)
        ));
        $this->assertEquals(3, count($col->where(array('a'=>1))));
        $this->assertEquals(1, count($col->where(array('a'=>2))));
        $this->assertEquals(1, count($col->where(array('a'=>3))));
        $this->assertEquals(0, count($col->where(array('b'=>1))));
        $this->assertEquals(2, count($col->where(array('b'=>2))));
        $this->assertEquals(1, count($col->where(array('a'=>1,'b'=>2))));
        $this->assertEquals(0, count($col->where(array('c'=>1))));
    }
    
    /**
     * Collection: Underscore methods
     */
    public function testCollectionUnderscoreMethods() {
        $col = $this->col;
        $this->assertEquals('a b c d', implode(' ',$col->map(function($model) { return $model->label; })));
        $this->assertEquals(false, $col->any(function($model) { return $model->id() === 100; }));
        $this->assertEquals(true, $col->any(function($model) { return $model->id() === 0; }));
        $this->assertEquals(1, $col->indexOf($this->b));
        $this->assertEquals(4, $col->length());
        $this->assertEquals(3, count($col->rest()));
        $this->assertNotContains($this->a, $col->rest());
        $this->assertContains($this->d, $col->rest());
        $this->assertFalse($col->isEmpty());
        $this->assertNotContains($this->d, $col->without($this->d));
        $this->assertEquals(3, $col->max(function($model) { return $model->id; })->id());
        $this->assertEquals(0, $col->min(function($model) { return $model->id; })->id());
        $this->assertEquals(array(4,0), array_values(array_map( function($o) { return $o->id()*2; }, $col->filter(function($o){return $o->id() % 2 == 0;}) )));
    }
    
    /**
     * Collection: reset
     */
    public function testCollectionReset() {
        $col = $this->col;
        $resetCount = 0;
        $models = $col->models();
        $col->on('reset', function() use (&$resetCount) { $resetCount += 1; });
        $col->reset();
        $this->assertEquals(1, $resetCount);
        $this->assertEquals(0, $col->length());
        $this->assertEquals(null, $col->last());
        $col->reset($models);
        $this->assertEquals(2, $resetCount);
        $this->assertEquals(4, $col->length());
        $this->assertSame($this->d, $col->last());
        $col->reset(array_map(function($m) { return $m->attributes(); }, $models));
        $this->assertEquals(3, $resetCount);
        $this->assertEquals(4, $col->length());
        $this->assertNotSame($this->d, $col->last());
        $this->assertEquals($this->d->attributes(), $col->last()->attributes());
    }
    
    /**
     * Collection: reset passes caller options
     */
    public function testCollectionResetPassesCallerOptions() {
        $Model = Backbone::uniqueId('Model_');
        eval('class '.$Model.' extends Backbone_Model { 
        	public $model_parameter;
        	public function initialize($attrs, array $options) {
        		$this->model_parameter = $options["model_parameter"];
        	}
    	}');
        $this->assertTrue(class_exists($Model));
        $col = new Backbone_Collection(array(), array('model'=>$Model));
        $col->reset(
            array(array('astring'=>'green', 'anumber'=>1), array('astring'=>'blue', 'anumber'=>2)),
            array('model_parameter'=>'model parameter')
        );
        $this->assertEquals(2, $col->length());
        foreach($col as $model)
            $this->assertEquals('model parameter', $model->model_parameter);
    }
    
    /**
     * Collection: trigger custom events on models
     */
    public function testCollectionTriggerCustomEventsOnModels() {
        $fired = null;
        $this->a->on("custom", function() use (&$fired) { $fired = true; });
        $this->a->trigger("custom");
        $this->assertEquals(true, $fired);
        
        $col_fired = null;
        $this->col->on("col_custom", function() use (&$col_fired) { $col_fired = true; });
        $this->a->trigger("col_custom");
        $this->assertEquals(true, $col_fired);
    }
    
    /**
     * Collection: add does not alter arguments
     */    
    public function testCollectionAddDoesNotAlterArguments() {
        $attrs = array();
        $models = array($attrs);
        $col = new Backbone_Collection();
        $col->add($models);
        $this->assertEquals(1, count($models));
        $this->assertSame($models[0], $attrs);
    }
    
    /**
     * Collection: #714: access `model.collection` in a brand new model.
     */
    public function testCollection714AccessModelCollectionInABrandNewMadel() {
        $Model = Backbone::uniqueId('Model_');
        eval('class '.$Model.' extends Backbone_Model { 
        	public $out;
        	public function set($attrs, $value=null, $options=array()) {
        		if (!$attrs) return $this;
        		$this->out = array("prop"=>$attrs["prop"],"col"=>$this->collection()); 
        		return $this;
    		}
        }');
        $this->assertTrue(class_exists($Model));
        $col = new Backbone_Collection(array(), array('model'=>$Model));
        $col->create(array('prop'=>'value'));
        
        $this->assertEquals('value', $col->first()->out['prop']);
        $this->assertSame($col, $col->first()->out['col']);
    }
    
    /**
     * Collection: #574, remove its own reference to the models array.
     */
    public function testCollection574RemoveOwnReferenceToTheModelsArray() {
        $col = new Backbone_Collection(array(array('id'=>1),array('id'=>2),array('id'=>3),array('id'=>4),array('id'=>5),array('id'=>6)));
        $this->assertEquals(6, $col->length());
        $col->remove($col->models());
        $this->assertEquals(0, $col->length());
    }
    
    /**
     * Collection: #861, adding models to a collection which do not pass validation
     */
    public function testCollection861AddingModelToCollectionNotPassValidation() {
        $Model = Backbone::uniqueId('Model_');
        eval('class '.$Model.' extends Backbone_Model { 
        	public function validate(array $attrs, array $options=array()) { 
        		if (isset($attrs["id"]) and $attrs["id"] == 3) return "id can\'t be 3"; 
    		}
    	}');
        $this->assertTrue(class_exists($Model));
        $col = new Backbone_Collection(array(), array('model'=>$Model));
        $col->add(array(array('id'=>1),array('id'=>2)));
        
        try {
            $col->add(array(array('id'=>3),array('id'=>4),array('id'=>5),array('id'=>6)));
            $this->fail("Can't add an invalid model to a collection");
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Can't add an invalid model to a collection", $e->getMessage());
        }
    }
    
    /**
     * Collection: index with comparator
     */
    public function testCollectionIndexWithComparator() {
        $test = $this;
        $counter = 0;
        $col = new Backbone_Collection(
            array(array('id'=>2), array('id'=>4)),
            array('comparator'=>function($model) { return $model->id(); })
        );
        $col->on('add', function($model, $collection, $options) use ($test, &$counter) {
            if ($model->id()==1) {
                $test->assertEquals(0, $options['index']);
                $test->assertEquals(0, $counter++);
            }
            if ($model->id()==3) {
                $test->assertEquals(2, $options['index']);
                $test->assertEquals(1, $counter++);
            }
        });
        $col->add(array(array('id'=>3), array('id'=>1)));
        $this->assertEquals(2, $counter);
    }
    
    /**
     * Collection: remove comparator once set
     */
    public function testCollectionRemoveComparator() {
        $col = new Backbone_Collection(
            array(array('id'=>3), array('id'=>5), array('id'=>4)),
            array('comparator'=>function($model) { return $model->id(); })
        );
        $this->assertEquals('3 4 5', implode(' ', $col->pluck('id')));
        $col->add(array('id'=>1));
        $this->assertEquals('1 3 4 5', implode(' ', $col->pluck('id')));
        $col->comparator(null);
        $col->add(array('id'=>2));
        $this->assertEquals('1 3 4 5 2', implode(' ', $col->pluck('id')));
    }
    
    /**
     * Collection: throwing during add leaves consistent state
     */
    public function testCollectionThrowingDuringAddLeavesConsistentState() {
        $test = $this;
        $col = new Backbone_Collection();
        $col->on('test', function() use ($test) { $test->fail(); });
        $Model = Backbone::uniqueId('Model_');
        eval('class '.$Model.' extends Backbone_Model { 
        	public function validate(array $attrs, array $options=array()) { 
        		if (empty($attrs["valid"])) return "invalid"; 
    		}
    	}');
        $this->assertTrue(class_exists($Model));
        $col->model($Model);
        $model = new $Model(array('id'=>1, 'valid'=>true));
        try {
            $col->add(array($model, array('id'=>2)));
            $this->fail("Adding any invalid model throws exception");
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Can't add an invalid model to a collection", $e->getMessage());
        }
        $model->trigger('test'); //Shouldn't be attached to the collection anymore.
        $this->assertNull($col->getByCid($model->cid()));
        $this->assertNull($col->get(1));
        $this->assertEquals(0, $col->length());
    }
    
    /**
     * Collection: multiple copies of the same model
     */
    public function testCollectionMultipleCopiesOfTheSameModel() {
        $col = new Backbone_Collection();
        $model = new Backbone_Model();
        $col->add(array($model, $model));
        $this->assertEquals(1, $col->length());
        $col->add(array(array('id'=>1), array('id'=>1)));
        $this->assertEquals(2, $col->length());
        $this->assertEquals(1, $col->last()->id());
    }
    
    /**
     * Collection: #964 - collection.get return inconsistent
     * PHP Only: get() requires an id argument, can't be called without it.
     */
    public function testCollection964CollectionGetReturnInconsistent() {
        $c = new Backbone_Collection();
        $this->assertNull($c->get(null));
        //$this->assertNull($c->get());
    }
    
    /**
     * Collection: #1112 - passing options.model sets collection.model
     */
    public function testCollection1112PassingOptionsModelSetsCollectionModel() {
        $Model = Backbone::uniqueId('Model_');
        eval('class '.$Model.' extends Backbone_Model {}');
        $this->assertTrue(class_exists($Model));

        $c = new Backbone_Collection(array('id'=>1), array('model'=>$Model));
        $this->assertEquals($Model, $c->model());
        $this->assertTrue($c->at(0) instanceof $Model);
    }
    
    /**
     * Collection: null and undefined are invalid ids.
     */
    public function testCollectionNullIsInvalidId() {
        $model = new Backbone_Model(array('id'=>1));
        $collection = new Backbone_Collection(array($model));
        $model->id = null;
        $this->assertNull($collection->get(null));
        $model->id = 1;
        $this->assertNotNull($collection->get(1));
        $model->id = null;
        $this->assertNull($collection->get(null));        
    }
    
    /**
     * Collection: falsy comparator
     */
    public function testCollectionFalsyComparator() {
        $Col = Backbone::uniqueId('Collection_');
        eval('class '.$Col.' extends Backbone_Collection {
        	public function initialize($attrs, array $options) {
        		if (!array_key_exists("comparator", $options)) 
        			$this->comparator(function($model) { return $model->id(); });
			} 
    	}');
        $this->assertTrue(class_exists($Col));
        
        $col = new $Col();
        $colSet = new Backbone_Collection(null, array('comparator'=>function($model) { return $model->id(); }));
        $colFalse = new $Col(null, array('comparator'=>false));
        $colNull = new $Col(null, array('comparator'=>null));
        $this->assertTrue((bool)$col->comparator());
        $this->assertTrue((bool)$colSet->comparator());
        $this->assertFalse((bool)$colFalse->comparator());
        $this->assertFalse((bool)$colNull->comparator());
    }
    
    /**
     * Collection: #1355 - `options` is passed to success callbacks
     */
    public function testCollection1355OptionsIsPassedToSuccessCallbacks() {
        $test = $this;
        $count = 0;
        $m = new Backbone_Model(array('x'=>1));
        $col = new Backbone_Collection();
        $opts = array(
            'success'=>function($collection, $response, $options) use ($test, &$count) {
                $test->assertTrue((bool)$options);
                $count++;
            }
        );
        $sync = function($collection, $response, $options) {
            call_user_func($options['success']); 
        };
        $col->sync($sync); $m->sync($sync);
        $col->fetch($opts);
        $col->create($m, $opts);
        $this->assertEquals(2, $count);
    }
    
    /**
     * Collection: #1447 - create with wait adds model.
     */
    public function testCollection1447CreateWithWaitAddsModel() {
        $collection = new Backbone_Collection();
        $count = 0;
        $model = new Backbone_Model();
        $model->sync(function($collection, $response, $options) {
            call_user_func($options['success']); 
        });
        $collection->on('add', function() use (&$count) { $count++; });
        $collection->create($model, array('wait'=>true));
        $this->assertEquals(1, $count);
    }
    
    /**
     * Collection: #1448 - add sorts collection after merge.
     */
    public function testCollection1448AddSortsCollectionAfterMerge() {
        $collection = new Backbone_Collection(array(
            array('id'=>1, 'x'=>1),
            array('id'=>2, 'x'=>2)
        ));
        $collection->comparator(function($model) { return $model->x; });
        $collection->add(array('id'=>1, 'x'=>3), array('merge'=>true));
        $this->assertEquals(array(2,1), $collection->pluck('id'));
    }
    
    //----------------------------------------------------------------------------
    //Backbone.php-specific tests
    
    /**
     * Backbone_Collection: Test the url accessor
     */
    public function testBCTestUrl() {
        $col = new Backbone_Collection();
        $this->assertNull($col->url(), 'No url defined initially');
        $this->assertEquals('/widgets', $col->url('/widgets'));
        $this->assertEquals('/widgets', $col->url());
    }
    
    /**
     * Backbone_Collection: Test comparator accessor/reflection
     */
    public function testBCComparator() {
        $func = Backbone::uniqueId('function_');
        eval('function '.$func.'($a, $b) { return ($a->id() > $b->id()) ? -1 : 1; }');
        $this->assertTrue(function_exists($func));
        
        $col = new Backbone_Collection();
        $this->assertEquals($func, $col->comparator($func));
        $this->assertEquals($func, $col->comparator());
        
        try {
            $col->comparator(function() {} );
            $this->fail('comparator has no parameters, unknown type');
        } catch(InvalidArgumentException $e) { }
    }
    
    /**
     * Backbone_Collection: Clone doesn't copy triggers 
     */
    public function testBCClone() {
        $test = $this;
        $c = new Backbone_Collection();
        $c->on('custom', function() use ($test) { $test->fail(); });
        $c2 = clone $c;
        $c2->trigger('custom');
    }
    
    /**
     * Backbone_Collection: misbehaving sync function is detected by fetch
     */
    public function testBCMisbehavingSync() {
        $col = new Backbone_Collection();
        $col->sync(function() { /* Do nothing */ });
        try { 
            $col->fetch(); $this->fail(); 
        } catch (LogicException $e) { }
    }
    
    /**
     * Backbone_Collection: fetch error
     */
    public function testBCFetchError() {
        $errEvent = 0; $errCall = 0;
        $col = new Backbone_Collection();
        $col->sync(function($method, $model, $options) { call_user_func($options['error']); });
        $col->on('error', function() use (&$errEvent) {  $errEvent++; });
        $callback = function() use(&$errCall) { $errCall++; };
        
        $response = $col->fetch();
        $this->assertFalse($response);
        $this->assertEquals(1, $errEvent);
        
        $response = $col->fetch(array('error'=>$callback));
        $this->assertFalse($response);
        $this->assertEquals(1, $errEvent);
        $this->assertEquals(1, $errCall);
    }
    
    /**
     * Backbone_Collection: iteration
     */
    public function testBCIteration() {
        $col = $this->col;
        $n = 0;
        foreach($col as $i=>$m) {
            $this->assertEquals($i, $n);
            $this->assertSame($col->at($i), $m);
            $n++;
        }
        $this->assertEquals(4, $n);
    }
    
    /**
     * Backbone_Collection: test other underscore methods 
     */
    public function testBCUndercore() {
        $col = $this->col;
        $this->assertTrue($col->all(function($m){ return $m->has('label'); }), 'all');
        $this->assertFalse($col->all(function($m){ return $m->label=='a'; }), 'all');
        $this->assertFalse($col->all(function($m){ return $m->label=='frog'; }), 'all');
        
        $this->assertTrue($col->any(function($m){ return $m->has('label'); }), 'any');
        $this->assertTrue($col->any(function($m){ return $m->label=='a'; }), 'any');
        $this->assertFalse($col->any(function($m){ return $m->label=='frog'; }), 'any');
    }
    
    /**
     * Backbone_Collection: Exported class name is correct for the original model, and implicit/explicit
     */
    public function testBCExportClassName() {
        $this->assertEquals('Backbone.Collection', Backbone_Collection::getExportedClassName());
        $this->assertEquals('Test_Collection', Test_Collection::getExportedClassName());
        $class = Test_Model::buildClass(array('exported_classname'=>'MyClass'));
        $this->assertEquals('MyClass', $class::getExportedClassName());
    }
    
    /**
     * Backbone_Collection: Exported object contains expected attributes
     */
    public function testBCExportedObject() {
        $collection = new Backbone_Collection(array(array('x'=>true)));
        $this->assertEquals('[{"x":true}]', $collection->export());
        
        $class = Test_Collection::buildClass(array());
        $collection = new $class(array($this->a, $this->b, $this->c, $this->d));
        $this->assertEquals('[{"id":3,"label":"a"},{"id":2,"label":"b"},{"id":1,"label":"c"},{"id":0,"label":"d"}]', (string)$collection);
        $this->assertEquals(
        	'[{"id":3,"label":"a"},{"id":2,"label":"b"},{"id":1,"label":"c"},{"id":0,"label":"d"}]', 
            $collection->export()
        );
    }
    
    /**
     * Backbone_Collection : Can't export the class for Backbone_Collection
     */
    public function testBCCantExportBCClass() {
        try {
            Backbone_Collection::exportClass();
            $this->fail();
        } catch (LogicException $e) { }
    }
    
    /**
     * Backbone_Collection : Exported class contains expected definition
     */
    public function testBCExportClass() {
        $definition = Test_Collection::exportClass();
        $this->assertStringStartsWith('var Test_Collection = Backbone.Collection.extend({', $definition);
    }
    
    /**
     * Backbone_Collection : Exported subclass extends from the parent's exported class name
     */
    public function testBCExportClassParent() {
        $class = Test_Collection::buildClass(array());
        $definition = $class::exportClass();
        $this->assertStringStartsWith('var '.$class.' = Test_Collection.extend({', $definition);
    }
    
    /**
     * Backbone_Collection: Exported class contains nominated members
     */
    public function testBCExportClassMembers() {
        $class = Test_Collection::buildClass(array('foo'=>array("a" => true)));
        $definition = $class::exportClass();
        $this->assertNotContains('foo: {"a":true}', $definition, "Not named, so not exported" );
        
        $class = Test_Collection::buildClass(array('foo'=>array("a" => true), 'exported_fields' => array('foo')));
        $definition = $class::exportClass();
        $this->assertContains('foo: {"a":true}', $definition, "Named, so exported" );
    }
    
    /**
     * Backbone_Collection: Exported functions aren't escaped
     */
    public function testBCExportClassFunctions() {
        $class = Test_Collection::buildClass(array('exported_functions'=>array('foo' => 'function() { alert("hi!"); }')));
        $definition = $class::exportClass();
        $this->assertContains('foo: function() { alert("hi!"); }', $definition);
    }
    
    /**
     * Backbone_Collection: Event registration passed on construction
     */
    public function testBCEventOnConstruction() {
        $called = false;
        $col = new Backbone_Collection(array(), array('on'=>array('custom'=>function() use (&$called) { $called = true; })));
        $col->trigger('custom');
        $this->assertTrue($called);
    }
    
    /**
     * Backbone_Collection: Sync method is passed to models if present
     */
    public function testBCSyncSetInModels() {
        $default_sync = Backbone::getDefaultSync();
        $sync = function($method, $model, $options) {};
        $sync2 = function($method, $model, $options) {};
        
        $col = new Backbone_Collection(array('x'=>true));
        $this->assertSame($default_sync, $col->sync());
        $this->assertSame($default_sync, $col->first()->sync());
        
        $col = new Backbone_Collection(array('x'=>true), array('sync'=>$sync));
        $this->assertNotSame($default_sync, $col->sync());
        $this->assertSame($sync, $col->sync());
        $this->assertSame($sync, $col->first()->sync(), "Built model uses collection sync method");
        $model = new Backbone_Model(array('foo'=>'bar'));
        $col->add($model);
        $this->assertSame($sync, $model->sync(), "Existing model without sync uses collection sync");
        $model->sync($sync2);
        $this->assertNotSame($sync, $model->sync(), "Model sync overrides collection sync");
        $col->add(array('id'=>42,'x'=>false),array('sync'=>$sync2));
        $this->assertNotSame($sync, $col->get(42)->sync(), "Collection sync method not set when options define sync method");
        
        $col = new Backbone_Collection(array('x'=>true), array('sync'=>$sync));
        $col->sync($sync2);
        $this->assertSame($sync2, $col->first()->sync(), "When collection sync changed, elements follow new sync");
    }
}