<?php

use Resource\models\ResModel;

require '../../vendor/autoload.php';

chdir('../..');

$customs = scandir('custom');

foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $custom]);

    $migrated = 0;

    $links = \SrcCore\models\DatabaseModel::select([
        'select' => ['res_parent', 'res_child'],
        'table'  => ['res_linked']
    ]);

    foreach ($links as $link) {
        $resParent = (string)$link['res_parent'];
        $resChild = (string)$link['res_child'];

        ResModel::update([
            'postSet' => ['linked_resources' => "jsonb_insert(linked_resources, '{0}', '\"{$resChild}\"')"],
            'where'   => ['res_id = ?', "(linked_resources @> ?) = false"],
            'data'    => [(int)$resParent, "\"{$resChild}\""]
        ]);
        ResModel::update([
            'postSet' => ['linked_resources' => "jsonb_insert(linked_resources, '{0}', '\"{$resParent}\"')"],
            'where'   => ['res_id = ?', "(linked_resources @> ?) = false"],
            'data'    => [(int)$resChild, "\"{$resParent}\""]
        ]);
        $migrated++;
    }

    printf("Migration des liaisons dans res_letterbox (CUSTOM {$custom}) : " . $migrated . " liaisons migrés.\n");
}
