<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Pastell Controller
 * @author pierreyvon.bezert@edissyum.com
 */

namespace ExternalSignatoryBook\controllers;

use SrcCore\models\CoreConfigModel;
use Attachment\models\AttachmentModel;
use Attachment\models\AttachmentTypeModel;
use Convert\controllers\ConvertPdfController;
use Convert\models\AdrModel;
use Docserver\models\DocserverModel;
use Entity\models\ListInstanceModel;
use Resource\models\ResModel;
use SrcCore\models\CurlModel;
use SrcCore\models\DatabaseModel;
use User\models\UserModel;
use Docserver\models\DocserverTypeModel;
use Resource\controllers\StoreController;


/**
 * @codeCoverageIgnore
 */
class PastellController
{
    public static function getIParapheurParams($aArgs)
    {

        $aArgs['api_url'] = rtrim($aArgs['config']['data']['url'], '/') . '/api/v2';

        // Vérification que l'entité pastell est bien accessible par le compte de web service
        $response = CurlModel::exec([
            'url' => $aArgs['api_url'] . '/entite',
            'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
            'method' => 'GET'
        ]);

        if (!empty($response['error-message'])) {
            return ["error" => $response['error-message']];
        }

        $id_e = null;
        foreach ($response["response"] as $entite) {
            if ($entite['denomination'] == $aArgs['config']['data']['entite']) {
                $aArgs['id_e'] = $entite['id_e'];
            }
            if ($entite['id_e'] == $aArgs['config']['data']['entite']) {
                $aArgs['id_e'] = $entite['id_e'];
            }
        }


        if (is_null($aArgs['id_e']))
            return ["error" => "L'entite pastell '" . $aArgs['config']['data']['entite'] . " n'existe pas"];

        // Récupération de l'identifiant du connecteur IParapheur
        $response = CurlModel::exec([
            'url' => $aArgs['api_url'] . '/entite/' . $aArgs['id_e'] . '/connecteur',
            'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
            'method' => 'GET'
        ]);

        if (!empty($response['error-message'])) {
            return ["error" => $response['error-message']];
        }

        $id_ce = null;
        foreach ($response["response"] as $connecteur)
            if ($connecteur['id_connecteur'] == 'iParapheur') {
                $aArgs['id_ce'] = $connecteur['id_ce'];
            }

        if (is_null($aArgs['id_ce']))
            return ["error" => "L'identifiant du connecteur iparapheur n'a pas été trouvé."];

        // Vérification du type de dossier IParapheur

        $response = CurlModel::exec([
            'url' => $aArgs['api_url'] . '/entite/' . $aArgs['id_e'] . '/connecteur/' . $aArgs['id_ce'] . '/externalData/iparapheur_type',
            'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
            'method' => 'GET'
        ]);

        if (!empty($response['error-message'])) {
            return ["error" => $response['error']];
        }

        if (empty($response)) {
            return ["error" => "Aucun type de dossier n'a été remonté. Veuillez vérifier la disponibilité du serveur IParapheur"];
        }

        $typeOK = false;
        foreach ($response["response"] as $type)
            if ($type == $aArgs['config']['data']['defaultType']) {
                $typeOK = true;
            }

        if (!$typeOK)
            return ["error" => "Le type de dossier '" . $aArgs['config']['data']['defaultType'] . " n'existe pas"];

        return $aArgs;
    }

