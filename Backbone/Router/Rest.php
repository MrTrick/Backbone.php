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
 * Backbone_Router_Rest matches the inbuilt REST routes:
 * 
 * - GET / => index
 * - POST / => create
 * - GET /:id => read
 * - PUT|PATCH /:id => update
 * - DELETE /:id
 * 
 * If other routes are present, they will be matched first. 
 * (So if a route 'foo' is defined, it will take precedence over an
 * object with id 'foo'. Take care to ensure that overlaps are handled correctly)   
 * @author Patrick Barnes
 *
 */
class Backbone_Router_Rest extends Backbone_Router
{
    /**
     * Empty handler for 'GET /' - read all records
     */
    public function index(array $options=array()) { return false; }
    
    /**
     * Empty handler for 'GET /:id' - read record with given id
     * @param string id
     */
    public function read($id, array $options=array()) { return false;}
    
    /**
     * Empty handler for 'POST /' - create new record
     */
    public function create(array $options=array()) { return false; }
    
    /**
     * Empty handler for 'PUT|PATCH /:id' - update record with given id
     * @param string id
     */
    public function update($id, array $options=array()) { return false; }
    
    /**
     * Empty handler for 'DELETE /:id' - delete record with given id
     */
    public function delete($id, array $options=array()) { return false; }
    
    /**
     * Call a route handler if set, or fall back to the REST handlers
     * 
     * Options:
     *  - 'request' : If present, set in the router and use for routing  
     * 
     * @param array|Zend_Controler_Request_Http|string $options Option array, or the request 
     * @return bool Whether any route handler was matched and invoked
     */
    public function __invoke($options = array()) {
        //Does another route match first?
        if (parent::__invoke($options)) return true;

        //What are we routing?
        if (!is_array($options)) $options = array('request'=>$options);
        $url = $this->url($options);
        if ($url === false) return false; //Won't match if the url is invalid
        if (strpos($url, '/')!==false) return false; //Shortcut - won't match if more than the id is given.  
        $id = $url ? rawurldecode($url) : null;
        $method = $this->getMethod(); 
        
        //Does this match a REST route?
        if ($method=='GET') $route = $id ? 'read' : 'index';
        elseif ($method=='POST' and !$id) $route = 'create';
        elseif (($method=='PUT' or $method=='PATCH') and $id) $route = 'update';
        elseif ($method=='DELETE' and $id) $route = 'delete';
        else return false; //Wrong pattern - no match.
        
        //Trigger an event before routing the request
        $this->trigger('before_route', $this, $route, array($id), $options);
        
        //Invoke that route callback (id will be passed to read/update/delete)
        $matched = call_user_func_array(array($this, $route), $id ? array($id, $options) : array($options))!==false; 
        
        //Trigger the named and global routing events
        //TODO: Should the events be triggered regardless, or only if the callback doesn't veto?        
        $this->trigger("route:$route", $this, $id);
        $this->trigger('route', $this, $route, array($id));
        
        return $matched;
    }
}