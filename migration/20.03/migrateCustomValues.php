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

    $migrated = [];

    // Migrate others Field
    $migrateToCustom = [
        ['id' => 'description',         'label' => 'Autres informations',             'customType' => 'string', 'modelId' => [1, 2, 3, 4]],
        ['id' => 'external_reference',  'label' => 'Référence courrier expéditeur',   'customType' => 'string', 'modelId' => [1]],
        ['id' => 'reference_number',    'label' => 'N° recommandé',                   'customType' => 'string', 'modelId' => [1, 2]],
        ['id' => 'scan_date',           'label' => 'Date de scan',                    'customType' => 'date',   'modelId' => [1, 2]],
        ['id' => 'scan_user',           'label' => 'Utilisateur de scan',             'customType' => 'string', 'modelId' => [1, 2]],
        ['id' => 'scan_location',       'label' => 'Lieu de scan',                    'customType' => 'string', 'modelId' => [1, 2]],
        ['id' => 'scan_wkstation',      'label' => 'Station de scan',                 'customType' => 'string', 'modelId' => [1, 2]],
        ['id' => 'scan_batch',          'label' => 'Batch de scan',                   'customType' => 'string', 'modelId' => [1, 2]],
        ['id' => 'scan_postmark',       'label' => 'Tampon de scan',                  'customType' => 'string', 'modelId' => [1, 2]],
    ];

    foreach ($migrateToCustom as $migration) {
        if ($migration['customType'] == 'date') {
            $where = [$migration['id'].' is not null'];
        } else {
            $where = [$migration['id'].' is not null', $migration['id'].' != \'\''];
        }
        $columnValues = \SrcCore\models\DatabaseModel::select([
            'select' => ['res_id', $migration['id']],
            'table'  => ['res_letterbox'],
            'where'  => $where
        ]);

        if (!empty($columnValues)) {
            $fieldId = \CustomField\models\CustomFieldModel::create([
                'label'     => $migration['label'],
                'type'      => $migration['customType'],
                'values'    => '[]'
            ]);

            $csColumn = "custom_fields->>''{$fieldId}''";
            if ($type == 'date') {
                $csColumn = "($csColumn)::date";
            }
            \Basket\models\BasketModel::update(['postSet' => ['basket_clause' => "REPLACE(basket_clause, '{$migration['id']}', '{$csColumn}')"], 'where' => ['1 = ?'], 'data' => [1]]);

            foreach ($migration['modelId'] as $modelId) {
                $indexingModels = \IndexingModel\models\IndexingModelModel::get([
                    'select'=> [1],
                    'where' => ['id = ?'],
                    'data'  => [$modelId]
                ]);

                if (!empty($indexingModels)) {
                    \IndexingModel\models\IndexingModelFieldModel::create([
                        'model_id'   => $modelId,
                        'identifier' => 'indexingCustomField_'.$fieldId,
                        'mandatory'  => 'false',
                        'unit'       => 'mail'
                    ]);
                }
            }
    
            $aValues = [];
            foreach ($columnValues as $columnValue) {
                $aValues[] = [$columnValue['res_id'], $fieldId, json_encode($columnValue[$migration['id']])];
                $valueColumn = json_encode($columnValue[$migration['id']]);
                $valueColumn = str_replace("'", "''", $valueColumn);
                \Resource\models\ResModel::update([
                    'postSet'   => ['custom_fields' => "jsonb_set(custom_fields, '{{$fieldId}}', '{$valueColumn}')"],
                    'where'     => ['res_id = ?'],
                    'data'      => [$columnValue['res_id']]
                ]);
            }

            $migrated[] = $migration['id'];
        }
    }

    if (!empty($migrated)) {
        $migrated = implode(',', $migrated);
        printf("Migration vers les champs personnalisés (CUSTOM {$custom}) : Les champs suivants ont été migrés : " . $migrated . ".\n");
    }
}
