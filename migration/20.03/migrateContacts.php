<?php

require '../../vendor/autoload.php';

chdir('../..');

$customs =  scandir('custom');

foreach ($customs as $custom) {
    $debut = microtime(true);
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    if (file_exists("custom/{$custom}/apps/maarch_entreprise/xml/config.xml")) {
        $path = "custom/{$custom}/apps/maarch_entreprise/xml/config.xml";
    } else {
        $path = 'apps/maarch_entreprise/xml/config.xml';
    }

    if (file_exists($path)) {
        $loadedXml = simplexml_load_file($path);
        if ($loadedXml) {
            $server     = (string)$loadedXml->CONFIG->databaseserver;
            $port       = (string)$loadedXml->CONFIG->databaseserverport;
            $name       = (string)$loadedXml->CONFIG->databasename;
            $user       = (string)$loadedXml->CONFIG->databaseuser;
            $password   = (string)$loadedXml->CONFIG->databasepassword;
        }

        $databaseConnection = pg_connect(
            'host=' . $server .
            ' user=' . $user .
            ' password=' . $password .
            ' dbname=' . $name .
            ' port=' . $port
        );
    } else {
        echo "No config file found ";
        continue;
    }

    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $custom]);

    // TRUNCATE CONTACTS TABLES
    \SrcCore\models\DatabaseModel::delete([
        'table' => 'contacts',
        'where' => ['id > 0']
    ]);
    \SrcCore\models\DatabaseModel::delete([
        'table' => 'contacts_custom_fields_list',
        'where' => ['id > 0']
    ]);
    \SrcCore\models\DatabaseModel::delete([
        'table' => 'contacts_parameters',
        'where' => ['id > 0']
    ]);

    $aValues = [];
    $migrated = 0;
    $contactsInfo = \SrcCore\models\DatabaseModel::select([
        'select' => ['contact_id', 'society', 'contact_firstname', 'contact_lastname', 'contact_title', 'contact_function', 'contact_other_data',
            'creation_date', 'update_date', 'ca_id', 'departement', 'firstname', 'lastname', 'title', 'function', 'occupancy',
            'address_num', 'address_street', 'address_complement', 'address_town', 'address_postal_code', 'address_country', 'phone', 'email', 'other_data',
            'user_id', 'enabled', 'external_id', 'society_short', 'contact_purpose_label', 'contact_type_label', 'website', 'salutation_header', 'salutation_footer'
        ],
        'table' => ['view_contacts']
    ]);

    // Fields not migrated
    //contact_user_id, contact_enabled, contact_entity_id, entity_id, is_private, is_corporate_person, contact_type, contact_purpose_id

    $contactTypes = \SrcCore\models\DatabaseModel::select([
        'select' => ['label'],
        'table'  => ['contact_types']
    ]);
    $contactTypes = array_column($contactTypes, 'label');

    $contactPurposes = \SrcCore\models\DatabaseModel::select([
        'select' => ['label'],
        'table'  => ['contact_purposes']
    ]);
    $contactPurposes = array_column($contactPurposes, 'label');

    $customFields= [
        ['oldId' => 'salutation_header',     'label' => 'Formule de politesse (Début)',     'type' => 'string',     'value' => ['']],
        ['oldId' => 'salutation_footer',     'label' => 'Formule de politesse (Fin)',       'type' => 'string',     'value' => ['']],
        ['oldId' => 'website',               'label' => 'Site internet',                    'type' => 'string',     'value' => ['']],
        ['oldId' => 'contact_type_label',    'label' => 'Type de contact',                  'type' => 'select',     'value' => $contactTypes],
        ['oldId' => 'contact_purpose_label', 'label' => 'Dénomination',                     'type' => 'select',     'value' => $contactPurposes],
        ['oldId' => 'society_short',         'label' => 'Sigle de la structure',            'type' => 'string',     'value' => ['']],
    ];

    $newCustomFields = addCustomFields(['customFields' => $customFields]);

    $firstMan = \User\models\UserModel::get(['select' => ['id'], 'orderBy' => ['id'], 'limit' => 1, 'where' => ['status = ?'], 'data' => ['OK']]);

    $table = "contacts (id, civility, firstname, lastname, company, department, function, address_number, address_street,"
        . "address_additional1, address_additional2, address_postcode, address_town, address_country, email, phone,"
        . "communication_means, notes, creator, creation_date, modification_date, enabled, external_id)";

    $contactInfoSeparator = "\t";

    $id = 1;
    $contacts = [];
    $customInfos = [];
    $debutMigrateInProgress = microtime(true);
    foreach ($contactsInfo as $contactInfo) {
        $oldContactId = $contactInfo['contact_id'];
        $oldAddressId = $contactInfo['ca_id'];
        unset($contactInfo['contact_id']);
        unset($contactInfo['ca_id']);

        // Civility
        $contactInfo['civility'] = !empty($contactInfo['contact_title']) ? $contactInfo['contact_title'] : $contactInfo['title'];
        unset($contactInfo['contact_title']);
        unset($contactInfo['title']);

        // Firstname
        $contactInfo['firstname'] = !empty($contactInfo['contact_firstname']) ? $contactInfo['contact_firstname'] : $contactInfo['firstname'];
        unset($contactInfo['contact_firstname']);

        // Lastname
        $contactInfo['lastname'] = !empty($contactInfo['contact_lastname']) ? $contactInfo['contact_lastname'] : $contactInfo['lastname'];
        unset($contactInfo['contact_lastname']);

        // Company
        $contactInfo['company'] = $contactInfo['society'];
        unset($contactInfo['society']);

        // Function
        $contactInfo['function'] = !empty($contactInfo['contact_function']) ? $contactInfo['contact_function'] : $contactInfo['function'];
        unset($contactInfo['contact_function']);

        // Department
        $contactInfo['department'] = $contactInfo['departement'];
        unset($contactInfo['departement']);

        // Address
        $contactInfo['address_number'] = $contactInfo['address_num'];
        unset($contactInfo['address_num']);
        $contactInfo['address_additional1'] = $contactInfo['occupancy'];
        unset($contactInfo['occupancy']);
        $contactInfo['address_additional2'] = $contactInfo['address_complement'];
        unset($contactInfo['address_complement']);
        $contactInfo['address_postcode'] = $contactInfo['address_postal_code'];
        unset($contactInfo['address_postal_code']);

        //Moyen de communication
        $communicationMeans = \SrcCore\models\DatabaseModel::select(['select' => ['value'], 'table' => ['contact_communication'], 'where' => ['contact_id = ?'], 'data' => [$oldContactId]]);
        if (!empty($communicationMeans)) {
            $communicationMeans = $communicationMeans[0]['value'];
            if (filter_var($communicationMeans, FILTER_VALIDATE_EMAIL)) {
                $aCommunicationMeans = ['email' => $communicationMeans];
            } elseif (filter_var($communicationMeans, FILTER_VALIDATE_URL)) {
                $aCommunicationMeans = ['url' => $communicationMeans];
            }
        }
        $contactInfo['communication_means'] = !empty($communicationMeans) ? json_encode($aCommunicationMeans) : null;

        // Notes
        $contactInfo['notes'] = !empty($contactInfo['contact_other_data']) ? $contactInfo['contact_other_data'] : $contactInfo['other_data'];
        unset($contactInfo['contact_other_data']);
        unset($contactInfo['other_data']);

        // Creator
        $creator = \User\models\UserModel::getByLogin(['select' => ['id'], 'login' => $contactInfo['user_id']]);
        if (empty($creator)) {
            $contactInfo['creator'] = $firstMan[0]['id'];
        } else {
            $contactInfo['creator'] = $creator['id'];
        }
        unset($contactInfo['user_id']);

        // Modification date
        $contactInfo['modification_date'] = $contactInfo['update_date'];
        unset($contactInfo['update_date']);

        // Enabled
        $contactInfo['enabled'] = $contactInfo['enabled'] == 'Y' ? 'true' : 'false';

        $contactCustomInfo = [
            'salutation_header'     => $contactInfo['salutation_header'],
            'salutation_footer'     => $contactInfo['salutation_footer'],
            'website'               => $contactInfo['website'],
            'contact_type_label'    => $contactInfo['contact_type_label'],
            'contact_purpose_label' => $contactInfo['contact_purpose_label'],
            'society_short'         => $contactInfo['society_short'],
        ];

        unset($contactInfo['salutation_header']);
        unset($contactInfo['salutation_footer']);
        unset($contactInfo['website']);
        unset($contactInfo['contact_type_label']);
        unset($contactInfo['contact_purpose_label']);
        unset($contactInfo['society_short']);

        $contactInfo['id'] = $id;

        foreach ($contactInfo as $key => $item) {
            $contactInfo[$key] = (str_replace("\t", " ", $contactInfo[$key]));
            $contactInfo[$key] = $contactInfo[$key] ?? "NULL";
            if (empty($contactInfo[$key])) {
                $contactInfo[$key] = "NULL";
            }
        }

        if ($contactInfo['creation_date'] == 'NULL') {
            $contactInfo['creation_date'] = 'NOW()';
        }

        $contact = $id . $contactInfoSeparator
            . $contactInfo['civility'] . $contactInfoSeparator
            . $contactInfo['firstname'] . $contactInfoSeparator
            . $contactInfo['lastname'] . $contactInfoSeparator
            . $contactInfo['company'] . $contactInfoSeparator
            . $contactInfo['departement'] . $contactInfoSeparator
            . $contactInfo['function'] . $contactInfoSeparator
            . $contactInfo['address_number'] . $contactInfoSeparator
            . $contactInfo['address_street'] . $contactInfoSeparator
            . $contactInfo['address_additional1'] . $contactInfoSeparator
            . $contactInfo['address_additional2'] . $contactInfoSeparator
            . $contactInfo['address_postcode'] . $contactInfoSeparator
            . $contactInfo['address_town'] . $contactInfoSeparator
            . $contactInfo['address_country'] . $contactInfoSeparator
            . $contactInfo['email'] . $contactInfoSeparator
            . $contactInfo['phone'] . $contactInfoSeparator
            . $contactInfo['communication_means'] . $contactInfoSeparator
            . $contactInfo['notes'] . $contactInfoSeparator
            . $contactInfo['creator'] . $contactInfoSeparator
            . $contactInfo['creation_date'] . $contactInfoSeparator
            . $contactInfo['modification_date'] . $contactInfoSeparator
            . $contactInfo['enabled'] . $contactInfoSeparator
            . $contactInfo['external_id'];

        $contact = str_replace("\r", " ", $contact);
        $contact = str_replace("\n", " ", $contact);

        $contacts[] = $contact;

        $ids[$id] = ['oldAddressId' => $oldAddressId, 'oldContactId' => $oldContactId];

        $customInfos[$id] = $contactCustomInfo;

        $currentValuesContactRes = migrateContactRes(['oldAddressId' => $oldAddressId, 'oldContactId' => $oldContactId, 'newContactId' => $id]);

        $aValues = array_merge($aValues, $currentValuesContactRes);

        $migrated++;

        if ($migrated % 5000 == 0) {
            $finMigrateInProgress = microtime(true);
            $delaiInProgress = $finMigrateInProgress - $debutMigrateInProgress;
            echo "Migration En cours : ".$delaiInProgress." secondes.\n";
            $debutMigrateInProgress = microtime(true);
            printf($migrated . " contact(s) migré(s) - En cours...\n");
        }

        if ($migrated % 50000 == 0) {
            echo "Migration de 50000 contacts...\n";

            $beforeCopyContacts = microtime(true);
            pg_copy_from($databaseConnection, $table, $contacts, $contactInfoSeparator, "NULL");
            $afterCopyContacts = microtime(true);
            $copyTimeContacts = $afterCopyContacts - $beforeCopyContacts;
            echo "Temps copy contacts = $copyTimeContacts\n";

            $beforeCustomFields = microtime(true);
            foreach ($customInfos as $newId => $customInfo) {
                migrateCustomField(['newContactId' => $newId, 'contactCustomInfo' => $customInfo, 'newCustomFields' => $newCustomFields]);
            }
            $customInfos = [];
            $afterCustomFields = microtime(true);
            $timeCustoms = $afterCustomFields - $beforeCustomFields;
            echo "Temps migrate custom fields = $timeCustoms\n";

            pg_copy_from($databaseConnection, 'resource_contacts (res_id, item_id, type, mode)', $aValues, "\t", 	"\\\\N");
            $finMigrateInProgress = microtime(true);
            $delaiInProgress = $finMigrateInProgress - $debutMigrateInProgress;
            echo "Migration En cours : ".$delaiInProgress." secondes.\n";
            $debutMigrateInProgress = microtime(true);
            printf($migrated . " contact(s) migré(s) - En cours...\n");
            $aValues = [];

            $contacts = [];
        }

        $id++;
    }

    if (!empty($aValues)) {
        $beforeCopy = microtime(true);
        pg_copy_from($databaseConnection, 'resource_contacts (res_id, item_id, type, mode)', $aValues, "\t", "\\\\N");
        $afterCopy = microtime(true);
        $copyTime = $afterCopy - $beforeCopy;
        echo "Temps copy resource contacts = $copyTime secondes\n";
    }

    if (!empty($contacts)) {
        $beforeCopyContacts = microtime(true);
        pg_copy_from($databaseConnection, $table, $contacts, $contactInfoSeparator, "NULL");
        $afterCopyContacts = microtime(true);
        $copyTimeContacts = $afterCopyContacts - $beforeCopyContacts;
        echo "Temps copy contacts = $copyTimeContacts\n";
    }

    if (!empty($customInfos)) {
        $beforeCustomFields = microtime(true);
        foreach ($customInfos as $newId => $customInfo) {
            migrateCustomField(['newContactId'    => $newId, 'contactCustomInfo' => $customInfo,
                                'newCustomFields' => $newCustomFields]);
        }
        $customInfos = [];
        $afterCustomFields = microtime(true);
        $timeCustoms = $afterCustomFields - $beforeCustomFields;
        echo "Temps migrate custom fields = $timeCustoms\n";
    }

    $beforeUpdates = microtime(true);
    $valuesOldAddress = '';
    $firstDone = false;
    foreach ($ids as $newId => $value) {
        $oldAddressId = $value['oldAddressId'];
        if ($firstDone) {
            $valuesOldAddress .= ', ';
        }
        $valuesOldAddress .= "( $newId , $oldAddressId)";
        if (!$firstDone) {
            $firstDone = true;
        }
    }

    // Migrate addresses in res_letterbox
    $query = "insert into resource_contacts (res_id, item_id, type, mode)
    select res_id, tmp.new_id, 'contact_v3' as type,
       case
           when category_id = 'outgoing' then 'recipient'
           else 'sender'
       end as mode
    from res_letterbox,
         (values 
             $valuesOldAddress
         ) as tmp(new_id, old_address_id)
    where tmp.old_address_id = res_letterbox.address_id";
    pg_query($databaseConnection, $query);


    // Acknowledgement Receipts
    $query = "update acknowledgement_receipts as ar set
            contact_id = tmp.old_address_id
        from (values
               $valuesOldAddress 
            ) as tmp(new_id, old_address_id) 
        where ar.contact_address_id = tmp.old_address_id";
    pg_query($databaseConnection, $query);


    // Group list
    $query = "update contacts_groups_lists as cgl set
            contact_id = tmp.old_address_id
        from (values
               $valuesOldAddress
            ) as tmp(new_id, old_address_id) 
        where cgl.contact_addresses_id = tmp.old_address_id";

    pg_query($databaseConnection, $query);


    // Resources contacts
    $query = "update resource_contacts as rc set
        item_id = tmp.old_address_id, type = 'contact_v3'
    from (values
           $valuesOldAddress
        ) as tmp(new_id, old_address_id) 
    where rc.item_id = tmp.old_address_id and type = 'contact'";
    pg_query($databaseConnection, $query);

    $valuesOld= '';
    $firstDone = false;
    foreach ($ids as $newId => $value) {
        $oldAddressId = !empty($value['oldAddressId']) ? $value['oldAddressId'] : 'NULL';
        $oldContactId = !empty($value['oldContactId']) ? $value['oldContactId'] : 'NULL';

        if ($firstDone) {
            $valuesOld .= ', ';
        }
        $valuesOld .= "( $newId , $oldAddressId, $oldContactId)";
        if (!$firstDone) {
            $firstDone = true;
        }
    }

    // Res attach
    $query = "update res_attachments as ra set
        recipient_id = tmp.old_address_id, recipient_type = 'contact'
    from (values
           $valuesOld
        ) as tmp(new_id, old_address_id, old_contact_id) 
    where ra.dest_contact_id = tmp.old_contact_id and ra.dest_address_id = tmp.old_address_id";
    pg_query($databaseConnection, $query);

    $afterUpdates = microtime(true);
    $updatesTime = $afterUpdates - $beforeUpdates;
    echo "Temps updates = $updatesTime secondes\n";

    $finMigrateInProgress = microtime(true);
    $delaiInProgress = $finMigrateInProgress - $debutMigrateInProgress;
    echo "Dernière Migration En cours : ".$delaiInProgress." secondes.\n";
    $debutMigrateInProgress = microtime(true);
    printf($migrated . " contact(s) migré(s) - Fin...\n");

    $debutEndMigrate = microtime(true);
    migrateContactRes_Users(['firstManId' => $firstMan[0]['id'], 'databaseConnection' => $databaseConnection]);
    migrateResletterbox_Users(['firstManId' => $firstMan[0]['id'], 'databaseConnection' => $databaseConnection]);
    migrateResattachments_Users(['firstManId' => $firstMan[0]['id']]);
    migrateContactParameters();
    migrateContactPrivileges();
    $finEndMigrate = microtime(true);
    $delaiEndMigrate = $finEndMigrate - $debutEndMigrate;
    echo "Migration du bas : ".$delaiEndMigrate." secondes.\n";

    \SrcCore\models\DatabaseModel::update([
        'set'   => ['type' => 'contact'],
        'table' => 'resource_contacts',
        'where' => ['type = ?'],
        'data'  => ['contact_v3']
    ]);

    $fin = microtime(true);
    $delai = $fin - $debut;
    echo "Le temps écoulé est de ".$delai." secondes.\n";
    printf("Migration Contacts (CUSTOM {$custom}) : " . $migrated . " Contact(s) trouvée(s) et migrée(s).\n");
}

