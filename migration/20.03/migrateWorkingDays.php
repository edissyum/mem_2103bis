<?php

require '../../vendor/autoload.php';

chdir('../..');

$migrated = 0;
$customs =  scandir('custom');
foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    $workingDays = 1;
    $xmlfile = null;
    $path = "custom/{$custom}/apps/maarch_entreprise/xml/features.xml";
    if (is_file($path)) {
        $xmlfile = simplexml_load_file($path);

        if ($xmlfile) {
            $calendarType = $xmlfile->FEATURES->type_calendar;
            if ($calendarType == 'calendar') {
                $workingDays = 0;
            } else {
                $workingDays = 1;
            }
            unset($xmlfile->FEATURES->type_calendar);

            $res = $xmlfile->asXML();
            $fp = fopen($path, "w+");
            if ($fp) {
                fwrite($fp, $res);
            }

            $migrated++;
        }
    }

    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $custom]);

    \Parameter\models\ParameterModel::delete(['id' => 'workingDays']);
    \Parameter\models\ParameterModel::create([
        'id'              => 'workingDays',
        'description'     => 'Si activé (1), les délais de traitement sont calculés en jours ouvrés (Lundi à Vendredi). Sinon, en jours calendaire',
        'param_value_int' => $workingDays
    ]);
}

printf("Migration calendar type : " . $migrated . " custom(s) avec un fichier features.xml trouvé(s) et migré(s).\n");
