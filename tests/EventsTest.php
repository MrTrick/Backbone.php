<?php
require_once('common.php');

class Test_Events_TestCase extends PHPUnit_Framework_TestCase {
    protected $name = "Backbone.Events";

    /**
     * Events: on and trigger
     */
    public function testOnAndTrigger() {
        $obj = new Backbone_Events();
        $obj->counter = 0;
        
        $obj->on('event', function() use ($obj) { $obj->counter += 1; });
        $obj->trigger('event');
        $this->assertEquals(1, $obj->counter, 'counter should be incremented.');
        $obj->trigger('event');
        $obj->trigger('event');
        $obj->trigger('event');
        $obj->trigger('event');
        $this->assertEquals(5, $obj->counter, 'counter should be incremented 5 times.');
    }
    
    /**
     * Events: binding and triggering multiple events
     */
    public function testBindAndTriggerMultiple() {
        $obj = new Backbone_Events();
        $obj->counter = 0;

        $obj->on('a b c', function() use ($obj) { $obj->counter += 1; });

        $obj->trigger('a');
        $this->assertEquals(1, $obj->counter);
        
        $obj->trigger('a b');
        $this->assertEquals(3, $obj->counter);
        
        $obj->trigger('c');
        $this->assertEquals(4, $obj->counter);
        
        $obj->off('a c');
        $obj->trigger('a b c');
        $this->assertEquals(5, $obj->counter);
    }
  
    /**
     * Events: trigger all for each event
     */
    public function testTriggerAllForEach() {
        $a = false; 
        $b = false;
        $obj = new Backbone_Events();
        $obj->counter = 0;
        $obj->on('all', function($event) use (&$a, &$b, $obj) {
            $obj->counter++;
            if ($event == 'a') $a = true;
            if ($event == 'b') $b = true;
        })
        ->trigger('a b');
        $this->assertTrue($a);
        $this->assertTrue($b);
        $this->assertEquals(2, $obj->counter);
    }
    
    /**
     * Events: on, then unbind all functions
     */
    public function testOnThenUnbind() {
        $obj = new Backbone_Events();
        $obj->counter = 0;
        $callback = function() use ($obj) { $obj->counter += 1; };
        $obj->on('event', $callback);
        $obj->trigger('event');
        $obj->off('event');
        $obj->trigger('event');
        $this->assertEquals(1, $obj->counter, 'counter should have only been incremented once.');
    }
    
    /**
     * Events: bind two callbacks, unbind only one
     */
    public function testBindTwoUnbindOne() {
        $obj = new Backbone_Events();
        $obj->counterA = 0;
        $obj->counterB = 0;
        $callback = function() use ($obj) { $obj->counterA += 1; };
        $obj->on('event', $callback);
        $obj->on('event', function() use($obj) { $obj->counterB += 1; });
        $obj->trigger('event');
        $obj->off('event', $callback);
        $obj->trigger('event');
        $this->assertEquals(1, $obj->counterA, 'counterA should have only been incremented once.');
        $this->assertEquals(2, $obj->counterB, 'counterB should have been incremented twice.');
    }
        
    /**
     * Events: unbind a callback in the midst of it firing
     */
    public function testUnbindCallbackWhileFiring() {
        $obj = new Backbone_Events();
        $obj->counter = 0;
        $callback = function() use ($obj, &$callback) { 
            $obj->counter += 1; 
            $obj->off('event', $callback);
        };
        $obj->on('event', $callback);
        $obj->trigger('event');
        $obj->trigger('event');
        $obj->trigger('event');
        $this->assertEquals(1, $obj->counter, 'the callback should have been unbound.');
    }
    
    /**
     * Events: two binds that unbind themselves
     */
    public function testTwoBindsThatUnbindThemselves() {
        $obj = new Backbone_Events();
        $obj->counterA = 0;
        $obj->counterB = 0;
        $incrA = function() use ($obj, &$incrA) { $obj->counterA += 1; $obj->off('event', $incrA); };
        $incrB = function() use ($obj, &$incrB) { $obj->counterB += 1; $obj->off('event', $incrB); };
        $obj->on('event', $incrA);
        $obj->on('event', $incrB);
        $obj->trigger('event');
        $obj->trigger('event');
        $obj->trigger('event');
        $this->assertEquals(1, $obj->counterA, 'counterA should have only been incremented once.');
        $this->assertEquals(1, $obj->counterB, 'counterA should have only been incremented once.');
    }
    