function addCustomFields($args = [])
{
    \SrcCore\models\DatabaseModel::beginTransaction();
    $fillingValues = \SrcCore\models\DatabaseModel::select([
        'select' => ['rating_columns'],
        'table'  => ['contacts_filling']
    ]);

    $fillingValues = json_decode($fillingValues[0]['rating_columns'], true);

    $customFields = [];
    $aValues      = [];
    foreach ($args['customFields'] as $value) {
        $customFieldId = \Contact\models\ContactCustomFieldListModel::create([
            'label'  => $value['label'],
            'type'   => $value['type'],
            'values' => json_encode($value['value'])
        ]);

        $filling = 'false';
        if (in_array($value['oldId'], $fillingValues)) {
            $filling = 'true';
        }

        $aValues[] = [
            'contactCustomField_' . $customFieldId,
            'false',
            $filling,
            'false',
            'false',
        ];

        $customFields[$value['oldId']] = $customFieldId;
    }

    if (!empty($aValues)) {
        \SrcCore\models\DatabaseModel::insertMultiple([
            'table'    => 'contacts_parameters',
            'columns'  => ['identifier', 'mandatory', 'filling', 'searchable', 'displayable'],
            'values'   => $aValues
        ]);
    }
    \SrcCore\models\DatabaseModel::commitTransaction();
    return $customFields;
}

