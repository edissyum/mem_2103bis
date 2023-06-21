<?php

use Convert\models\AdrModel;
use Docserver\controllers\DocserverController;
use Docserver\models\DocserverModel;
use Resource\models\ResModel;
use SrcCore\models\CoreConfigModel;

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
    $attachmentsInfo = \SrcCore\models\DatabaseModel::select([
        'select' => ['l.category_id', 'a.res_id', 'a.relation', 'a.docserver_id', 'a.path', 'a.filename', 'a.filesize', 'a.format', 'a.res_id_master', 'a.in_signature_book',
                        'a.in_send_attach', 'a.external_id->>\'signatureBookId\' as signaturebookid', 'a.origin_id', 'a.fingerprint'],
        'table'  => ['res_attachments a, res_letterbox l'],
        'where'  => ['attachment_type = ?', 'a.status not in (?)', 'a.res_id_master = l.res_id', 'category_id = ?'],
        'data'   => ['outgoing_mail', ['DEL', 'TMP', 'OBS'], 'outgoing'],
        'orderBy' => ['a.res_id desc']
    ]);

    $superadmin = \User\models\UserModel::getByLogin(['select' => ['id'], 'login' => 'superadmin']);
    if (empty($superadmin)) {
        $firstMan = \User\models\UserModel::get(['select' => ['id'], 'orderBy' => ['id'], 'limit' => 1]);
        $masterOwnerId = $firstMan[0]['id'];
    } else {
        $masterOwnerId = $superadmin['id'];
    }

    $tmpPath = CoreConfigModel::getTmpPath();


    $resIdsMaster = array_column($attachmentsInfo, 'res_id_master', 'res_id');

    $resIdsUniqueMaster = array_column($attachmentsInfo, 'res_id_master', 'res_id');
    $resIdsUniqueMaster = array_unique($resIdsUniqueMaster);
    $resIdsUniqueMaster = array_keys($resIdsUniqueMaster);

    if (!empty($resIdsMaster)) {
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'adr_letterbox',
            'where' => ['res_id in (?)'],
            'data'  => [$resIdsMaster]
        ]);

        $convertedDocuments = \SrcCore\models\DatabaseModel::select([
            'select'    => ['docserver_id','path', 'filename', 'fingerprint', 'res_id'],
            'table'     => ['adr_attachments'],
            'where'     => ['res_id in (?)', 'type = ?'],
            'data'      => [$resIdsUniqueMaster, 'PDF'],
        ]);
        $convertedDocuments = array_column($convertedDocuments, null, 'res_id');

        $outgoingMailsSigned = \SrcCore\models\DatabaseModel::select([
            'select'  => ['res_id', 'relation', 'docserver_id', 'path', 'filename', 'format', 'res_id_master'],
            'table'   => ['res_attachments'],
            'where'   => ['attachment_type = ?', 'res_id_master in (?)', 'status not in (?)'],
            'data'    => ['outgoing_mail_signed', $resIdsMaster, ['DEL']],
            'orderBy' => ['res_id desc']
        ]);
        $outgoingMailsSigned = array_column($outgoingMailsSigned, null, 'res_id');

        $signedResponses = \SrcCore\models\DatabaseModel::select([
            'select'  => ['res_id', 'relation', 'docserver_id', 'path', 'filename', 'format', 'res_id_master'],
            'table'   => ['res_attachments'],
            'where'   => ['attachment_type = ?', 'origin = ?', 'status not in (?)'],
            'data'    => ['signed_response', $resIdsMaster.',res_attachments', ['DEL']],
            'orderBy' => ['res_id desc']
        ]);
        $signedResponses = array_column($signedResponses, null, 'res_id');
    }

    $previousResId = 0;
    $attachmentToDelete = [];
    foreach ($attachmentsInfo as $attachmentInfo) {
        if ($previousResId == $attachmentInfo['res_id_master']) {
            continue;
        }
        $previousResId = $attachmentInfo['res_id_master'];

        $integration = [];
        $integration['inSignatureBook'] = empty($attachmentInfo['in_signature_book']) ?  'false' : 'true';
        $integration['inShipping']      = empty($attachmentInfo['in_send_attach']) ?  'false' : 'true';

        if (empty($attachmentInfo['signaturebookid'])) {
            $attachmentInfo['signaturebookid'] = 'null';
        }

        ResModel::update([
            'set' => [
                'docserver_id' => $attachmentInfo['docserver_id'],
                'path'         => $attachmentInfo['path'],
                'filename'     => $attachmentInfo['filename'],
                'fingerprint'  => $attachmentInfo['fingerprint'],
                'filesize'     => $attachmentInfo['filesize'],
                'version'      => $attachmentInfo['relation'],
                'integrations' => json_encode($integration)
            ],
            'postSet' => ['external_id' => "jsonb_set(external_id, '{signatureBookId}', to_jsonb('{$attachmentInfo['signaturebookid']}'::text))"],
            'where' => ['res_id = ?'],
            'data'  => [$attachmentInfo['res_id_master']]
        ]);

        if (!empty($convertedDocuments[$attachmentInfo['res_id']])) {
            $convertedDocument = $convertedDocuments[$attachmentInfo['res_id']];

            AdrModel::createDocumentAdr([
                'resId'         => $attachmentInfo['res_id_master'],
                'type'          => 'PDF',
                'docserverId'   => $convertedDocument['docserver_id'],
                'path'          => $convertedDocument['path'],
                'filename'      => $convertedDocument['filename'],
                'version'       => $attachmentInfo['relation'],
                'fingerprint'   => $convertedDocument['fingerprint']
            ]);
        }
    
        $attachmentToDelete[] = $attachmentInfo['res_id'];
        $customId = empty($custom) ? 'null' : $custom;
        exec("php src/app/convert/scripts/FullTextScript.php --customId {$customId} --resId {$attachmentInfo['res_id_master']} --collId letterbox_coll --userId {$masterOwnerId} > /dev/null &");
    
        if ($attachmentInfo['relation'] > 1) {
            $attachmentsVersion = \SrcCore\models\DatabaseModel::select([
                'select'  => ['res_id', 'relation', 'docserver_id', 'path', 'filename', 'fingerprint', 'format', 'res_id_master'],
                'table'   => ['res_attachments'],
                'where'   => ['(origin_id = ? or res_id = ?)', 'relation < ?'],
                'data'    => [$attachmentInfo['origin_id'], $attachmentInfo['origin_id'], $attachmentInfo['relation']],
                'orderBy' => ['relation asc']
            ]);

            foreach ($attachmentsVersion as $attachmentVersion) {
                $docserver      = DocserverModel::getByDocserverId(['docserverId' => $attachmentVersion['docserver_id'], 'select' => ['path_template']]);
                $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $attachmentVersion['path']) . $attachmentVersion['filename'];

                if (file_exists($pathToDocument)) {
                    $resource = file_get_contents($pathToDocument);
                    $pathInfo = pathinfo($pathToDocument);
                    $storeResult = DocserverController::storeResourceOnDocServer([
                        'collId'            => 'letterbox_coll',
                        'docserverTypeId'   => 'DOC',
                        'encodedResource'   => base64_encode($resource),
                        'format'            => $pathInfo['extension']
                    ]);
                    \SrcCore\models\DatabaseModel::insert([
                        'table'         => 'adr_letterbox',
                        'columnsValues' => [
                            'res_id'       => $attachmentVersion['res_id_master'],
                            'type'         => 'DOC',
                            'docserver_id' => $storeResult['docserver_id'],
                            'path'         => $storeResult['destination_dir'],
                            'filename'     => $storeResult['file_destination_name'],
                            'version'      => $attachmentVersion['relation'],
                            'fingerprint'  => empty($storeResult['fingerPrint']) ? null : $storeResult['fingerPrint']
                        ]
                    ]);
                } else {
                    echo "Le document suivant n'est pas migré car il n'existe pas :" . $pathToDocument . "\n";
                }
                $attachmentToDelete[] = $attachmentVersion['res_id'];
            }
        }

        if (!empty($outgoingMailsSigned[$attachmentInfo['res_id']])) {
            $outgoingMailSigned = $outgoingMailsSigned[$attachmentInfo['res_id']];
            // Version signée outgoing_mail_signed
            $outgoingMailSigned['adrType'] = 'SIGN';
            $outgoingMailSigned['relation'] = $attachmentInfo['relation'];
            addOutgoingMailSignedInAdr($outgoingMailSigned);
            $attachmentToDelete[] = $outgoingMailSigned['res_id'];
        } else {
            if (!empty($signedResponses[$attachmentInfo['res_id']])) {
                $signedResponse = $signedResponses[$attachmentInfo['res_id']];
                // Réponse signée signed_response
                $signedResponse['adrType'] = 'SIGN';
                $signedResponse['relation'] = $attachmentInfo['relation'];
                addOutgoingMailSignedInAdr($signedResponse);
                $attachmentToDelete[] = $signedResponse['res_id'];
            }
        }
            
        migrateHistoryVersion(['oldResId' => $attachmentInfo['res_id'], 'newResId' => $attachmentInfo['res_id_master']]);
        migrateEmailsVersion(['oldResId' => $attachmentInfo['res_id'], 'newResId' => $attachmentInfo['res_id_master']]);
        migrateMessageExchangeVersion(['oldResId' => $attachmentInfo['res_id'], 'newResId' => $attachmentInfo['res_id_master']]);
        migrateShippingVersion(['oldResId' => $attachmentInfo['res_id'], 'newResId' => $attachmentInfo['res_id_master']]);

        $migrated++;
    }

    // Version annotée
    $outgoigAnnotated = \SrcCore\models\DatabaseModel::select([
        'select'  => ['l.version', 'a.res_id', 'a.docserver_id', 'a.path', 'a.filename', 'a.format', 'a.res_id_master'],
        'table'   => ['res_attachments a, res_letterbox l'],
        'where'   => ['a.attachment_type = ?', 'a.status in (?)', 'category_id = ?', 'a.res_id_master = l.res_id'],
        'data'    => ['document_with_notes', ['A_TRA', 'TRA'], 'incoming'],
        'orderBy' => ['res_id desc']
    ]);

    $documentWithNote = 0;
    $previousResId    = 0;
    foreach ($outgoigAnnotated as $document) {
        if ($previousResId == $document['res_id_master']) {
            continue;
        }
        $previousResId = $document['res_id_master'];
        $document['adrType']  = 'NOTE';
        $document['relation'] = $document['version'];
        addOutgoingMailSignedInAdr($document);
        $attachmentToDelete[] = $document['res_id'];
        migrateHistoryVersion(['oldResId' => $document['res_id'], 'newResId' => $document['res_id_master']]);
        migrateEmailsVersion(['oldResId' => $document['res_id'], 'newResId' => $document['res_id_master']]);
        migrateMessageExchangeVersion(['oldResId' => $document['res_id'], 'newResId' => $document['res_id_master']]);
        migrateShippingVersion(['oldResId' => $document['res_id'], 'newResId' => $document['res_id_master']]);
        $documentWithNote++;
    }

    if (!empty($attachmentToDelete)) {
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'res_attachments',
            'where' => ['res_id in (?)'],
            'data'  => [$attachmentToDelete]
        ]);

        \SrcCore\models\DatabaseModel::delete([
            'table' => 'adr_attachments',
            'where' => ['res_id in (?)'],
            'data'  => [$attachmentToDelete]
        ]);
    }

    printf("Migration outgoing_mail, outgoing_mail_signed (CUSTOM {$custom}) : " . $migrated . " courier(s) départ(s) trouvé(s) et migré(s). ".$documentWithNote." courrier(s) annoté(s)\n");
}