    public static function createDossier($aArgs) {
        $config = $aArgs['config'];

        // Création du dossier (nécessaire pour récupérer les sous-types de dossiers)
        // le ws post ne marche pas avec un header content-type:application/json
        // il faut utiliser queryParams => $bodyData ou 'headers'

        $curlResponse = CurlModel::exec([
            'url' => $aArgs['api_url'] . '/entite/' . $aArgs['id_e'] . '/document',
            'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
            'headers' => ['content-type:application/json'],
            'method' => 'POST',
            'queryParams' => ['type' => $aArgs['config']['data']['pastellType']],
            'body' => json_encode([])
        ]);

        if ($curlResponse['code'] != '201') {
            if (!empty($curlResponse['response']['error-message'])) {
                $errors = $curlResponse['response']['error-message'];
            } else {
                $errors = $curlResponse['error'];
            }
            if (empty($errors)) {
                $errors = 'An error occured. Please check your configuration file.';
            }
            return ["error" => $errors];
        }

        $id_d = $curlResponse['response']['id_d'];

        // Vérification du sous-type de dossier IParapheur

        $response = CurlModel::exec([
            'url' => $aArgs['api_url'] . '/entite/' . $aArgs['id_e'] . '/document/' . $id_d . '/externalData/iparapheur_sous_type',
            'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
            'method' => 'GET'
        ]);

        if (!empty($response['error-message'])) {
            return ["error" => $response['error']];
        }

        $sousTypes = array();
        foreach ($response['response'] as $item)
            array_push($sousTypes, $item);

        if (in_array($aArgs['signatory'], $sousTypes)) {
            $sousType = $aArgs['signatory'];
        } elseif (in_array($aArgs['config']['data']['defaultSousType'], $sousTypes)) {
            $sousType = $aArgs['config']['data']['defaultSousType'];
        } else {
            return ["error" => "Les sous-types " . $aArgs['signatory'] . " et " . $aArgs['config']['data']['defaultSousType'] . " n'existent pas."];
        }

        // Renseignement des metadonnees du dossier
        $mainResource = ResModel::getById(['resId' => $aArgs['resIdMaster'], 'select' => ['subject', 'process_limit_date']]);
        $dossierTitre = $mainResource['subject'] . ' - Référence: ' . $aArgs['resIdMaster'];


        if (empty($mainResource['process_limit_date'])) {
            $processLimitDate = $mainResource['process_limit_date'] = date('Y-m-d', strtotime(date("Y-m-d") . ' + 14 days'));
        } else {
            $processLimitDateTmp = explode(" ", $mainResource['process_limit_date']);
            $processLimitDate = $processLimitDateTmp[0];
        }

        $bodyDataDocument = array(
            'libelle' => $dossierTitre,
            'date_limite' => $processLimitDate,
            'has_date_limite' => 'on'
        );

        if (isset($config['data']['metadata'])) {
            foreach ($config['data']['metadata']->rules as $modelElements) {
                $type = (string)$modelElements->type;
                if (strtolower($type) == 'text') {
                    $value = (string)$modelElements->value;
                    $title .= $value;
                } else if (strtolower($type) == 'database') {
                    $tables = [];
                    $whereArray = [];
                    $select = (string)$modelElements->select;
                    $column = (string)$modelElements->column;
                    $table = (string)$modelElements->table;
                    $left_join = (string)$modelElements->left_join;
                    $res_id_column = (string)$modelElements->res_id_column;
                    $where = (string)$modelElements->where;

                    foreach (explode(',', $table) as $_ta) {
                        $tables[] = trim($_ta);
                    }

                    if (!empty($where)) {
                        $whereArray = [$where];
                    }
                    array_push($whereArray, $res_id_column . ' = ?');

                    $res = DatabaseModel::select([
                        'select' => [$select],
                        'table' => $tables,
                        'left_join' => $left_join ? [$left_join] : [],
                        'where' => $whereArray,
                        'data' => [$aArgs['resIdMaster']]
                    ]);
                    if ($res and count($res)) {
                        $bodyDataDocument[(string)$modelElements->pastell_field] = $res[0][$column];
                    }
                }
            }
        }

        $curlResponse = CurlModel::exec([
            'url' => $aArgs['api_url'] . '/entite/' . $aArgs['id_e'] . '/document/' . $id_d,
            'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'method' => 'PATCH',
            'body' => http_build_query($bodyDataDocument)
        ]);

        if (!in_array($curlResponse['code'], [200, 201])) {
            if (!empty($curlResponse['response']['error-message'])) {
                $errors = $curlResponse['response']['error-message'];
            } else {
                $errors = $curlResponse['error'];
            }
            if (empty($errors)) {
                $errors = 'An error occured. Please check your configuration file.';
            }
            return ["error" => $errors];
        }

        $bodyData = array(
            'iparapheur_sous_type' => $sousType
        );

        $curlResponse = CurlModel::exec([
            'url' => $aArgs['api_url'] . '/entite/' . $aArgs['id_e'] . '/document/' . $id_d . '/externalData/iparapheur_sous_type',
            'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'method' => 'PATCH',
            'body' => http_build_query($bodyData)
        ]);

        if (!in_array($curlResponse['code'], [200, 201])) {
            if (!empty($curlResponse['response']['error-message'])) {
                $errors = $curlResponse['response']['error-message'];
            } else {
                $errors = $curlResponse['error'];
            }
            if (empty($errors)) {
                $errors = 'An error occured. Please check your configuration file.';
            }
            return ["error" => $errors];
        }
        return ['id_d' => $id_d, 'sousType' => $sousType];
    }

