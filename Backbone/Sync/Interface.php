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
//@codeCoverageIgnoreStart
/**
 * Provides an interface definition for a callable sync object
 * @author Patrick Barnes
 */
interface Backbone_Sync_Interface {
    /**
     * 
     * Perform the given synchronization method on the object.
     * 
     * The sync() method is expected to be synchronous, unless $options['async'] is set.
     * 
     * Backbone_Sync is expected to:
     *  - Take parameters function($method, $model|$collection, $options)
     *  - Call $options['success']($model|$collection, $response, $options) if the sync was successful.
     *  - Call $options['error']($model|$collection, $response, $options) if the sync was successful.
     *  - Return true|false On success/failure
     * 
     * @param string $method One of; 'create', 'update', 'delete', 'read'
     * @param Backbone_Model|Backbone_Collection $model_or_collection The model to synchronize, typically $this.
     * @param array $options Any options that need to be set in the upstream sync method
     * @return bool TRUE if the operation was successful, FALSE if the operation encountered errors.
     */    
    public function __invoke($method, $model_or_collection, array $options=array());    
}
//@codeCoverageIgnoreEnd