function addOutgoingMailSignedInAdr($args = [])
{
    $convertedDocument = \SrcCore\models\DatabaseModel::select([
        'select'    => ['docserver_id','path', 'filename', 'fingerprint'],
        'table'     => ['adr_attachments'],
        'where'     => ['res_id = ?', 'type = ?'],
        'data'      => [$args['res_id'], 'PDF'],
    ]);

    if (!empty($convertedDocument)) {
        \SrcCore\models\DatabaseModel::delete([
            'table' => 'adr_letterbox',
            'where' => ['res_id = ?', 'type = ?', 'version = ?'],
            'data'  => [$args['res_id_master'], $args['adrType'], $args['relation']]
        ]);
        AdrModel::createDocumentAdr([
            'resId'       => $args['res_id_master'],
            'type'        => $args['adrType'],
            'docserverId' => $convertedDocument[0]['docserver_id'],
            'path'        => $convertedDocument[0]['path'],
            'filename'    => $convertedDocument[0]['filename'],
            'version'     => $args['relation'],
            'fingerprint' => $convertedDocument[0]['fingerprint']
        ]);
    
        if ($args['adrType'] == 'SIGN') {
            \SrcCore\models\DatabaseModel::delete([
                'table' => 'adr_letterbox',
                'where' => ['res_id = ?', 'type = ?', 'version = ?'],
                'data'  => [$args['res_id_master'], 'TNL', $args['relation']]
            ]);
        }
    }
}