    public static function sendDatas($aArgs)
    {
        $aArgs = self::getIParapheurParams($aArgs);

        $api_url = rtrim($aArgs['config']['data']['url'], '/') . '/api/v2';
        $aArgs['apiUrl'] = $api_url;

        // Maarch
        // Retrieve the annexes of the attachment to sign (other attachments and the original document)
        $annexes = [];
        $annexes['letterbox'] = ResModel::get([
            'select' => ['res_id', 'path', 'filename', 'docserver_id', 'format', 'category_id', 'external_id', 'integrations', 'subject'],
            'where' => ['res_id = ?'],
            'data' => [$aArgs['resIdMaster']]
        ]);

        if (!empty($annexes['letterbox'][0]['docserver_id'])) {
            $adrMainInfo = ConvertPdfController::getConvertedPdfById(['resId' => $aArgs['resIdMaster'], 'collId' => 'letterbox_coll']);
            $letterboxPath = DocserverModel::getByDocserverId(['docserverId' => $adrMainInfo['docserver_id'], 'select' => ['path_template']]);
            $annexes['letterbox'][0]['filePath'] = $letterboxPath['path_template'] . str_replace('#', '/', $adrMainInfo['path']) . $adrMainInfo['filename'];
        }

        $attachments = AttachmentModel::get([
            'select' => ['res_id', 'docserver_id', 'path', 'filename', 'format', 'attachment_type', 'fingerprint', 'title'],
            'where' => ['res_id_master = ?', 'attachment_type not in (?)', "status NOT IN ('DEL','OBS', 'FRZ', 'TMP', 'SEND_MASS')", "in_signature_book = 'true'"],
            'data' => [$aArgs['resIdMaster'], ['signed_response']]
        ]);

        $attachmentTypes = AttachmentTypeModel::get(['select' => ['type_id', 'signable']]);
        $attachmentTypes = array_column($attachmentTypes, 'signable', 'type_id');
        foreach ($attachments as $key => $value) {
            if (!$attachmentTypes[$value['attachment_type']]) {
                $adrInfo = AdrModel::getConvertedDocumentById(['resId' => $value['res_id'], 'collId' => 'attachments_coll', 'type' => 'PDF']);
                $annexeAttachmentPath = DocserverModel::getByDocserverId(['docserverId' => $adrInfo['docserver_id'], 'select' => ['path_template', 'docserver_type_id']]);
                $value['filePath'] = $annexeAttachmentPath['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $adrInfo['path']) . $adrInfo['filename'];
                $docserverType = DocserverTypeModel::getById(['id' => $annexeAttachmentPath['docserver_type_id'], 'select' => ['fingerprint_mode']]);
                unset($attachments[$key]);
                $annexes['attachments'][] = $value;
            }
        }
        // END annexes
        $attachmentToFreeze = [];
        foreach ($attachments as $attachment) {
            $resId = $attachment['res_id'];
            $collId = 'attachments_coll';

            $res = self::createDossier($aArgs);
            $id_d = $res['id_d'];
            $sousType = $res['sousType'];

            $response = PastellController::uploadFile([
                'resId' => $resId,
                'collId' => $collId,
                'resIdMaster' => $aArgs['resIdMaster'],
                'annexes' => $annexes,
                'sousType' => $sousType,
                'config' => $aArgs['config'],
                'id_d' => $id_d,
                'id_e' => $aArgs['id_e'],
                'id_ce' => $aArgs['id_ce'],
                'title' => $attachment['title'],
            ]);

            if (!empty($response['error'])) {
                return $response;
            } else {
                $attachmentToFreeze[$collId][$resId] = $id_d;
            }
        }

        if (empty($attachmentToFreeze)) {
            $res = self::createDossier($aArgs);
            $id_d = $res['id_d'];
            $sousType = $res['sousType'];
            // Send main document if in signature book
            if (!empty($annexes['letterbox'][0]) && count($attachments) == 0) {
                $mainDocumentIntegration = json_decode($annexes['letterbox'][0]['integrations'], true);
                $externalId = json_decode($annexes['letterbox'][0]['external_id'], true);
                if ($mainDocumentIntegration['inSignatureBook'] && empty($externalId['signatureBookId'])) {
                    $resId = $annexes['letterbox'][0]['res_id'];
                    $title = $annexes['letterbox'][0]['subject'];
                    $collId = 'letterbox_coll';
                    unset($annexes['letterbox']);

                    $response = PastellController::uploadFile([
                        'resId' => $resId,
                        'collId' => $collId,
                        'resIdMaster' => $aArgs['resIdMaster'],
                        'annexes' => $annexes,
                        'sousType' => $sousType,
                        'config' => $aArgs['config'],
                        'id_d' => $id_d,
                        'id_e' => $aArgs['id_e'],
                        'id_ce' => $aArgs['id_ce'],
                        'title' => $title
                    ]);

                    if (!empty($response['error'])) {
                        return $response;
                    } else {
                        $attachmentToFreeze[$collId][$resId] = $id_d;
                    }
                }
            }
        }
        return ['sended' => $attachmentToFreeze];
    }

    public static function uploadFile($aArgs)
    {
        $adrInfo = ConvertPdfController::getConvertedPdfById(['resId' => $aArgs['resId'], 'collId' => $aArgs['collId']]);
        if (empty($adrInfo['docserver_id']) || strtolower(pathinfo($adrInfo['filename'], PATHINFO_EXTENSION)) != 'pdf') {
            return ['error' => 'Document ' . $aArgs['resIdMaster'] . ' is not converted in pdf'];
        }
        $attachmentPath = DocserverModel::getByDocserverId(['docserverId' => $adrInfo['docserver_id'], 'select' => ['path_template']]);
        $attachmentFilePath = $attachmentPath['path_template'] . str_replace('#', '/', $adrInfo['path']) . $adrInfo['filename'];

        // Envoi du document principal
        $bodyData = array(
            'file_name' => 'Document principal.' . pathinfo($attachmentFilePath)['extension'],
            'file_content' => file_get_contents($attachmentFilePath)
        );

        $curlResponse = CurlModel::exec([
            'url' =>
                rtrim($aArgs['config']['data']['url'], '/') . '/api/v2' . '/entite/' .
                $aArgs['id_e'] . '/document/' . $aArgs['id_d'] . '/file/document',
            'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'method' => 'POST',
            'body' => http_build_query($bodyData)
        ]);

        if (!in_array($curlResponse['code'], [200, 201])) {
            if (!empty($curlResponse['response']['error-message'])) {
                $errors = $curlResponse['response']['error-message'];
            } else {
                $errors = $curlResponse['error'];
            }
            if (empty($errors)) {
                $errors = 'An error occured. Please check your configuration file.';
            }
            return ["error" => '434 ' . $errors];
        }

        $crtAttachment = 0;

        // Envoi du fichier original si disponible
        if (!empty($aArgs['annexes']['letterbox'][0]['filePath'])) {
            $bodyData = array(
                'file_name' => 'Courrier arrivé.' . pathinfo($aArgs['annexes']['letterbox'][0]['filePath'])['extension'],
                'file_content' => file_get_contents($aArgs['annexes']['letterbox'][0]['filePath'])
            );

            $curlResponse = CurlModel::exec([
                'url' =>
                    rtrim($aArgs['config']['data']['url'], '/') . '/api/v2' . '/entite/' .
                    $aArgs['id_e'] . '/document/' . $aArgs['id_d'] . '/file/autre_document_attache/' . $crtAttachment,
                'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'method' => 'POST',
                'body' => http_build_query($bodyData)
            ]);

            if (!in_array($curlResponse['code'], [200, 201])) {
                if (!empty($curlResponse['response']['error-message'])) {
                    $errors = $curlResponse['response']['error-message'];
                } else {
                    $errors = $curlResponse['error'];
                }
                if (empty($errors)) {
                    $errors = 'An error occured. Please check your configuration file.';
                }
                return ["error" => $errors];
            }
            $crtAttachment += 1;
        }

        if (!empty($aArgs['annexes']['attachments'])) {
            for ($j = 0; $j < count($aArgs['annexes']['attachments']); $j++) {
                // Envoi des PJs annexes
                if (!empty($aArgs['annexes']['letterbox'][0]['filePath'])) {
                    $bodyData = array(
                        'file_name' => 'Pièce jointe n°' . ($j + 1) . '.' . pathinfo($aArgs['annexes']['attachments'][$j]['filePath'])['extension'],
                        'file_content' => file_get_contents($aArgs['annexes']['attachments'][$j]['filePath'])
                    );

                    $curlResponse = CurlModel::exec([
                        'url' =>
                            rtrim($aArgs['config']['data']['url'], '/') . '/api/v2' . '/entite/' .
                            $aArgs['id_e'] . '/document/' . $aArgs['id_d'] . '/file/autre_document_attache/' . $crtAttachment,
                        'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
                        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                        'method' => 'POST',
                        'body' => http_build_query($bodyData)
                    ]);

                    if (!in_array($curlResponse['code'], [200, 201])) {
                        if (!empty($curlResponse['response']['error-message'])) {
                            $errors = $curlResponse['response']['error-message'];
                        } else {
                            $errors = $curlResponse['error'];
                        }
                        if (empty($errors)) {
                            $errors = 'An error occured. Please check your configuration file.';
                        }
                        return ["error" => '500:' . $errors];
                    }
                    $crtAttachment += 1;
                }
            }
        }

        if (!in_array($curlResponse['code'], [200, 201])) {
            if (!empty($curlResponse['response']['error-message'])) {
                $errors = $curlResponse['response']['error-message'];
            } else {
                $errors = $curlResponse['error'];
            }
            if (empty($errors)) {
                $errors = 'An error occured. Please check your configuration file.';
            }
            return ["error" => $errors];
        }

        foreach ($aArgs['config']['data']['preActions'] as $preAction) {
            $curlResponse = CurlModel::exec([
                'url' =>
                    rtrim($aArgs['config']['data']['url'], '/') . '/api/v2' . '/entite/' .
                    $aArgs['id_e'] . '/document/' . $aArgs['id_d'] . '/action/' . (string)$preAction,
                'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'method' => 'POST',
            ]);

            if (!in_array($curlResponse['code'], [200, 201])) {
                if (!empty($curlResponse['response']['error-message'])) {
                    $errors = $curlResponse['response']['error-message'];
                } else {
                    $errors = $curlResponse['error'];
                }
                if (empty($errors)) {
                    $errors = 'An error occured. Please check your configuration file.';
                }
                echo $errors;
            }
        }

        PastellController::processVisaWorkflow(['res_id_master' => $aArgs['resIdMaster'], 'processSignatory' => false]);
        return ['success' => $aArgs['id_d']];
    }

    public static function download($aArgs)
    {
        $tmpFile = CoreConfigModel::getTmpPath() . rand() . '.pdf';
        echo $tmpFile . PHP_EOL;

        $response = CurlModel::exec([
            'url' => $aArgs['api_url'] . '/entite/' . $aArgs['id_e'] . '/document/' . $aArgs['id_d'] . '/file/document',
            'method' => 'GET',
            'filepath' => $tmpFile,
            'basicAuth' => [
                'user' => $aArgs['config']['data']['userId'],
                'password' => $aArgs['config']['data']['password'],
            ],
            'noLogs' => true,
            'headers' => [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADER => false
            ]
        ]);

        $fileContent = base64_encode(file_get_contents($tmpFile));

        if (!empty($response['error-message'])) {
            return ["error" => $response['error-message']];
        }
        return ['b64FileContent' => $fileContent];
    }


    public static function retrieveSignedMails($aArgs)
    {
        $config = $aArgs['config'];
        $aArgs = self::getIParapheurParams($aArgs);
        $api_url = rtrim($aArgs['config']['data']['url'], '/') . '/api/v2';
        $version = $aArgs['version'];
        $noteContent = '';
        $aArgs['idsToRetrieve']['error'] = [$version => []];
        foreach ($aArgs['idsToRetrieve'][$version] as $resId => $value) {
            if (!empty($value['external_id'])) {
                echo "Processing folder " . $value['external_id'] . PHP_EOL;
                $curlResponse = CurlModel::exec([
                    'url' => $api_url . '/entite/' . $aArgs['id_e'] . '/document/' . $value['external_id'],
                    'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
                    'method' => 'GET'
                ])['response'];

                if ($curlResponse['last_action']['action'] == $config['data']['visaState']) {
                    echo "Folder " . $value['external_id'] . " is in iparapheur" . PHP_EOL;
                    continue;
                } elseif ($curlResponse['last_action']['action'] == $config['data']['refusedVisa'] ||
                    $curlResponse['last_action']['action'] == $config['data']['refusedSign']) {
                    echo "Folder " . $value['external_id'] . " has been refused" . PHP_EOL;
                    $noteContent .= $curlResponse['last_action']['message'] . PHP_EOL;
                    $aArgs['idsToRetrieve'][$version][$resId]['status'] = 'refused';
                    $aArgs['idsToRetrieve'][$version][$resId]['notes'][] = ['content' => $noteContent];

                    foreach ($aArgs['config']['data']['postActions'] as $postAction) {
                        $curlResponse = CurlModel::exec([
                            'url' =>
                                rtrim($aArgs['config']['data']['url'], '/') . '/api/v2' . '/entite/' .
                                $aArgs['id_e'] . '/document/' . $value['external_id'] . '/action/' . (string)$postAction,
                            'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
                            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                            'method' => 'POST',
                        ]);

                        if (!in_array($curlResponse['code'], [200, 201])) {
                            if (!empty($curlResponse['response']['error-message'])) {
                                $errors = $curlResponse['response']['error-message'];
                            } else {
                                $errors = $curlResponse['error'];
                            }
                            if (empty($errors)) {
                                $errors = 'An error occured. Please check your configuration file.';
                            }
                            echo $errors;
                        }
                    }
                } elseif ($curlResponse['last_action']['action'] == $config['data']['signState']) {
                    echo "Folder " . $value['external_id'] . " has been signed" . PHP_EOL;
                    $response = CurlModel::exec([
                        'url' => $api_url . '/entite/' . $aArgs['id_e'] . '/document/' . $value['external_id'] . '/externalData/iparapheur_sous_type',
                        'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
                        'method' => 'GET'
                    ])['response'];

                    if (!empty($response['error-message'])) {
                        $aArgs['idsToRetrieve']['error'][$version][$resId] = $response['error-message'];
                        unset($aArgs['idsToRetrieve'][$version][$resId]);
                        continue;
                    }
                    $aArgs['id_d'] = $value['external_id'];
                    $response = PastellController::download($aArgs);
                    if (!empty($response['error'])) {
                        return ['error' => $response['error']];
                    }

                    $aArgs['idsToRetrieve'][$version][$resId]['status'] = 'validated';
                    $aArgs['idsToRetrieve'][$version][$resId]['format'] = 'pdf';
                    $aArgs['idsToRetrieve'][$version][$resId]['encodedFile'] = $response['b64FileContent'];
                    $aArgs['idsToRetrieve'][$version][$resId]['noteContent'] = $noteContent;

                    foreach ($aArgs['config']['data']['postActions'] as $postAction) {
                        $curlResponse = CurlModel::exec([
                            'url' =>
                                rtrim($aArgs['config']['data']['url'], '/') . '/api/v2' . '/entite/' .
                                $aArgs['id_e'] . '/document/' . $value['external_id'] . '/action/' . (string)$postAction,
                            'basicAuth' => ['user' => $aArgs['config']['data']['userId'], 'password' => $aArgs['config']['data']['password']],
                            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                            'method' => 'POST',
                        ]);

                        if (!in_array($curlResponse['code'], [200, 201])) {
                            if (!empty($curlResponse['response']['error-message'])) {
                                $errors = $curlResponse['response']['error-message'];
                            } else {
                                $errors = $curlResponse['error'];
                            }
                            if (empty($errors)) {
                                $errors = 'An error occured. Please check your configuration file.';
                            }
                            echo $errors;
                        }
                    }
                    PastellController::processVisaWorkflow(['res_id_master' => $value['res_id_master'], 'res_id' => $value['res_id'], 'processSignatory' => true]);
                } else {
                    $aArgs['idsToRetrieve'][$version][$resId]['status'] = 'waiting';
                }
            } else {
                echo 'ExternalId is empty';
            }
        }
        return $aArgs['idsToRetrieve'];
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
}
