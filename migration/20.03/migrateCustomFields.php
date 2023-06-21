<?php

require '../../vendor/autoload.php';

chdir('../..');

$customs =  scandir('custom');

foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $custom]);

    $migrated = 0;
    $path = "custom/{$custom}/apps/maarch_entreprise/xml/index_letterbox.xml";
    if (file_exists($path)) {
        if (!is_readable($path) || !is_writable($path)) {
            continue;
        }
        $loadedXml = simplexml_load_file($path);
        
        if ($loadedXml) {
		    $indexingModels = \IndexingModel\models\IndexingModelModel::get(['select'=> ['id']]);
            if (!empty($indexingModels)) {
                $indexingModelsId = array_column($indexingModels, 'id');
            }

            $i = 0;
            foreach ($loadedXml->INDEX as $value) {
                $customExists = \SrcCore\models\DatabaseModel::select([
                        'select' => [1],
                        'table'  => ['doctypes_indexes'],
                        'where'  => ['field_name = ?'],
                        'data'   => [(string)$value->column]
                ]);
                if (empty($customExists)) {
                    continue;
                }

                $label = (string)$value->label;
                $type = trim((string)$value->type);
                if ($type == 'float') {
                    $type = 'integer';
                }

                $values = [];
                if (!empty($value->values_list)) {
                    foreach ($value->values_list->value as $valueList) {
                        $values[] = (string)$valueList->label;
                    }
                }
                if (!empty($value->table) && !empty($value->table->table_name) && !empty($value->table->foreign_label)) {
                    $tableName    = (string)$value->table->table_name;
                    $foreignLabel = (string)$value->table->foreign_label;
                    $whereClause  = (string)$value->table->where_clause;
                    $order        = (string)$value->table->order;

                    $customValues = \SrcCore\models\DatabaseModel::select([
                        'select'   => [$foreignLabel],
                        'table'    => [$tableName],
                        'where'    => empty($whereClause) ? [] : [$whereClause],
                        'order_by' => [str_ireplace("order by", "", $order)]
                    ]);

                    foreach ($customValues as $valueList) {
                        $values[] = $valueList[$foreignLabel];
                    }
                }

                $fieldId = \CustomField\models\CustomFieldModel::create([
                    'label'     => $label,
                    'type'      => $type,
                    'values'    => empty($values) ? '[]' : json_encode($values)
                ]);

                if (!empty($indexingModelsId)) {
                    foreach ($indexingModelsId as $indexingModelId) {
                        \IndexingModel\models\IndexingModelFieldModel::create([
                            'model_id'   => $indexingModelId,
                            'identifier' => 'indexingCustomField_'.$fieldId,
                            'mandatory'  => 'false',
                            'unit'       => 'mail'
                        ]);
                    }
                }

                $column = (string)$value->column;
                $csColumn = "custom_fields->>''{$fieldId}''";

                if ($type == 'date') {
                    $csColumn = "($csColumn)::date";
                }

                \Basket\models\BasketModel::update(['postSet' => ['basket_clause' => "REPLACE(basket_clause, 'doc_{$column}', '{$csColumn}')"], 'where' => ['1 = ?'], 'data' => [1]]);
                \Basket\models\BasketModel::update(['postSet' => ['basket_clause' => "REPLACE(basket_clause, '{$column}', '{$csColumn}')"], 'where' => ['1 = ?'], 'data' => [1]]);
                $resources = \Resource\models\ResModel::get([
                    'select'    => ['res_id', $column],
                    'where'     => [$column . ' is not null'],
                ]);

                foreach ($resources as $resource) {
                    $valueColumn = json_encode($resource[$column]);
                    $valueColumn = str_replace("'", "''", $valueColumn);
                    $resId = $resource['res_id'];
                    \Resource\models\ResModel::update([
                        'postSet'   => ['custom_fields' => "jsonb_set(custom_fields, '{{$fieldId}}', '{$valueColumn}')"],
                        'where'     => ['res_id = ?'],
                        'data'      => [$resId]
                    ]);
                }

                $migrated++;
            }
        }
    }

    printf("Migration Champs Custom (CUSTOM {$custom}) : " . $migrated . " Champs custom utilisé(s) et migré(s).\n");
}
