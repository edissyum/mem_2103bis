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
    $path = "custom/{$custom}/modules/sendmail/batch/config/externalMailsEntities.xml";
    if (file_exists($path)) {
        $xmlfile = simplexml_load_file($path);
    }

    if ($xmlfile) {
        if (!is_dir("custom/{$custom}/modules/sendmail/xml")) {
            echo "Création du dossier custom/{$custom}/modules/sendmail/xml...\n";
            if (!mkdir("custom/{$custom}/modules/sendmail/xml", 0777, true)) {
                die('Echec lors de la création des répertoires...');
            }
        }

        rename("custom/{$custom}/modules/sendmail/batch/config/externalMailsEntities.xml", "custom/{$custom}/modules/sendmail/xml/externalMailsEntities.xml");

        $migrated++;
    }
}

printf($migrated . " custom(s) avec externalMailsEntities.xml trouvé(s) et déplacé(s).\n");
