<?php

require '../../vendor/autoload.php';

chdir('../..');

$migrated = 0;
$customs =  scandir('custom');
foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $custom]);

    $xmlfile = null;
    $path = "custom/{$custom}/apps/maarch_entreprise/xml/features.xml";
    if (file_exists($path)) {
        $xmlfile = simplexml_load_file($path);
    } else {
        $xmlfile = simplexml_load_file("apps/maarch_entreprise/xml/features.xml");
    }

    if ($xmlfile) {
        $keepDest = (string)$xmlfile->FEATURES->dest_to_copy_during_redirection;

        if (!empty($keepDest) && $keepDest == 'true') {
            \Parameter\models\ParameterModel::create([
                'id'                => 'keepDestForRedirection',
                'description'       => 'Si activé (1), mets le destinataire en copie de la liste de diffusion lors d\'une action de redirection',
                'param_value_int'   => 1
            ]);
        } else {
            \Parameter\models\ParameterModel::create([
                'id'                => 'keepDestForRedirection',
                'description'       => 'Si activé (1), mets le destinataire en copie de la liste de diffusion lors d\'une action de redirection',
                'param_value_int'   => 0
            ]);
        }

        $migrated++;
    }
}

printf($migrated . " custom(s) avec un fichier features.xml trouvé(s) et migré(s).\n");
