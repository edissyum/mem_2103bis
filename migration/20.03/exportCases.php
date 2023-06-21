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


    $cases = \SrcCore\models\DatabaseModel::select([
        'select' => ['case_id', 'case_label', 'case_closing_date'],
        'table'  => ['cases']
    ]);

    if (!empty($cases)) {
        $file = fopen("migration/20.03/cases_{$custom}.csv", 'w+');
        $csvHead = ['Identifiant affaire', 'Libellé affaire', 'Date de cloture', 'Identifiant courrier', 'Numéro chrono', 'Sujet courrier'];
        fputcsv($file, $csvHead, ',');

        foreach ($cases as $case) {
            $resources = \SrcCore\models\DatabaseModel::select([
                'select'    => ['r.res_id', 'r.subject', 'mlb.alt_identifier'],
                'table'     => ['cases_res c, res_letterbox r, mlb_coll_ext mlb'],
                'where'     => ['c.res_id = r.res_id', 'r.res_id = mlb.res_id', 'case_id = ?', 'r.status <> \'DEL\''],
                'data'      => [$case['case_id']]
            ]);
            foreach ($resources as $resource) {
                $csvContent = [$case['case_id'], $case['case_label'], $case['case_closing_date'], $resource['res_id'], $resource['alt_identifier'], $resource['subject']];
                fputcsv($file, $csvContent, ',');
            }
            ++$migrated;
        }

        fclose($file);
    }

    printf("Export Affaires (CUSTOM {$custom}) : " . $migrated . " Affaire(s) exportée(s) dans le fichier cases_{$custom}.csv\n");
}
