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

    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $custom]);

    $path = "custom/{$custom}/apps/maarch_entreprise/xml/config.xml";
    if (file_exists($path)) {
        if (!is_readable($path) || !is_writable($path)) {
            $nonReadableFiles[] = $path;
            continue;
        }
        $loadedXml = simplexml_load_file($path);
        
        if ($loadedXml) {
            if (!empty($loadedXml->COLLECTION[0]->categories->category)) {
                foreach ($loadedXml->COLLECTION[0]->categories->category as $category) {
                    $aCategories[] = (string)$category->id;
                }
                if (!empty($aCategories)) {
                    $indexingModels = \IndexingModel\models\IndexingModelModel::get([
                        'select'=> ['id'],
                        'where' => ['category not in (?)'],
                        'data'  => [$aCategories]
                    ]);
    
                    if (!empty($indexingModels)) {
                        $indexingModelsId = array_column($indexingModels, 'id');
    
                        \IndexingModel\models\IndexingModelFieldModel::delete([
                            'where' => ['model_id in (?)'],
                            'data'  => [$indexingModelsId]
                        ]);
                    }
    
                    \IndexingModel\models\IndexingModelModel::delete([
                        'where' => ['category not in (?)'],
                        'data'  => [$aCategories]
                    ]);
                }
                $defaultCategory = (string)$loadedXml->COLLECTION[0]->categories->default_category;
    
                \IndexingModel\models\IndexingModelModel::update([
                    'set'   => [
                        '"default"' => 'false'
                    ],
                    'where' => ['1=?'],
                    'data' => [1]
                ]);
    
                \IndexingModel\models\IndexingModelModel::update([
                    'set'   => [
                        '"default"' => 'true'
                    ],
                    'where' => ['category = ?'],
                    'data' => [$defaultCategory],
                ]);
            }

            $i = 0;
            foreach ($loadedXml->COLLECTION as $value) {
                unset($loadedXml->COLLECTION[$i]->categories);
                $i++;
            }

            $res = formatXml($loadedXml);
            $fp = fopen($path, "w+");
            if ($fp) {
                fwrite($fp, $res);
            }

            //Default Priority
            $defaultPriority = \Priority\models\PriorityModel::get([
                'select' => ['id'],
                'where'  => ['default_priority = ?'],
                'data'   => [1]
            ]);
            if (!empty($defaultPriority)) {
                \SrcCore\models\DatabaseModel::update([
                    'set'   => ['default_value' => json_encode($defaultPriority[0]['id'])],
                    'table' => 'indexing_models_fields',
                    'where' => ['identifier = ?', 'model_id in (SELECT id FROM indexing_models WHERE private = FALSE)'],
                    'data'  => ['priority']
                ]);
            }

            $migrated++;
        }
    }
}

foreach ($nonReadableFiles as $file) {
    printf("The file %s it is not readable or not writable.\n", $file);
}

printf($migrated . " custom(s) avec config.xml (categorie) trouvé(s) et migré(s).\n");

function formatXml($simpleXMLElement)
{
    $xmlDocument = new DOMDocument('1.0');
    $xmlDocument->preserveWhiteSpace = false;
    $xmlDocument->formatOutput = true;
    $xmlDocument->loadXML($simpleXMLElement->asXML());

    return $xmlDocument->saveXML();
}
