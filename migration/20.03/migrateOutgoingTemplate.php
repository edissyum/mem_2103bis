<?php

use Contact\models\ContactCustomFieldListModel;
use CustomField\models\CustomFieldModel;
use Docserver\controllers\DocserverController;
use Docserver\models\DocserverModel;
use SrcCore\models\DatabaseModel;
use Template\models\TemplateModel;

require '../../vendor/autoload.php';

include_once('../../vendor/tinybutstrong/opentbs/tbs_plugin_opentbs.php');

const OFFICE_EXTENSIONS = ['odt', 'ods', 'odp', 'xlsx', 'pptx', 'docx', 'odf'];

$DATA_TO_REPLACE = [
    'res_letterbox.destination'         => '[destination.entity_id]',
    'res_letterbox.entity_label'        => '[destination.entity_label]',
    'res_letterbox.process_notes'       => '[notes]',
    'res_letterbox.contact_firstname'   => '[recipient.firstname]',
    'res_letterbox.contact_lastname'    => '[recipient.lastname]',
    'res_letterbox.contact_society'     => '[recipient.company]',

    'res_letterbox.nature_id' => '[res_letterbox.customField_1]',

    // Initiator
    'res_letterbox.initiator_entity_id'           => '[initiator.entity_id]',
    'res_letterbox.initiator_entity_label'        => '[initiator.entity_label]',
    'res_letterbox.initiator_short_label'         => '[initiator.short_label]',
    'res_letterbox.initiator_email'               => '[initiator.email]',
    'res_letterbox.initiator_parent_entity_id'    => '[initiator.parent_entity_id]',
    'res_letterbox.initiator_parent_entity_label' => '[initiator.parent_entity_label]',
    'res_letterbox.initiator_entity_type'         => '[initiator.entity_type]',
    'res_letterbox.initiator_entity_path'         => '[initiator.entity_path]',
    'res_letterbox.initiator_entity_fullname'     => '[initiator.entity_fullname]',
    'res_letterbox.initiator_zipcode'             => '[initiator.zipcode]',
    'res_letterbox.initiator_city'                => '[initiator.city]',
    'res_letterbox.initiator_country'             => '[initiator.country]',
    'res_letterbox.initiator_ldap_id'             => '[initiator.ldap_id]',
    'res_letterbox.initiator_archival_agence'     => '[initiator.archival_agence]',
    'res_letterbox.initiator_archival_agreement'  => '[initiator.archival_agreement]',
    'res_letterbox.initiator_business_id'         => '[initiator.business_id]',

    'attachments.chrono' => '[res_letterbox.alt_identifier]',

    'visa.firstnameSign' => '',
    'visa.lastnameSign'  => '[visas]',
    'visa.entitySign'    => '',
    'visa.firstname1'    => '',
    'visa.lastname1'     => '[visas]',
    'visa.firstname2'    => '',
    'visa.lastname2'     => '',
    'visa.firstname3'    => '',
    'visa.lastname3'     => '',
    'visa.firstname4'    => '',
    'visa.lastname4'     => '',
    'visa.firstname5'    => '',
    'visa.lastname5'     => '',
    'visa.firstname6'    => '',
    'visa.lastname6'     => '',
    'visa.firstname7'    => '',
    'visa.lastname7'     => '',
    'visa.firstname8'    => '',
    'visa.lastname8'     => '',
    'visa.firstname9'    => '',
    'visa.lastname9'     => '',
    'visa.entity1'       => '',
    'visa.entity2'       => '',
    'visa.entity3'       => '',
    'visa.entity4'       => '',
    'visa.entity5'       => '',
    'visa.entity6'       => '',
    'visa.entity7'       => '',
    'visa.entity8'       => '',
    'visa.entity9'       => '',

    'avis.firstname1' => '',
    'avis.lastname1'  => '[opinions]',
    'avis.firstname2' => '',
    'avis.lastname2'  => '',
    'avis.firstname3' => '',
    'avis.lastname3'  => '',
    'avis.firstname4' => '',
    'avis.lastname4'  => '',
    'avis.firstname5' => '',
    'avis.lastname5'  => '',
    'avis.firstname6' => '',
    'avis.lastname6'  => '',
    'avis.firstname7' => '',
    'avis.lastname7'  => '',
    'avis.firstname8' => '',
    'avis.lastname8'  => '',
    'avis.firstname9' => '',
    'avis.lastname9'  => '',
    'avis.role1'      => '',
    'avis.entity1'    => '',
    'avis.note1'      => '',
    'avis.role2'      => '',
    'avis.entity2'    => '',
    'avis.note2'      => '',
    'avis.role3'      => '',
    'avis.entity3'    => '',
    'avis.note3'      => '',
    'avis.role4'      => '',
    'avis.entity4'    => '',
    'avis.note4'      => '',
    'avis.role5'      => '',
    'avis.entity5'    => '',
    'avis.note5'      => '',
    'avis.role6'      => '',
    'avis.entity6'    => '',
    'avis.note6'      => '',
    'avis.role7'      => '',
    'avis.entity7'    => '',
    'avis.note7'      => '',
    'avis.role8'      => '',
    'avis.entity8'    => '',
    'avis.note8'      => '',
    'avis.role9'      => '',
    'avis.entity9'    => '',
    'avis.note9'      => '',

    'copies.firstname1' => '',
    'copies.lastname1'  => '[copies]',
    'copies.firstname2' => '',
    'copies.lastname2'  => '',
    'copies.firstname3' => '',
    'copies.lastname3'  => '',
    'copies.firstname4' => '',
    'copies.lastname4'  => '',
    'copies.firstname5' => '',
    'copies.lastname5'  => '',
    'copies.firstname6' => '',
    'copies.lastname6'  => '',
    'copies.firstname7' => '',
    'copies.lastname7'  => '',
    'copies.firstname8' => '',
    'copies.lastname8'  => '',
    'copies.firstname9' => '',
    'copies.lastname9'  => '',
    'copies.entity1'    => '',
    'copies.entity2'    => '',
    'copies.entity3'    => '',
    'copies.entity4'    => '',
    'copies.entity5'    => '',
    'copies.entity6'    => '',
    'copies.entity7'    => '',
    'copies.entity8'    => '',
    'copies.entity9'    => '',

    'user.role'             => '[userPrimaryEntity.role]',
    'user.entity_id'        => '[userPrimaryEntity.entity_id]',
    'user.entity_label'     => '[userPrimaryEntity.entity_label]',
    'user.short_label'      => '[userPrimaryEntity.short_label]',
    'user.adrs_1'           => '[userPrimaryEntity.adrs_1]',
    'user.adrs_2'           => '[userPrimaryEntity.adrs_2]',
    'user.adrs_3'           => '[userPrimaryEntity.adrs_3]',
    'user.zipcode'          => '[userPrimaryEntity.zipcode]',
    'user.city'             => '[userPrimaryEntity.city]',
    'user.email'            => '[userPrimaryEntity.email]',
    'user.parent_entity_id' => '[userPrimaryEntity.parent_entity_id]',
    'user.entity_type'      => '[userPrimaryEntity.entity_type]',
    'user.entity_path'      => '[userPrimaryEntity.path]',

    'contact.contact_type_label'        => '',
    'contact.society_short'             => '',
    'contact.contact_purpose_label'     => '',
    'contact.website'                   => '',
    'contact.salutation_header'         => '',
    'contact.salutation_footer'         => '',
    'contact.society'                   => '[recipient.company]',
    'contact.departement'               => '[recipient.department]',
    'contact.title'                     => '[recipient.civility]',
    'contact.contact_title'             => '[recipient.civility]',
    'contact.contact_lastname'          => '[recipient.lastname]',
    'contact.contact_firstname'         => '[recipient.firstname]',
    'contact.lastname'                  => '[recipient.lastname]',
    'contact.firstname'                 => '[recipient.firstname]',
    'contact.function'                  => '[recipient.function]',
    'contact.postal_address;strconv=no' => '[recipient.postal_address;strconv=no]',
    'contact.postal_address'            => '[recipient.postal_address]',
    'contact.address_num'               => '[recipient.address_number]',
    'contact.address_street'            => '[recipient.address_street]',
    'contact.occupancy'                 => '[recipient.address_additional1]',
    'contact.address_complement'        => '[recipient.address_additional2]',
    'contact.address_town'              => '[recipient.address_town]',
    'contact.address_postal_code'       => '[recipient.address_postcode]',
    'contact.address_country'           => '[recipient.address_country]',
    'contact.phone'                     => '[recipient.phone]',
    'contact.email'                     => '[recipient.email]',

    'attachmentRecipient.company'                   => '[recipient.company]',
    'attachmentRecipient.departement'               => '[recipient.department]',
    'attachmentRecipient.civility'                  => '[recipient.civility]',
    'attachmentRecipient.lastname'                  => '[recipient.lastname]',
    'attachmentRecipient.firstname'                 => '[recipient.firstname]',
    'attachmentRecipient.function'                  => '[recipient.function]',
    'attachmentRecipient.postal_address;strconv=no' => '[recipient.postal_address;strconv=no]',
    'attachmentRecipient.postal_address'            => '[recipient.postal_address]',
    'attachmentRecipient.address_number'            => '[recipient.address_number]',
    'attachmentRecipient.address_street'            => '[recipient.address_street]',
    'attachmentRecipient.address_additional1'       => '[recipient.address_additional1]',
    'attachmentRecipient.address_additional2'       => '[recipient.address_additional2]',
    'attachmentRecipient.address_town'              => '[recipient.address_town]',
    'attachmentRecipient.address_postcode'          => '[recipient.address_postcode]',
    'attachmentRecipient.address_country'           => '[recipient.address_country]',
    'attachmentRecipient.phone'                     => '[recipient.phone]',
    'attachmentRecipient.email'                     => '[recipient.email]',

    'notes.identifier'                       => '[res_letterbox.res_id]',
    'notes.subject'                          => '[res_letterbox.subject]',
    'notes.note_text'                        => '[notes]',
    'notes.user_id'                          => '',
    'notes.# ;frm=0000'                      => '[res_letterbox.# ;frm=0000]',
    'notes.doc_date;block=tr;frm=dd/mm/yyyy' => '[res_letterbox.doc_date;block=tr;frm=dd/mm/yyyy]',
    'notes.doc_date;block=tr'                => '[res_letterbox.doc_date;block=tr]',
    'notes.doc_date;frm=dd/mm/yyyy'          => '[res_letterbox.doc_date;frm=dd/mm/yyyy]',
    'notes.doc_date'                         => '[res_letterbox.doc_date]',
    'notes.contact_society'                  => '[contact.company]',
    'notes.contact_firstname'                => '[contact.firstname]',
    'notes.contact_lastname'                 => '[contact.lastname]',
    'notes.linktodetail'                     => '[res_letterbox.linktodetail]',
    'notes.linktodoc'                        => '[res_letterbox.linktodoc]',
];

