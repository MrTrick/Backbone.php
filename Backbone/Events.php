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
 * Backbone_Events
 * PHP Implementation of Backbone.js's Backbone.Events
 *
 * A module that can be mixed in to any object in order to provide it with custom events. 
 * You may bind with on or remove with off callback functions to an event; trigger`-ing an event fires all callbacks in succession.
 * 
 * class MyClass extends Backbone_Events { };
 * $my_object = new MyClass();
 * $my_object->on('expand', function($foo) { echo "Expanded: ".$foo; } );
 * $my_object->trigger('expand', $foo); 
 *
 * @author Patrick Barnes
 */
class Backbone_Events {
    
    /**
     * Constructor; allows concrete classes to register events at build time.
     * 
     * Options:
     *  - 'on' : An array of event=>callback pairs to register at construction time.
     */
    public function __construct($options = null) {
        //Register any construction-time events
        if (!empty($options['on']))
            foreach($options['on'] as $event=>$callback)
                $this->on($event, $callback);
    }
    
    /**
     * Used to split event strings
     * @var Regular expression  
     */
    protected static $eventSplitter = '/\s+/';
    
    /**
     * Map of callback functions by event name
     * @var array
     */
    protected $_callbacks = array();
    
    /**
     * Bind a callback function to the given events.
     * NOT SUPPORTED: The $context parameter; Use closures if access is required to something other than the class the function is defined in. 
     * 
     * The event "all" will bind the callback to all events fired.
     * @param string|array $events An array of event names, an event name, or a delimited string (according to $eventSplitter) of event names. 
     * @param callable $callback If not provided, on() does nothing.
     * @return __CLASS__ provides a fluent interface
     */
    public function on($events, $callback=null) {
        if (!$callback) return $this; //No function to call, nothing to do.
        
        //Input parsing/checking        
        if (is_string($events)) $events = preg_split(static::$eventSplitter, $events, -1, PREG_SPLIT_NO_EMPTY);
        $callback = $this->parseCallback($callback);
        
        //Register the callback on each given event.
        foreach($events as $event) {
            //if (empty($this->_callbacks[$event])) $this->_callbacks[$event] = array();
            $this->_callbacks[$event][] = $callback;
        }
        
        return $this;
    }
    
    /**
     * Remove one or more callbacks.
     * NOT SUPPORTED: The $context parameter.
     *  
     * If $callback is null, removes all callbacks for the event.
     * If $events is null, removes the given callbacks from all events.
     * 
     * @param string|array $events An array of event names, an event name, or a space-delimited string of event names
     * @param callable $callback
     * @return __CLASS__ provides a fluent interface
     */
    public function off($events=null, $callback=null) {
        //No callbacks registered, short-circuit. No events and no callback given, clear all. 
        if (empty($this->_callbacks)) return $this;
        if (empty($events) and empty($callback)) { $this->_callbacks = array(); return $this; }        

        //Input parsing
        if (is_string($events)) $events = preg_split(static::$eventSplitter, $events, -1, PREG_SPLIT_NO_EMPTY);
        if (is_string($callback) and method_exists($this, $callback)) $callback = array($this, $callback);
        if (empty($events)) $events = array_keys($this->_callbacks); //If events not given, use all events.
        
        foreach($events as $event) {
            if (!$callback) $this->_callbacks[$event] = array();
            foreach($this->_callbacks[$event] as $k=>$c) if ($c === $callback) { unset($this->_callbacks[$event][$k]); break; } 
        }
        
        return $this;
    }
    
    /**
     * Trigger one or many events, firing all bound callbacks.
     * Callbacks are passed the same arguments as trigger() is, apart from the event name.
     * (except for the "all" callback, which receives the true name of the event as the first argument)
     *  
     * @param array|string $events The events to be triggered
     * @return __CLASS__ provides a fluent interface
     */
    public function trigger($events) {
        //If nothing to do, short-circuit return
        if (empty($this->_callbacks) || empty($events)) return $this; 
        
        //Input parsing
        if (is_string($events)) $events = preg_split(static::$eventSplitter, $events, -1, PREG_SPLIT_NO_EMPTY);
        $args = array_slice(func_get_args(), 1); //Shift off the $events arg
        
        //Trigger for each event;
        foreach($events as $event) {
            //Copy callback lists to prevent handler modification during that event.
            $list = empty($this->_callbacks[$event]) ? array() : $this->_callbacks[$event];
            $all = empty($this->_callbacks['all']) ? array() : $this->_callbacks['all']; 
            
            //Any named event handlers
            foreach($list as $callback) {
                call_user_func_array($callback, $args);
            }
            //And any 'all' handlers
            foreach($all as $callback) {
                call_user_func_array($callback, array_merge(array($event), $args));
            } 
        }
        
        return $this;
    }

    /**
     * Helper method to validate callbacks, and transform 'method_name' to 
     * the callable array($this, 'method_name') from.
     * @param mixed $callback
     * @throws InvalidArgumentException
     * @return callable 
     */
    protected function parseCallback($callback) {
        if (is_callable($callback)) return $callback;
        elseif (is_string($callback) and method_exists($this, $callback)) return array($this, $callback);
        else throw new InvalidArgumentException("Invalid callback");
    }
}