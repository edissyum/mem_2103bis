<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief  Autofoldering Script
 * @author essaid.meghellet@edissyum.com
 */


use SrcCore\models\CoreConfigModel;

chdir('../..');

require 'vendor/autoload.php';

$customId = null;
if (!empty($argv[1]) && $argv[1] == '--customId' && !empty($argv[2])) {
    $customId = $argv[2];
}

$autofolderingConfigs = initialize($customId);

if (!empty($autofolderingConfigs['treeSetup'])) {
    $treeConfigs = $autofolderingConfigs['treeSetup'];
} else {
    writeLog(['message' => "[ERROR] No  autofoldering script configurations"]);
    exit(0);
}

//Params for user autofoldering
if (!empty($treeConfigs['userAutoFoldering'])) {
    $GLOBALS[' userAutofoldering'] = $treeConfigs['userAutoFoldering'];
    // This is the case if the id that is given in the configuration file
    if (is_int($treeConfigs['userAutoFoldering'])) {
        $userInfo = \User\models\UserModel::getById(['id' => $GLOBALS[' userAutofoldering']]);
    } else {
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS[' userAutofoldering'], 'select' => ['id']]);
    }
    if($userInfo) {
        $GLOBALS['user_id'] = $userInfo['id'];
        dbTablesCleaning($GLOBALS['user_id']);
    } else {
        writeLog(['message' => "[ERROR] userAutoFoldering data is not found in database"]);
        exit(0);
    }

} else {
    writeLog(['message' => "[ERROR] userAutoFoldering data is missing in configurations"]);
    exit(0);
}

//Params of the visibility
if (isset($treeConfigs['edition']) and !empty($treeConfigs['visibility']) and isset($treeConfigs['visibility']['public']) and  isset($treeConfigs['visibility']['entities'])) {
    $GLOBALS['public'] = $treeConfigs['visibility']['public'];
    $GLOBALS['visibility'] = $treeConfigs['visibility']['entities'];
    $GLOBALS['edition'] = $treeConfigs['edition'];

} else {
    writeLog(['message' => "[ERROR] visibility configs is missing in configurations"]);
    exit(0);
}

//Verification of configs params for nodes
if (!empty($treeConfigs['nodes']) and !empty($treeConfigs['levels'])) {
    $GLOBALS['levels'] = $treeConfigs['levels'];
    if (count($treeConfigs['nodes']) != $GLOBALS['levels']) {
        writeLog(['message' => "[ERROR] levels doesn't match with the number of nodes declared in configurations"]);
        exit(0);
    }
    for ($i=0; $i<count($treeConfigs['nodes']); $i++) {
        $node = $treeConfigs['nodes'][$i];
        foreach ($node as $nodeConfig) {
            if ($nodeConfig != '0' and $nodeConfig != null) {
                if (!isset($nodeConfig) or empty($nodeConfig)) {
                    writeLog(['message' => "[ERROR] config is missing in nodes configurations"]);
                    exit(0);
                }
            }
        }
    }
    //Nodes configuration content
    $GLOBALS['nodes'] = $treeConfigs['nodes'];
    autoFolderingLauncher();
} else {
    writeLog(['message' => "[ERROR] nodes or levels is missing in configurations"]);
    exit(0);
}


function checkDataType($table, $targetColumn ,$dataType) {
    $columns = SrcCore\models\DatabaseModel::getColumns([
        'table' => $table
    ]);
    if (!empty($columns)) {
        static $dataInfo = false;
        for ($i = 0; $i < count($columns); $i++) {
            if ($columns[$i]['column_name'] == $targetColumn and $columns[$i]['data_type'] == $dataType) {
                $dataInfo = true;
            }
        }
    } else {
        writeLog(['message' => "[ERROR] no information are found and the node cannot be created"]);
        die();
    }

    return($dataInfo);
}


//Retrieve the file clause if the data is a date
function getClauseFileTypeDate($nodeTargetColumn, $dateFormat, $nodeLabel) {
    if ($dateFormat == 'yyyy') {
        $timeStampOnSet = strtotime("$nodeLabel/01/01");
        $timeStampOutSet = strtotime("$nodeLabel/12/31 23:59:59");
        $timeStampOnSet = date("Y/m/d  H:i:s", $timeStampOnSet);
        $timeStampOutSet = date("Y/m/d  H:i:s", $timeStampOutSet);
        $fileClause = $nodeTargetColumn . " BETWEEN '".$timeStampOnSet."' and '".$timeStampOutSet."'";
    } else if ($dateFormat == 'yyyy/mm') {
        $date = date_create_from_format('Y/m', $nodeLabel);
        $year = date_format($date, 'Y');
        $month = date_format($date, 'm');
        $nb_jour = date('t',mktime(0, 0, 0, $month, 1, $year));
        $timeStampOnSet = strtotime("$nodeLabel/01");
        $timeStampOutSet = strtotime("$nodeLabel/$nb_jour 23:59:59");
        $timeStampOnSet = date("Y/m/d  H:i:s", $timeStampOnSet);
        $timeStampOutSet = date("Y/m/d  H:i:s", $timeStampOutSet);
        $fileClause = $nodeTargetColumn . " BETWEEN '".$timeStampOnSet."' and '".$timeStampOutSet."'";
    } else if ($dateFormat == 'yyyy/mm/dd') {
        $timeStampOnSet = strtotime($nodeLabel);
        $timeStampOnSet = date("Y/m/d  H:i:s", $timeStampOnSet);
        $timeStampOutSet = strtotime("$timeStampOnSet +23 hours +59 minutes +59 seconds");
        $timeStampOutSet = date("Y/m/d  H:i:s", $timeStampOutSet);
        $fileClause = $nodeTargetColumn . " BETWEEN '".$timeStampOnSet."' and '".$timeStampOutSet."'";
    } else {
        writeLog(['message' => "[ERROR] The date format found in configuration file is not correct!"]);
        exit(0);
    }
    return $fileClause ;
}


