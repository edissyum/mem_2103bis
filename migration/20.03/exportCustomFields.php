<?php

require '../../vendor/autoload.php';

chdir('../..');

$customs =  scandir('custom');


ini_set('memory_limit', -1);

foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }
    $migrated = 0;

    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $custom]);


    $fields = \SrcCore\models\DatabaseModel::select([
        'select' => ['res_id', 'alt_identifier', 'custom_n1', 'custom_f1', 'custom_d1', 'custom_t2', 'custom_n2', 'custom_f2', 'custom_d2', 'custom_t3', 'custom_n3', 'custom_f3', 'custom_d3', 'custom_t4', 'custom_n4', 'custom_f4', 'custom_d4', 'custom_t5', 'custom_n5', 'custom_f5', 'custom_d5', 'custom_t6', 'custom_d6', 'custom_t7', 'custom_d7', 'custom_t8', 'custom_d8', 'custom_t9', 'custom_d9', 'custom_t10', 'custom_d10', 'custom_t11', 'custom_t12', 'custom_t13', 'custom_t14', 'custom_t15'],
        'table'  => ['res_letterbox'],
        'where' => ['custom_n1 is not null or custom_f1 is not null or custom_d1 is not null or custom_t2 is not null or custom_n2 is not null or custom_f2 is not null or custom_d2 is not null or custom_t3 is not null or custom_n3 is not null or custom_f3 is not null or custom_d3 is not null or custom_t4 is not null or custom_n4 is not null or custom_f4 is not null or custom_d4 is not null or custom_t5 is not null or custom_n5 is not null or custom_f5 is not null or custom_d5 is not null or custom_t6 is not null or custom_d6 is not null or custom_t7 is not null or custom_d7 is not null or custom_t8 is not null or custom_d8 is not null  or custom_t9 is not null or custom_d9 is not null or custom_t10 is not null or custom_d10 is not null or custom_t11 is not null or custom_t12 is not null or custom_t13 is not null or custom_t14 is not null or custom_t15 is not null']
    ]);

    if (!empty($fields)) {
        $file = fopen("migration/20.03/customFields_{$custom}.csv", 'w+');
        $csvHead = ['Res id', 'Numéro chrono', 'custom_n1', 'custom_f1', 'custom_d1', 'custom_t2', 'custom_n2', 'custom_f2', 'custom_d2', 'custom_t3', 'custom_n3', 'custom_f3', 'custom_d3', 'custom_t4', 'custom_n4', 'custom_f4', 'custom_d4', 'custom_t5', 'custom_n5', 'custom_f5', 'custom_d5', 'custom_t6', 'custom_d6', 'custom_t7', 'custom_d7', 'custom_t8', 'custom_d8', 'custom_t9', 'custom_d9', 'custom_t10', 'custom_d10', 'custom_t11', 'custom_t12', 'custom_t13', 'custom_t14', 'custom_t15'];
        fputcsv($file, $csvHead, ',');

        foreach ($fields as $field) {
            fputcsv($file, $field, ',');
            $migrated++;
        }

        fclose($file);
    }

    printf("Export champs custom de res_letterbox (CUSTOM {$custom}) : " . $migrated . " Champ(s) exporté(s) dans le fichier customFields_{$custom}.csv\n");
}
