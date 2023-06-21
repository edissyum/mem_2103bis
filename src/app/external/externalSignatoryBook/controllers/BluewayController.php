<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Blueway Controller
 * @author dev@maarch.org
 */

namespace ExternalSignatoryBook\controllers;

use Attachment\models\AttachmentModel;
use Attachment\models\AttachmentTypeModel;
use Convert\controllers\ConvertPdfController;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Entity\models\ListInstanceModel;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use SrcCore\models\CurlModel;
use SrcCore\models\DatabaseModel;
use SrcCore\models\TextFormatModel;

/**
 * @codeCoverageIgnore
 */
class BluewayController
{
    public static function getInitializeDatas($config, $res)
    {
        $tokenAPI = null;
        $defaultNature = null;
        $defaultMessageModel = null;
        $defaultNatureUser = null;

        foreach ($config['data']['organization'] as $organization) {
            if ($organization->organizationName == $res['custom_fields'][$config['data']['customOrganization']]) {
                $tokenAPI = $organization->tokenAPI;
                $defaultNature = (string)$organization->defaultNature;
                break;
            }
        }
        if ($tokenAPI === null)
            return ['error' => 'No token found for the selected custom'];

        $natures = BluewayController::getNature(['config' => $config, 'tokenAPI' => $tokenAPI]);
        if (!empty($natures['error'])) {
            return ['error' => $natures['error']];
        }

        $rawResponse['natures'] = $natures['natures'];
        if ($defaultNature) {
            foreach ($natures['natures'] as $nature)
                if ($nature['nom'] == $defaultNature)
                    $rawResponse['defaultNature'] = $nature['identifiant'];
        }
        $rawResponse['messagesModel'] = [];

        foreach ($natures['natures'] as $nature) {
            $messagesModels = BluewayController::getMessagesModel([
                'config' => $config,
                'natureId' => $nature['identifiant'],
                'tokenAPI' => $tokenAPI,
                'signatory' => $res['signatory']['user_id']
            ]);
            if (!empty($messagesModels['error'])) {
                return ['error' => $messagesModels['error']];
            }
            $rawResponse['messagesModel'][$nature['identifiant']] = $messagesModels['messageModels'];

            $users = BluewayController::getNatureUsers([
                'config' => $config,
                'natureId' => $nature['identifiant'],
                'tokenAPI' => $tokenAPI,
                'typist' => $res['initiatior']['user_id']
            ]);
            if(is_null($users['users']))
                return ['error' => 'No typist were found'];
            if (!empty($users['error'])) {
                return ['error' => $users['error']];
            }
            $rawResponse['users'][$nature['identifiant']] = $users['users'];
        }

        if ($rawResponse['defaultNature']) {
            foreach ($rawResponse['messagesModel'][$rawResponse['defaultNature']] as $messageModels)
                if ($messageModels['nom'] == $res['signatory']['user_id'])
                    $rawResponse['defaultMessagesModel'] = $messageModels['identifiant'];
            foreach ($rawResponse['users'][$rawResponse['defaultNature']] as $users)
                if ($users['nomUtilisateur'] == $res['initiatior']['user_id'])
                    $rawResponse['defaultUser'] = $users['identifiant'];
        }

        return $rawResponse;
    }

    public static function getNature($aArgs)
    {
        if (strtolower($aArgs['config']['data']['ixbusDirectConnection']) == 'false') {
            $url = $aArgs['config']['data']['url'];
            $params = [
                'flowName' => 'S_IXPARAPHEUR_GET_NATURES',
                'flowType' => 'EAII',
                'enforceAsString' => 'false',
                'actionJSON' => 'launch'
            ];

            $curlResponse = CurlModel::exec([
                'url' => $url . http_build_query($params),
                'headers' => ['IXBUS_API:' . $aArgs['tokenAPI']],
                'method' => 'GET'
            ]);

        } else {
            $curlResponse = CurlModel::exec([
                'url' => rtrim($aArgs['config']['data']['url'], '/') . '/api/parapheur/v1/nature',
                'headers' => ['IXBUS_API:' . $aArgs['tokenAPI']],
                'method' => 'GET'
            ]);
        }

        if (!empty($curlResponse['response']['error'])) {
            return ['error' => $curlResponse['message']];
        }

        foreach ($curlResponse['response']['payload'] as $key => $value) {
            unset($curlResponse['response']['payload'][$key]['motClefs']);
        }
        return ['natures' => $curlResponse['response']['payload']];
    }

