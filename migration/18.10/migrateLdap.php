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
    $path = "custom/{$custom}/apps/maarch_entreprise/xml/config.xml";
    if (file_exists($path)) {
        $xmlfile = simplexml_load_file($path);
    }

    if ($xmlfile && (string)$xmlfile->CONFIG->ldap == 'true') {
        $loginMethodPath = "custom/{$custom}/apps/maarch_entreprise/xml/login_method.xml";

        if (!file_exists($loginMethodPath)) {
            copy('apps/maarch_entreprise/xml/login_method.xml', "custom/{$custom}/apps/maarch_entreprise/xml/login_method.xml");
        }
        $loginXmlfile = simplexml_load_file($loginMethodPath);

        foreach ($loginXmlfile->METHOD as $method) {
            $method->ENABLED = 'false';
            if ($method->ID == 'ldap') {
                $method->ENABLED = 'true';
            }
        }

        $res = $loginXmlfile->asXML();
        $fp = @fopen($loginMethodPath, "w+");
        if ($fp) {
            fwrite($fp, $res);
        }

        if (file_exists("custom/{$custom}/modules/ldap/xml/config.xml")) {
            $dom = new DOMDocument();
            $dom->formatOutput = true;

            $dom->load("custom/{$custom}/modules/ldap/xml/config.xml");

            $config = $dom->getElementsByTagName('config')[0];
            $saveNodes = [];
            foreach ($config->childNodes as $node) {
                $saveNodes[] = $node;
            }

            while ($config->hasChildNodes()) {
                $config->removeChild($config->firstChild);
            }

            $ldap = $dom->createElement('ldap');
            foreach ($saveNodes as $node) {
                $ldap->appendChild($node);
            }
            $ldap->appendChild($dom->createElement('standardConnect', 'false'));
            $config->appendChild($ldap);
            $res = $dom->saveXML($dom, LIBXML_NOEMPTYTAG);
            $fp = @fopen("custom/{$custom}/modules/ldap/xml/config.xml", "w+");
            if ($fp) {
                fwrite($fp, $res);
            }
        }

        $migrated++;
    }
}

printf($migrated . " custom(s) utilisant la connexion LDAP trouvé(s) et migré(s).\n");
