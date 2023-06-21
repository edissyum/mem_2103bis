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
    $folders = \SrcCore\models\DatabaseModel::select([
        'select'   => ['folders_system_id', 'typist', 'destination', 'folder_name', 'parent_id'],
        'table'    => ['folder_tmp'],
        'order_by' => ['folder_level asc']
    ]);

    if (!empty($folders)) {
        $superadmin = \User\models\UserModel::getByLogin(['select' => ['id'], 'login' => 'superadmin']);
        if (empty($superadmin)) {
            $firstMan = \User\models\UserModel::get(['select' => ['id'], 'orderBy' => ['id'], 'limit' => 1]);
            $masterOwnerId = $firstMan[0]['id'];
        } else {
            $masterOwnerId = $superadmin['id'];
        }

        $masterFolderId = \Folder\models\FolderModel::create([
            'label'     => 'Reprise Dossier',
            'public'    => true,
            'user_id'   => $masterOwnerId,
            'parent_id' => null,
            'level'     => 0
        ]);

        fillEntities($masterFolderId);

        $aFolderIdMap = [];
        foreach ($folders as $folder) {
            if (empty($folder['typist'])) {
                $user['id'] = $masterOwnerId;
            } else {
                $user = \User\models\UserModel::getByLogin(['select' => ['id'], 'login' => $folder['typist']]);
            }
            if (empty($folder['destination'])) {
                // Public
                if (empty($user)) {
                    $user['id'] = $masterOwnerId;
                }

                $folderId = \Folder\models\FolderModel::create([
                    'label'     => $folder['folder_name'],
                    'public'    => true,
                    'user_id'   => $user['id'],
                    'parent_id' => empty($folder['parent_id']) ? $masterFolderId : $aFolderIdMap[$folder['parent_id']],
                    'level'     => empty($folder['parent_id']) ? 1 : 2
                ]);
                fillEntities($folderId);
            } elseif (!empty($user)) {
                // Private
                $entity = \Entity\models\EntityModel::getByEntityId(['select' => ['id'], 'entityId' => $folder['destination']]);
                if (empty($entity)) {
                    continue;
                }

                $folderId = \Folder\models\FolderModel::create([
                    'label'     => $folder['folder_name'],
                    'public'    => true,
                    'user_id'   => $user['id'],
                    'parent_id' => $aFolderIdMap[$folder['parent_id']],
                    'level'     => empty($folder['parent_id']) ? 0 : 1
                ]);

                \Folder\models\EntityFolderModel::create([
                    'folder_id' => $folderId,
                    'entity_id' => $entity['id'],
                    'edition'   => true,
                ]);

                fillResources($folderId, $folder['folders_system_id']);
            }

            $aFolderIdMap[$folder['folders_system_id']] = $folderId;
            ++$migrated;
        }
    }

    printf("Migration Dossier (CUSTOM {$custom}) : " . $migrated . " Dossier(s) trouvé(s) et migré(s).\n");
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

function fillResources($folderId, $folderSystemId)
{
    $resources = \SrcCore\models\DatabaseModel::select([
        'select'    => ['res_id'],
        'table'     => ['res_letterbox'],
        'where'     => ['folders_system_id = ?'],
        'data'      => [$folderSystemId]
    ]);

    foreach ($resources as $resource) {
        \Folder\models\ResourceFolderModel::create([
            'folder_id' => $folderId,
            'res_id'    => $resource['res_id']
        ]);
    }
}