    public static function getMessagesModel($aArgs)
    {
        if (strtolower($aArgs['config']['data']['ixbusDirectConnection']) == 'false') {
            $url = $aArgs['config']['data']['url'];
            $params = [
                'flowName' => 'S_IXPARAPHEUR_GET_CIRCUITS',
                'flowType' => 'EAII',
                'enforceAsString' => 'false',
                'actionJSON' => 'launch',
                'id_nature' => $aArgs['natureId'],
                'id_service' => '',
                'nom_circuit' => $aArgs['signatory'],
            ];

            $curlResponse = CurlModel::exec([
                'url' => $url . http_build_query($params),
                'headers' => ['IXBUS_API:' . $aArgs['tokenAPI']],
                'method' => 'GET'
            ]);

        } else {
            $curlResponse = CurlModel::exec([
                'url' => rtrim($aArgs['config']['data']['url'], '/') . '/api/parapheur/v1/circuit/' . $aArgs['natureId'],
                'headers' => ['IXBUS_API:' . $aArgs['tokenAPI']],
                'method' => 'GET'
            ]);
        }

        if (!empty($curlResponse['response']['error'])) {
            return ['error' => $curlResponse['message']];
        }

        foreach ($curlResponse['response']['payload'] as $key => $value) {
            unset($curlResponse['response']['payload'][$key]['etapes']);
            unset($curlResponse['response']['payload'][$key]['options']);
        }
        return ['messageModels' => $curlResponse['response']['payload']];
    }

    public static function getNatureUsers($aArgs)
    {
        if (strtolower($aArgs['config']['data']['ixbusDirectConnection']) == 'false') {
            $url = $aArgs['config']['data']['url'];
            $params = [
                'flowName' => 'S_IXPARAPHEUR_GET_REFERENTS',
                'flowType' => 'EAII',
                'enforceAsString' => 'false',
                'actionJSON' => 'launch',
                'id_nature' => $aArgs['natureId'],
                'nom_referent' => $aArgs['typist'],
            ];

            $curlResponse = CurlModel::exec([
                'url' => $url . http_build_query($params),
                'headers' => ['IXBUS_API:' . $aArgs['tokenAPI']],
                'method' => 'GET'
            ]);
            if(is_null($curlResponse['response']['payload'])) {
                $params['nom_referent'] = 'GEC.AUTO';
                $curlResponse = CurlModel::exec([
                    'url' => $url . http_build_query($params),
                    'headers' => ['IXBUS_API:' . $aArgs['tokenAPI']],
                    'method' => 'GET'
                ]);
            }
        } else {
            $curlResponse = CurlModel::exec([
                'url' => rtrim($aArgs['config']['data']['url'], '/') . '/api/parapheur/v1/nature/' . $aArgs['natureId'] . '/redacteur',
                'headers' => ['IXBUS_API:' . $aArgs['tokenAPI']],
                'method' => 'GET'
            ]);
        }

        if (!empty($curlResponse['response']['error'])) {
            return ['error' => $curlResponse['message']];
        }

        return ['users' => $curlResponse['response']['payload']];
    }