function migrateHistoryVersion($args = [])
{
    \SrcCore\models\DatabaseModel::update([
        'postSet' => ['info' => "REPLACE(info, '{$args['oldResId']} (res_attachments)', '{$args['newResId']} (res_letterbox)')"],
        'table'   => 'history',
        'where'   => ['table_name = ?', 'record_id = ?'],
        'data'    => ['res_attachments', $args['oldResId']]
    ]);
}

function migrateEmailsVersion($args = [])
{
    $emails = \SrcCore\models\DatabaseModel::select([
        'select' => ['id', 'document'],
        'table' => ['emails'],
        'where' => ['document->\'attachments\' @> ?'],
        'data' => ['[{"id":'.$args['oldResId'].'}]']
    ]);

    foreach ($emails as $email) {
        $document = json_decode($email['document'], true);
        foreach ($document['attachments'] as $key => $attachment) {
            if ($attachment['id'] == $args['oldResId']) {
                $document['isLinked'] = true;
                $document['original'] = $attachment['original'];
                unset($document['attachments'][$key]);
                break;
            }
        }
        \SrcCore\models\DatabaseModel::update([
            'set'   => ['document' => json_encode($document)],
            'table' => 'emails',
            'where' => ['id = ?'],
            'data'  => [$email['id']]
        ]);
    }
}

function migrateMessageExchangeVersion($args = [])
{
    \SrcCore\models\DatabaseModel::update([
        'set'   => ['res_id' => $args['newResId'], 'tablename' => 'res_letterbox'],
        'table' => 'unit_identifier',
        'where' => ['res_id = ?', 'tablename = ?'],
        'data'  => [$args['oldResId'], 'res_attachments']
    ]);
}

function migrateShippingVersion($args = [])
{
    \SrcCore\models\DatabaseModel::update([
        'set'   => ['document_id' => $args['newResId'], 'document_type' => 'resource'],
        'table' => 'shippings',
        'where' => ['document_id = ?', 'document_type = ?'],
        'data'  => [$args['oldResId'], 'attachment']
    ]);
}