//Retrieve the folder clause if the data is a date
function getClauseFolderTypeDate($folderTargetColumn, $nodeClause, $folderDateFormat, $folderLabel) {
    if ($folderDateFormat == 'yyyy') {
        $timeStampOnSet = strtotime("$folderLabel/01/01");
        $timeStampOutSet = strtotime("$folderLabel/12/31 23:59:59");
        $timeStampOnSet = date("Y/m/d  H:i:s", $timeStampOnSet);
        $timeStampOutSet = date("Y/m/d  H:i:s", $timeStampOutSet);
        $currentClauseFolder = $folderTargetColumn." BETWEEN '$timeStampOnSet' and '$timeStampOutSet' and ".$nodeClause;
    } else if ($folderDateFormat == 'yyyy/mm') {
        $date = date_create_from_format('Y/m', $folderLabel);
        $year = date_format($date, 'Y');
        $month = date_format($date, 'm');
        $nb_jour = date('t',mktime(0, 0, 0, $month, 1, $year));
        $timeStampOnSet = strtotime("$folderLabel/01");
        $timeStampOutSet = strtotime("$folderLabel/$nb_jour 23:59:59");
        $timeStampOnSet = date("Y/m/d  H:i:s", $timeStampOnSet);
        $timeStampOutSet = date("Y/m/d  H:i:s", $timeStampOutSet);
        $currentClauseFolder = $folderTargetColumn . " BETWEEN '".$timeStampOnSet."' and '".$timeStampOutSet."' and ".$nodeClause;
    } else if ($folderDateFormat == 'yyyy/mm/dd') {
        //If the date format is dd/mm/yyyy
        $timeStampOnSet = strtotime($folderLabel);
        $timeStampOnSet = date("Y/m/d  H:i:s", $timeStampOnSet);
        $timeStampOutSet = strtotime("$timeStampOnSet +23 hours +59 minutes +59 seconds");
        $timeStampOutSet = date("Y/m/d  H:i:s", $timeStampOutSet);
        $currentClauseFolder = $folderTargetColumn . " BETWEEN '".$timeStampOnSet."' and '".$timeStampOutSet."' and ".$nodeClause;
    } else {
        writeLog(['message' => "[ERROR] The date format found in configuration file is not correct!"]);
        exit(0);
    }
    return $currentClauseFolder ;
}


//Function to retrieve the global clause of the documents according to the levels
function getClauseFileByLevels($folderId, $levels) {
    $fileClause = '';
    for ($i= $levels - 1; $i >= 0; $i--) {
        $targetColumnCurrentLevel = $GLOBALS['nodes'][$i]['nodeTargetColumn'];
        $dataTypeCurrentLevel = $GLOBALS['nodes'][$i]['nodeDataType'];
        $dateFormatCurrentLevel = strtolower($GLOBALS['nodes'][$i]['dateFormat']);
        $parentFolderId = \Folder\models\FolderModel::get([
            'select' => ["parent_id"],
            'where' => ["id = $folderId "]
        ]);
        $parentFolderId = intval($parentFolderId[0]['parent_id']);
        $parentFolderLabel = \Folder\models\FolderModel::get([
            'select' => ["label"],
            'where' => ["id = $parentFolderId"],
        ]);
        $parentFolderLabel = (string) $parentFolderLabel[0]['label'];
        if ($dataTypeCurrentLevel =='date') {
            if ($dateFormatCurrentLevel == 'yyyy') {
                $timeStampOnSet = strtotime("$parentFolderLabel/01/01");
                $timeStampOutSet = strtotime("$parentFolderLabel/12/31 23:59:59");
                $timeStampOnSet = date("Y/m/d  H:i:s", $timeStampOnSet);
                $timeStampOutSet = date("Y/m/d  H:i:s", $timeStampOutSet);
                if ($i == 0) {
                    $fileClause .= $targetColumnCurrentLevel . " BETWEEN '$timeStampOnSet' and '$timeStampOutSet'";
                } else {
                    $fileClause .= $targetColumnCurrentLevel." BETWEEN '$timeStampOnSet' and '$timeStampOutSet' and ";
                }
            } else if ($dateFormatCurrentLevel == 'yyyy/mm') {
                $date = date_create_from_format('Y/m', $parentFolderLabel);
                $year = date_format($date, 'Y');
                $month = date_format($date, 'm');
                $nb_jour = date('t',mktime(0, 0, 0, $month, 1, $year));
                $timeStampOnSet = strtotime("$parentFolderLabel/01");
                $timeStampOutSet = strtotime("$parentFolderLabel/$nb_jour 23:59:59");
                $timeStampOnSet = date("Y/m/d  H:i:s", $timeStampOnSet);
                $timeStampOutSet = date("Y/m/d  H:i:s", $timeStampOutSet);
                if ($i == 0) {
                    $fileClause .= $targetColumnCurrentLevel . " BETWEEN '".$timeStampOnSet."' and '".$timeStampOutSet."'";
                } else {
                    $fileClause .= $targetColumnCurrentLevel . " BETWEEN '".$timeStampOnSet."' and '".$timeStampOutSet."' and ";
                }
            } else if ($dateFormatCurrentLevel == 'yyyy/mm/dd') {
                //Si le format de la date est de dd/mm/yyyy
                $timeStampOnSet = strtotime($parentFolderLabel);
                $timeStampOnSet = date("Y/m/d  H:i:s", $timeStampOnSet);
                $timeStampOutSet = strtotime("$timeStampOnSet +23 hours +59 minutes +59 seconds");
                $timeStampOutSet = date("Y/m/d  H:i:s", $timeStampOutSet);
                if ($i == 0) {
                    $fileClause .= $targetColumnCurrentLevel . " BETWEEN '".$timeStampOnSet."' and '".$timeStampOutSet."'";
                } else {
                    $fileClause .= $targetColumnCurrentLevel . " BETWEEN '".$timeStampOnSet."' and '".$timeStampOutSet."' and ";
                }
            } else {
                writeLog(['message' => "[ERROR] The date format found in configuration file is not correct!"]);
                exit(0);
            }
        } else {
            if ($i == 0) {
                $fileClause .= $targetColumnCurrentLevel . " = '" .escapePunctuation($parentFolderLabel). "' ";
            }else {
                $parentFolderLabel = escapePunctuation($parentFolderLabel);
                $fileClause .= $targetColumnCurrentLevel." = '"."$parentFolderLabel' and ";
            }
        }
        $folderId = $parentFolderId;
    }
    return $fileClause;
}