    public static function sendDatas($aArgs)
    {
        $mainResource = ResModel::getById([
            'select' => ['res_id', 'path', 'filename', 'docserver_id', 'format', 'category_id', 'external_id', 'integrations', 'subject'],
            'resId' => $aArgs['resIdMaster']
        ]);

        if (!empty($mainResource['docserver_id'])) {
            $adrMainInfo = ConvertPdfController::getConvertedPdfById(['resId' => $aArgs['resIdMaster'], 'collId' => 'letterbox_coll']);
            $letterboxPath = DocserverModel::getByDocserverId(['docserverId' => $adrMainInfo['docserver_id'], 'select' => ['path_template']]);
            $mainDocumentFilePath = $letterboxPath['path_template'] . str_replace('#', '/', $adrMainInfo['path']) . $adrMainInfo['filename'];
        }

        $attachments = AttachmentModel::get([
            'select' => [
                'res_id', 'title', 'identifier', 'attachment_type', 'status', 'typist', 'docserver_id', 'path', 'filename', 'creation_date',
                'validation_date', 'relation', 'origin_id', 'fingerprint', 'format'
            ],
            'where' => ["res_id_master = ?", "attachment_type not in (?)", "status not in ('DEL', 'OBS', 'FRZ', 'TMP', 'SEND_MASS')", "in_signature_book = 'true'"],
            'data' => [$aArgs['resIdMaster'], ['incoming_mail_attachment', 'signed_response']]
        ]);

        $annexesAttachments = [];
        $attachmentTypes = AttachmentTypeModel::get(['select' => ['type_id', 'signable']]);
        $attachmentTypes = array_column($attachmentTypes, 'signable', 'type_id');
        foreach ($attachments as $key => $value) {
            if (!$attachmentTypes[$value['attachment_type']]) {
                $adrInfo = ConvertPdfController::getConvertedPdfById(['resId' => $value['res_id'], 'collId' => 'attachments_coll']);
                if (empty($adrInfo['docserver_id']) || strtolower(pathinfo($adrInfo['filename'], PATHINFO_EXTENSION)) != 'pdf') {
                    return ['error' => 'Attachment ' . $value['res_id'] . ' is not converted in pdf'];
                }
                $docserverInfo = DocserverModel::getByDocserverId(['docserverId' => $adrInfo['docserver_id']]);
                if (empty($docserverInfo['path_template'])) {
                    return ['error' => 'Docserver does not exist ' . $adrInfo['docserver_id']];
                }
                $filePath = $docserverInfo['path_template'] . str_replace('#', '/', $adrInfo['path']) . $adrInfo['filename'];
                $docserverType = DocserverTypeModel::getById(['id' => $docserverInfo['docserver_type_id'], 'select' => ['fingerprint_mode']]);
                $fingerprint = StoreController::getFingerPrint(['filePath' => $filePath, 'mode' => $docserverType['fingerprint_mode']]);
                if ($adrInfo['fingerprint'] != $fingerprint) {
                    return ['error' => 'Fingerprints do not match'];
                }

                $annexesAttachments[] = ['filePath' => $filePath, 'fileName' => $value['title'] . '.pdf'];
                unset($attachments[$key]);
            }
        }

        $attachmentToFreeze = [];
        $mainResource = ResModel::getById([
            'resId' => $aArgs['resIdMaster'],
            'select' => ['res_id', 'subject', 'path', 'filename', 'docserver_id', 'format', 'category_id', 'external_id', 'integrations', 'process_limit_date', 'fingerprint', 'custom_fields']
        ]);

        foreach ($aArgs['config']['data']['organization'] as $organization) {
            if ($organization->organizationName == json_decode($mainResource['custom_fields'], true)[$aArgs['config']['data']['customOrganization']]) {
                $aArgs['tokenAPI'] = (string)$organization->tokenAPI;
                break;
            }
        }

        if (empty($mainResource['process_limit_date'])) {
            $processLimitDate = date('Y-m-d', strtotime(date("Y-m-d") . ' + 14 days'));
        } else {
            $processLimitDateTmp = explode(" ", $mainResource['process_limit_date']);
            $processLimitDate = $processLimitDateTmp[0];
        }

        $attachmentsData = [];
        if (!empty($mainDocumentFilePath)) {
            $attachmentsData = [[
                'filePath' => $mainDocumentFilePath,
                'fileName' => TextFormatModel::formatFilename(['filename' => $mainResource['subject'], 'maxLength' => 250]) . '.pdf'
            ]];
        }
        $attachmentsData = array_merge($annexesAttachments, $attachmentsData);

        $signature = $aArgs['manSignature'] == 'manual' ? 1 : 0;
        $bodyData = [
            'nature' => $aArgs['natureId'],
            'referent' => $aArgs['referent'],
            'circuit' => $aArgs['messageModel'],
            'options' => [
                'confidentiel' => false,
                'dateLimite' => true,
                'documentModifiable' => true,
                'annexesSignables' => false,
                'autoriserModificationAnnexes' => false,
                'signature' => $signature,
                'circuitModifiable' => false,
                'autoriserRefusAssistant' => false,
                'autoriserDroitRemordSig' => false,
                'ajouterAnnotationPubliqueEtapeSignature' => false,
                'nePasCalculerCircuitHierarchique' => false,
                'remplacerCircuitHierarchiqueParResponsable' => false,
                'fusionnerEtapesSuccessives' => false,
                'informerPersonnesEvolutionTraitement' => false,
                'informerPersonnesDebutTraitement' => false,
                'informerPersonnesFinTraitement' => false
            ],
            'dateLimite' => $processLimitDate
        ];
        if( ! is_null($aArgs['note']) && $aArgs['note'] != '')
            $bodyData['annotations'] = array(
                [
                    'identifiant' => '',
                    'type' => 'Public',
                    'texte' => $aArgs['note']
                ]
            );

        //print_r($bodyData); exit();

        foreach ($attachments as $value) {
            $resId = $value['res_id'];
            $collId = 'attachments_coll';

            $adrInfo = ConvertPdfController::getConvertedPdfById(['resId' => $resId, 'collId' => $collId]);
            $docserverInfo = DocserverModel::getByDocserverId(['docserverId' => $adrInfo['docserver_id']]);
            $filePath = $docserverInfo['path_template'] . str_replace('#', '/', $adrInfo['path']) . $adrInfo['filename'];

            $docserverType = DocserverTypeModel::getById(['id' => $docserverInfo['docserver_type_id'], 'select' => ['fingerprint_mode']]);
            $fingerprint = StoreController::getFingerPrint(['filePath' => $filePath, 'mode' => $docserverType['fingerprint_mode']]);
            if ($adrInfo['fingerprint'] != $fingerprint) {
                return ['error' => 'Fingerprints do not match'];
            }

            $bodyData['nom'] = $value['title'];

            $createdFile = BluewayController::createFolder(['config' => $aArgs['config'], 'body' => $bodyData, 'tokenAPI' => $aArgs['tokenAPI']]);
            if (!empty($createdFile['error'])) {
                return ['error' => $createdFile['message']];
            }
            $folderId = $createdFile['folderId'];

            $addedFile = BluewayController::addFileToFolder([
                'config' => $aArgs['config'],
                'folderId' => $folderId,
                'filePath' => $filePath,
                'fileName' => TextFormatModel::formatFilename(['filename' => $value['title'], 'maxLength' => 250]) . '.pdf',
                'fileType' => 'principal',
                'tokenAPI' => $aArgs['tokenAPI'],
            ]);

            if (!empty($addedFile['error'])) {
                return ['error' => $addedFile['message']];
            }

            foreach ($attachmentsData as $attachmentData) {
                $addedFile = BluewayController::addFileToFolder([
                    'config' => $aArgs['config'],
                    'folderId' => $folderId,
                    'filePath' => $attachmentData['filePath'],
                    'fileName' => $attachmentData['fileName'],
                    'fileType' => 'annexe',
                    'tokenAPI' => $aArgs['tokenAPI'],
                ]);
                if (!empty($addedFile['error'])) {
                    return ['error' => $addedFile['message']];
                }
            }

            $transmittedFolder = BluewayController::transmitFolder([
                'config' => $aArgs['config'],
                'folderId' => $folderId,
                'tokenAPI' => $aArgs['tokenAPI']
            ]);
            if (!empty($transmittedFolder['error'])) {
                return ['error' => $transmittedFolder['message']];
            }

            $attachmentToFreeze[$collId][$resId] = $folderId;
        }


        // Send main document if in signature book
        $mainDocumentIntegration = json_decode($mainResource['integrations'], true);
        $externalId = json_decode($mainResource['external_id'], true);
        if ($mainDocumentIntegration['inSignatureBook'] && empty($externalId['signatureBookId'])) {
            $resId = $mainResource['res_id'];
            $collId = 'letterbox_coll';

            $adrInfo = ConvertPdfController::getConvertedPdfById(['resId' => $resId, 'collId' => $collId]);
            $docserverInfo = DocserverModel::getByDocserverId(['docserverId' => $adrInfo['docserver_id']]);
            $filePath = $docserverInfo['path_template'] . str_replace('#', '/', $adrInfo['path']) . $adrInfo['filename'];

            $docserverType = DocserverTypeModel::getById(['id' => $docserverInfo['docserver_type_id'], 'select' => ['fingerprint_mode']]);
            $fingerprint = StoreController::getFingerPrint(['filePath' => $filePath, 'mode' => $docserverType['fingerprint_mode']]);
            if ($adrInfo['fingerprint'] != $fingerprint) {
                return ['error' => 'Fingerprints do not match'];
            }

            $bodyData['nom'] = $mainResource['subject'];

            $createdFile = BluewayController::createFolder([
                'config' => $aArgs['config'],
                'body' => $bodyData,
                'tokenAPI' => $aArgs['tokenAPI']
            ]);
            if (!empty($createdFile['error'])) {
                return ['error' => $createdFile['message']];
            }
            $folderId = $createdFile['folderId'];

            $addedFile = BluewayController::addFileToFolder([
                'config' => $aArgs['config'],
                'folderId' => $folderId,
                'filePath' => $filePath,
                'fileName' => TextFormatModel::formatFilename(['filename' => $mainResource['subject'], 'maxLength' => 250]) . '.pdf',
                'fileType' => 'principal',
                'tokenAPI' => $aArgs['tokenAPI'],
            ]);
            if (!empty($addedFile['error'])) {
                return ['error' => $addedFile['message']];
            }

            foreach ($attachmentsData as $attachmentData) {
                $addedFile = BluewayController::addFileToFolder([
                    'config' => $aArgs['config'],
                    'folderId' => $folderId,
                    'filePath' => $attachmentData['filePath'],
                    'fileName' => $attachmentData['fileName'],
                    'fileType' => 'annexe',
                    'tokenAPI' => $aArgs['tokenAPI']
                ]);
                if (!empty($addedFile['error'])) {
                    return ['error' => $addedFile['message']];
                }
            }

            $transmittedFolder = BluewayController::transmitFolder([
                'config' => $aArgs['config'],
                'folderId' => $folderId,
                'tokenAPI' => $aArgs['tokenAPI']]);
            if (!empty($transmittedFolder['error'])) {
                return ['error' => $transmittedFolder['message']];
            }
            $attachmentToFreeze[$collId][$resId] = $folderId;
        }

        BluewayController::processVisaWorkflow(['res_id_master' => $aArgs['resIdMaster'], 'processSignatory' => false]);

        return ['sended' => $attachmentToFreeze];
    }


