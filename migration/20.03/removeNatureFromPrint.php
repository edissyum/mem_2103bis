<?php

require '../../vendor/autoload.php';

chdir('../..');

$migrated = 0;
$customs =  scandir('custom');
foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    $xmlfile = null;
    $path = "custom/{$custom}/apps/maarch_entreprise/xml/print.xml";
    if (file_exists($path)) {
        $xmlfile = simplexml_load_file($path);

        if ($xmlfile) {
            $i = 0;
            foreach ($xmlfile->letterbox_coll->FIELD as $field) {
                if ($field->DATABASE_FIELD == 'nature_id') {
                    unset($xmlfile->letterbox_coll->FIELD[$i]);
                    break;
                }
                $i++;
            }

            $res = $xmlfile->asXML();
            $fp = fopen($path, "w+");
            if ($fp) {
                fwrite($fp, $res);
            }

            $migrated++;
        }
    }
}

printf("Remove Nature : " . $migrated . " custom(s) avec un fichier print.xml trouvé(s) et migré(s).\n");
