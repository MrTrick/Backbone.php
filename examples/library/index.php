<?php
///////////////////////////////////////////////////////////////////////////////////
// Quick bootstrap
set_include_path('../../'.PATH_SEPARATOR.get_include_path());
require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance()->setFallbackAutoloader(true);

try {
    $db = Zend_Db::factory('Pdo_Sqlite', array('dbname'=> $dbname=dirname(__FILE__).'/library.db'));
    Zend_Db_Table::setDefaultAdapter($db);
} catch (Zend_Db_Adapter_Exception $ex) {
    echo "Error connecting to database: ".$ex->getMessage(); exit;
}

class Library_AuthorSync extends Backbone_Sync_DB_Table { protected $table = 'authors'; }
class Library_AuthorModel extends Backbone_Model { protected $idAttribute = 'author'; protected static $urlRoot = 'authors/'; }
class Library_AuthorCollection extends Backbone_Collection { 
    protected $model = 'Library_AuthorModel'; 
    protected $sync = 'Library_AuthorSync';
    protected $url = 'authors/'; 
}
class Library_AuthorRouter extends Backbone_Router_Model { 
    protected $collection = 'Library_AuthorCollection'; 
    protected $routes = array(
        ':author/books/*url'=>array('name'=>'books', 'callback'=>'Library_BookRouter') //Delegated to Library_BookRouter with 'author' set in params.
    );
}

class Library_BookSync extends Backbone_Sync_DB_Table { protected $table = 'books'; }
class Library_BookModel extends Backbone_Model { }
class Library_BookCollection extends Backbone_Collection { 
    protected $model = 'Library_BookModel'; 
    protected $sync = 'Library_BookSync';
    protected $url = 'books/'; 
}
class Library_BookRouter extends Backbone_Router_Model { protected $collection = 'Library_BookCollection'; }

class LibraryRouter extends Backbone_Router {
    protected $routes = array(
        '' => 'index',
    	'authors/*url' => array('name'=>'authors', 'callback'=>'Library_AuthorRouter'),
        'books/*url' => array('name'=>'books', 'callback'=>'Library_BookRouter')
    );
    
    public function index($options) {
        $response = Backbone::getCurrentResponse();
        $response->setBody(file_get_contents('index.html'));
    }
}

$router = new LibraryRouter();

try {
    $match = $router(array('error' => function($m_or_c, $error, $options) { throw $error; }));
    if (!$match) throw new Exception('No route matched', 404);
    echo (string)Backbone::getCurrentResponse();
} catch (Exception $e) {
    $response = Backbone::getCurrentResponse();
    
    $code = $e->getCode();
    if ($code >= 400 && $code <= 599)
        $response->setHttpResponseCode($code);
    else
        $response->setHttpResponseCode(500);
    
    $response->setBody("<PRE>".$e."</PRE>");
    //$response->setBody($e->getMessage());
    echo (string)$response;
}