function migrateCustomField($args = [])
{
    \SrcCore\models\DatabaseModel::beginTransaction();
    foreach ($args['contactCustomInfo'] as $key => $value) {
        if (!empty($value)) {
            $value = json_encode($value);
            $value = str_replace("'", "''", $value);
            \Contact\models\ContactModel::update([
                'postSet' => ['custom_fields' => "jsonb_set('{}', '{{$args['newCustomFields'][$key]}}', '{$value}')"],
                'where' => ['id = ?'],
                'data' => [$args['newContactId']]
            ]);
        }
    }
    \SrcCore\models\DatabaseModel::commitTransaction();
}

function migrateContactRes($args = [])
{
    $contactRes = \SrcCore\models\DatabaseModel::select([
        'select' => ['res_id'],
        'table'  => ['contacts_res'],
        'where'  => ['contact_id = ?', 'address_id = ?'],
        'data'  => [$args['oldContactId'], $args['oldAddressId']],
    ]);

    $aValues = [];
    foreach ($contactRes as $value) {
        $resInfo = \SrcCore\models\DatabaseModel::select([
            'select' => ['category_id'],
            'table'  => ['res_letterbox'],
            'where'  => ['res_id = ?'],
            'data'   => [$value['res_id']]
        ]);

        $mode = 'sender';
        if ($resInfo[0]['category_id'] == 'outgoing') {
            $mode = 'recipient';
        }

        $aValues[] = implode("\t", [
            $value['res_id'],
            $args['newContactId'],
            'contact_v3',
            $mode
        ]) . "\n";
    }

    return $aValues;
}

