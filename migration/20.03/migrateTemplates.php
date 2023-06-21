<?php

use Contact\models\ContactCustomFieldListModel;
use CustomField\models\CustomFieldModel;
use Docserver\controllers\DocserverController;
use Docserver\models\DocserverModel;
use SrcCore\models\CoreConfigModel;
use Template\models\TemplateModel;

require '../../vendor/autoload.php';

include_once('../../vendor/tinybutstrong/opentbs/tbs_plugin_opentbs.php');

const OFFICE_EXTENSIONS = ['odt', 'ods', 'odp', 'xlsx', 'pptx', 'docx', 'odf'];

$DATA_TO_REPLACE = [
    'res_letterbox.destination'         => '[destination.entity_id]',
    'res_letterbox.entity_label'        => '[destination.entity_label]',
    'res_letterbox.process_notes'       => '[notes]',
    'res_letterbox.contact_firstname'   => '[sender.firstname]',
    'res_letterbox.contact_lastname'    => '[sender.lastname]',
    'res_letterbox.contact_society'     => '[sender.company]',

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

    'attachments.chrono' => '[attachment.chrono]',

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

const DATA_CONTACT_ATTACHMENT = [
    'contact.contact_type_label'        => '',
    'contact.society_short'             => '',
    'contact.contact_purpose_label'     => '',
    'contact.website'                   => '',
    'contact.salutation_header'         => '',
    'contact.salutation_footer'         => '',
    'contact.society'                   => '[attachmentRecipient.company]',
    'contact.departement'               => '[attachmentRecipient.department]',
    'contact.title'                     => '[attachmentRecipient.civility]',
    'contact.contact_title'             => '[attachmentRecipient.civility]',
    'contact.contact_lastname'          => '[attachmentRecipient.lastname]',
    'contact.contact_firstname'         => '[attachmentRecipient.firstname]',
    'contact.lastname'                  => '[attachmentRecipient.lastname]',
    'contact.firstname'                 => '[attachmentRecipient.firstname]',
    'contact.function'                  => '[attachmentRecipient.function]',
    'contact.postal_address;strconv=no' => '[attachmentRecipient.postal_address;strconv=no]',
    'contact.postal_address'            => '[attachmentRecipient.postal_address]',
    'contact.address_num'               => '[attachmentRecipient.address_number]',
    'contact.address_street'            => '[attachmentRecipient.address_street]',
    'contact.occupancy'                 => '[attachmentRecipient.address_additional1]',
    'contact.address_complement'        => '[attachmentRecipient.address_additional2]',
    'contact.address_town'              => '[attachmentRecipient.address_town]',
    'contact.address_postal_code'       => '[attachmentRecipient.address_postcode]',
    'contact.address_country'           => '[attachmentRecipient.address_country]',
    'contact.phone'                     => '[attachmentRecipient.phone]',
    'contact.email'                     => '[attachmentRecipient.email]',
];

const DATA_CONTACT_ACKNOWLEDGEMENT_RECEIPT = [
    'contact.contact_type_label'        => '',
    'contact.society_short'             => '',
    'contact.contact_purpose_label'     => '',
    'contact.website'                   => '',
    'contact.salutation_header'         => '',
    'contact.salutation_footer'         => '',
    'contact.society'                   => '[sender.company]',
    'contact.departement'               => '[sender.department]',
    'contact.title'                     => '[sender.civility]',
    'contact.contact_title'             => '[sender.civility]',
    'contact.contact_lastname'          => '[sender.lastname]',
    'contact.contact_firstname'         => '[sender.firstname]',
    'contact.lastname'                  => '[sender.lastname]',
    'contact.firstname'                 => '[sender.firstname]',
    'contact.function'                  => '[sender.function]',
    'contact.postal_address;strconv=no' => '[sender.postal_address;strconv=no]',
    'contact.postal_address'            => '[sender.postal_address]',
    'contact.address_num'               => '[sender.address_number]',
    'contact.address_street'            => '[sender.address_street]',
    'contact.occupancy'                 => '[sender.address_additional1]',
    'contact.address_complement'        => '[sender.address_additional2]',
    'contact.address_town'              => '[sender.address_town]',
    'contact.address_postal_code'       => '[sender.address_postcode]',
    'contact.address_country'           => '[sender.address_country]',
    'contact.phone'                     => '[sender.phone]',
    'contact.email'                     => '[sender.email]',
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

    $migrated    = 0;
    $nonMigrated = 0;

    $docserver     = DocserverModel::getByDocserverId(['docserverId' => 'TEMPLATES']);
    $templatesPath = $docserver['path_template'];
    $templates     = TemplateModel::get(['where' => ['template_target != ?'], 'data' => ['indexingFile']]);

    $tmpPath = CoreConfigModel::getTmpPath();

    foreach ($templates as $template) {
        if ($template['template_type'] == 'HTML' || $template['template_type'] == 'TXT' || $template['template_type'] == 'OFFICE_HTML') {
            $content = $template['template_content'];

            $newContent = $content;
            foreach ($DATA_TO_REPLACE as $key => $value) {
                $newContent = str_replace('[' . $key . ']', $value, $newContent);
            }

            if ($template['template_target'] == 'acknowledgementReceipt') {
                foreach (DATA_CONTACT_ACKNOWLEDGEMENT_RECEIPT as $key => $value) {
                    $newContent = str_replace('[' . $key . ']', $value, $newContent);
                }
            } else {
                foreach (DATA_CONTACT_ATTACHMENT as $key => $value) {
                    $newContent = str_replace('[' . $key . ']', $value, $newContent);
                }
            }

            if ($template['template_target'] == 'doctypes') {
                $pathFilename = $tmpPath . 'template_migration_' . rand() . '_'. rand() .'.html';
                file_put_contents($pathFilename, $newContent);

                $resource = file_get_contents($pathFilename);
                $pathInfo = pathinfo($pathFilename);
                $storeResult = DocserverController::storeResourceOnDocServer([
                    'collId'           => 'templates',
                    'docserverTypeId'  => 'TEMPLATES',
                    'encodedResource'  => base64_encode($resource),
                    'format'           => $pathInfo['extension']
                ]);

                if (!empty($storeResult['errors'])) {
                    echo $storeResult['errors'];
                    continue;
                }

                TemplateModel::update([
                        'set'   => [
                            'template_content'    => '',
                            'template_type'       => 'OFFICE',
                            'template_path'       => $storeResult['destination_dir'],
                            'template_file_name'  => $storeResult['file_destination_name'],
                            'template_style'      => '',
                            'template_datasource' => 'letterbox_attachment',
                            'template_target'     => 'indexingFile',
                            'template_attachment_type' => 'all'
                        ],
                        'where' => ['template_id = ?'],
                        'data'  => [$template['template_id']]
                    ]);
                unlink($pathFilename);
            } else {
                if ($content != $newContent) {
                    TemplateModel::update([
                        'set'   => [
                            'template_content' => $newContent
                        ],
                        'where' => ['template_id = ?'],
                        'data'  => [$template['template_id']]
                    ]);
                    $migrated++;
                } else {
                    $nonMigrated++;
                }
            }
        }
        if ($template['template_type'] == 'OFFICE' || $template['template_type'] == 'OFFICE_HTML') {
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

                if ($template['template_target'] == 'acknowledgementReceipt') {
                    $tbs->ReplaceFields(DATA_CONTACT_ACKNOWLEDGEMENT_RECEIPT);
                } else {
                    $tbs->ReplaceFields(DATA_CONTACT_ATTACHMENT);
                }
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
    }

    printf("Migration de Modèles de documents (CUSTOM {$custom}) : " . $migrated . " Modèle(s) migré(s), $nonMigrated non migré(s).\n");
}