//Function to retrieve the global clause of the folder creation according to the levels
function getClauseFolderByLevels($folderId, $level) {
    $folderClause = '';
    for ($i= $level - 1 ; $i >= 0; $i--) {
        $targetColumnCurrentLevel = $GLOBALS['nodes'][$i]['nodeTargetColumn'];
        $dataTypeCurrentLevel = $GLOBALS['nodes'][$i]['nodeDataType'];
        $dateFormatCurrentLevel = strtolower($GLOBALS['nodes'][$i]['dateFormat']);
        $parentFolderId = \Folder\models\FolderModel::get([
            'select' => ["parent_id"],
            'where' => ["id = $folderId"]
        ]);

        $parentFolderId = intval($parentFolderId[0]['parent_id']);
        $parentFolderLabel = \Folder\models\FolderModel::get([
            'select' => ["label"],
            'where' => ["id = $parentFolderId"],
        ]);
        $parentFolderLabel = $parentFolderLabel[0]['label'];
        if ($dataTypeCurrentLevel =='date') {
            if ($dateFormatCurrentLevel == 'yyyy') {
                $timeStampOnSet = strtotime("$parentFolderLabel/01/01");
                $timeStampOutSet = strtotime("$parentFolderLabel/12/31 23:59:59");
                $timeStampOnSet = date("Y/m/d  H:i:s", $timeStampOnSet);
                $timeStampOutSet = date("Y/m/d  H:i:s", $timeStampOutSet);
                if ($i == 0) {
                    $folderClause .= $targetColumnCurrentLevel . " BETWEEN '$timeStampOnSet' and '$timeStampOutSet'";
                } else {
                    $folderClause .= $targetColumnCurrentLevel." BETWEEN '$timeStampOnSet' and '$timeStampOutSet' and ";
                }
            } else if ($dateFormatCurrentLevel == 'yyyy/mm') {
                $date = date_create_from_format('Y/m', $parentFolderLabel);
                $year = date_format($date, 'Y');
                $month = date_format($date, 'm');
                $nb_jour = date('t',mktime(0, 0, 0, $month, 1, $year));
                $timeStampOnSet = strtotime("$parentFolderLabel/01");
                $timeStampOutSet = strtotime("$parentFolderLabel/$nb_jour 23:59:59");
                $timeStampOnSet = date("Y/m/d  H:i:s", $timeStampOnSet);
                $timeStampOutSet = date("Y/m/d  H:i:s", $timeStampOutSet);
                if ($i == 0) {
                    $folderClause .= $targetColumnCurrentLevel . " BETWEEN '".$timeStampOnSet."' and '".$timeStampOutSet."'";
                } else {
                    $folderClause .= $targetColumnCurrentLevel . " BETWEEN '".$timeStampOnSet."' and '".$timeStampOutSet."' and ";
                }
            } else if ($dateFormatCurrentLevel == 'yyyy/mm/dd') {
                //Si le format de la date est de dd/mm/yyyy
                $timeStampOnSet = strtotime($parentFolderLabel);
                $timeStampOnSet = date("Y/m/d  H:i:s", $timeStampOnSet);
                $timeStampOutSet = strtotime("$timeStampOnSet +23 hours +59 minutes +59 seconds");
                $timeStampOutSet = date("Y/m/d  H:i:s", $timeStampOutSet);
                if ($i == 0) {
                    $folderClause .= $targetColumnCurrentLevel . " BETWEEN '".$timeStampOnSet."' and '".$timeStampOutSet."'";
                } else {
                    $folderClause .= $targetColumnCurrentLevel . " BETWEEN '".$timeStampOnSet."' and '".$timeStampOutSet."' and ";
                }
            } else {
                writeLog(['message' => "[ERROR] The date format found in configuration file is not correct!"]);
                exit(0);
            }
        } else {
            if ($i == 0) {
                $folderClause .= $targetColumnCurrentLevel . " = '" . $parentFolderLabel . "' ";
            } else {
                $folderClause .= $targetColumnCurrentLevel." = '"."$parentFolderLabel' and ";
            }
        }
        $folderId = $parentFolderId;
    }
    return $folderClause;
}


