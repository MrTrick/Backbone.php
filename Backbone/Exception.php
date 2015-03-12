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
 * Parent of all Backbone exceptions  
 * @author Patrick Barnes
 * 
 * Message defaults to HTTP-compliant statuses.
 */
class Backbone_Exception extends Exception
{
    protected static $status_codes = array(
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Resource Conflict',
        410 => 'Resource Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m A Teapot',
        420 => 'Enhance Your Calm',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        500 => 'Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        511 => 'Network Authentication Required'
    );
    
    /**
     * Optionally, more information about the error
     * @var mixed
     */
    public $body;
    
    /**
     * Construct and return an exception, using the HTTP status code to build a more specific exception where possible  
     * @param string|array $error
     * @param string $code
     * @param Exception $previous
     * @param mixed $body
     * @return Backbone_Exception or a child class
     */
    public static function factory($error, $code = null, Exception $previous = null, $body = null) {
        //Decode the error if possible
        if (is_string($error)) {
            $_error = json_decode($error, true);
            if ($_error) $error = $_error;
            else $error = array('message'=>$error);
        }
        if (!empty($error['error'])) $error = $error['error'];
        
        //What kind of exception should be built?
        $code = !empty($error['code']) ? $error['code'] : $code;
        if (isset(self::$status_codes[$code])) {
            $name = str_replace(array(' ','\''), '', self::$status_codes[$code]);
            $path = __DIR__.'/Exception/'.$name.'.php';
            $class = 'Backbone_Exception_'.$name; 
            
            if (is_readable($path)) require_once($path);
            if (!class_exists($class, false)) $class = 'Backbone_Exception';
        } else $class = 'Backbone_Exception';
        
        //Build and return it
        return new $class($error['message'], $code, $previous, isset($error['body']) ? $error['body'] : $body);
    }

    public function __construct($message = "", $code = null, Exception $previous = null, $body = null) {
        if (empty($message) && !empty($code) && array_key_exists($code, static::$status_codes)) {
            $message = static::$status_codes[$code];
        }
        if ($body) $this->body = $body;
        parent::__construct($message, $code, $previous);
    }
    
    /**
     * Return the HTTP status message associated with the exception's code, if it exists, otherwise return its message.
     * Static to allow 
     * @param Exception $e
     * @return multitype:string
     */
    public function getName() {
        if (array_key_exists($this->getCode(), static::$status_codes)) {
            return static::$status_codes[$this->getCode()];
        }
        else {
            return $this->getMessage();
        }
    }
}