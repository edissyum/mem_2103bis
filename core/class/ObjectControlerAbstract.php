<?php

define ("_DEBUG", false);
define ("_ADVANCED_DEBUG",false);

/**
 * Implementing few generic features for controlers of type
 * "all-the-properties-of-the-object-are-the-columns-of-the-
 * database-table", i.e. BaseObject-kind.
 *
 * @author boulio
 *
 */
abstract class ObjectControler
{
    static protected $db;
    static protected $computed_properties = array();
    static protected $foolish_ids = array();
    static protected $specific_id ;

    protected function set_foolish_ids($array)
    {
        if (isset($array) && is_array($array)){
            self::$foolish_ids = $array;
        }
    }

    protected function set_specific_id($id)
    {
        self::$specific_id = $id;
    }

    /**
     * Get object of given class with given id from
     * good table and according with given class name.
     * Can return null if no corresponding object.
     * @param long $id Id of object to get
     * @param string $class_name
     * @return unknown_type
     */
    protected function advanced_get($id, $table_name)
    {
        if (strlen($id) == 0) {
            return null;
        }
        $object_name = $table_name;
        $table_id = $table_name . '_id';

        if(isset(self::$specific_id) && !empty(self::$specific_id)) {
            $table_id = self::$specific_id;
        }

        self::$db = new Database();
        
        $select = "select * from $table_name where $table_id=?";

        $stmt = self::$db->query($select, array($id));
        if ($stmt->rowCount() == 0) {
            return null;
        } else {
            // Constructing result
            $object = new $object_name();
            $queryResult = $stmt->fetchObject();
            foreach ((array)$queryResult as $key => $value) {
                if ($value == 't') {          /* BUG FROM PGSQL DRIVER! */
                    $value = true;            /*                        */
                } elseif ($value == 'f') {    /*                        */
                    $value = false;           /*                        */
                }                            /**************************/
                $object->{$key} = $value;
            }
        }

        return $object;
    }

    /**
     * Enable given object from given table, according with
     * given table id name.
     * Return true if succeeded.
     * @param Any $object
     * @return boolean
     */
    protected function advanced_enable($object)
    {
        if (!isset($object)) {
            return false;
        }
        $table_name = get_class($object);
        $table_id = $table_name . '_id';

        if (isset(self::$specific_id) && !empty(self::$specific_id)) {
            $table_id = self::$specific_id;
        }
        self::$db = new Database();
        
        $query="update $table_name set enabled = 'Y' where $table_id=?";
        
        $stmt = self::$db->query($query, array($object->{$table_id}));
        
        if ($stmt) {
            $result = true;
        } else {
            $result = false;
        }
        
        return $result;
    }

    /**
     * Disable given object from given table, according with
     * given table id name.
     * Return true if succeeded.
     * @param Any $object
     * @return boolean
     */
    protected function advanced_disable($object)
    {
        if (!isset($object)) {
            return false;
        }
        $table_name = get_class($object);
        $table_id=$table_name."_id";

        if (isset(self::$specific_id) && !empty(self::$specific_id)) {
            $table_id = self::$specific_id;
        }
        self::$db = new Database();
        
        $query = "update $table_name set enabled = 'N' where $table_id=?";
        
        $stmt = self::$db->query($query, array($object->{$table_id}));
        
        if ($stmt) {
            $result = true;
        } else {
            $result = false;
        }

        return $result;
    }
}
