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


if (!defined('DEBUG') or !DEBUG) die("Cannot use Backbone_Auth_Storage_Hmac_Bypass unless in DEBUG mode");

/**
 * Subverts the HMAC mechanism - doesn't require any valid token.
 * 
 * If given an 'Authorization' header, uses it.
 * If given a query parameter 'identity', uses that.
 * 
 * @author Patrick Barnes 
 */
class Backbone_Auth_Storage_Hmac_Bypass extends Backbone_Auth_Storage_Hmac {

    /*************************************************************************\
     * Utility methods
    \*************************************************************************/
    
    /**
     * Hijack the header validation process 
     * @return string The signature identity (username)
     * @throws Backbone_Exception_Unauthorized If the request is signed, but invalid
     */
    protected function _readIdentity() {
    	$auth = $this->request()->getHeader('Authorization');
    	
    	if ($auth) {
	        //Parse the header if given
	        if ( !preg_match('/^HMAC (.*)$/', $auth, $m) )
	            throw new Backbone_Exception_Unauthorized("Invalid Signature; Incorrect header format");    
	        parse_str($m[1], $params);
	        
	        //Allow a valid signature (Hey, why not)
	        $error = $this->validateSignature($params);
	        if (!$error) return $this->identity;
	        //Otherwise, be happy with identity.
	        else if ($params['identity']) return $this->identity = $params['identity'];
	        //If no identity, why not?
	        else throw new Backbone_Exception_Unauthorized("Invalid Signature; $error");
	        
    	} else {
    		//Check for identity as a query parameter
    		$identity = $this->request()->get('identity');
    		if ($identity) {
    			return $this->identity = $identity;
    		} else {
    			return $this->identity = false;
    		}
    	}
    }
} 