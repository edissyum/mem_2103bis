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
    $path = "custom/{$custom}/apps/maarch_entreprise/xml/entreprise.xml";
    if (file_exists($path)) {
        $xmlfile = simplexml_load_file($path);

        if ($xmlfile) {
            unset($xmlfile->process_modes);

            $res = $xmlfile->asXML();
            $fp = fopen($path, "w+");
            if ($fp) {
                fwrite($fp, $res);
            }

            $migrated++;
        }
    }
}

printf("Migration Process Mode : " . $migrated . " custom(s) avec un fichier entreprise.xml trouvé(s) et migré(s).\n");
