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
 * Test the Model class
 * @author Patrick Barnes
 *
 */
class Test_Auth_TestCase extends PHPUnit_Framework_TestCase {
    protected $name = "Backbone.Auth";
    
    /**
     * Test the HMAC request signing
     */
    public function testHmacUnsigned() {
        $options = array('lifetime' => 10,'server_key' => 'SEEKRIT');
        
        //If the request is not signed, there is no identity.  
        $hmac = new Backbone_Auth_Storage_Hmac($options);
        
        $this->assertFalse( $hmac->read(), "No identity - false");
        $this->assertFalse( $hmac->read(), "No identity - false (cached)");
        $this->assertTrue( $hmac->isEmpty(), "No identity - true");        
    }
    
    /**
     * What if another kind of authentication was used? Should be ignored
     */
    public function testHmacOtherAuth() {
        $options = array('lifetime' => 10,'server_key' => 'SEEKRIT');
        
        $hmac = new Backbone_Auth_Storage_Hmac($options);
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setHeader('Authorization', 'Basic f00f00f00f00f00');
        $hmac->request($request);
        
        try {
            $this->assertFalse( $hmac->read(), "No identity - false");
        } catch(Backbone_Exception_Unauthorized $e) {
            $this->fail("Should treat as no identity, not an error");
        }
    }
    
    /**
     * Check that the credentials match the expected form  
     */
    public function testHmacCredentials() {
        $options = array('lifetime' => 10,'server_key' => 'SEEKRIT');
        $hmac = new Backbone_Auth_Storage_Hmac($options);
        $hmac->write('testuser');
        $cred = $hmac->getClientCredentials();

        $this->assertArrayHasKey('identity', $cred);
        $this->assertEquals($cred['identity'], 'testuser');
        
        $this->assertArrayHasKey('created', $cred);
        $this->assertInternalType('int', $cred['created']);
        
        $this->assertArrayHasKey('expiry', $cred);
        $this->assertInternalType('int', $cred['expiry']);
        
        $this->assertEquals($options['lifetime'], $cred['expiry'] - $cred['created'], "Credentials match lifetime");
        
        $this->assertArrayHasKey('client_key', $cred);
        $this->assertInternalType('string', $cred['client_key']);
        $this->assertEquals(64, strlen($cred['client_key']));
    }
    
    /**
     * Check that the request can be validated properly 
     */
    public function testHmacValidation() {
        $options = array('lifetime' => 10,'server_key' => 'SEEKRIT');
        $url = "http://example.com/test/foo/bar";
        
        //Get some credentials
        $hmac = new Backbone_Auth_Storage_Hmac($options);
        $hmac->write('testuser');
        $cred = $hmac->getClientCredentials();
        
        //Test against another auth object
        $hmac = new Backbone_Auth_Storage_Hmac($options);
        $_SERVER['HTTP_HOST'] = 'example.com';
        $hmac->request( new Zend_Controller_Request_HttpTestCase($url) );
        
        //Sign the request properly - works. 
        $method = "GET";
        $signature = hash_hmac('sha256', $method.$url, $cred['client_key']);
        $params = array(
            'identity' => $cred['identity'],
            'expiry' => $cred['expiry'],
            'url' => $url,
            'signature' => $signature
        );
        $response = $hmac->validateSignature($params);
        $this->assertEquals(null, $response);
        
        //Omit an attribute - fails.
        $_params = array_diff_key($params, array('url'=>true));
        $response = $hmac->validateSignature($_params);
        $this->assertEquals("Missing 'url' parameter", $response);
        
        //Expired credentials
        $_params = array_merge($params, array('expiry'=>time()-30));
        $response = $hmac->validateSignature($_params);
        $this->assertStringStartsWith("Credentials expired.", $response);
        
        //Wrong url - fails
        $_params = array_merge($params, array('url'=>"http://example.com/test/oops"));
        $response = $hmac->validateSignature($_params);
        $this->assertEquals("URL does not match. URI: http://example.com/test/foo/bar, Signature: http://example.com/test/oops", $response);
        
        //Wrong expiry - fails due to signature
        $_params = array_merge($params, array('expiry'=>time()+2));
        $response = $hmac->validateSignature($_params);
        $this->assertEquals("Forged signature", $response);
    }
} 



