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


    $fileplans = \SrcCore\models\DatabaseModel::select([
        'select'    => ['fileplan_id', 'fileplan_label'],
        'table'     => ['fp_fileplan'],
        'where'     => ['user_id is null']
    ]);

    if (!empty($fileplans)) {
        $file = fopen("migration/20.03/fileplans_{$custom}.csv", 'w+');
        $csvHead = ['Identifiant plan', 'Libellé plan', 'Identifiant position', 'Libellé position', 'Identifiant courrier', 'Numéro chrono', 'Sujet courrier'];
        fputcsv($file, $csvHead, ',');

        foreach ($fileplans as $fileplan) {
            $csvContent = [];
            $positions = \SrcCore\models\DatabaseModel::select([
                'select'    => ['position_id', 'position_label'],
                'table'     => ['fp_fileplan_positions'],
                'where'     => ['fileplan_id = ?'],
                'data'      => [$fileplan['fileplan_id']]
            ]);

            foreach ($positions as $position) {
                $resources = \SrcCore\models\DatabaseModel::select([
                    'select'    => ['r.res_id', 'r.subject', 'mlb.alt_identifier'],
                    'table'     => ['fp_res_fileplan_positions p, res_letterbox r, mlb_coll_ext mlb'],
                    'where'     => ['p.res_id = r.res_id', 'r.res_id = mlb.res_id', 'position_id = ?', 'r.status <> \'DEL\''],
                    'data'      => [$position['position_id']]
                ]);
                foreach ($resources as $resource) {
                    $csvContent = [$fileplan['fileplan_id'], $fileplan['fileplan_label'], $position['position_id'], $position['position_label'], $resource['res_id'], $resource['alt_identifier'], $resource['subject']];
                    fputcsv($file, $csvContent, ',');
                }
            }

            if (!empty($csvContent)) {
                ++$migrated;
            }
        }

        fclose($file);
    }

    printf("Export Plans de Classements (CUSTOM {$custom}) : " . $migrated . " Plan(s) de classement public exporté(s) dans le fichier fileplans_{$custom}.csv\n");
}
