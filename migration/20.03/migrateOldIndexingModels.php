<?php

$aDataIncoming['incoming'] = [
    'doctype'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'priority'              => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'confidentiality'          => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'docDate'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'arrivalDate'           => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'subject'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'indexingCustomField_1' => ['mandatory' => 'false', 'default_value' => '""', 'unit' => 'mail'],
    'senders'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'contact'],
    'recipients'            => ['mandatory' => 'false', 'default_value' => '""', 'unit' => 'contact'],
    'initiator'             => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'process'],
    'destination'           => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'process'],
    'processLimitDate'      => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'process'],
    'folder'                => ['mandatory' => 'false', 'default_value' => '""', 'unit' => 'classement'],
    'tags'                  => ['mandatory' => 'false', 'default_value' => '""', 'unit' => 'classement'],
];

$aDataIncoming['outgoing'] = [
    'doctype'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'priority'              => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'confidentiality'          => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'docDate'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'subject'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'indexingCustomField_1' => ['mandatory' => 'false', 'default_value' => '""', 'unit' => 'mail'],
    'senders'               => ['mandatory' => 'false', 'default_value' => '""', 'unit' => 'contact'],
    'recipients'            => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'contact'],
    'initiator'             => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'process'],
    'destination'           => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'process'],
    'processLimitDate'      => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'process'],
    'folder'                => ['mandatory' => 'false', 'default_value' => '""', 'unit' => 'classement'],
    'tags'                  => ['mandatory' => 'false', 'default_value' => '""', 'unit' => 'classement'],
];

$aDataIncoming['internal'] = [
    'doctype'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'priority'              => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'confidentiality'          => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'docDate'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'subject'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'indexingCustomField_1' => ['mandatory' => 'false', 'default_value' => '""', 'unit' => 'mail'],
    'senders'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'contact'],
    'recipients'            => ['mandatory' => 'false', 'default_value' => '""', 'unit' => 'contact'],
    'initiator'             => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'process'],
    'destination'           => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'process'],
    'processLimitDate'      => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'process'],
    'folder'                => ['mandatory' => 'false', 'default_value' => '""', 'unit' => 'classement'],
    'tags'                  => ['mandatory' => 'false', 'default_value' => '""', 'unit' => 'classement'],
];

$aDataIncoming['ged_doc'] = [
    'doctype'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'confidentiality'          => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'docDate'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'subject'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'mail'],
    'senders'               => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'contact'],
    'recipients'         => ['mandatory' => 'false', 'default_value' => '""', 'unit' => 'contact'],
    'initiator'             => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'process'],
    'destination'           => ['mandatory' => 'true', 'default_value' => '""', 'unit' => 'process'],
];

require '../../vendor/autoload.php';

chdir('../..');

$customs =  scandir('custom');

foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $custom]);


    $oldIndexingModels = \SrcCore\models\DatabaseModel::select([
        'select' => ['*'],
        'table'  => ['indexingmodels']
    ]);

    $superadmin = \User\models\UserModel::getByLogin(['select' => ['id'], 'login' => 'superadmin']);
    if (empty($superadmin)) {
        $firstMan = \User\models\UserModel::get(['select' => ['id'], 'orderBy' => ['id'], 'limit' => 1]);
        $masterOwnerId = $firstMan[0]['id'];
    } else {
        $masterOwnerId = $superadmin['id'];
    }

    $migrated = 0;
    foreach ($oldIndexingModels as $oldIndexingModel) {
        $fieldContent = json_decode($oldIndexingModel['fields_content'], true);
        if (empty($fieldContent['category_id'])) {
            continue;
        }
        $datasToImport = $aDataIncoming[$fieldContent['category_id']];

        $modelId = \IndexingModel\models\IndexingModelModel::create([
            'label'     => $oldIndexingModel['label'],
            'category'  => $fieldContent['category_id'],
            'default'   => 'false',
            'owner'     => $masterOwnerId,
            'private'   => 'false'
        ]);

        foreach ($fieldContent as $key => $field) {
            if ($key == 'type_id') {
                $doctype = \Doctype\models\DoctypeModel::get(['select' => [1], 'where' => ['type_id = ?', 'enabled = ?'], 'data' => [$field, 'Y']]);
                if (!empty($doctype)) {
                    $datasToImport['doctype']['default_value'] = json_encode($field);
                }
            } elseif ($key == 'priority') {
                $priority = \Priority\models\PriorityModel::getById(['select' => [1], 'id' => $field]);
                if (!empty($priority)) {
                    $datasToImport['priority']['default_value'] = json_encode($field);
                }
            } elseif ($key == 'destination') {
                $destination = \Entity\models\EntityModel::get(['select' => ['id'], 'where' => ['entity_id = ?', 'enabled = ?'], 'data' => [$field, 'Y']]);
                if (!empty($destination)) {
                    $datasToImport['destination']['default_value'] = json_encode($destination[0]['id']);
                }
            } elseif ($key == 'subject') {
                $datasToImport['subject']['default_value'] = json_encode($field);
            }
        }
        foreach ($datasToImport as $id => $defaultValue) {
            \IndexingModel\models\IndexingModelFieldModel::create([
                'model_id'      => $modelId,
                'identifier'    => $id,
                'mandatory'     => $defaultValue['mandatory'],
                'default_value' => $defaultValue['default_value'],
                'unit'          => $defaultValue['unit']
            ]);
        }
        ++$migrated;
    }

    printf("Migration anciens modèles d'indexation (CUSTOM {$custom}) : " . $migrated . " modèle(s) utilisé(s) et migré(s).\n");
}