    public static function createFolder(array $aArgs)
    {
        if (strtolower($aArgs['config']['data']['ixbusDirectConnection']) == 'false') {
            $url = $aArgs['config']['data']['url'];
            $params = [
                'flowName' => 'S_IXPARAPHEUR_POST_DOSSIER',
                'flowType' => 'EAII',
                'enforceAsString' => 'false',
                'actionJSON' => 'launch',
                'transmettre' => 'false',
            ];
            $aArgs['body']['options'] = [

            ];

            $curlResponse = CurlModel::exec([
                'url' => $url . http_build_query($params),
                'headers' => ['IXBUS_API:' . $aArgs['tokenAPI'], 'Content-Type: application/json'],
                'method' => 'POST',
                'body' => json_encode($aArgs['body'])
            ]);

        } else {
            $curlResponse = CurlModel::exec([
                'url' => rtrim($aArgs['config']['data']['url'], '/') . '/api/parapheur/v1/dossier',
                'headers' => ['content-type:application/json', 'IXBUS_API:' . $aArgs['tokenAPI']],
                'method' => 'POST',
                'body' => json_encode($aArgs['body'])
            ]);
        }
        if (!empty($curlResponse['response']['error'])) {
            return ['error' => $curlResponse['response']['message']];
        }
        return ['folderId' => $curlResponse['response']['payload']['identifiant']];
    }


