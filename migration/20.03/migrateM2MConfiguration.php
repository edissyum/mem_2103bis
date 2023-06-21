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
    $path = "custom/{$custom}/apps/maarch_entreprise/xml/m2m_config.xml";
    if (file_exists($path)) {
        $xmlfile = simplexml_load_file($path);

        if ($xmlfile) {
            $xmlfile->res_letterbox->indexingModelId = 1;
            unset($xmlfile->contacts_v2);
            unset($xmlfile->contact_addresses);

            $res = $xmlfile->asXML();
            $fp = fopen($path, "w+");
            if ($fp) {
                fwrite($fp, $res);
            }

            $migrated++;
        }
    }
}

printf("Migration m2m configuration : " . $migrated . " custom(s) avec un fichier m2m_config.xml trouvé(s) et migré(s).\n");
