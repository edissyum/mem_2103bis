<?php


/*
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*/

/**
* @brief Contains the docservers_controler Object
* (herits of the BaseObject class)
*
* @file
* @author Luc KEULEYAN - BULL
* @author Laurent Giovannoni
* @date $date$
* @version $Revision$
* @ingroup core
*/

//Loads the required class
try {
    require_once 'core/class/class_request.php';
    require_once 'core/class/docservers.php';
    require_once 'core/docservers_tools.php';
    require_once 'core/core_tables.php';
    require_once 'core/class/ObjectControlerAbstract.php';
    require_once 'core/class/ObjectControlerIF.php';
    require_once 'core/class/class_history.php';
} catch (Exception $e) {
    functions::xecho($e->getMessage()) . ' // ';
}

/**
 * Class for controling docservers objects from database
 */
class docservers_controler extends ObjectControler implements ObjectControlerIF
{

    /**
     * Save given object in database:
     * - make an update if object already exists,
     * - make an insert if new object.
     * Return updated object.
     * @param docservers $docservers
     * @return array
     */
    public function save($docserver, $mode='')
    {
    }


    /**
     * Get docservers with given id.
     * Can return null if no corresponding object.
     * @param $id Id of docservers to get
     * @return docservers
     */
    public function get($docserver_id)
    {
        $this->set_foolish_ids(array('docserver_id'));
        $this->set_specific_id('docserver_id');
        $docserver = $this->advanced_get($docserver_id, _DOCSERVERS_TABLE_NAME);
        if (get_class($docserver) <> 'docservers') {
            return null;
        } else {
            return $docserver;
        }
    }

    public function delete($args)
    {
    }

    /**
    * Disables a given docservers
    *
    * @param  $docserver docservers object
    * @return bool true if the disabling is complete, false otherwise
    */
    public function disable($docserver)
    {
        if ($docserver->docserver_id <> 'TEMPLATES') {
            $control = array();
            if (!isset($docserver) || empty($docserver)) {
                $control = array(
                    'status' => 'ko',
                    'value' => '',
                    'error' => _DOCSERVER_EMPTY,
                );
                return $control;
            }
            $docserver = $this->isADocserver($docserver);
            $this->set_foolish_ids(array('docserver_id'));
            $this->set_specific_id('docserver_id');
            if ($this->advanced_disable($docserver)) {
                $control = array(
                    'status' => 'ok',
                    'value' => $docserver->docserver_id,
                );
                if ($_SESSION['history']['docserversban'] == 'true') {
                    $history = new history();
                    $history->add(
                        _DOCSERVERS_TABLE_NAME,
                        $docserver->docserver_id,
                        'BAN',
                        'docserversban',
                        _DOCSERVER_DISABLED . ' : ' . $docserver->docserver_id,
                        $_SESSION['config']['databasetype']
                    );
                }
            } else {
                $control = array(
                    'status' => 'ko',
                    'value' => '',
                    'error' => _PB_WITH_DOCSERVER,
                );
            }
        } else {
            $control = array(
                'status' => 'ko',
                'value' => '',
                'error' => _CANNOT_SUSPEND_DOCSERVER . ' ' . $docserver->docserver_id,
            );
        }
        return $control;
    }

    /**
    * Enables a given docserver
    *
    * @param  $docserver docservers object
    * @return bool true if the enabling is complete, false otherwise
    */
    public function enable($docserver)
    {
    }

    /**
    * Fill a docserver object with an object if it's not a docserver
    *
    * @param  $object ws docserver object
    * @return object docservers
    */
    private function isADocserver($object)
    {
        if (get_class($object) <> 'docservers') {
            $func = new functions();
            $docserverObject = new docservers();
            $array = array();
            $array = $func->object2array($object);
            foreach (array_keys($array) as $key) {
                $docserverObject->{$key} = $array[$key];
            }
            return $docserverObject;
        } else {
            return $object;
        }
    }

    /**
    * Sets the size of the docserver
    * @param $docserver docservers object
    * @param $newSize integer New size of the docserver
    */
    public function setSize($docserver, $newSize)
    {
        $db = new Database();
        $stmt = $db->query(
            "update " . _DOCSERVERS_TABLE_NAME
            . " set actual_size_number = ? where docserver_id = ?",
            array(
                $newSize,
                $docserver->docserver_id
            )
        );
        
        return $newSize;
    }
}
