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

    $redirectGroupBasket = \SrcCore\models\DatabaseModel::select([
        'select'    => ['group_id', 'basket_id', 'id'],
        'table'     => ['actions_groupbaskets, actions'],
        'where'     => ['keyword = ?', 'id_action = id'],
        'data'      => ['redirect']
    ]);

    $migrated = 0;
    foreach ($redirectGroupBasket as $value) {
        $keywordsAndEntities = \SrcCore\models\DatabaseModel::select([
            'select'    => [1],
            'table'     => ['groupbasket_redirect'],
            'where'     => ['group_id = ?', 'basket_id = ?', 'action_id in (?)'],
            'data'      => [$value['group_id'], $value['basket_id'], $value['id']]
        ]);

        if (empty($keywordsAndEntities)) {
            \Basket\models\GroupBasketRedirectModel::create([
                'id'            => $value['basket_id'],
                'groupId'       => $value['group_id'],
                'actionId'      => $value['id'],
                'entityId'      => '',
                'keyword'       => 'ALL_ENTITIES',
                'redirectMode'  => 'ENTITY'
            ]);
            $migrated++;
        }
    }

    printf("Migration keywords redirection vide (CUSTOM {$custom}) : " . $migrated . " keyword(s) ALL_ENTITIES ajouté(s).\n");
}