    public static function addFileToFolder(array $aArgs)
    {

        if (strtolower($aArgs['config']['data']['ixbusDirectConnection']) == 'false') {
            $url = $aArgs['config']['data']['url'];
            $params = [
                'flowName' => 'S_IXPARAPHEUR_POST_AJOUTFICHIER',
                'flowType' => 'EAII',
                'enforceAsString' => 'false',
                'actionJSON' => 'launch',
                'id_dossier' => $aArgs['folderId'],
            ];

            $curlResponse = CurlModel::exec([
                'url' => $url . http_build_query($params),
                'headers' => ['IXBUS_API:' . $aArgs['tokenAPI'], 'Content-Type: text/plain'],
                'method' => 'CUSTOM',
                'customRequest' => 'POST',
                'body' => json_encode(['nom_fichier' => strstr($aArgs['filePath'], $aArgs['config']['data']['customId']), 'type' => $aArgs['fileType']])
            ]);
        } else {
            $curlResponse = CurlModel::exec([
                'url' => rtrim($aArgs['config']['data']['url'], '/') . '/api/parapheur/v1/document/' . $aArgs['folderId'],
                'headers' => ['IXBUS_API:' . $aArgs['tokenAPI']],
                'customRequest' => 'POST',
                'method' => 'CUSTOM',
                'body' => ['fichier' => CurlModel::makeCurlFile(['path' => $aArgs['filePath'], 'name' => $aArgs['fileName']]), 'type' => $aArgs['fileType']]
            ]);
        }
        if (!empty($curlResponse['response']['error'])) {
            return ['error' => $curlResponse['response']['message']];
        }

        return $curlResponse['response'];
    }

