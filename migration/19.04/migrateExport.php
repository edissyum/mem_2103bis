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
    $path = "custom/{$custom}/apps/maarch_entreprise/xml/export.xml";
    if (file_exists($path)) {
        $xmlfile = simplexml_load_file($path);
    } else {
        $xmlfile = simplexml_load_file("apps/maarch_entreprise/xml/export.xml");
    }

    if ($xmlfile) {
        $delimiter = (string)$xmlfile->CSVOPTIONS->DELIMITER;

        $aData = [];
        if (!empty($xmlfile->letterbox_coll->FIELD)) {
            foreach ($xmlfile->letterbox_coll->FIELD as $value) {
                $field = (string)$value->DATABASE_FIELD;
                if (strpos($field, ".") !== false) {
                    $aField = explode(".", $field);
                    $field  = $aField[1];
                }
                if ($field == 'typist') {
                    printf("La valeur typist a été remplacé par la fonction getTypist\n");
                    $function  = $xmlfile->letterbox_coll->FUNCTIONS->addChild('FUNCTION');
                    $function->addChild('CALL', 'getTypist');
                    $function->addChild('LIBELLE', 'Rédacteur');
                    continue;
                } elseif ($field == 'dest_user') {
                    printf("La valeur dest_user a été remplacé par la fonction getAssignee\n");
                    $function  = $xmlfile->letterbox_coll->FUNCTIONS->addChild('FUNCTION');
                    $function->addChild('CALL', 'getAssignee');
                    $function->addChild('LIBELLE', 'Attributaire');
                    continue;
                } elseif ($field == 'destination') {
                    printf("La valeur destination a été remplacé par la fonction getDestinationEntity\n");
                    $function  = $xmlfile->letterbox_coll->FUNCTIONS->addChild('FUNCTION');
                    $function->addChild('CALL', 'getDestinationEntity');
                    $function->addChild('LIBELLE', 'Libellé de l\'entité traitante');
                    continue;
                } elseif ($field == 'entitytype') {
                    printf("La valeur entitytype a été remplacé par la fonction getDestinationEntityType\n");
                    $function  = $xmlfile->letterbox_coll->FUNCTIONS->addChild('FUNCTION');
                    $function->addChild('CALL', 'getDestinationEntityType');
                    $function->addChild('LIBELLE', 'Type de l\'entité traitante');
                    continue;
                } elseif (in_array($field, ['contact_firstname', 'contact_lastname', 'contact_society'])) {
                    printf("La valeur ".$field." a été remplacé par la fonction getSender\n");
                    continue;
                }

                if (!in_array($field, [
                    'res_id',
                    'type_label',
                    'doctypes_first_level_label',
                    'doctypes_second_level_label',
                    'format',
                    'doc_date',
                    'reference_number',
                    'departure_date',
                    'department_number_id',
                    'barcode',
                    'fold_status',
                    'folder_name',
                    'confidentiality',
                    'nature_id',
                    'alt_identifier',
                    'admission_date',
                    'process_limit_date',
                    'recommendation_limit_date',
                    'closing_date',
                    'sve_start_date',
                    'subject',
                    'case_label'])) {
                    printf("Le champ " . $field . " a été trouvé mais non migré car non maintenue\n");
                    continue;
                }
                $oData             = new stdClass();
                $oData->value      = $field;
                $oData->label      = (string)$value->LIBELLE;
                $oData->isFunction = false;
                $aData[] = $oData;
            }
        }

        if (!empty($xmlfile->letterbox_coll->FUNCTIONS->FUNCTION)) {
            $sender  = $xmlfile->letterbox_coll->FUNCTIONS->addChild('FUNCTION');
            $sender->addChild('CALL', 'getSender');
            $sender->addChild('LIBELLE', 'Expéditeur');
            $recipient  = $xmlfile->letterbox_coll->FUNCTIONS->addChild('FUNCTION');
            $recipient->addChild('CALL', 'getRecipient');
            $recipient->addChild('LIBELLE', 'Destinataire');

            foreach ($xmlfile->letterbox_coll->FUNCTIONS->FUNCTION as $value) {
                $functionName = (string)$value->CALL;
                if ($functionName == 'get_status') {
                    $functionName = 'getStatus';
                } elseif ($functionName == 'get_priority') {
                    $functionName = 'getPriority';
                } elseif ($functionName == 'retrieve_copies') {
                    $functionName = 'getCopies';
                } elseif ($functionName == 'makeLink_detail') {
                    $functionName = 'getDetailLink';
                } elseif ($functionName == 'get_parent_folder') {
                    $functionName = 'getParentFolder';
                } elseif ($functionName == 'get_category_label') {
                    $functionName = 'getCategory';
                } elseif ($functionName == 'get_entity_initiator_short_label') {
                    $functionName = 'getInitiatorEntity';
                } elseif ($functionName == 'get_entity_dest_short_label') {
                    printf("La fonction get_entity_dest_short_label a été remplacé par la fonction getDestinationEntity\n");
                    continue;
                } elseif ($functionName == 'get_contact_type') {
                    printf("La fonction get_contact_type a été remplacé par la fonction getSender\n");
                    continue;
                } elseif ($functionName == 'get_contact_civility') {
                    printf("La fonction get_contact_civility a été remplacé par la fonction getSender\n");
                    continue;
                } elseif ($functionName == 'get_contact_function') {
                    printf("La fonction get_contact_function a été remplacé par la fonction getSender\n");
                    continue;
                } elseif ($functionName == 'get_tags') {
                    $functionName = 'getTags';
                } elseif ($functionName == 'get_signatory_name') {
                    $functionName = 'getSignatories';
                } elseif ($functionName == 'get_signatory_date') {
                    $functionName = 'getSignatureDates';
                } elseif (!in_array($functionName, ['getTypist', 'getAssignee', 'getDestinationEntity', 'getDestinationEntityType', 'getSender', 'getRecipient'])) {
                    printf("La fonction " . $functionName . " a été trouvé mais non migré car non maintenue.\n");
                    continue;
                }
                $oData             = new stdClass();
                $oData->value      = $functionName;
                $oData->label      = (string)$value->LIBELLE;
                $oData->isFunction = true;
                $aData[] = $oData;
            }
        }

        if (!empty($xmlfile->letterbox_coll->EMPTYS->EMPTY)) {
            $oData             = new stdClass();
            $oData->value      = '';
            $oData->label      = 'Commentaire';
            $oData->isFunction = true;
            $aData[] = $oData;
        }

        $users = \User\models\UserModel::get([
            'select' => ['id'],
            'where'  => ['status != ?'],
            'data'   => ['DEL']
            ]);

        $aValues = [];
        foreach ($users as $user) {
            $aValues[] = [$user['id'], 'csv', $delimiter, json_encode($aData)];
        }

        \SrcCore\models\DatabaseModel::insertMultiple([
            'table'     => 'exports_templates',
            'columns'   => ['user_id', 'format', 'delimiter', 'data'],
            'values'    => $aValues
        ]);

        $migrated++;
    }
}

printf($migrated . " custom(s) avec une configuration export trouvé(s) et migré(s).\n");