function createFoldersListAndDisplayDocs($NodesListToCreate, $nodeTargetColumn, $folderId, $level, $nodeDataType, $dateFormat, $nodeTargetTable, $displayDocs, $foldersList, $displayDocsClause) {
    $nodeCreationSuccess = false;
    $filesOrderSuccess = false;
    for ($j = 0; $j < count($NodesListToCreate); $j++) {
        //Retrieve the value that will be inserted as a label in the folders table
        $nodeLabel = $NodesListToCreate[$j][$nodeTargetColumn];
        if (!$nodeLabel) {
            writeLog(['message' => "[ERROR] the program stops because a label for the target column node is missing for level [{$level}]"]);
            exit(0);
        }
        $newFolderId = \Folder\models\FolderModel::create([
            'label' => $nodeLabel,
            'public' => $GLOBALS['public'],
            'user_id' => $GLOBALS['user_id'],
            'parent_id' => $folderId,
            'level' => $level
        ]);
        if ($newFolderId) {
            //Associates the newly created folder with the user who created it.
            \Folder\models\UserPinnedFolderModel::create([
                'folder_id' => $newFolderId,
                'user_id' => $GLOBALS['user_id']
            ]);
            //The file will be visible to all entities and no right to modify or delete
            if ($GLOBALS['public'] and $GLOBALS['visibility'] == "ALL_ENTITIES" and $GLOBALS['edition'] == false) {
                $newEntityFolder = \Folder\models\EntityFolderModel::create([
                    'folder_id' => $newFolderId,
                    'entity_id' => null,
                    'edition' => false,
                    'keyword' => "ALL_ENTITIES"
                ]);
                if (!$newEntityFolder) {
                    writeLog(['message' => "[ERROR] failure to add folder entity for folder_id [{$newFolderId}]"]);
                    exit(0);
                } else {
                    $nodeCreationSuccess = true;
                    $folderData = [
                        "folder_id" =>  $newFolderId ,
                        "label" => $nodeLabel ,
                        "level" => $level
                    ];
                    array_push($foldersList, $folderData);
                    if ($displayDocs) {
                        try {
                            $fileGlobalClause = '';
                            $parentFilesClause = getClauseFileByLevels($newFolderId, $level);
                            if ($parentFilesClause) {
                                if ($nodeDataType =='date') {
                                    $fileClause = getClauseFileTypeDate($nodeTargetColumn, $dateFormat, $nodeLabel);
                                } else {
                                    $fileClause = $nodeTargetColumn ."='".escapePunctuation($nodeLabel)."'";
                                }
                                $fileGlobalClause .= $fileClause." and ". $parentFilesClause;
                                if ($displayDocsClause != null)  {
                                    $fileGlobalClause .= ' and '.$displayDocsClause;
                                }
                                $listFilesToOrder = SrcCore\models\DatabaseModel::select([
                                    'select' => ['res_id'],
                                    'table' => [$nodeTargetTable],
                                    'where' => [$fileGlobalClause],
                                ]);
                                if (!empty($listFilesToOrder)) {
                                    foreach ($listFilesToOrder as $fileToOrder) {
                                        //Add each file to the resources_folders table
                                        \Folder\models\ResourceFolderModel::create([
                                            'folder_id' => $newFolderId,
                                            'res_id' => $fileToOrder['res_id']
                                        ]);
                                    }
                                    $filesOrderSuccess = true;
                                } else {
                                    deleteFolderWithoutFiles($newFolderId);
                                }
                            } else {
                                writeLog(['message' => "[ERROR] impossible to retrieve clause from previous nodes"]);
                                exit(0);
                            }
                        } catch (Exception $e) {
                            writeLog(['message' => "[ERROR] Impossible to execute the query the settings are incorrect [{$e->getMessage()}]"]);
                            die();
                        }
                    } else {
                        $filesOrderSuccess = false;
                    }
                }
            }
        } else {
            writeLog(['message' => "[ERROR] impossible to add a new folder"]);
            exit(0);
        }
    }
    return [array($nodeCreationSuccess, $filesOrderSuccess), $foldersList];
}

