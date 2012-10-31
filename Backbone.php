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
 * Top-level Backbone object
 * @author Patrick Barnes
 *
 */
class Backbone {
    /**
     * Current version of the library.
     * Follows the Backbone.js version number - to indicate compatibility.  
     * @var string
     */
    const VERSION = '0.9.2';
    
    protected static $idCounter = 0;
    /**
     * Generate a unique id 
     * (Unique only for the duration of execution)
     * @param string $prefix Optionally a string to prefix to the generated id.
     */
    public static function uniqueId($prefix='') {
        return $prefix . self::$idCounter++;
    }
    
    /*************************************************************************\
     * Default Sync 
    \*************************************************************************/
        
    protected static $default_sync = array();
    
    /**
     * Set the default sync function|object
     * This function will be called by Models and Collections that do not have a sync function|object set
     * The default sync can be cleared by setting it to null.
     *
     * Different default sync functions can be set per model class by passing a map of modelclass => sync.
     * (If a single function given, modelclass is 'Backbone_Model')
     * 
     * The sync object may be passed as the class name, and it will be instantiated the first time it is 
     * retrieved.
     * 
     * @param callable|Backbone_Sync_Interface|array $sync
     */
    public static function setDefaultSync($sync) {
        //If passed a bare callback, assume it's for any model
        if (!is_array($sync) or (reset($sync) and is_numeric(key($sync)))) 
            $sync = array('Backbone_Model'=>$sync);
        
        //Check the validity of each sync function/object
        foreach($sync as $class=>$_sync) {
            if ($_sync!==null and !(is_callable($_sync) || is_a($_sync, 'Backbone_Sync_Interface', true))) 
                throw new InvalidArgumentException('Cannot set $sync - invalid');
        }
        
        static::$default_sync = $sync;
    }
    
    /**
     * Get the default sync function|object for the given class, instantiating it if defined as string.
     * If no class|object is given, assumes for 'Backbone_Model'
     * 
     * Reads sequentially through the defined default sync map, and returns the first match.
     * (So when setting, declare more specific sync functions first)
     * 
     * @param string|Backbone_Model 
     * @return callable|Backbone_Sync_Interface $sync The sync function, or null if one is not set.
     */
    public static function getDefaultSync($model = null) {
        if (!$model) $model = 'Backbone_Model';
        
        foreach(static::$default_sync as $class=>$sync) {
            if (is_a($model, $class, true)) {
                //If storing the class name, construct and cache the object on first use
                if (is_string($sync) and !is_callable) static::$default_sync[$class] = new $sync();
                
                return $sync;
            }
        }
        
        return null;
    }

    /*************************************************************************\
     * Global Request
    \*************************************************************************/
    
    protected static $request = null;
    
    /**
     * Fetch the server request 
     * (Caches a single instance)
     * @return Zend_Controller_Request_Http
     */
    public static function getCurrentRequest() {
        if (!static::$request) static::$request = new Zend_Controller_Request_Http();
        
        return static::$request;
    }
    
    /**
     * Override the server request
     * Included for completeness, and testing
     * @see Zend_Controller_Request_HttpTestCase
     * @param Zend_Controller_Request_Http|string $request
     */
    public static function setCurrentRequest($request) {
         if (is_string($request)) {
            if (preg_match('#^https?://#',$request)) 
                $_request = new Zend_Controller_Request_Http($request);
            else {
                $_request = new Zend_Controller_Request_Http();
                $_request->setPathInfo($request);
            }
            static::$request = $_request;
        } else {
            static::$request = $request;
        }
    }
    
    /*************************************************************************\
     * Global Response
    \*************************************************************************/
    
    protected static $response = null;
    
    /**
     * Fetch the server response 
     * (Caches a single instance)
     * @return Zend_Controller_Response_Http
     */
    public static function getCurrentResponse() {
        if (!static::$response) static::$response = new Zend_Controller_Response_Http();
        
        return static::$response;
    }
    
    /**
     * Override the server response
     * Included for completeness, and testing
     * @see Zend_Controller_Response_HttpTestCase
     * @param Zend_Controller_Response_Http $response
     */
    public static function setCurrentResponse(Zend_Controller_Response_Http $response) {
        static::$response = $response;
    }
}