$contactCustomFields = [
    ['oldId' => 'salutation_header', 'label' => 'Formule de politesse (Début)'],
    ['oldId' => 'salutation_footer', 'label' => 'Formule de politesse (Fin)'],
    ['oldId' => 'website', 'label' => 'Site internet'],
    ['oldId' => 'contact_type_label', 'label' => 'Type de contact'],
    ['oldId' => 'contact_purpose_label', 'label' => 'Dénomination'],
    ['oldId' => 'society_short', 'label' => 'Sigle de la structure'],
];

$resourceCustomFields = [
    ['id' => 'description', 'label' => 'Autres informations'],
    ['id' => 'external_reference', 'label' => 'Référence courrier expéditeur'],
    ['id' => 'reference_number', 'label' => 'N° recommandé'],
    ['id' => 'scan_date', 'label' => 'Date de scan'],
    ['id' => 'scan_user', 'label' => 'Utilisateur de scan'],
    ['id' => 'scan_location', 'label' => 'Lieu de scan'],
    ['id' => 'scan_wkstation', 'label' => 'Station de scan'],
    ['id' => 'scan_batch', 'label' => 'Batch de scan'],
    ['id' => 'scan_postmark', 'label' => 'Tampon de scan'],
];

chdir('../..');

$customs = scandir('custom');

foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $custom]);

    $docserver     = DocserverModel::getByDocserverId(['docserverId' => 'TEMPLATES']);
    $templatesPath = $docserver['path_template'];

    // BEGIN Change attachment all in outgoingMail

    $templatesAllAttachmentTypes = TemplateModel::get([
        'where' => ['template_target = ?', 'template_attachment_type in (?)'],
        'data'  => ['attachments', ['all', 'outgoing_mail']]
    ]);

    $migrated      = 0;
    $nonMigrated   = 0;

    foreach ($templatesAllAttachmentTypes as $template) {
        $path = str_replace('#', '/', $template['template_path']);

        $pathToDocument = $templatesPath . $path . $template['template_file_name'];

        $pathInfo = pathinfo($pathToDocument);
        $extension = $pathInfo['extension'];

        if (!in_array($extension, OFFICE_EXTENSIONS)) {
            $nonMigrated++;
            continue;
        }

        if (!is_writable($pathToDocument) || !is_readable($pathToDocument)) {
            $nonMigrated++;
            continue;
        }

        $encodedFile = base64_encode(file_get_contents($pathToDocument));

        $storeResult = DocserverController::storeResourceOnDocServer([
            'collId'            => 'templates',
            'docserverTypeId'   => 'TEMPLATES',
            'encodedResource'   => $encodedFile,
            'format'            => $pathInfo['extension']
        ]);

        $template['template_path']      = $storeResult['destination_dir'];
        $template['template_file_name'] = $storeResult['file_destination_name'];

        DatabaseModel::insert([
            'table'         => 'templates',
            'columnsValues' => [
                'template_label'            => $template['template_label'] . ' (départ)',
                'template_comment'          => $template['template_comment'],
                'template_content'          => $template['template_content'],
                'template_type'             => $template['template_type'],
                'template_style'            => $template['template_style'],
                'template_datasource'       => $template['template_datasource'],
                'template_target'           => 'indexingFile',
                'template_attachment_type'  => 'all',
                'template_path'             => $template['template_path'],
                'template_file_name'        => $template['template_file_name'],
            ]
        ]);
    }

    // END

    foreach ($contactCustomFields as $customField) {
        $idNewCustomField = ContactCustomFieldListModel::get([
            'select' => ['id'],
            'where'  => ['label = ?'],
            'data'   => [$customField['label']]
        ]);
        $DATA_TO_REPLACE["contact." . $customField['oldId']] = "[recipient.customField_{$idNewCustomField[0]['id']}]";
    }

    foreach ($resourceCustomFields as $customField) {
        $idNewCustomField = CustomFieldModel::get([
            'select' => ['id'],
            'where'  => ['label = ?'],
            'data'   => [$customField['label']]
        ]);
        $DATA_TO_REPLACE["res_letterbox." . $customField['id']] = "[res_letterbox.customField_{$idNewCustomField[0]['id']}]";
    }

    $templates     = TemplateModel::get([
        'where' => ['template_target = ?', 'template_attachment_type = ?'],
        'data'  => ['indexingFile', 'all']
    ]);

    foreach ($templates as $template) {
        $path = str_replace('#', '/', $template['template_path']);

        $pathToDocument = $templatesPath . $path . $template['template_file_name'];

        $pathInfo = pathinfo($pathToDocument);
        $extension = $pathInfo['extension'];

        if (!in_array($extension, OFFICE_EXTENSIONS)) {
            $nonMigrated++;
            continue;
        }

        if (!is_writable($pathToDocument) || !is_readable($pathToDocument)) {
            $nonMigrated++;
            continue;
        }

        $tbs = new clsTinyButStrong();
        $tbs->NoErr = true;
        $tbs->Protect = false;
        $tbs->PlugIn(TBS_INSTALL, OPENTBS_PLUGIN);

        $tbs->LoadTemplate($pathToDocument, OPENTBS_ALREADY_UTF8);

        $pages = 1;
        if ($extension == 'xlsx') {
            $pages = $tbs->PlugIn(OPENTBS_COUNT_SHEETS);
        }

        for ($i = 0; $i < $pages; ++$i) {
            if ($extension == 'xlsx') {
                $tbs->PlugIn(OPENTBS_SELECT_SHEET, $i + 1);
            }

            $tbs->ReplaceFields($DATA_TO_REPLACE);
        }

        if (in_array($extension, OFFICE_EXTENSIONS)) {
            $tbs->Show(OPENTBS_STRING);
        } else {
            $tbs->Show(TBS_NOTHING);
        }

        $content = base64_encode($tbs->Source);

        $result = file_put_contents($pathToDocument, base64_decode($content));
        if ($result !== false) {
            $migrated++;
        } else {
            echo "Erreur lors de la migration du modèle : $pathToDocument\n";
            $nonMigrated++;
        }
    }

    $path = "custom/{$custom}/apps/maarch_entreprise/xml/entreprise.xml";
    if (file_exists($path)) {
        if (!is_readable($path) || !is_writable($path)) {
            printf("The file $path it is not readable or not writable.\n");
            continue;
        }
        $loadedXml = simplexml_load_file($path);
        
        if ($loadedXml) {
            for ($i=count($loadedXml->attachment_types->type); $i >= 0; $i--) {
                if (in_array($loadedXml->attachment_types->type[$i]->id, ['outgoing_mail_signed'])) {
                    unset($loadedXml->attachment_types->type[$i]);
                }
            }

            $res = formatXml($loadedXml);
            $fp = fopen($path, "w+");
            if ($fp) {
                fwrite($fp, $res);
            }
            $migrated++;
        }
    }

    printf("Migration de Modèles de document départ spontannée (CUSTOM {$custom}) : " . $migrated . " Modèle(s) migré(s), $nonMigrated non migré(s).\n");
}

function formatXml($simpleXMLElement)
{
    $xmlDocument = new DOMDocument('1.0');
    $xmlDocument->preserveWhiteSpace = false;
    $xmlDocument->formatOutput = true;
    $xmlDocument->loadXML($simpleXMLElement->asXML());

    return $xmlDocument->saveXML();
}