//The script that will order the nodes and the files they contain for the level 0
function folderingLaunchFirstLevel(array $nodeConfigurations) {
    $nodeTargetTable = strtolower($nodeConfigurations['nodeTargetTable']);
    $nodeTargetColumn = strtolower($nodeConfigurations['nodeTargetColumn']);
    $nodeDataType = strtolower($nodeConfigurations['nodeDataType']);
    $dateFormat = strtolower($nodeConfigurations['dateFormat']);
    $nodeClause = (string) $nodeConfigurations['nodeClause'];
    $nodeOrderBy = (string) $nodeConfigurations['nodeOrderBy'];
    $level = (string) $nodeConfigurations['level'];
    $displayDocs = (string) $nodeConfigurations['displayDocs'];
    $displayDocsClause = (string) $nodeConfigurations['displayDocsClause'];

    static $NodeCreationSuccess = false;
    static $filesOrderSuccess = false;
    //Table that will contain all the nodes we have created
    $resFoldersList = array() ;

    if ($nodeTargetTable == null or $nodeTargetColumn == null or $nodeDataType == null or $nodeClause == null or $level == null) {
        $NodeCreationSuccess = false;
        writeLog(['message' => "[ERROR] failure to add folder entity, missing information in configuration file"]);
        exit(0);
    }
    //If the data type is a date and the format is not filled in
    if ($nodeDataType == 'date' and $dateFormat == null) {
        $NodeCreationSuccess = false;
        writeLog(['message' => "[ERROR] The type of the target column is a date but the date format is not filled in, please fill in the date format in configuration file"]);
        exit(0);
    } else if ($nodeDataType == 'date' and isset($dateFormat)) {
        //If the data type is a date and the format is filled in
        $nodeDataIsDate = checkDataType($nodeTargetTable, $nodeTargetColumn ,'timestamp without time zone');
        if ($nodeDataIsDate and $nodeOrderBy == null) {
            $NodesListToCreate = SrcCore\models\DatabaseModel::select([
                'select' => ["DISTINCT TO_CHAR($nodeTargetColumn :: DATE, '$dateFormat') as $nodeTargetColumn"],
                'table' => [$nodeTargetTable],
                'where' => [$nodeClause],
            ]);
        } else if ($nodeDataIsDate and isset($nodeOrderBy)) {
            $NodesListToCreate = SrcCore\models\DatabaseModel::select([
                'select' => ["DISTINCT TO_CHAR($nodeTargetColumn :: DATE, '$dateFormat')  as $nodeTargetColumn"],
                'table' => [$nodeTargetTable],
                'where' => [$nodeClause],
                'order_by' => [$nodeOrderBy]
            ]);
        } else {
            $NodeCreationSuccess = false;
            writeLog(['message' => "[ERROR] the type of the target column is not a date"]);
            exit(0);
        }
    } else {
        //if the $nodeDataType is something other than date
        if ($nodeOrderBy == null) {
            $NodesListToCreate = SrcCore\models\DatabaseModel::select([
                'select' => ["DISTINCT $nodeTargetColumn "],
                'table' => [$nodeTargetTable],
                'where' => [$nodeClause],
            ]);
        } else {
            $NodesListToCreate = SrcCore\models\DatabaseModel::select([
                'select' => ["DISTINCT $nodeTargetColumn"],
                'table' => [$nodeTargetTable],
                'where' => [$nodeClause],
                'order_by' => [$nodeOrderBy] //si $orderby ? $orderby =>['']
            ]);
        }
    }
    if ($NodesListToCreate) {
        $nodeTargetDataInfo = checkDataType($nodeTargetTable, $nodeTargetColumn ,$nodeDataType);
        if ($nodeTargetDataInfo) {
            for ($i=0; $i<count($NodesListToCreate); $i++) {
                //Retrieve the value that will be inserted as a label in the folders table
                $nodeLabel = $NodesListToCreate[$i][$nodeTargetColumn];
                if (!$nodeLabel) {
                    writeLog(['message' => "[ERROR] the program stops because a label for the target column node is missing for level [{$level}]"]);
                    exit(0);
                }
                //The parent_id of the first level is a null because it is level 0
                $newFolderId = \Folder\models\FolderModel::create([
                    'label' => $nodeLabel,
                    'public' => $GLOBALS['public'],
                    'user_id' => $GLOBALS['user_id'],
                    'parent_id' => null,
                    'level' => $level
                ]);

                if ($newFolderId) {
                    //Associates the newly created folder with the user who created it.
                    \Folder\models\UserPinnedFolderModel::create([
                        'folder_id' => $newFolderId,
                        'user_id' => $GLOBALS['user_id']
                    ]);

                    //The file will be visible to all entities and no right to modify or delete
                    if ($GLOBALS['public'] and $GLOBALS['visibility'] == "ALL_ENTITIES" and $GLOBALS['edition'] == false) {
                        $newEntityFolder = \Folder\models\EntityFolderModel::create([
                            'folder_id' => $newFolderId,
                            'entity_id' => null,
                            'edition' => false,
                            'keyword' => "ALL_ENTITIES"
                        ]);
                        if (!$newEntityFolder) {
                            $NodeCreationSuccess = false;
                            writeLog(['message' => "[ERROR] failure to add folder entity for folder_id [{$newFolderId}]"]);
                            exit(0);
                        } else {
                            $NodeCreationSuccess = true;
                            $folderData =[
                                "folder_id" =>  $newFolderId ,
                                "label" => $nodeLabel ,
                                "level" => $level
                            ];
                            array_push($resFoldersList, $folderData);
                            //If the node is well created, we move on to the classification of the documents
                            //Switch to Document Display if docsDisplay is true
                            if ($displayDocs) {
                                try {
                                    if ($nodeDataType == 'date') {
                                        $fileClause = getClauseFileTypeDate($nodeTargetColumn, $dateFormat, $nodeLabel);
                                    } else {
                                        $fileClause = $nodeTargetColumn ."='".escapePunctuation($nodeLabel)."'";
                                    }
                                    if ($displayDocsClause != null)  {
                                        $fileClause .= ' and '.$displayDocsClause;
                                    }
                                    $listFilesToDisplay = SrcCore\models\DatabaseModel::select([
                                        'select' => ['res_id'],
                                        'table' => [$nodeTargetTable],
                                        'where' => [$fileClause]
                                    ]);
                                    if (!empty($listFilesToDisplay)) {
                                        foreach ($listFilesToDisplay as $fileToDisplay) {
                                            //add each file to the resources_folders table
                                            \Folder\models\ResourceFolderModel::create([
                                                'folder_id' => $newFolderId,
                                                'res_id' => $fileToDisplay['res_id']
                                            ]);
                                        }
                                        $filesOrderSuccess = true;
                                    }
                                } catch (Exception $e) {
                                    writeLog(['message' => "[ERROR] Impossible to execute the query the settings are incorrect [{$e->getMessage()}]"]);
                                    die();
                                }
                            } else {
                                $filesOrderSuccess = false;
                            }
                        }
                    }
                } else {
                    writeLog(['message' => "[ERROR] impossible to add a new folder"]);
                    exit(0);
                }
            }
        }
    } else {
        writeLog(['message' => "[ERROR] no information are found and the nodes cannot be created"]);
        die();
    }
    return [array($NodeCreationSuccess, $filesOrderSuccess), $resFoldersList];
}

