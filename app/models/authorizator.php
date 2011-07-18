<?php

use Nette\Environment;
class Permissions extends Nette\Object{
    private $storage;
    private $uniq_key;
    private $tags;
    public function __construct(){
        $user = Environment::getUser();
        $roles = $user->getRoles();
        $this->uniq_key = 'permission_' . $user->getId() . '_' . $roles[0];
        $this->tags = array('permission_user_' . $user->getId(), 'permission_group_' . $roles[0], 'permission_all');
        $this->load();
    }
    
    public function __destruct() {
        try{
            if(!is_null($this->storage)){
                MC::write($this->uniq_key, serialize($this->storage), array("tags" => $this->tags));
            }
        }
        catch(Exception $e){
            dump($e);
            die("");
        }
    }
    public function getId(){
        return $this->uniq_key;
    }
    
    public function getRaw(){
        return $this->storage;
    }
    
    /* Loads permissions for session */
    private function load(){
        $r = MC::read($this->uniq_key);
        if($r){                                     /* Get permissions from cache if posible, else reload it form db */
            $this->storage = unserialize ($r);
        }else{
            $this->forceReload();
        }
    }
    
    /* Removes all permisions from memory/storage */
    public static function unload(){
        $t = empty($this) ? new Permissions() : $this;
        $t->storage = NULL;
        MC::remove($t->getId());
    }
    
    public function forceReload(){
        foreach(DB::permissions("target", Environment::getUser()->getRoles())->where("type", "group")->union(DB::permissions("target", Environment::getUser()->getId())->where("type", "user")) as $perm){
            if(empty($this->storage[$perm["resource"]])) $this->storage[$perm["resource"]] = array();
            if(is_null($perm["operation"])) $this->storage[$perm["resource"]]['_ALL'] = $perm["value"];
            else $this->storage[$perm["resource"]][$perm["operation"]] = $perm["value"];
        }    
    }
    
    public function get($resource, $priviledge, $forceReload = false){
        if($forceReload) $this->forceReload ();
        if(empty($this->storage[$resource])) return false;                              /* Permission has not been defined */
        if(is_array($this->storage[$resource])){                                        /* Access with exact permission */
            return (!isset($this->storage[$resource][$priviledge]) ? $this->storage[$resource]['_ALL'] : $this->storage[$resource][$priviledge]) ? true : false; /* If permission for operation is not set, it will try to use global permision for resource */
        }
        return false;                                                                   /* Fallback */
    }
    
    public function hasPermissionSet($permission){
        return isset($this->storage[$permission]);
    }
    
    public function setResource(Nette\Utils\Strings $resource, array $permissions, $override = false){
        if($override || $this->hasPermissionSet($resource))
            $this->storage[$resource] = $permissions;
    }
    public function setOwner(Nette\Utils\Strings $resource){
        $this->storage[$resource] = array("_ALL" => true);
    }
}

class UserAuthorizator extends Nette\Object implements Nette\Security\IAuthorizator
{
    private static $instance;
    public static function getInstance(){
        if(!self::$instance){
            self::$instance = new Permissions();
        }
        return self::$instance;
    }
    public function isAllowed($role = self::ALL, $resource = self::ALL, $privilege = self::ALL)
    {
        /* Role must be defined */
        if($role == null || !Environment::getUser()->isLoggedIn()) return false;
        /* root is allowed to do anything */
        if($role == 0 || Environment::getUser()->getId() == 0 || (is_array($role) && in_array(0, $role))) return true; 
        
        /* Return final priviledge */
        return $this->getInstance()->get($resource, $privilege);
    }
}