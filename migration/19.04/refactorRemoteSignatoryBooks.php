<?php

chdir('../..');

$migrated = 0;
$nonReadableFiles = [];
$customs =  scandir('custom');

foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    $priorities = [];
    $path = "custom/{$custom}/modules/visa/xml/remoteSignatoryBooks.xml";
    if (file_exists($path)) {
        if (!is_readable($path) || !is_writable($path)) {
            $nonReadableFiles[] = $path;
            continue;
        }
        $loadedXml = simplexml_load_file($path);
        if ($loadedXml) {
            $newSignatoryBook = $loadedXml->addChild('signatoryBook');
            $newSignatoryBook->addChild('id', 'maarchParapheur');
            $newSignatoryBook->addChild('userId', ' ');
            $newSignatoryBook->addChild('password', ' ');
            $newSignatoryBook->addChild('url', ' ');
            $newSignatoryBook->addChild('signature', 'SIGN');
            $newSignatoryBook->addChild('annotation', 'NOTE');
            $newSignatoryBook->addChild('externalValidated', 'VAL');
            $newSignatoryBook->addChild('externalRefused', 'REF');

            $res = formatXml($loadedXml);
            $fp = fopen($path, "w+");
            if ($fp) {
                fwrite($fp, $res);
            }
            ++$migrated;
        }
    }
}

foreach ($nonReadableFiles as $file) {
    printf("The file %s it is not readable or not writable.\n", $file);
}

printf($migrated . " custom(s) avec un fichier remoteSignatoryBooks.xml trouvé(s) et refactoré(s).\n");

function formatXml($simpleXMLElement)
{
    $xmlDocument = new DOMDocument('1.0');
    $xmlDocument->preserveWhiteSpace = false;
    $xmlDocument->formatOutput = true;
    $xmlDocument->loadXML($simpleXMLElement->asXML());

    return $xmlDocument->saveXML();
}
