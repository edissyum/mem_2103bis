<?php

require '../../vendor/autoload.php';

chdir('../..');

$customs =  scandir('custom');
foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    $migrated = 0;

    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $custom]);

    $listModels = \SrcCore\models\DatabaseModel::select([
        'select'    => ['*'],
        'table'     => ['listmodels'],
        'order_by'  => ['sequence', 'id']
    ]);

    $formattedListModels = [];
    foreach ($listModels as $listModel) {
        $formattedId = $listModel['object_id'].$listModel['object_type'];
        if (empty($formattedListModels[$formattedId])) {
            $formattedListModels[$formattedId] = [
                'object_id'     => $listModel['object_id'],
                'object_type'   => $listModel['object_type'],
                'title'         => $listModel['title'],
                'description'   => $listModel['description'],
                'items'         => [$listModel]
            ];
        } else {
            $formattedListModels[$formattedId]['items'][] = $listModel;
        }
    }

    foreach ($formattedListModels as $value) {
        $entityId = null;
        if (strpos($value['object_id'], 'VISA_CIRCUIT_') === false && strpos($value['object_id'], 'AVIS_CIRCUIT_') === false) {
            $entity = \Entity\models\EntityModel::getByEntityId(['entityId' => $value['object_id'], 'select' => ['id']]);
            $entityId = $entity['id'];
        }

        $type = $value['object_type'] == 'entity_id' ? 'diffusionList' : ($value['object_type'] == 'VISA_CIRCUIT' ? 'visaCircuit' : 'opinionCircuit');
        if (!empty($value['title'])) {
            $title = $value['title'];
        } elseif (!empty($value['description'])) {
            $title = $value['description'];
        } else {
            $title = $value['object_id'];
        }

        $listTemplateId = \Entity\models\ListTemplateModel::create([
            'title'         => $title,
            'description'   => $value['description'],
            'type'          => $type,
            'entity_id'     => $entityId
        ]);

        foreach ($value['items'] as $key => $item) {
            if (empty($item['item_id'])) {
                continue;
            }
            if ($item['item_type'] == 'user_id') {
                $itemId = \User\models\UserModel::getByLogin(['login' => $item['item_id'], 'select' => ['id']]);
            } else {
                $itemId = \Entity\models\EntityModel::getByEntityId(['entityId' => $item['item_id'], 'select' => ['id']]);
            }
            if (empty($itemId['id'])) {
                continue;
            }

            \Entity\models\ListTemplateItemModel::create([
                'list_template_id'  => $listTemplateId,
                'item_id'           => $itemId['id'],
                'item_type'         => $item['item_type'] == 'user_id' ? 'user' : 'entity',
                'item_mode'         => $item['item_mode'],
                'sequence'          => $key
            ]);
        }

        ++$migrated;
    }

    printf("Migration List templates (CUSTOM {$custom}) : " . $migrated . " modèles de listes trouvé(s) et migré(s).\n");
}