//The script that will order the nodes and the files they contain
function folderingLaunchOthersLevels(array $nodeConfigurations, array $foldersList) {
    $nodeTargetTable = strtolower($nodeConfigurations['nodeTargetTable']);
    $nodeTargetColumn = strtolower($nodeConfigurations['nodeTargetColumn']);
    $nodeDataType = strtolower($nodeConfigurations['nodeDataType']);
    $dateFormat = strtolower($nodeConfigurations['dateFormat']);
    $nodeClause = (string) $nodeConfigurations['nodeClause'];
    $nodeOrderBy = (string) $nodeConfigurations['nodeOrderBy'];
    $level = (string) $nodeConfigurations['level'];
    $displayDocs = (string) $nodeConfigurations['displayDocs'];
    $displayDocsClause = (string) $nodeConfigurations['displayDocsClause'];

    static $NodeCreationSuccess = false;
    static $filesOrderSuccess = false;

    $resFoldersList = array();

    if ($nodeTargetTable == null or $nodeTargetColumn == null or $nodeDataType == null or $nodeClause == null or $level == null) {
        $NodeCreationSuccess = false;
        writeLog(['message' => "[ERROR] failure to add folder entity, missing information in configuration file"]);
        exit(0);
    }
    //If the data type is a date and the format is not filled in
    if ($nodeDataType == 'date' and $dateFormat == null) {
        $NodeCreationSuccess = false;
        writeLog(['message' => "[ERROR] The type of the target column is a date but the date format is not filled in, please fill in the date format in configuration file"]);
        exit(0);
    } else if ($nodeDataType == 'date' and isset($dateFormat)) {
        $nodeDataIsDate = checkDataType($nodeTargetTable, $nodeTargetColumn ,'timestamp without time zone');
        if (!$nodeDataIsDate) {
            writeLog(['message' => "[ERROR] Impossible to create this node"]);
            writeLog(['message' => "[ERROR] column data type for this node îs incorrect in configuration file"]);
            die();
        } else {
            //If the type is a date and the format is given
            for ($i=0; $i < count($foldersList); $i++) {
                //Retrieve information about the folder that has already been created
                $folderLevel = $foldersList[$i]['level'];
                $folderId  = $foldersList[$i]['folder_id'];
                $folderLabel = $foldersList[$i]['label'];

                $folderTargetColumn =  $GLOBALS['nodes'][$folderLevel]['nodeTargetColumn'];
                $folderNodeDataType =  $GLOBALS['nodes'][$folderLevel]['nodeDataType'];
                $folderDateFormat =  $GLOBALS['nodes'][$folderLevel]['dateFormat'];
                if ($folderLevel == 0 ) {
                    $targetColumnFirstLevel = $GLOBALS['nodes'][0]['nodeTargetColumn'];
                    $dataTypeFirstLevel = $GLOBALS['nodes'][0]['nodeDataType'];
                    $dateFormatFirstLevel = strtolower($GLOBALS['nodes'][0]['dateFormat']);
                    if ($dataTypeFirstLevel == 'date') {
                        $folderGlobalClause = getClauseFolderTypeDate($targetColumnFirstLevel, $nodeClause, $dateFormatFirstLevel, $folderLabel);
                    } else {
                        $folderGlobalClause = $targetColumnFirstLevel." = '".escapePunctuation($folderLabel)."' and ".$nodeClause;
                    }
                } else {
                    if ($folderNodeDataType == 'date') {
                        $currentClauseFolder = getClauseFolderTypeDate($folderTargetColumn, $nodeClause, $folderDateFormat, $folderLabel);
                    } else {
                        $currentClauseFolder = $folderTargetColumn." = '".escapePunctuation($folderLabel)."' and ".$nodeClause;
                    }
                    $clauseFolderToCreate = getClauseFolderByLevels($folderId, $folderLevel);
                    $folderGlobalClause = $currentClauseFolder.' and '.$nodeClause.' and '.$clauseFolderToCreate;
                }
                if (!$folderGlobalClause) {
                    $NodeCreationSuccess = false;
                    writeLog(['message' => "[ERROR] the script stops because it is impossible to retrieve the parent folder clause"]);
                    exit(0);
                } else {
                    if ($nodeDataIsDate and $nodeOrderBy == null) {
                        $NodesListToCreate = SrcCore\models\DatabaseModel::select([
                            'select' => ["DISTINCT TO_CHAR($nodeTargetColumn :: DATE, '$dateFormat') as $nodeTargetColumn"],
                            'table' => [$nodeTargetTable],
                            'where' => [$folderGlobalClause],
                        ]);
                    } else if ($nodeDataIsDate and isset($nodeOrderBy)) {
                        $NodesListToCreate = SrcCore\models\DatabaseModel::select([
                            'select' => ["DISTINCT TO_CHAR($nodeTargetColumn :: DATE, '$dateFormat') as $nodeTargetColumn"],
                            'table' => [$nodeTargetTable],
                            'where' => [$folderGlobalClause],
                            'order_by' => [$nodeOrderBy]
                        ]);
                    } else {
                        $NodeCreationSuccess = false;
                        writeLog(['message' => "[ERROR] the type of the target column is not a date"]);
                        exit(0);
                    }
                    if ($NodesListToCreate) {
                        $nodeTargetDataInfo = checkDataType($nodeTargetTable, $nodeTargetColumn, $nodeDataType);
                        if ($nodeTargetDataInfo) {
                            $res = createFoldersListAndDisplayDocs($NodesListToCreate, $nodeTargetColumn, $folderId, $level, $nodeDataType, $dateFormat, $nodeTargetTable, $displayDocs, $resFoldersList, $displayDocsClause);
                            if ($res and $res[1]) {
                                $NodeCreationSuccess = $res[0][0];
                                $filesOrderSuccess = $res[0][1];
                                $resFoldersList = $res[1];
                            } else {
                                writeLog(['message' => "[ERROR] Problem occurred when running createFoldersListAndDisplayDocs function for level [{$level}]"]);
                                exit(0);
                            }
                        } else {
                            writeLog(['message' => "[ERROR] Impossible to create this node"]);
                            writeLog(['message' => "[ERROR] column data type for this node îs incorrect in configuration file"]);
                            die();
                        }
                    }
                }
            }
            return [array($NodeCreationSuccess, $filesOrderSuccess), $resFoldersList];
        }
    } else {
        //If the type is something other than a date
        $nodeDataIsDate = checkDataType($nodeTargetTable, $nodeTargetColumn ,$nodeDataType);
        if (!$nodeDataIsDate) {
            writeLog(['message' => "[ERROR] Impossible to create this node"]);
            writeLog(['message' => "[ERROR] column data type for this node îs incorrect in configuration file"]);
            die();
        } else {
            for ($i=0; $i < count($foldersList); $i++) {
                $folderLevel = $foldersList[$i]['level'];
                $folderId  = $foldersList[$i]['folder_id'];
                $folderLabel = $foldersList[$i]['label'];
                $folderTargetColumn =  $GLOBALS['nodes'][$folderLevel]['nodeTargetColumn'];
                $folderNodeDataType =  $GLOBALS['nodes'][$folderLevel]['nodeDataType'];
                $folderDateFormat =  $GLOBALS['nodes'][$folderLevel]['dateFormat'];
                if ($folderLevel == 0 ) {
                    $targetColumnFirstLevel = $GLOBALS['nodes'][0]['nodeTargetColumn'];
                    $dataTypeFirstLevel = $GLOBALS['nodes'][0]['nodeDataType'];
                    $dateFormatFirstLevel = strtolower($GLOBALS['nodes'][0]['dateFormat']);
                    if ($dataTypeFirstLevel =='date') {
                        $folderGlobalClause = getClauseFolderTypeDate($targetColumnFirstLevel, $nodeClause, $dateFormatFirstLevel, $folderLabel);
                    } else {
                        $folderGlobalClause = $targetColumnFirstLevel." ='".escapePunctuation($folderLabel)."' and ".$nodeClause;
                    }
                } else {
                    if ($folderNodeDataType == 'date') {
                        $currentClauseFolder = getClauseFolderTypeDate($folderTargetColumn, $nodeClause, $folderDateFormat, $folderLabel);
                    } else {
                        $currentClauseFolder = $folderTargetColumn." = '".escapePunctuation($folderLabel)."' and ".$nodeClause;
                    }
                    $clauseFolderToCreate = getClauseFolderByLevels($folderId, $folderLevel);
                    $folderGlobalClause = $currentClauseFolder.' and '.$nodeClause.' and '.$clauseFolderToCreate;
                }

                if (!$folderGlobalClause) {
                    $NodeCreationSuccess = false;
                    writeLog(['message' => "[ERROR] le script s'arrete  cat impossible de recuperer la clause des folders parent pour ce niveau"]);
                    exit(0);
                } else {
                    if ($nodeOrderBy == null) {
                        $NodesListToCreate = SrcCore\models\DatabaseModel::select([
                            'select' => ["DISTINCT $nodeTargetColumn "],
                            'table' => [$nodeTargetTable],
                            'where' => [$folderGlobalClause],
                        ]);
                    } else {
                        $NodesListToCreate = SrcCore\models\DatabaseModel::select([
                            'select' => ["DISTINCT $nodeTargetColumn"],
                            'table' => [$nodeTargetTable],
                            'where' => [$folderGlobalClause],
                            'order_by' => [$nodeOrderBy]
                        ]);
                    }
                    if ($NodesListToCreate) {
                        $nodeTargetDataInfo = checkDataType($nodeTargetTable, $nodeTargetColumn, $nodeDataType);
                        if ($nodeTargetDataInfo) {
                            $res = createFoldersListAndDisplayDocs($NodesListToCreate, $nodeTargetColumn, $folderId, $level, $nodeDataType, $dateFormat, $nodeTargetTable, $displayDocs, $resFoldersList, $displayDocsClause);
                            if ($res and $res[1]) {
                                $NodeCreationSuccess = $res[0][0];
                                $filesOrderSuccess = $res[0][1];
                                $resFoldersList = $res[1];
                            } else {
                                writeLog(['message' => "[ERROR] Problem occurred when running createFoldersListAndDisplayDocs function fro level [{$level}]"]);
                                exit(0);
                            }
                        } else {
                            writeLog(['message' => "[ERROR] Impossible to create this node"]);
                            writeLog(['message' => "[ERROR] column data type for this node îs incorrect in configuration file"]);
                            die();
                        }
                    }
                }
            }
        }
        return [array($NodeCreationSuccess, $filesOrderSuccess), $resFoldersList];
    }
}


