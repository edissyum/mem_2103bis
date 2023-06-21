<?php

require '../../vendor/autoload.php';

chdir('../..');

$migrated = 0;
$customs =  scandir('custom');
foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $custom]);

    $xmlfile = null;
    $path = "custom/{$custom}/apps/maarch_entreprise/xml/config.xml";
    $xmlfile = simplexml_load_file($path);

    if ($xmlfile) {

        $i = 0;
        foreach ($xmlfile->COLLECTION as $collection) {
            if ((string)$collection->table == 'res_letterbox') {
                $collId = 'letterbox_coll';
            } elseif ((string)$collection->table == 'res_attachments') {
                $collId = 'attachments_coll';
            } else {
                unset($xmlfile->COLLECTION[$i]);
                continue;
            }

            \Docserver\models\DocserverModel::update([
                'set'   => [
                    'path_template' => (string)$collection->path_to_lucene_index
                ],
                'where' => ['docserver_type_id = ?', 'coll_id = ?'],
                'data'  => ['FULLTEXT', $collId]
            ]);
            unset($xmlfile->COLLECTION[$i]->path_to_lucene_index);

            ++$i;
        }

        $i = 0;
        foreach ($xmlfile->MODULES as $module) {
            if ((string)$module->moduleid == 'full_text') {
                unset($xmlfile->MODULES[$i]);
                break;
            }
            ++$i;
        }

        $res = $xmlfile->asXML();
        $fp = fopen($path, "w+");
        if ($fp) {
            fwrite($fp, $res);
        }

        $migrated++;
    }
}

printf("Migration Full Text : " . $migrated . " custom(s) avec un fichier config.xml trouvé(s) et migré(s).\n");
