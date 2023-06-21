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
    $path = "custom/{$custom}/apps/maarch_entreprise/xml/extensions.xml";
    if (file_exists($path)) {
        $xmlfile = simplexml_load_file($path);

        if ($xmlfile) {
            $i = 0;
            foreach ($xmlfile->FORMAT as $item) {
                if (isset($item->index_frame_show)) {
                    $xmlfile->FORMAT[$i]->canConvert = $item->index_frame_show;
                    unset($xmlfile->FORMAT[$i]->index_frame_show);
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
}

printf("Migration Extensions : " . $migrated . " custom(s) avec un fichier extensions.xml trouvé(s) et migré(s).\n");