    /**
     * Events: bind a callback with a supplied context
     * Setting context is not supported by Backbone_Events.
     */
    public function testBindWithSuppliedContext() {
        //Setting context is not supported by Backbone_Events.
    }
    
    /**
     * Events: nested trigger with unbind
     */
    public function testNestedTriggerWithUnbind() {
        $obj = new Backbone_Events();
        $obj->counter = 0;
        $incr1 = function() use ($obj, &$incr1) { $obj->counter += 1; $obj->off('event', $incr1); $obj->trigger('event'); };
        $incr2 = function() use ($obj) { $obj->counter += 1; };
        $obj->on('event', $incr1);
        $obj->on('event', $incr2);
        $obj->trigger('event');
        $this->assertEquals(3, $obj->counter, 'counter should have been incremented three times.');
    }
    
    /**
     * Events: callback list is not altered during trigger
     */
    public function testCallbackListIsNotAlteredDuringTrigger() {
        $counter = 0;
        $obj = new Backbone_Events();
        $incr = function() use(&$counter) { $counter++; };
        $obj->on('event', function() use ($obj, $incr) { $obj->on('event', $incr)->on('all', $incr); })
        ->trigger('event');
        $this->assertEquals(0, $counter, 'bind does not alter callback list');
        $obj->off()
        ->on('event', function() use ($obj, $incr) { $obj->off('event', $incr)->off('all', $incr); })
        ->on('event', $incr)
        ->on('all', $incr)
        ->trigger('event');
        $this->assertEquals(2, $counter, 'unbind does not alter callback list');
    }
    
    /**
     * #1282 - 'all' callback list is retrieved after each event.
     * "Assuming that model.trigger('x y') should behave like a 
     * shortcut for model.trigger('x').trigger('y'), the "all" 
     * callbacks list should be updated after each event is triggered."
     */
    public function test1282AllRetrievedAfterEvent() {
        $counter = 0;
        $obj = new Backbone_Events();
        $incr = function() use (&$counter) { $counter++; };
        $obj->on('x', function() use ($obj, $incr) { 
            $obj->on('y', $incr)->on('all', $incr);
        })
        ->trigger('x y');
        $this->assertEquals(2, $counter); 
    }
    
    /**
     * if no callback is provided, `on` is a noop
     */
    public function testNoCallback() {
        $obj = new Backbone_Events();
        $obj->on('test')->trigger('test');
    }
    
    /**
     * remove all events for a specific context
     */
    public function testRemoveAllForContext() {
        //Setting context is not supported by Backbone_Events.        
    }
    
    /**
     * remove all events for a specific callback
     */
    public function testRemoveForSpecificCallback() {
        $test = $this;
        $obj = new Backbone_Events();
        $counter = 0;
        $success = function() use (&$counter, $test) { $test->assertTrue(true, 'will get called'); $counter++; };
        $fail = function() use ($test) { $test->fail(); };
        $obj->on('x y all', $success);
        $obj->on('x y all', $fail);
        $obj->off(null, $fail);
        $obj->trigger('x y');
        $this->assertEquals(4, $counter);
    }
    
    /**
     * off is chainable
     */
    public function testOffIsChainable() {
        $obj = new Backbone_Events();
        // With no events
        $this->assertSame($obj, $obj->off());
        // When removing all events
        $obj->on('event', function(){});
        $this->assertSame($obj, $obj->off());
        // When removing some events
        $obj->on('event', function(){});
        $this->assertSame($obj, $obj->off('event'));
    }
    
    /**
     * #1310 - off does not skip consecutive events
     * Imperfect copy of the js test, due to lack of context support.
     * (Though Backbone.Events.php is less likely to suffer this bug, as it stores in an array instead of linked list)
     */
    public function test1310OffDoesNotSkipConsecutive() {
        $test = $this;
        $obj = new Backbone_Events();
        $obj->on('event', function() use ($test) { $this->fail(); });
        $obj->on('event', function() use ($test) { $this->fail(); });
        $obj->off(null, null);
        $obj->trigger('event');
    }
}