<?php
//This class is currently orphan code, needs refactoring before use.
class Backbone_Validate extends Zend_Validate {
    /**
     * Given a base name (eg 'alnum'), discover and return the full validator class name. (eg 'Zend_Validate_Alnum')
     * The class is guaranteed to exist, but not guaranteed to implement Zend_Validate_Interface 
     * @param string $classBaseName
     * @param array $namespaces
     */
    public static function getValidatorClassName($classBaseName, $namespaces = array()) {
        $namespaces = array_merge((array) $namespaces, self::$_defaultNamespaces, array('Zend_Validate'));
        $className  = ucfirst($classBaseName);
        
        //If a full class name is given, just use it.
        if (class_exists($className, false))
            return $className;
            
        //Search through the given namespaces, looking for a file that should contain $namespace_$className
        require_once 'Zend/Loader.php';
        foreach($namespaces as $namespace) {
            $class = $namespace . '_' . $className;
            $file  = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
            if (Zend_Loader::isReadable($file)) {
                try {
                    Zend_Loader::loadClass($class);
                    return $class;
                } catch (Exception $e) {
                    break;
                }
            }
        }
        
        require_once 'Zend/Validate/Exception.php';
        throw new Zend_Validate_Exception("Validate class not found from basename '$classBaseName'");
    }
    
    /**
     * Fetch the validator instance for the given validator definition
     * 
     * Copied just the section out of Zend_Validate::is() that fetches the validator instance,
     * so that error messages can be retrieved.
     * Partially delegates to getValidatorClassName 
     * 
     * @param  string   $classBaseName
     * @param  array    $args          OPTIONAL
     * @param  mixed    $namespaces    OPTIONAL
     * @return Zend_Validate_Interface
     * @throws Zend_Validate_Exception
     */
    public static function getValidator($classBaseName, array $args = array(), $namespaces = array()) {
        $className = self::getValidatorClassName($classBaseName, $namespaces);
        
        $class = new ReflectionClass($className);
        if ($class->implementsInterface('Zend_Validate_Interface')) {
            if ($class->hasMethod('__construct')) {
                $keys    = array_keys($args);
                $numeric = false;
                foreach($keys as $key) {
                    if (is_numeric($key)) {
                        $numeric = true;
                        break;
                    }
                }

                if ($numeric) {
                    $object = $class->newInstanceArgs($args);
                } else {
                    $object = $class->newInstance($args);
                }
            } else {
                $object = $class->newInstance();
            }

            return $object;
        }
    }
    
    /**
     * Given the validator definition, tries to return the equivalent Backbone.validations rule.
     * TODO: Implement
     *  
     * @param string $classBaseName
     * @param array $args
     * @param array $namespaces
     * @throws Exception
     */
    public static function getBackboneRule($classBaseName, array $args = array(), $namespaces = array()) {
        throw new Exception("Not implemented yet");                
    }
}