    public static function transmitFolder(array $aArgs)
    {
        if (strtolower($aArgs['config']['data']['ixbusDirectConnection']) == 'false') {
            $url = $aArgs['config']['data']['url'];
            $params = [
                'flowName' => 'S_IXPARAPHEUR_POST_TRANSMISSIONDOSSIER',
                'flowType' => 'EAII',
                'enforceAsString' => 'false',
                'actionJSON' => 'launch',
                'id_dossier' => $aArgs['folderId'],
            ];

            $curlResponse = CurlModel::exec([
                'url' => $url . http_build_query($params),
                'headers' => ['IXBUS_API:' . $aArgs['tokenAPI']],
                'customRequest' => 'POST',
                'method' => 'CUSTOM',
            ]);
        } else {
            $curlResponse = CurlModel::exec([
                'url' => rtrim($aArgs['config']['data']['url'], '/') . '/api/parapheur/v1/dossier/' . $aArgs['folderId'] . '/transmettre',
                'headers' => ['IXBUS_API:' . $aArgs['tokenAPI']],
                'method' => 'POST'
            ]);
        }
        if (!empty($curlResponse['response']['error'])) {
            return ['error' => $curlResponse['response']['message']];
        }
        return [];
    }

    public static function retrieveSignedMails($aArgs)
    {
        $version = $aArgs['version'];

        if ($version == 'resLetterbox') {
            $resources = ResModel::get([
                'select' => ['res_id', 'custom_fields'],
                'where' => ['status NOT IN (?)', 'external_id->>\'signatureBookId\' IS NOT NULL', 'external_id->>\'signatureBookId\' <> \'\''],
                'data' => [['DEL', 'END']]
            ]);
        } else {
            $resources = \SrcCore\models\DatabaseModel::select([
                'select' => ['a.res_id', 'r.custom_fields'],
                'table' => ['res_letterbox r', 'res_attachments a'],
                'left_join' => ['r.res_id = a.res_id_master'],
                'where' => ['a.status = ?', 'a.external_id->>\'signatureBookId\' IS NOT NULL', 'a.external_id->>\'signatureBookId\' <> \'\''],
                'data' => ['FRZ']
            ]);
        }

        $customFields = [];
        foreach ($resources as $resource) {
            $customFields[$resource['res_id']] = $resource['custom_fields'];
        }

        foreach ($aArgs['idsToRetrieve'][$version] as $resId => $value) {
            foreach ($aArgs['config']['data']['organization'] as $organization) {
                if ($organization->organizationName == json_decode($customFields[$resId], true)[$aArgs['config']['data']['customOrganization']]) {
                    $aArgs['tokenAPI'] = (string)$organization->tokenAPI;
                    break;
                }
            }

            $folderData = BluewayController::getDossier([
                'config' => $aArgs['config'],
                'folderId' => $value['external_id'],
                'tokenAPI' => $aArgs['tokenAPI']
            ]);


            if (in_array($folderData['data']['etat'], ['Refusé', 'Terminé'])) {
                $aArgs['idsToRetrieve'][$version][$resId]['status'] = $folderData['data']['etat'] == 'Refusé' ? 'refused' : 'validated';
                $signedDocument = BluewayController::getDocument([
                    'config' => $aArgs['config'],
                    'documentId' => $folderData['data']['documents']['principal']['identifiant'],
                    'tokenAPI' => $aArgs['tokenAPI']
                ]);
                $aArgs['idsToRetrieve'][$version][$resId]['format'] = 'pdf';
                $aArgs['idsToRetrieve'][$version][$resId]['encodedFile'] = $signedDocument['encodedDocument'];
                if (!empty($folderData['data']['detailEtat'])) {
                    $aArgs['idsToRetrieve'][$version][$resId]['notes'][] = ['content' => $folderData['data']['detailEtat']];
                }
                if ($folderData['data']['etat'] === 'Terminé') {
                    if ($version != 'resLetterbox') {
                        $res = DatabaseModel::select([
                            'select' => ['users.id'],
                            'table' => ['listinstance', 'users'],
                            'left_join' => ['listinstance.item_id = users.id'],
                            'where' => ['res_id = ?', 'process_date is null', 'difflist_type = ?'],
                            'data' => [$value['res_id_master'], 'VISA_CIRCUIT']
                        ])[0];
                        $aArgs['idsToRetrieve'][$version][$resId]['signatory_user_serial_id'] = $res['id'];
                    }
                    BluewayController::processVisaWorkflow(['res_id_master' => $value['res_id_master'], 'res_id' => $value['res_id'], 'processSignatory' => true]);

                }
            } else {
                unset($aArgs['idsToRetrieve'][$version][$resId]);
            }
        }

        // retourner seulement les mails récupérés (validés ou refusé)
        return $aArgs['idsToRetrieve'];
    }