function migrateContactRes_Users($args = [])
{
    \SrcCore\models\DatabaseModel::beginTransaction();
    $userContactRes = \SrcCore\models\DatabaseModel::select([
        'select' => ['res_id', 'contact_id'],
        'table'  => ['contacts_res'],
        'where'  => ['address_id = 0']
    ]);

    $aValues = [];
    foreach ($userContactRes as $value) {
        $resInfo = \SrcCore\models\DatabaseModel::select([
            'select' => ['category_id'],
            'table'  => ['res_letterbox'],
            'where'  => ['res_id = ?'],
            'data'   => [$value['res_id']]
        ]);

        $user = \User\models\UserModel::getByLogin(['login' => $value['contact_id'], 'select' => ['id']]);
        if (empty($user)) {
            $user = $args['firstManId'];
        } else {
            $user = $user['id'];
        }

        $mode = 'sender';
        if ($resInfo[0]['category_id'] == 'outgoing') {
            $mode = 'recipient';
        }

        $aValues[] = implode("\t", [
            $value['res_id'],
            $user,
            'user',
            $mode
        ]) . "\n";
    }

    if (!empty($aValues)) {
        pg_copy_from($args['databaseConnection'], 'resource_contacts (res_id, item_id, type, mode)', $aValues, "\t", "\\\\N");
    }
    \SrcCore\models\DatabaseModel::commitTransaction();
}

