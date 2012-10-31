<?php

///////////////////////////////////////////////////////////////////////////////////
// Quick bootstrap
set_include_path('../../'.PATH_SEPARATOR.get_include_path());
require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance()->setFallbackAutoloader(true);
try {
    $db = Zend_Db::factory('Pdo_Sqlite', array('dbname'=>$dbname=dirname(__FILE__).'/auth.db'));
    Zend_Db_Table::setDefaultAdapter($db);
} catch (Zend_Db_Adapter_Exception $ex) {
    echo "Error connecting to database: ".$ex->getMessage(); exit;
}

$storage = new Backbone_Auth_Storage_Hmac(array('server_key'=>'AE230r983uaF#$J@A3rua', 'lifetime'=>60));
Zend_Auth::getInstance()->setStorage($storage);

$writer = new Zend_Log_Writer_Firebug();
$logger = new Zend_Log($writer);
$log_channel = Zend_Wildfire_Channel_HttpHeaders::getInstance()
    ->setRequest(Backbone::getCurrentRequest())
    ->setResponse(Backbone::getCurrentResponse());

///////////////////////////////////////////////////////////////////////////////////
// Define the identity models

class AE_PersonSync extends Backbone_Sync_DB_Table { 
    protected $table = 'people';
    //Only allow people to update their own record
    //public function update(Backbone_Model $model, array $options = array()) {}
}
class AE_PersonModel extends Backbone_Model { 
    protected $idAttribute = 'username'; 
    //protected $defaults = array('username'=>null,'first_name'=>null,'surname'=>null,'email'=>null);
    public function export(array $options = array()) {
        return json_encode(array_diff_key($this->attributes, array('password'=>true)));
    } 
}
class AE_PersonCollection extends Backbone_Collection { 
    protected $model = 'AE_PersonModel'; 
    protected $sync = 'AE_PersonSync';
    protected $url = 'people/'; 
}

///////////////////////////////////////////////////////////////////////////////////
// Routers
class AuthRouter extends Backbone_Router {
    protected $routes = array(
        'identity' => 'identity',
        'login' => 'login',
        'logout' => 'logout'
    );
    
    /**
     * Fetch and output the person model corresponding to the currently 'logged in' user
     */
    public function identity($options) {
        $id = Zend_Auth::getInstance()->getIdentity();
        if (!$id) throw new Backbone_Exception_Unauthorized("Not logged in");
        
        //Rather than re-implementing a read action, just delegate to AE_PersonRouter
        $apr = new AE_PersonRouter();
        $apr->read($id, $options);
    }
    
    /**
     * Authenticate against the adapter
     * TODO: Figure out how best to make this router generic, configure/pass any adapter (But without hurting performance)
     */
    public function login($options) {
        if (!empty($options['logger'])) $options['logger']->info("Logging in");
        $request = $this->request();
        $username = $request->getParam('username');
        $password = $request->getParam('password');
        
        //Adapter is only defined/used here
        $adapter = new Zend_Auth_Adapter_DbTable(null,'people','username','password');
        $adapter->setIdentity($username)->setCredential($password);
        $result = Zend_Auth::getInstance()->authenticate($adapter);
        if (!$result->isValid()) 
        //    throw new Backbone_Exception_Unauthorized( implode("\n", $result->getMessages()) );
            throw new Backbone_Exception_Unauthorized( "Invalid username or password" );
        
        $credentials = Zend_Auth::getInstance()->getStorage()->getClientCredentials();
        
        $this->response()
            ->setHeader('Content-Type', 'application/json')
            ->setBody( json_encode($credentials) );
    }
    
    /**
     * Logout action
     * Does nothing, just 'catches' clients logging out. In a production server, would be logged.
     */
    public function logout() {
        $this->response()->setBody('OK');   
    }
}

class AE_PersonRouter extends Backbone_Router_Model { 
    protected $collection = 'AE_PersonCollection'; 
    
    public function initialize(array $options) {
        $this->on('before_route', function($_this, $name, $params, $options) {
            //Allow any user to read class definitions
            if ($name == 'exportClasses') 
                return;
            //Other routes require the user to be logged in 
            else if (!$options['identity'])
                throw new Backbone_Exception_Forbidden("Must sign your requests");
        });
    }
}

class IndexRouter extends Backbone_Router {
    protected $routes = array(
        '' => 'index',
        'people/*url' => array('name'=>'people', 'callback'=>'AE_PersonRouter'),
        'auth/*url' => array('name'=>'auth', 'callback'=>'AuthRouter'),
        'public' => array('name'=>'public', 'callback'=>'_public'),
        'private' => array('name'=>'private', 'callback'=>'_private'),
    );
    
    public function _public($options) {
        echo "Public route. No identity needed.<br>";
        echo "Identity: ".$options['identity'];
    }
    
    public function _private($options) {
        if (!$options['identity']) throw new Backbone_Exception_Forbidden("Private route. Identity required.");
        echo "Private route. Identity required.<br>";
        echo "Identity: ".$options['identity'];
    }
    
    public function index($options) {
        $this->response()->setBody(file_get_contents('index.html'));
    }        
}

///////////////////////////////////////////////////////////////////////////////////
// Run
try {
    $router = new IndexRouter();
    $response = $router->response();
    $identity = Zend_Auth::getInstance()->getIdentity();
    
    //By default: no caching    
    $response->setHeader('Pragma', 'no-cache');
    $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $response->setHeader('Cache-Control', 'post-check=0, pre-check=0');
    $response->setHeader('Last-Modified', gmdate("D, d M Y H:i:s").' GMT');
    
    //Run the application
    $match = $router(array(
    	'error' => function($m_or_c, $error, $options) { throw $error; },
        'identity' => $identity,
        'logger' => $logger
    ));
    
    //404 if no match 
    if (!$match)
        throw new Backbone_Exception_NotFound('No route matched - Path not found');
} catch (Exception $e) {
    $code = $e->getCode();
    $response
        ->setHttpResponseCode( ($code>=400 && $code<=599) ? $code : 500 )
        ->setHeader('Content-Type', 'application/json')
        ->setBody( json_encode(array(
            'error'=>$e->getMessage(),
            'type'=>get_class($e),
            'code'=>$code,
        )));
    //$response->setBody("<PRE>".$e."</PRE>");
}
$log_channel->flush();
$response->sendResponse();