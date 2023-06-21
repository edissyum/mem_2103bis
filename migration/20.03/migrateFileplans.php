<?php

require '../../vendor/autoload.php';

chdir('../..');

$customs =  scandir('custom');

foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $custom]);

    $migrated = 0;

    $fileplans = \SrcCore\models\DatabaseModel::select([
        'select' => ['*'],
        'table'  => ['fp_fileplan']
    ]);

    if (!empty($fileplans)) {

        $superadmin = \User\models\UserModel::getByLogin(['select' => ['id'], 'login' => 'superadmin']);
        if (empty($superadmin)) {
            $firstMan = \User\models\UserModel::get(['select' => ['id'], 'orderBy' => ['id'], 'limit' => 1]);
            $masterOwnerId = $firstMan[0]['id'];
        } else {
            $masterOwnerId = $superadmin['id'];
        }

        $masterFolderId = \Folder\models\FolderModel::create([
            'label'     => 'Reprise Plan de Classement',
            'public'    => true,
            'user_id'   => $masterOwnerId,
            'parent_id' => null,
            'level'     => 0
        ]);

        fillEntities($masterFolderId);

        foreach ($fileplans as $fileplan) {
            $positions = \SrcCore\models\DatabaseModel::select([
                'select'    => ['*'],
                'table'     => ['fp_fileplan_positions'],
                'where'     => ['fileplan_id = ?'],
                'data'      => [$fileplan['fileplan_id']]
            ]);

            if (empty($fileplan['user_id'])) {
                $id = \Folder\models\FolderModel::create([
                    'label'     => $fileplan['fileplan_label'],
                    'public'    => true,
                    'user_id'   => $masterOwnerId,
                    'parent_id' => $masterFolderId,
                    'level'     => 1
                ]);
                fillEntities($id);

                foreach ($positions as $position) {
                    if (empty($position['parent_id'])) {
                        $id = \Folder\models\FolderModel::create([
                            'label'     => $position['position_label'],
                            'public'    => true,
                            'user_id'   => $masterOwnerId,
                            'parent_id' => $id,
                            'level'     => 2
                        ]);
                        fillEntities($id);

                        runPositionsForPublic($positions, $position['position_id'], $id, 3, $masterOwnerId);
                    }
                }
            } else {
                $user = \User\models\UserModel::getByLogin(['select' => ['id'], 'login' => $fileplan['user_id']]);
                if (empty($user)) {
                    continue;
                }

                $id = \Folder\models\FolderModel::create([
                    'label'     => $fileplan['fileplan_label'],
                    'public'    => false,
                    'user_id'   => $user['id'],
                    'parent_id' => null,
                    'level'     => 0
                ]);

                foreach ($positions as $position) {
                    if (empty($position['parent_id'])) {
                        $id = \Folder\models\FolderModel::create([
                            'label'     => $position['position_label'],
                            'public'    => false,
                            'user_id'   => $user['id'],
                            'parent_id' => $id,
                            'level'     => 1
                        ]);
                        fillResources($id, $position['position_id']);

                        runPositionsForPrivate($positions, $position['position_id'], $id, 2, $user['id']);
                    }
                }
            }

            ++$migrated;
        }
    }

    printf("Migration Plan de Classement (CUSTOM {$custom}) : " . $migrated . " Plan trouvé(s) et migré(s).\n");
}

function runPositionsForPublic($positions, $parentPositionId, $parentFolderId, $level, $masterOwnerId)
{
    foreach ($positions as $position) {
        if ($position['parent_id'] == $parentPositionId) {
            $id = \Folder\models\FolderModel::create([
                'label'     => $position['position_label'],
                'public'    => true,
                'user_id'   => $masterOwnerId,
                'parent_id' => $parentFolderId,
                'level'     => $level
            ]);
            fillEntities($id);

            runPositionsForPublic($positions, $position['position_id'], $id, $level + 1, $masterOwnerId);
            break;
        }
    }
}

function runPositionsForPrivate($positions, $parentPositionId, $parentFolderId, $level, $ownerId)
{
    foreach ($positions as $position) {
        if ($position['parent_id'] == $parentPositionId) {
            $id = \Folder\models\FolderModel::create([
                'label'     => $position['position_label'],
                'public'    => false,
                'user_id'   => $ownerId,
                'parent_id' => $parentFolderId,
                'level'     => $level
            ]);
            fillResources($id, $position['position_id']);

            runPositionsForPrivate($positions, $position['position_id'], $id, $level + 1, $ownerId);
            break;
        }
    }
}

function fillEntities($folderId)
{
    \Folder\models\EntityFolderModel::create([
        'folder_id' => $folderId,
        'entity_id' => null,
        'edition'   => true,
        'keyword'   => 'ALL_ENTITIES'
    ]);
}

function fillResources($folderId, $positionId)
{
    $resources = \SrcCore\models\DatabaseModel::select([
        'select'    => ['*'],
        'table'     => ['fp_res_fileplan_positions'],
        'where'     => ['position_id = ?', 'coll_id = ?'],
        'data'      => [$positionId, 'letterbox_coll']
    ]);

    foreach ($resources as $resource) {
        \Folder\models\ResourceFolderModel::create([
            'folder_id' => $folderId,
            'res_id'    => $resource['res_id']
        ]);
    }
}
