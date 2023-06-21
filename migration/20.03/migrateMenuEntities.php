<?php

require '../../vendor/autoload.php';

chdir('../..');

$nonReadableFiles = [];
$migrated = 0;
$customs =  scandir('custom');

foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    $natures = [];
    $path = "custom/{$custom}/modules/entities/xml/menu.xml";
    if (file_exists($path)) {
        if (!is_readable($path) || !is_writable($path)) {
            $nonReadableFiles[] = $path;
            continue;
        }
        $loadedXml = simplexml_load_file($path);
        
        if ($loadedXml) {
            $i = 0;
            foreach ($loadedXml->MENU as $value) {
                if ($value->id == 'entities_print_sep_mlb') {
                    $loadedXml->MENU[$i]->url = '/separators/print';
                    $loadedXml->MENU[$i]->angular = 'true';
                    break;
                }
                ++$i;
            }
            $res = formatXml($loadedXml);
            $fp = fopen($path, "w+");
            if ($fp) {
                fwrite($fp, $res);
                $migrated++;
            }
        }
    }
}

foreach ($nonReadableFiles as $file) {
    printf("The file %s it is not readable or not writable.\n", $file);
}

printf($migrated . " custom(s) avec menu.xml (entities) trouvé(s) et migré(s).\n");

function formatXml($simpleXMLElement)
{
    $xmlDocument = new DOMDocument('1.0');
    $xmlDocument->preserveWhiteSpace = false;
    $xmlDocument->formatOutput = true;
    $xmlDocument->loadXML($simpleXMLElement->asXML());

    return $xmlDocument->saveXML();
}