function autoFolderingLauncher() {
    if ($GLOBALS['levels'] > 0) {
        $listFolders = array();
        for ($i = 0; $i < count($GLOBALS['nodes']); $i++) {
            $nodeConfigs = $GLOBALS['nodes'][$i];
            if ($nodeConfigs['level'] != $i) {
                writeLog(['message' => "[ERROR] The level number for the node[{$nodeConfigs['level']}] does not correspond to the position of the level"]);
                exit(0);
            } else {
                if ($nodeConfigs['level'] == 0) {
                    $res = folderingLaunchFirstLevel($nodeConfigs);
                    if ($res and $res[1]) {
                        $listFolders = $res[1];
                    } else {
                        writeLog(['message' => "[ERROR] Problem occurred when running autofoldering program and no file folder been created fro the first level"]);
                        exit(0);
                    }
                } else {
                    $res = folderingLaunchOthersLevels($nodeConfigs, $listFolders);
                    if ($res and $res[1]) {
                        $listFolders = $res[1];
                    } else {
                        writeLog(['message' => "[ERROR] Problem occurred when running autofoldering program and no file folder have been created for the level [{$i}]"]);
                        exit(0);
                    }
                }
                if (!$res[0][0] and !$res[0][1]) {
                    echo "[message] => [INFO] Problems encountered for the level [{$i}] \n";
                    writeLog(['message' => "[ERROR] Problem occurred when running autofoldering program"]);
                    exit(0);
                } else if ($res[0][0] and !$res[0][1]) {
                    echo "[message] => [INFO] The level: [{$i}] has been successfully created and contains no documents \n";
                } else {
                    echo "[message] => [INFO] The level: [$i] has been successfully created and contains documents \n";
                }
            }
        }
        deleteFolderWithoutChild();
    } else {
        writeLog(['message' => "[ERROR] impossible to create the tree, the number of levels is incorrect"]);
        exit(0);
    }
}

