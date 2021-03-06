<?php

include_once dirname(__FILE__) . "/NotORM.php";

/*
 *  Singleton static class to access main mysql database
 * represents NotORM object.
 *
 */
class ConventionTable extends NotORM_Structure_Convention implements NotORM_Structure{
   
    function getReferencingColumn($target, $source) {
        //dump("$source=>$target");
        switch($target){
            case "users_profiles":
                if($source == "users") return "id";
                break;
            case "chatroom_occupants":
                if($source == "chatrooms") return "idroom";
                if($source == "users") return "id";
                break;
            case "users":
                if($source == "chatroom_occupants") return "id";
                if($source == "users_profiles") return "id";
                break;
            case "forum_posts_data":
                return "id";
                break;
            /*default:
                return "id";*/
        }
        
        return parent::getReferencedColumn($target, $source);
    }
}

class DB{
    private function __construct(){}
    private function __clone(){}
    private static $instance;
    public static function getInstance(){
        if(!self::$instance){
            $dbcfg = Nette\Environment::getConfig('database');
            $m = new Memcache();
            $m->connect("localhost");
            self::$instance = new NotORM(new PDO($dbcfg['driver'] . ":" . $dbcfg['params'], $dbcfg['user'], $dbcfg['password']), 
                    new ConventionTable($primary = 'id', $foreign = 'id',$table = '%s'), 
                    new NotORM_Cache_Memcache($m));
        }
        return self::$instance;
    }
    final public static function __callStatic( $chrMethod, $arrArguments ) {

        $objInstance = self::getInstance();

        return call_user_func_array(array($objInstance, $chrMethod), $arrArguments);

    }
}
