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

    $folders = \SrcCore\models\DatabaseModel::select([
        'select'   => ['f.folders_system_id', 'f.folder_name', 'f.status', 'r.res_id', 'mlb.alt_identifier', 'r.subject'],
        'table'    => ['folder_tmp f, res_letterbox r, mlb_coll_ext mlb'],
        'where'    => ['f.folders_system_id = r.folders_system_id', 'r.res_id = mlb.res_id', '(f.destination is null or f.destination = \'\')', 'f.status <> \'DEL\'', 'r.status <> \'DEL\''],
        'order_by' => ['f.folder_name']
    ]);

    if (!empty($folders)) {
        $file = fopen("migration/20.03/folders_{$custom}.csv", 'w+');
        $csvHead = ['Identifiant dossier', 'Libellé dossier', 'Statut dossier', 'Identifiant courrier', 'Numéro chrono', 'Sujet courrier'];
        fputcsv($file, $csvHead, ',');

        foreach ($folders as $folder) {
            $csvContent = [$folder['folders_system_id'], $folder['folder_name'], $folder['status'], $folder['res_id'], $folder['alt_identifier'], $folder['subject']];
            fputcsv($file, $csvContent, ',');

            if (!empty($csvContent)) {
                ++$migrated;
            }
        }

        fclose($file);
    }

    printf("Export Dossiers (CUSTOM {$custom}) : " . $migrated . " Dossier(s) public(s) exporté(s) dans le fichier folders_{$custom}.csv\n");
}
