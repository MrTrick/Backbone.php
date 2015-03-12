<?php
require_once('Zend/Loader/Autoloader.php');
Zend_Loader_Autoloader::getInstance()->setFallbackAutoloader(true);

/**
 * Stores common classes for testing
 */

/**
 * Test_Model makes some small changes from Backbone_Model;
 * A static buildClass method to extend a new class:
 *  - buildClass($static, $attrs, $options) 
 *  - Generates a unique class name ( using Backbone::uniqueId() )
 *  - Defines a new child class, sets the static parameters in that class to the given values (not modifying the parent class) 
 * 
 * For each of 'initialize', 'validate', 'parse', 'urlRoot':
 *  - Allows a callback to be passed on construction to override that function.
 *  - Allows a callback to be set via setFunc($name, $func) to override that function.
 *  
 * @author Patrick Barnes
 */
class Test_Model extends Backbone_Model {
    protected $_funcs = array('initialize'=>null, 'validate'=>null, 'parse'=>null, 'urlRoot'=>null);
    public function setFunc($name, $func) { $this->_funcs[$name] = $func; }
    public function func($name, $args) { 
        return $this->_funcs[$name] 
            ? call_user_func_array($this->_funcs[$name], array_merge($args, array($this))) 
            : call_user_func_array("parent::$name", $args);
    }    
    public function initialize($attrs, array $options) { return $this->func('initialize', array($attrs, $options)); }
    public function validate(array $attrs, array $options=array()) { return $this->func('validate', array($attrs, $options)); }
    public function parse($in) { return $this->func('parse', array($in)); }
    public function urlRoot($urlRoot=null) { return $this->func('urlRoot', array($urlRoot)); }
    public function __construct($attrs = array(), $options = array()) {
        foreach($this->_funcs as $func=>$j) if (!empty($options[$func])) $this->setFunc($func, $options[$func]);
        parent::__construct($attrs, $options);
    }
    public static function buildClass($static) {
        $class = Backbone::uniqueId('Test_Model_');
        $static_members = implode("\n", array_map(function($v) { return 'public static $'.$v.';'; }, array_keys($static)));
        eval('class '.$class.' extends Test_Model {'.$static_members.'}');
        foreach($static as $k=>$v) $class::$$k = $v;
        return $class;
    }
    
    public static $staticMember = 'foo';
    const TEST_CONSTANT = "TEST";
}

class Test_Collection extends Backbone_Collection {
    protected static $defaultUrl = '/collection';
    
    public static function buildClass($static) {
        $class = Backbone::uniqueId('Test_Collection_');
        $static_members = implode("\n", array_map(function($v) { return 'public static $'.$v.';'; }, array_keys($static)));
        eval('class '.$class.' extends Test_Collection {'.$static_members.'}');
        foreach($static as $k=>$v) $class::$$k = $v;
        return $class;
    }
}