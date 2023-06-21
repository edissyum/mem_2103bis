<?php

require '../../vendor/autoload.php';

chdir('../..');

$nonReadableFiles = [];
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
            $i = 0;
            foreach ($loadedXml->priorities->priority as $value) {
                if (isset($loadedXml->priorities->default_priority) && $loadedXml->priorities->default_priority == $i) {
                    $priorities[] = [
                        'id' => $i,
                        'label' => (string)$value,
                        'color' => (string)$value['color'],
                        'working_days' => (string)$value['working_days'],
                        'delays' => (string)$value['with_delay'] == 'false' ? null : (int)$value['with_delay'],
                        'default_priority' => 'true'
                    ];
                } else {
                    $priorities[] = [
                        'id' => $i,
                        'label' => (string)$value,
                        'color' => (string)$value['color'],
                        'working_days' => (string)$value['working_days'],
                        'delays' => (string)$value['with_delay'] == 'false' ? null : (int)$value['with_delay'],
                        'default_priority' => 'false'
                    ];
                }
                ++$i;
            }
        }

        \SrcCore\models\DatabasePDO::reset();

        $db = new \SrcCore\models\DatabasePDO(['customId' => $custom]);
        foreach ($priorities as $key => $priority) {
            if ($priority['default_priority'] == 'true') {
                $query = "UPDATE priorities SET default_priority = false WHERE default_priority = true";
                $db->query($query, []);
            }

            $id = \SrcCore\models\CoreConfigModel::uniqueId();
            $query = "INSERT INTO priorities (id, label, color, working_days, delays, default_priority, \"order\") VALUES (?, ?, ?, ?, ?, ?, ?)";
            $db->query($query, [
                $id,
                $priority['label'],
                $priority['color'],
                $priority['working_days'],
                $priority['delays'],
                $priority['default_priority'],
                $key
            ]);

            $query = "UPDATE res_letterbox SET priority = ? WHERE priority = ?";
            $db->query($query, [$id, $priority['id']]);

            $priorities[$key]['priorityId'] = $id;
        }

        $i = 0;
        foreach ($loadedXml->process_modes->process_mode as $processMode) {
            foreach ($priorities as $priority) {
                if ($priority['id'] == $processMode->process_mode_priority) {
                    $loadedXml->process_modes->process_mode[$i]->process_mode_priority = $priority['priorityId'];
                }
            }
            ++$i;
        }

        $res = $loadedXml->asXML();
        $fp = fopen($path, "w+");
        if ($fp) {
            fwrite($fp, $res);
        }
    }
}

foreach ($nonReadableFiles as $file) {
    printf("The file %s it is not readable or not writable.\n", $file);
}