function migrateResletterbox_Users($args = [])
{
    \SrcCore\models\DatabaseModel::beginTransaction();
    $userContact = \SrcCore\models\DatabaseModel::select([
        'select' => ['res_id', 'exp_user_id', 'dest_user_id'],
        'table'  => ['res_letterbox'],
        'where'  => ['(exp_user_id != \'\' and exp_user_id is not null) or (dest_user_id != \'\' and dest_user_id is not null)']
    ]);

    $aValues = [];
    foreach ($userContact as $value) {
        if (!empty($value['exp_user_id'])) {
            $login = $value['exp_user_id'];
            $mode = 'sender';
        } else {
            $login = $value['dest_user_id'];
            $mode = 'recipient';
        }
        if (empty($login)) {
            continue;
        }
        $user = \User\models\UserModel::getByLogin(['login' => $login, 'select' => ['id']]);
        if (empty($user)) {
            $user = $args['firstManId'];
        } else {
            $user = $user['id'];
        }

        $aValues[] = implode("\t", [
            $value['res_id'],
            $user,
            'user',
            $mode
        ]) . "\n";
    }

    if (!empty($aValues)) {
        pg_copy_from($args['databaseConnection'], 'resource_contacts (res_id, item_id, type, mode)', $aValues, "\t", "\\\\N");
    }
    \SrcCore\models\DatabaseModel::commitTransaction();
}