    public static function getDossier($aArgs)
    {
        if (strtolower($aArgs['config']['data']['ixbusDirectConnection']) == 'false') {
            $url = $aArgs['config']['data']['url'];
            $params = [
                'flowName' => 'S_IXPARAPHEUR_GET_DOSSIER',
                'flowType' => 'EAII',
                'enforceAsString' => 'false',
                'actionJSON' => 'launch',
                'id_dossier' => $aArgs['folderId']
            ];

            $curlResponse = CurlModel::exec([
                'url' => $url . http_build_query($params),
                'headers' => ['IXBUS_API:' . $aArgs['tokenAPI']],
                'method' => 'GET'
            ]);

        } else {
            $curlResponse = CurlModel::exec([
                'url' => rtrim($aArgs['config']['data']['url'], '/') . '/api/parapheur/v1/dossier/' . $aArgs['folderId'],
                'headers' => ['content-type:application/json', 'IXBUS_API:' . $aArgs['tokenAPI']],
                'method' => 'GET'
            ]);
        }
        if (!empty($curlResponse['response']['error'])) {
            return ['error' => $curlResponse['response']['message']];
        }

        return ['data' => $curlResponse['response']['payload']];
    }

    public static function getDocument($aArgs)
    {
        if (strtolower($aArgs['config']['data']['ixbusDirectConnection']) == 'false') {
            $url = $aArgs['config']['data']['url'];
            $params = [
                'flowName' => 'S_IXPARAPHEUR_GET_DOCUMENT',
                'flowType' => 'EAII',
                'enforceAsString' => 'false',
                'actionJSON' => 'launch',
                'id_document' => $aArgs['documentId']
            ];

            $curlResponse = CurlModel::exec([
                'url' => $url . http_build_query($params),
                'headers' => ['IXBUS_API:' . $aArgs['tokenAPI']],
                'method' => 'GET'
            ]);
            $binaryFile = $curlResponse['response']['binaire'];
        } else {
            $curlResponse = CurlModel::exec([
                'url' => rtrim($aArgs['config']['data']['url'], '/') . '/api/parapheur/v1/document/contenu/' . $aArgs['documentId'],
                'headers' => [
                    'Accept: application/zip',
                    'content-type:application/json',
                    'IXBUS_API:' . $aArgs['tokenAPI']],
                'method' => 'GET'
            ]);
            $binaryFile = base64_encode($curlResponse['response']);
        }

        return ['encodedDocument' => $binaryFile];
    }

