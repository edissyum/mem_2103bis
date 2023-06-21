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
    $path = "custom/{$custom}/apps/maarch_entreprise/xml/entreprise.xml";
    if (file_exists($path)) {
        if (!is_readable($path) || !is_writable($path)) {
            $nonReadableFiles[] = $path;
            continue;
        }
        $loadedXml = simplexml_load_file($path);
        
        if ($loadedXml) {
            $default_nature = (string)$loadedXml->mail_natures->default_nature;
            unset($loadedXml->mail_natures->default_nature);
            $newNature = $loadedXml->mail_natures->addChild('nature');
            $newNature->addAttribute('with_reference', 'true');
            $newNature->addChild('id', 'message_exchange');
            $newNature->addChild('label', '_NUMERIC_PACKAGE');

            $loadedXml->mail_natures->addChild('default_nature', $default_nature);
            $res = formatXml($loadedXml);
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

printf($migrated . " custom(s) avec entreprise.xml (natures) trouvé(s) et migré(s).\n");

function formatXml($simpleXMLElement)
{
    $xmlDocument = new DOMDocument('1.0');
    $xmlDocument->preserveWhiteSpace = false;
    $xmlDocument->formatOutput = true;
    $xmlDocument->loadXML($simpleXMLElement->asXML());

    return $xmlDocument->saveXML();
}