function migrateResattachments_Users($args = [])
{
    \SrcCore\models\DatabaseModel::beginTransaction();
    $attachments = \SrcCore\models\DatabaseModel::select([
        'select' => ['dest_user', 'res_id'],
        'table'  => ['res_attachments'],
        'where'  => ['dest_user != \'\' and dest_user is not null']
    ]);

    foreach ($attachments as $value) {
        $user = \User\models\UserModel::getByLogin(['login' => $value['dest_user'], 'select' => ['id']]);
        if (empty($user)) {
            $user = $args['firstManId'];
        } else {
            $user = $user['id'];
        }

        \SrcCore\models\DatabaseModel::update([
            'set'   => ['recipient_id' => $user, 'recipient_type' => 'user'],
            'table' => 'res_attachments',
            'where' => ['res_id = ?'],
            'data'  => [$value['res_id']],
        ]);
    }
    \SrcCore\models\DatabaseModel::commitTransaction();
}

function migrateContactParameters()
{
    \SrcCore\models\DatabaseModel::beginTransaction();
    $fillingValues = \SrcCore\models\DatabaseModel::select([
        'select' => ['rating_columns'],
        'table'  => ['contacts_filling']
    ]);

    $fillingValues = json_decode($fillingValues[0]['rating_columns'], true);

    $contactParameters = [
        ['oldIdentifier' => 'title',                'identifier' => 'civility',             'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'false', 'displayable' => 'false'],
        ['oldIdentifier' => 'firstname',            'identifier' => 'firstname',            'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'true', 'displayable' => 'true'],
        ['oldIdentifier' => 'lastname',             'identifier' => 'lastname',             'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'true', 'displayable' => 'true'],
        ['oldIdentifier' => 'society',              'identifier' => 'company',              'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'true', 'displayable' => 'true'],
        ['oldIdentifier' => 'departement',          'identifier' => 'department',           'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'false', 'displayable' => 'false'],
        ['oldIdentifier' => 'function',             'identifier' => 'function',             'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'false', 'displayable' => 'false'],
        ['oldIdentifier' => 'address_num',          'identifier' => 'addressNumber',        'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'true', 'displayable' => 'true'],
        ['oldIdentifier' => 'address_street',       'identifier' => 'addressStreet',        'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'true', 'displayable' => 'true'],
        ['oldIdentifier' => 'occupancy',            'identifier' => 'addressAdditional1',   'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'false', 'displayable' => 'false'],
        ['oldIdentifier' => 'address_complement',   'identifier' => 'addressAdditional2',   'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'false', 'displayable' => 'false'],
        ['oldIdentifier' => 'address_postal_code',  'identifier' => 'addressPostcode',      'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'false', 'displayable' => 'false'],
        ['oldIdentifier' => 'address_town',         'identifier' => 'addressTown',          'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'true', 'displayable' => 'true'],
        ['oldIdentifier' => 'address_country',      'identifier' => 'addressCountry',       'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'false', 'displayable' => 'false'],
        ['oldIdentifier' => 'email',                'identifier' => 'email',                'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'false', 'displayable' => 'false'],
        ['oldIdentifier' => 'phone',                'identifier' => 'phone',                'mandatory' => 'false', 'filling' => 'false', 'searchable' => 'false', 'displayable' => 'false'],
    ];
    
    foreach ($contactParameters as $value) {
        $filling = 'false';
        if (in_array($value['oldIdentifier'], ['lastname', 'society']) || in_array($value['oldIdentifier'], $fillingValues)) {
            $filling = 'true';
        }

        $aValues[] = [
            $value['identifier'],
            $value['mandatory'],
            $filling,
            $value['searchable'],
            $value['displayable']
        ];
    }

    \SrcCore\models\DatabaseModel::insertMultiple([
        'table'     => 'contacts_parameters',
        'columns'   => ['identifier', 'mandatory', 'filling', 'searchable', 'displayable'],
        'values'    => $aValues
    ]);
    
    \SrcCore\models\DatabaseModel::commitTransaction();
}

function migrateContactPrivileges()
{
    \SrcCore\models\DatabaseModel::beginTransaction();
    $usergroupServices = \SrcCore\models\DatabaseModel::select([
        'select' => ['group_id'],
        'table'  => ['usergroups_services'],
        'where'  => ['service_id = ?'],
        'data'   => ['create_contacts']
    ]);

    foreach ($usergroupServices as $usergroupService) {
        $servicesEnabled = \SrcCore\models\DatabaseModel::select([
            'select' => ['group_id'],
            'table'  => ['usergroups_services'],
            'where'  => ['service_id = ?', 'group_id = ?'],
            'data'   => ['update_contacts', $usergroupService['group_id']]
        ]);
        if (empty($servicesEnabled)) {
            \SrcCore\models\DatabaseModel::insert([
                'table'         => 'usergroups_services',
                'columnsValues' => [
                    'service_id' => 'update_contacts',
                    'group_id'   => $usergroupService['group_id']
                ]
            ]);
        }
    }

    foreach (['my_contacts_menu', 'my_contacts'] as $service) {
        $usergroupServices = \SrcCore\models\DatabaseModel::select([
            'select' => ['group_id'],
            'table'  => ['usergroups_services'],
            'where'  => ['service_id = ?'],
            'data'   => [$service]
        ]);
    
        foreach ($usergroupServices as $usergroupService) {
            $servicesEnabled = \SrcCore\models\DatabaseModel::select([
                'select' => ['group_id', 'service_id'],
                'table'  => ['usergroups_services'],
                'where'  => ['service_id = ?', 'group_id = ?'],
                'data'   => ['create_contacts', $usergroupService['group_id']]
            ]);
            if (empty($servicesEnabled)) {
                \SrcCore\models\DatabaseModel::insert([
                    'table'         => 'usergroups_services',
                    'columnsValues' => [
                        'service_id' => 'create_contacts',
                        'group_id'   => $usergroupService['group_id']
                    ]
                ]);
            }
        }
    }

    \SrcCore\models\DatabaseModel::delete([
        'table' => 'usergroups_services',
        'where' => ['service_id in (?)'],
        'data'  => [['my_contacts_menu', 'my_contacts']]
    ]);
    \SrcCore\models\DatabaseModel::commitTransaction();
}