//Cleaning the relevant tables before running the autofoldering  script
function dbTablesCleaning($userId) {
    $ListFoldersToDelete = \Folder\models\FolderModel::getByUserId([
        'select' => ['folders.id'],
        'id' => $userId
    ]);
    if (!$ListFoldersToDelete) {
        writeLog(['message' => "[ERROR] no folder has been created with user_id [{$userId}]"]);
    } else {
        for ($i = 0; $i < count($ListFoldersToDelete); $i++) {
            $folderID = intval($ListFoldersToDelete[$i]['id']);

            $deleteFolder = \Folder\models\FolderModel::delete([
                'where' => ['id = ?'],
                'data' => [$folderID]
            ]);

            $deleteFolderEntity = \Folder\models\EntityFolderModel::delete([
                'where' => ['folder_id = ?'],
                'data' => [$folderID]
            ]);
            $deleteResourceFolder = \Folder\models\ResourceFolderModel::delete([
                'where' => ['folder_id = ?'],
                'data' => [$folderID]
            ]);
            $deleteUserPinnedUser = \Folder\models\UserPinnedFolderModel::delete([
                'where' => ['folder_id = ?'],
                'data' => [$folderID]
            ]);
            if (!$deleteFolder or !$deleteFolderEntity or !$deleteResourceFolder or !$deleteUserPinnedUser) {
                writeLog(['message' => "[ERROR] failure to delete Folder or Entity Folder for user_id [{$userId}]"]);
                exit(0);
            }
        }
    }
}

function initialize($customId) {
    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $customId]);

    $path = 'apps/maarch_entreprise/xml/autofoldering.json';
    if (!empty($customId) && is_file("custom/{$customId}/{$path}")) {
        $path = "custom/{$customId}/{$path}";
    } else {
        writeLog(['message' => "[ERROR] custom ID is missing or incorrect"]);
        exit();
    }
    if (!is_file($path)) {
        writeLog(['message' => "[ERROR] autofoldering file is missing"]);
        exit();
    }
    $autofolderingFile = CoreConfigModel::getJsonLoaded(['path' => $path]);
    //Retrieve the contents of the configuration file for filing
    if (empty($autofolderingFile) || empty($autofolderingFile['treeSetup'])) {
        writeLog(['message' => "[ERROR] No  autofoldering script configurations found"]);
        exit();
    }
    return $autofolderingFile;
}


function deleteFolderWithoutFiles($folderID) {
    $deleteFolder = \Folder\models\FolderModel::delete([
        'where' => ['id = ?'],
        'data' => [$folderID]
    ]);
    $deleteFolderEntity = \Folder\models\EntityFolderModel::delete([
        'where' => ['folder_id = ?'],
        'data' => [$folderID]
    ]);
    $deleteResourceFolder = \Folder\models\ResourceFolderModel::delete([
        'where' => ['folder_id = ?'],
        'data' => [$folderID]
    ]);
    $deleteUserPinnedUser = \Folder\models\UserPinnedFolderModel::delete([
        'where' => ['folder_id = ?'],
        'data' => [$folderID]
    ]);
    if (!$deleteFolder or !$deleteFolderEntity or !$deleteResourceFolder or !$deleteUserPinnedUser) {
        writeLog(['message' => "[ERROR] failure to delete Folder with folder_id [{$folderID}]"]);
        exit(0);
    }
}


function deleteFolderWithoutChild() {
    $ListFolders = \Folder\models\FolderModel::getByUserId([
        'select' => ['folders.id'],
        'id' =>  $GLOBALS['user_id']
    ]);
    if (!$ListFolders) {
        writeLog(['message' => "[ERROR] no folder has been created with user_id [{$GLOBALS['user_id']}]"]);
    } else {
        for ($i = 0; $i < count($ListFolders); $i++) {
            $folderItemId = intval($ListFolders[$i]['id']);
            $folderChildId = \Folder\models\FolderModel::getChild([
                'select'=> ['id'],
                'id' => $folderItemId
            ]);
            $resourcesInFolders = \Folder\models\FolderModel::getWithResources([
                'select' => ['resources_folders.res_id'],
                'where'  => ['resources_folders.folder_id in (?)'],
                'data'   => [$folderItemId]
            ]);
            if (empty($folderChildId) and empty($resourcesInFolders)) {
                $deleteFolder = \Folder\models\FolderModel::delete([
                    'where' => ['id = ?'],
                    'data' => [$folderItemId]
                ]);
                $deleteFolderEntity = \Folder\models\EntityFolderModel::delete([
                    'where' => ['folder_id = ?'],
                    'data' => [$folderItemId]
                ]);
                $deleteResourceFolder = \Folder\models\ResourceFolderModel::delete([
                    'where' => ['folder_id = ?'],
                    'data' => [$folderItemId]
                ]);
                $deleteUserPinnedUser = \Folder\models\UserPinnedFolderModel::delete([
                    'where' => ['folder_id = ?'],
                    'data' => [$folderItemId]
                ]);
                if (!$deleteFolder or !$deleteFolderEntity or !$deleteResourceFolder or !$deleteUserPinnedUser) {
                    writeLog(['message' => "[ERROR] failure to delete Folder or Entity Folder for user_id [{$GLOBALS['user_id']}]"]);
                    exit(0);
                }
            }
        }
        echo "[message] => [INFO] Delete all nodes that have no children and no documents to display ... \n";
    }
}


function writeLog(array $args) {
    if (strpos($args['message'], '[ERROR]') === 0) {
        \SrcCore\controllers\LogsController::add([
            'isTech' => true,
            'moduleId' => 'autofolderingScript',
            'level' => 'ERROR',
            'tableName' => '',
            'recordId' => 'autofolderingScript',
            'eventType' => 'autofolderingScript',
            'eventId' => $args['message']
        ]);
    } else {
        \SrcCore\controllers\LogsController::add([
            'isTech'    => true,
            'moduleId'  => 'autofolderingScript',
            'level'     => $args['level'] ?? 'INFO',
            'tableName' => '',
            'recordId'  => 'autofolderingScript',
            'eventType' => 'autofolderingScript',
            'eventId'   => $args['message']
        ]);
    }
}

function escapePunctuation($string) {
    $punctuation = array("'", '"', "\\", ";", ",", "(", ")");
    $replacement = array("''", '""', "\\\\", "\\;", "\\,", "\\(", "\\)");

    return str_replace($punctuation, $replacement, $string);
}