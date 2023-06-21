<?php

chdir('../..');

$nonReadableFiles = [];
$migrated = 0;
$customs =  scandir('custom');

foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    $priorities = [];
    $path = "custom/{$custom}/apps/maarch_entreprise/xml/entreprise.xml";
    if (file_exists($path)) {
        if (!is_readable($path) || !is_writable($path)) {
            $nonReadableFiles[] = $path;
            continue;
        }
        $loadedXml = simplexml_load_file($path);
        if ($loadedXml) {
            unset($loadedXml->priorities);
            $res = $loadedXml->asXML();
            $fp = fopen($path, "w+");
            if ($fp) {
                fwrite($fp, $res);
            }
            $migrated++;
        }
    }
}

foreach ($nonReadableFiles as $file) {
    printf("The file %s it is not readable or not writable.\n", $file);
}

printf($migrated . " custom(s) avec entreprise.xml (priorities) trouvé(s) et migré(s).\n");