    public static function processVisaWorkflow($aArgs = [])
    {
        $resIdMaster = $aArgs['res_id_master'] ?? $aArgs['res_id'];

        $attachments = AttachmentModel::get(['select' => ['count(1)'], 'where' => ['res_id_master = ?', 'status = ?'], 'data' => [$resIdMaster, 'FRZ']]);
        if ((count($attachments) < 2 && $aArgs['processSignatory']) || !$aArgs['processSignatory']) {
            $visaWorkflow = ListInstanceModel::get([
                'select' => ['listinstance_id', 'requested_signature'],
                'where' => ['res_id = ?', 'difflist_type = ?', 'process_date IS NULL'],
                'data' => [$resIdMaster, 'VISA_CIRCUIT'],
                'orderBY' => ['ORDER BY listinstance_id ASC']
            ]);

            if (!empty($visaWorkflow)) {
                foreach ($visaWorkflow as $listInstance) {
                    if ($listInstance['requested_signature']) {
                        // Stop to the first signatory user
                        if ($aArgs['processSignatory']) {
                            ListInstanceModel::update(['set' => ['signatory' => 'true', 'process_date' => 'CURRENT_TIMESTAMP'], 'where' => ['listinstance_id = ?'], 'data' => [$listInstance['listinstance_id']]]);
                        }
                        break;
                    }
                    ListInstanceModel::update(['set' => ['process_date' => 'CURRENT_TIMESTAMP'], 'where' => ['listinstance_id = ?'], 'data' => [$listInstance['listinstance_id']]]);
                }
            }
        }
    }

    public static function getSignatoryUserInfo($args = [])
    {
        $res = DatabaseModel::select([
            'select' => ['firstname', 'lastname', 'users.id'],
            'table' => ['listinstance', 'users'],
            'left_join' => ['listinstance.item_id = users.id'],
            'where' => ['res_id = ?', 'process_date is null', 'difflist_type = ?'],
            'data' => [$args['resId'], 'VISA_CIRCUIT']
        ])[0];

        return $res;
    }
}
