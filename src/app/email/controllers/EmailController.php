<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Email Controller
 * @author dev@maarch.org
 */

namespace Email\controllers;

use Attachment\controllers\AttachmentController;
use Attachment\models\AttachmentModel;
use Attachment\models\AttachmentTypeModel;
use Configuration\models\ConfigurationModel;
use Convert\models\AdrModel;
use Docserver\models\DocserverModel;
use Docserver\models\DocserverTypeModel;
use Email\models\EmailModel;
use Entity\models\EntityModel;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use History\models\HistoryModel;
use MessageExchange\models\MessageExchangeModel;
use Note\controllers\NoteController;
use Note\models\NoteEntityModel;
use Note\models\NoteModel;
use PHPMailer\PHPMailer\PHPMailer;
use Resource\controllers\ResController;
use Resource\controllers\ResourceListController;
use Resource\controllers\StoreController;
use Resource\models\ResModel;
use Respect\Validation\Validator;
use Slim\Http\Request;
use Slim\Http\Response;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\PasswordModel;
use SrcCore\models\TextFormatModel;
use SrcCore\models\ValidatorModel;
use User\models\UserModel;
// EDISSYUM - AMO01 - Rajout d'une option -> mettre PJ dans mail via liens éphémères
use SrcCore\models\DatabaseModel;
use Parameter\controllers\ParameterController;
// END EDISSYUM - AMO01

// EDISSYUM - OBR01 - Rajout du paramètre force_admin_mail_from
use Parameter\models\ParameterModel;
// END EDISSYUM - OBR01 - Rajout du paramètre force_admin_mail_from

class EmailController
{
    public function send(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'sendmail', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        $isSent = EmailController::createEmail(['userId' => $GLOBALS['id'], 'data' => $body]);

        if (!empty($isSent['errors'])) {
            $httpCode = empty($isSent['code']) ? 400 : $isSent['code'];
            return $response->withStatus($httpCode)->withJson(['errors' => $isSent['errors']]);
        }

        return $response->withJson(['id' => $isSent]);
    }

    public static function createEmail(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId', 'data']);
        ValidatorModel::intVal($args, ['userId']);
        ValidatorModel::arrayType($args, ['data', 'options']);

        $check = EmailController::controlCreateEmail(['userId' => $args['userId'], 'data' => $args['data'], 'isAcknowledgementReceipt' => !empty($args['options']['acknowledgementReceiptId'])]);
        if (!empty($check['errors'])) {
            return ['errors' => $check['errors'], 'code' => $check['code']];
        }

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_email_server', 'select' => ['value']]);
        $configuration = json_decode($configuration['value'], true);
        if (empty($configuration)) {
            return ['errors' => 'Configuration is missing'];
        }

        if (!empty($configuration['useSMTPAuth'])) {
            $args['data']['sender']['email'] = $configuration['from'];
        }


        $id = EmailModel::create([
            'userId'                => $args['userId'],
            'sender'                => empty($args['data']['sender']) ? '{}' : json_encode($args['data']['sender']),
            'recipients'            => empty($args['data']['recipients']) ? '[]' : json_encode($args['data']['recipients']),
            'cc'                    => empty($args['data']['cc']) ? '[]' : json_encode($args['data']['cc']),
            'cci'                   => empty($args['data']['cci']) ? '[]' : json_encode($args['data']['cci']),
            'object'                => empty($args['data']['object']) ? null : $args['data']['object'],
            'body'                  => empty($args['data']['body']) ? null : $args['data']['body'],
            'document'              => empty($args['data']['document']) ? null : json_encode($args['data']['document']),
            'isHtml'                => $args['data']['isHtml'] ? 'true' : 'false',
            'status'                => $args['data']['status'] == 'DRAFT' ? 'DRAFT' : 'WAITING',
            'messageExchangeId'     => empty($args['data']['messageExchangeId']) ? null : $args['data']['messageExchangeId']
        ]);

        $isSent = ['success' => 'success'];
        if ($args['data']['status'] != 'DRAFT') {
            if ($args['data']['status'] == 'EXPRESS') {
                $isSent = EmailController::sendEmail(['emailId' => $id, 'userId' => $args['userId']]);
                if (!empty($isSent['success'])) {
                    EmailModel::update(['set' => ['status' => 'SENT', 'send_date' => 'CURRENT_TIMESTAMP'], 'where' => ['id = ?'], 'data' => [$id]]);
                } else {
                    EmailModel::update(['set' => ['status' => 'ERROR', 'send_date' => 'CURRENT_TIMESTAMP'], 'where' => ['id = ?'], 'data' => [$id]]);
                }
                if (PrivilegeController::hasPrivilege(['privilegeId' => 'admin_email_server', 'userId' => $GLOBALS['id']])) {
                    $online = !empty($isSent['success']) ? 'true' : 'false';
                    ConfigurationModel::update([
                        'postSet' => ['value' => "jsonb_set(value, '{online}', '{$online}')"],
                        'where'   => ['privilege = ?'],
                        'data'    => ['admin_email_server']
                    ]);
                }
            } else {
                $customId = CoreConfigModel::getCustomId();
                if (empty($customId)) {
                    $customId = 'null';
                }
                $encryptKey = CoreConfigModel::getEncryptKey();
                $options = empty($args['options']) ? '' : serialize($args['options']);
                exec("php src/app/email/scripts/sendEmail.php {$customId} {$id} {$args['userId']} \"{$encryptKey}\" '{$options}' '{$args['data']['receivedReceipt']}' '{$args['data']['pjLinkChecked']}' > /dev/null &"); // EDISSYUM - NCH01 Ajout d'un accusé de lecture ajout receivedReceipt. AMO01 - Rajout d'une option -> mettre PJ dans mail via liens éphémères. OBR01 - Fix si l'encypt key contient des '
            }
            if (!empty($isSent)) {
                $info = _EMAIL_ADDED ;

                if (!empty($configuration['useSMTPAuth'])) {
                    $info .= ' : ' . _SENDER_EMAIL_REPLACED_SMTP_SENDER;
                }

                HistoryController::add([
                    'tableName' => 'emails',
                    'recordId'  => $id,
                    'eventType' => 'ADD',
                    'eventId'   => 'emailCreation',
                    'info'      => $info
                ]);

                if (!empty($args['data']['document']['id'])) {
                    HistoryController::add([
                        'tableName' => 'res_letterbox',
                        'recordId'  => $args['data']['document']['id'],
                        'eventType' => 'ADD',
                        'eventId'   => 'emailCreation',
                        'info'      => $info
                    ]);
                }
            }
        } else {
            HistoryController::add([
                'tableName'    => 'emails',
                'recordId'     => $id,
                'eventType'    => 'ADD',
                'eventId'      => 'emailDraftCreation',
                'info'         => _EMAIL_DRAFT_SAVED
            ]);

            if (!empty($args['data']['document']['id'])) {
                HistoryController::add([
                    'tableName' => 'res_letterbox',
                    'recordId'  => $args['data']['document']['id'],
                    'eventType' => 'ADD',
                    'eventId'   => 'emailDraftCreation',
                    'info'      => _EMAIL_DRAFT_SAVED
                ]);
            }
        }

        if (!empty($isSent['errors'])) {
            return $isSent;
        }

        return $id;
    }

    public function getById(Request $request, Response $response, array $args)
    {
        $rawEmail = EmailModel::getById(['id' => $args['id']]);
        $document = json_decode($rawEmail['document'], true);

        if (!empty($document['id']) && !ResController::hasRightByResId(['resId' => [$document['id']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        if (!empty($document['isLinked'])) {
            $resource = ResModel::getById(['resId' => $document['id'], 'select' => ['alt_identifier', 'subject', 'typist', 'format', 'filesize', 'version']]);
            $size = null;
            if (empty($document['original'])) {
                $convertedResource = AdrModel::getDocuments([
                    'select'  => ['docserver_id', 'path', 'filename'],
                    'where'   => ['res_id = ?', 'type in (?)', 'version = ?'],
                    'data'    => [$document['id'], ['PDF', 'SIGN'], $resource['version']],
                    'orderBy' => ["type='SIGN' DESC"],
                    'limit'   => 1
                ]);

                if (!empty($convertedResource[0])) {
                    $docserver = DocserverModel::getByDocserverId(['docserverId' => $convertedResource[0]['docserver_id'], 'select' => ['path_template']]);
                    $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $convertedResource[0]['path']) . $convertedResource[0]['filename'];
                    if (file_exists($pathToDocument)) {
                        $size = StoreController::getFormattedSizeFromBytes(['size' => filesize($pathToDocument)]);
                    }
                }
            } else {
                $size = StoreController::getFormattedSizeFromBytes(['size' => $resource['filesize']]);
            }

            $document['resource'] = [
                'id'                => $document['id'],
                'chrono'            => $resource['alt_identifier'],
                'label'             => $resource['subject'],
                'creator'           => UserModel::getLabelledUserById(['id' => $resource['typist']]),
                'format'            => $resource['format'],
                'size'              => $size
            ];
        }
        if (!empty($document['attachments'])) {
            foreach ($document['attachments'] as $key => $attachment) {
                $attachmentInfo = AttachmentModel::getById(['id' => $attachment['id'], 'select' => ['title', 'format', 'filesize']]);

                $size = null;
                if (empty($attachment['original'])) {
                    $convertedResource = AdrModel::getAttachments([
                        'select'  => ['docserver_id', 'path', 'filename'],
                        'where'   => ['res_id = ?', 'type = ?'],
                        'data'    => [$attachment['id'], 'PDF'],
                        'orderBy' => ["type='SIGN' DESC"],
                        'limit'   => 1
                    ]);

                    if (!empty($convertedResource[0])) {
                        $docserver = DocserverModel::getByDocserverId(['docserverId' => $convertedResource[0]['docserver_id'], 'select' => ['path_template']]);
                        $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $convertedResource[0]['path']) . $convertedResource[0]['filename'];
                        if (file_exists($pathToDocument)) {
                            $size = StoreController::getFormattedSizeFromBytes(['size' => filesize($pathToDocument)]);
                            $document['attachments'][$key]['format'] = 'PDF';
                        }
                    }
                } else {
                    $document['attachments'][$key]['format'] = $attachmentInfo['format'];
                    $size = StoreController::getFormattedSizeFromBytes(['size' => $attachmentInfo['filesize']]);
                }

                $document['attachments'][$key]['label'] = $attachmentInfo['title'];
                $document['attachments'][$key]['size'] = $size;
            }
        }
        if (!empty($document['notes'])) {
            $notes = NoteModel::get(['select' => ['id', 'note_text', 'user_id'], 'where' => ['id in (?)'], 'data' => [$document['notes']]]);
            $notes = array_column($notes, null, 'id');
            foreach ($document['notes'] as $key => $noteId) {
                $document['notes'][$key] = [
                    'id'        => $noteId,
                    'label'     => $notes[$noteId]['note_text'],
                    'typeLabel' => 'note',
                    'creator'   => UserModel::getLabelledUserById(['id' => $notes[$noteId]['user_id']]),
                    'format'    => 'pdf',
                    'size'      => null
                ];
            }
        }

        $sender = json_decode($rawEmail['sender'], true);
        $entityLabel = null;
        if (!empty($sender['entityId'])) {
            $entityLabel = EntityModel::getById(['select' => ['entity_label'], 'id' => $sender['entityId']]);
            $entityLabel = $entityLabel['entity_label'];
        }
        $sender['label'] = $entityLabel;

        $email = [
            'id'            => $rawEmail['id'],
            'sender'        => $sender,
            'recipients'    => json_decode($rawEmail['recipients'], true),
            'cc'            => json_decode($rawEmail['cc'], true),
            'cci'           => json_decode($rawEmail['cci'], true),
            'userId'        => $rawEmail['user_id'],
            'object'        => $rawEmail['object'],
            'body'          => $rawEmail['body'],
            'isHtml'        => $rawEmail['is_html'],
            'status'        => $rawEmail['status'],
            'creationDate'  => $rawEmail['creation_date'],
            'sendDate'      => $rawEmail['send_date'],
            'document'      => $document
        ];

        return $response->withJson($email);
    }

    public function update(Request $request, Response $response, array $args)
    {
        $body = $request->getParsedBody();

        $check = EmailController::controlCreateEmail(['userId' => $GLOBALS['id'], 'data' => $body]);
        if (!empty($check['errors'])) {
            return $response->withStatus($check['code'])->withJson(['errors' => $check['errors']]);
        }

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_email_server', 'select' => ['value']]);
        $configuration = json_decode($configuration['value'], true);
        if (empty($configuration)) {
            return ['errors' => 'Configuration is missing'];
        }

        if (!empty($configuration['useSMTPAuth'])) {
            $body['sender']['email'] = $configuration['from'];
        }


        EmailModel::update([
            'set' => [
                'sender'      => empty($body['sender']) ? '{}' : json_encode($body['sender']),
                'recipients'  => empty($body['recipients']) ? '[]' : json_encode($body['recipients']),
                'cc'          => empty($body['cc']) ? '[]' : json_encode($body['cc']),
                'cci'         => empty($body['cci']) ? '[]' : json_encode($body['cci']),
                'object'      => empty($body['object']) ? null : $body['object'],
                'body'        => empty($body['body']) ? null : $body['body'],
                'document'    => empty($body['document']) ? null : json_encode($body['document']),
                'is_html'     => $body['isHtml'] ? 'true' : 'false',
                'status'      => $body['status'] == 'DRAFT' ? 'DRAFT' : 'WAITING'
            ],
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        if ($body['status'] != 'DRAFT') {
            $customId = CoreConfigModel::getCustomId();
            if (empty($customId)) {
                $customId = 'null';
            }
            $encryptKey = CoreConfigModel::getEncryptKey();
            exec("php src/app/email/scripts/sendEmail.php {$customId} {$args['id']} {$GLOBALS['id']} '{$encryptKey}' > /dev/null &");

            HistoryController::add([
                'tableName' => 'emails',
                'recordId'  => $args['id'],
                'eventType' => 'ADD',
                'eventId'   => 'emailCreation',
                'info'      => _EMAIL_ADDED
            ]);

            if (!empty($args['data']['document']['id'])) {
                HistoryController::add([
                    'tableName' => 'res_letterbox',
                    'recordId'  => $args['data']['document']['id'],
                    'eventType' => 'ADD',
                    'eventId'   => 'emailCreation',
                    'info'      => _EMAIL_ADDED
                ]);
            }
        } else {
            $info = _EMAIL_UPDATED ;

            if (!empty($configuration['useSMTPAuth'])) {
                $info .= ' : ' . _SENDER_EMAIL_REPLACED_SMTP_SENDER;
            }

            HistoryController::add([
                'tableName'    => 'emails',
                'recordId'     => $args['id'],
                'eventType'    => 'UP',
                'eventId'      => 'emailModification',
                'info'         => $info
            ]);

            if (!empty($args['data']['document']['id'])) {
                HistoryController::add([
                    'tableName' => 'res_letterbox',
                    'recordId'  => $args['data']['document']['id'],
                    'eventType' => 'UP',
                    'eventId'   => 'emailModification',
                    'info'      => $info
                ]);
            }
        }

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        $email = EmailModel::getById(['select' => ['user_id', 'document'], 'id' => $args['id']]);
        if (empty($email)) {
            return $response->withStatus(400)->withJson(['errors' => 'Email does not exist']);
        }
        if ($email['user_id'] != $GLOBALS['id']) {
            return $response->withStatus(403)->withJson(['errors' => 'Email out of perimeter']);
        }

        EmailModel::delete([
            'where' => ['id = ?'],
            'data'  => [$args['id']]
        ]);

        HistoryController::add([
            'tableName'    => 'emails',
            'recordId'     => $args['id'],
            'eventType'    => 'DEL',
            'eventId'      => 'emailDeletion',
            'info'         => _EMAIL_REMOVED
        ]);

        if (!empty($email['document'])) {
            $document = json_decode($email['document'], true);

            HistoryController::add([
                'tableName' => 'res_letterbox',
                'recordId'  => $document['id'],
                'eventType' => 'DEL',
                'eventId'   => 'emailDeletion',
                'info'      => _EMAIL_REMOVED
            ]);
        }

        return $response->withStatus(204);
    }

    public function getByResId(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $queryParams = $request->getQueryParams();
        if (!empty($queryParams['limit']) && !Validator::intVal()->validate($queryParams['limit'])) {
            return $response->withStatus(400)->withJson(['errors' => 'Query limit is not an int value']);
        }

        // EDISSYUM - NCH01 Fix pour permettre au superadmin de voir les emails
        $superadmin_id = UserModel::get([
            'select' => ['id'],
            'where'  => ['user_id = ?'],
            'data'   => ['superadmin']
        ])[0];

        $where = ["document->>'id' = ?", "(status != 'DRAFT' or (status = 'DRAFT' and user_id IN (?)))"];
        if ($GLOBALS['id'] == $superadmin_id['id']){
            unset($where[1]);
        }
        // END EDISSYUM - NCH01

        $where = ["document->>'id' = ?", "(status != 'DRAFT' or (status = 'DRAFT' and user_id = ?))"];

        if (!empty($queryParams['type'])) {
            if (!Validator::stringType()->validate($queryParams['type'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Query type is not a string value']);
            }

            if ($queryParams['type'] == 'ar') {
                $where[] = "object LIKE '[AR]%'";
            } elseif ($queryParams['type'] == 'm2m') {
                $where[] = 'message_exchange_id is not null';
            } elseif ($queryParams['type'] == 'email') {
                $where[] = "(object NOT LIKE '[AR]%' OR object is null)";
                $where[] = 'message_exchange_id is null';
            }
        }

        $emails = EmailModel::get([
            'select' => ['*'],
            'where'  => $where,
            'data'   => [$args['resId'], $GLOBALS['id']],
            'limit'  => (int)$queryParams['limit']
        ]);

        foreach ($emails as $key => $email) {
            $emails[$key]['sender']     = json_decode($email['sender']);
            $emails[$key]['recipients'] = json_decode($email['recipients']);
            $emails[$key]['cc']         = json_decode($email['cc']);
            $emails[$key]['cci']        = json_decode($email['cci']);
            $emails[$key]['document']   = json_decode($email['document']);
        }

        return $response->withJson(['emails' => $emails]);
    }

    public function getAvailableEmails(Request $request, Response $response)
    {
        $availableEmails = EmailController::getAvailableEmailsByUserId(['userId' => $GLOBALS['id']]);

        return $response->withJson(['emails' => $availableEmails]);
    }

    public function getInitializationByResId(Request $request, Response $response, array $args)
    {
        if (!Validator::intVal()->validate($args['resId']) || !ResController::hasRightByResId(['resId' => [$args['resId']], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Document out of perimeter']);
        }

        $resource = ResModel::getById(['select' => ['filename', 'version', 'alt_identifier', 'subject', 'typist', 'format', 'filesize'], 'resId' => $args['resId']]);
        if (empty($resource)) {
            return $response->withStatus(400)->withJson(['errors' => 'Document does not exist']);
        }

        $document = [];
        if (!empty($resource['filename'])) {
            $convertedResource = AdrModel::getDocuments([
                'select'    => ['docserver_id', 'path', 'filename'],
                'where'     => ['res_id = ?', 'type in (?)', 'version = ?'],
                'data'      => [$args['resId'], ['PDF', 'SIGN'], $resource['version']],
                'orderBy'   => ["type='SIGN' DESC"],
                'limit'     => 1
            ]);
            $convertedDocument = null;
            if (!empty($convertedResource[0])) {
                $docserver = DocserverModel::getByDocserverId(['docserverId' => $convertedResource[0]['docserver_id'], 'select' => ['path_template']]);
                $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $convertedResource[0]['path']) . $convertedResource[0]['filename'];
                if (file_exists($pathToDocument)) {
                    $convertedDocument = [
                        'size'  => StoreController::getFormattedSizeFromBytes(['size' => filesize($pathToDocument)])
                    ];
                }
            }

            $document = [
                'id'                => $args['resId'],
                'chrono'            => $resource['alt_identifier'],
                'label'             => $resource['subject'],
                'convertedDocument' => $convertedDocument,
                'creator'           => UserModel::getLabelledUserById(['id' => $resource['typist']]),
                'format'            => $resource['format'],
                'size'              => StoreController::getFormattedSizeFromBytes(['size' => $resource['filesize']])
            ];
        }

        $attachments = [];
        $attachmentTypes = AttachmentTypeModel::get(['select' => ['type_id', 'label', 'email_link']]);
        $attachmentTypes = array_column($attachmentTypes, null, 'type_id');
        $rawAttachments = AttachmentModel::get([
            'select'    => ['res_id', 'title', 'identifier', 'attachment_type', 'typist', 'format', 'filesize', 'status'],
            'where'     => ['res_id_master = ?', 'attachment_type not in (?)', 'status not in (?)'],
            'data'      => [$args['resId'], ['signed_response'], ['DEL', 'OBS']]
        ]);
        foreach ($rawAttachments as $attachment) {
            $attachmentId = $attachment['res_id'];
            $signedAttachment = AttachmentModel::get([
                'select'    => ['res_id'],
                'where'     => ['origin = ?', 'status != ?', 'attachment_type = ?'],
                'data'      => ["{$attachmentId},res_attachments", 'DEL', 'signed_response']
            ]);
            if (!empty($signedAttachment[0])) {
                $attachmentId = $signedAttachment[0]['res_id'];
            }

            $convertedAttachment = AdrModel::getAttachments([
                'select'    => ['docserver_id', 'path', 'filename'],
                'where'     => ['res_id = ?', 'type = ?'],
                'data'      => [$attachmentId, 'PDF'],
            ]);
            $convertedDocument = null;
            if (!empty($convertedAttachment[0])) {
                $docserver = DocserverModel::getByDocserverId(['docserverId' => $convertedAttachment[0]['docserver_id'], 'select' => ['path_template']]);
                $pathToDocument = $docserver['path_template'] . str_replace('#', DIRECTORY_SEPARATOR, $convertedAttachment[0]['path']) . $convertedAttachment[0]['filename'];
                if (file_exists($pathToDocument)) {
                    $convertedDocument = [
                        'size'  => StoreController::getFormattedSizeFromBytes(['size' => filesize($pathToDocument)])
                    ];
                }
            }

            $attachments[] = [
                'id'                => $attachmentId,
                'chrono'            => $attachment['identifier'],
                'label'             => $attachment['title'],
                'typeLabel'         => $attachmentTypes[$attachment['attachment_type']]['label'],
                'attachInMail'      => $attachmentTypes[$attachment['attachment_type']]['email_link'],
                'convertedDocument' => $convertedDocument,
                'creator'           => UserModel::getLabelledUserById(['id' => $attachment['typist']]),
                'format'            => $attachment['format'],
                'size'              => StoreController::getFormattedSizeFromBytes(['size' => $attachment['filesize']]),
                'status'            => $attachment['status']
            ];
        }

        $notes = [];
        $userEntities = EntityModel::getByUserId(['userId' => $GLOBALS['id'], 'select' => ['entity_id']]);
        $userEntities = array_column($userEntities, 'entity_id');
        $rawNotes = NoteModel::get(['select' => ['id', 'note_text', 'user_id'], 'where' => ['identifier = ?'], 'data' => [$args['resId']]]);
        foreach ($rawNotes as $rawNote) {
            $allowed = false;
            if ($rawNote['user_id'] == $GLOBALS['id']) {
                $allowed = true;
            } else {
                $noteEntities = NoteEntityModel::get(['select' => ['item_id'], 'where' => ['note_id = ?'], 'data' => [$rawNote['id']]]);
                if (!empty($noteEntities)) {
                    foreach ($noteEntities as $noteEntity) {
                        if (in_array($noteEntity['item_id'], $userEntities)) {
                            $allowed = true;
                            break;
                        }
                    }
                } else {
                    $allowed = true;
                }
            }
            if ($allowed) {
                $notes[] = [
                    'id'        => $rawNote['id'],
                    'label'     => $rawNote['note_text'],
                    'typeLabel' => 'note',
                    'creator'   => UserModel::getLabelledUserById(['id' => $rawNote['user_id']]),
                    'format'    => 'pdf',
                    'size'      => null
                ];
            }
        }

        return $response->withJson(['resource' => $document, 'attachments' => $attachments, 'notes' => $notes]);
    }

    public static function sendEmail(array $args)
    {
        ValidatorModel::notEmpty($args, ['emailId', 'userId']);
        ValidatorModel::intVal($args, ['emailId', 'userId']);

        // EDISSYUM - AMO01 Rajout d'une option -> mettre PJ dans mail via liens éphémères
        $CheckboxIsCheckedAndNextcloudArgsDefined = False;
        if($args['pjLinkChecked']){
            $tmpPath = CoreConfigModel::getTmpPath();
            $res = ParameterController::checkNextcloudConnection();
            $res_id = json_decode(EmailModel::getById(['id' => $args['emailId']])['document'], True)['id'];
            $destination = DatabaseModel::select([
                'select'    => ['destination'],
                'table'     => ['res_view_letterbox'],
                'where'     => ['res_id = '.$res_id],

            ])[0]['destination'];
            $creation_date = explode(' ', DatabaseModel::select([
                'select'    => ['creation_date'],
                'table'     => ['res_view_letterbox'],
                'where'     => ['res_id = '.$res_id],

            ])[0]['creation_date'])[0];
            $alt_identifier = str_replace(array(' ','/','*',':','<','>','"',"'"), '_', DatabaseModel::select([
                'select'    => ['alt_identifier'],
                'table'     => ['res_view_letterbox'],
                'where'     => ['res_id = '.$res_id],
            ])[0]['alt_identifier']);

            if($res['result'] == 'OK') {
                $CheckboxIsCheckedAndNextcloudArgsDefined = True;
            }
        }
        if($CheckboxIsCheckedAndNextcloudArgsDefined) {
            //CONFIG NEXTCLOUD PARAMETERS MAARCH
            $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_attachments_hosts', 'select' => ['value']]);
            $configuration = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];

            $configuration = $configuration['nextcloud'];
            $username = $configuration['username'];
            $password = $configuration['password'];
            $url = $configuration['url'] . '/remote.php/dav/files/';
            $url2 = $configuration['url'] . '/ocs/v1.php/apps/files_sharing/api/v1/shares';
            $expireIn_X_Days = $configuration['urlExpirationDate'];
            $textAddedAboveURLS = $configuration['textAddedAboveURLS'];

            $dossier = $configuration['folderNextcloud'] . "/" . $destination . '/' ;
            $create_folder = "curl -u '" . $username . ":" . $password . "' -H \"OCS-APIRequest: true\" -X MKCOL " . $url . $username . "/" . $dossier;
            exec($create_folder, $output);

            $dossier .= "/" . $creation_date . "/" ;
            $create_folder_creation_date = "curl -u '" . $username . ":" . $password . "' -H \"OCS-APIRequest: true\" -X MKCOL " . $url . $username . "/" . $dossier;
            exec($create_folder_creation_date, $output);

            $dossier .= '/' . $res_id . '-' . $alt_identifier . '/';
            $create_folder_resid_alt_identifier = "curl -u '" . $username . ":" . $password . "' -H \"OCS-APIRequest: true\" -X MKCOL " . $url . $username . "/" . $dossier;
            exec($create_folder_resid_alt_identifier, $output);

            $dossier .=  Date("Y-m-d_").Date("H:i:s") . '_' . $args['emailId'] . "/";
            $create_folder_today = "curl -u '" . $username . ":" . $password . "' -H \"OCS-APIRequest: true\" -X MKCOL " . $url . $username . "/" . $dossier;
            exec($create_folder_today, $output);

            $array_of_PJLINKS = [];
            $true_array_of_PJLINKS = [];

            if (!empty($expireIn_X_Days) && is_numeric($expireIn_X_Days)) {
                $date_expired = Date('Y-m-d', strtotime('+' . $expireIn_X_Days . ' days'));
                $share_expiration = '-d expireDate="' . $date_expired . '"';
            } else {
                $share_expiration = '';
            }
        }
        // END EDISSYUM - AMO01

        $email = EmailModel::getById(['id' => $args['emailId']]);
        $email['sender']        = json_decode($email['sender'], true);
        $email['recipients']    = array_unique(json_decode($email['recipients']));
        $email['cc']            = array_unique(json_decode($email['cc']));
        $email['cci']           = array_unique(json_decode($email['cci']));

        $hierarchyMail = ['cci' => ['recipients', 'cc'], 'cc' => ['recipients']];
        foreach ($hierarchyMail as $lowEmail => $ahighEmail) {
            foreach ($ahighEmail as $highEmail) {
                foreach ($email[$lowEmail] as $currentKey => $currentEmail) {
                    if (in_array($currentEmail, $email[$highEmail])) {
                        unset($email[$lowEmail][$currentKey]);
                    }
                }
            }
        }

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_email_server', 'select' => ['value']]);
        $configuration = json_decode($configuration['value'], true);
        if (empty($configuration)) {
            return ['errors' => 'Configuration is missing'];
        }

        $user = UserModel::getById(['id' => $args['userId'], 'select' => ['firstname', 'lastname', 'user_id']]);

        $phpmailer = new PHPMailer();

        $emailFrom = !empty($email['sender']['email']) ? $email['sender']['email'] : $configuration['from'];
        // EDISSYUM - OBR01 - Rajout du paramètre force_admin_mail_from
        $parameter = ParameterModel::getById(['select' => ['param_value_int'], 'id' => 'force_admin_mail_from']);
        if ($parameter['param_value_int'] == 0) {
            $emailFrom = !empty($email['sender']['email']) ? $email['sender']['email'] : $configuration['from'];
        } else {
            $emailFrom = $configuration['from'];
        }
        // END EDISSYUM - OBR01 - Rajout du paramètre force_admin_mail_from

        if (empty($email['sender']['entityId'])) {
            // Usefull for old sendmail server which doesn't support accent encoding
            $setFrom = TextFormatModel::normalize(['string' => "{$user['firstname']} {$user['lastname']}"]);
            $phpmailer->setFrom($emailFrom, ucwords($setFrom));
        } else {
            $entity = EntityModel::getById(['id' => $email['sender']['entityId'], 'select' => ['short_label']]);
            // Usefull for old sendmail server which doesn't support accent encoding
            $setFrom = TextFormatModel::normalize(['string' => $entity['short_label']]);
            $phpmailer->setFrom($emailFrom, ucwords($setFrom));
        }
        if (in_array($configuration['type'], ['smtp', 'mail'])) {
            if ($configuration['type'] == 'smtp') {
                $phpmailer->isSMTP();
            } elseif ($configuration['type'] == 'mail') {
                $phpmailer->isMail();
            }

            $phpmailer->Host = $configuration['host'];
            $phpmailer->Port = $configuration['port'];
            $phpmailer->SMTPAutoTLS = false;
            if (!empty($configuration['secure'])) {
                $phpmailer->SMTPSecure = $configuration['secure'];
            }
            $phpmailer->SMTPAuth = $configuration['auth'];
            if ($configuration['auth']) {
                $phpmailer->Username = $configuration['user'];
                if (!empty($configuration['password'])) {
                    $phpmailer->Password = PasswordModel::decrypt(['cryptedPassword' => $configuration['password']]);
                }
            }
        } elseif ($configuration['type'] == 'sendmail') {
            $phpmailer->isSendmail();
        } elseif ($configuration['type'] == 'qmail') {
            $phpmailer->isQmail();
        }

        $phpmailer->addReplyTo($email['sender']['email']);
        $phpmailer->CharSet = $configuration['charset'];

        foreach ($email['recipients'] as $recipient) {
            $phpmailer->addAddress($recipient);
        }
        foreach ($email['cc'] as $recipient) {
            $phpmailer->addCC($recipient);
        }
        foreach ($email['cci'] as $recipient) {
            $phpmailer->addBCC($recipient);
        }

        if ($email['is_html']) {
            $phpmailer->isHTML(true);

            $dom = new \DOMDocument();
            $internalErrors = libxml_use_internal_errors(true);
            $dom->loadHTML($email['body'], LIBXML_NOWARNING);
            libxml_use_internal_errors($internalErrors);
            $images = $dom->getElementsByTagName('img');

            foreach ($images as $key => $image) {
                $originalSrc = $image->getAttribute('src');
                if (preg_match('/^data:image\/(\w+);base64,/', $originalSrc)) {
                    $encodedImage = substr($originalSrc, strpos($originalSrc, ',') + 1);
                    $imageFormat = substr($originalSrc, 11, strpos($originalSrc, ';') - 11);
                    $phpmailer->addStringEmbeddedImage(base64_decode($encodedImage), "embeded{$key}", "embeded{$key}.{$imageFormat}");
                    $email['body'] = str_replace($originalSrc, "cid:embeded{$key}", $email['body']);
                }
            }
        }

        $phpmailer->Subject = $email['object'];
        $phpmailer->Body = $email['body'];
        if (empty($email['body'])) {
            $phpmailer->AllowEmpty = true;
        }

        // EDISSYUM - NCH01 Ajout d'un accusé de lecture
        if ($args['receivedReceipt']) {
            $phpmailer->addCustomHeader("Disposition-Notification-To: " . $email['sender']['email']);
        }
        // END EDISSYUM - NCH01

        //zip M2M
        if ($email['message_exchange_id']) {
            $messageExchange = MessageExchangeModel::getMessageByIdentifier(['messageId' => $email['message_exchange_id'], 'select' => ['docserver_id','path','filename','fingerprint','reference']]);
            $docserver       = DocserverModel::getByDocserverId(['docserverId' => $messageExchange['docserver_id']]);
            $docserverType   = DocserverTypeModel::getById(['id' => $docserver['docserver_type_id']]);

            $pathDirectory = str_replace('#', DIRECTORY_SEPARATOR, $messageExchange['path']);
            $filePath      = $docserver['path_template'] . $pathDirectory . $messageExchange['filename'];
            $fingerprint   = StoreController::getFingerPrint([
                'filePath' => $filePath,
                'mode'     => $docserverType['fingerprint_mode'],
            ]);

            if ($fingerprint != $messageExchange['fingerprint']) {
                $email['document'] = json_decode($email['document'], true);
                return ['errors' => 'Pb with fingerprint of document. ResId master : ' . $email['document']['id']];
            }

            if (is_file($filePath)) {
                $fileContent = file_get_contents($filePath);
                if ($fileContent === false) {
                    return ['errors' => 'Document not found on docserver'];
                }

                $title = TextFormatModel::formatFilename(['filename' => $messageExchange['reference'], 'maxLength' => 30]);

                $phpmailer->addStringAttachment($fileContent, $title . '.zip');
            }
        } else {
            if (!empty($email['document'])) {
                $email['document'] = json_decode($email['document'], true);
                if ($email['document']['isLinked']) {
                    $encodedDocument = ResController::getEncodedDocument(['resId' => $email['document']['id'], 'original' => $email['document']['original']]);

                    // EDISSYUM - AMO01 Rajout d'une option -> mettre PJ dans mail via liens éphémères
                    if ($CheckboxIsCheckedAndNextcloudArgsDefined) {
                        $filePath = $tmpPath . 'document_' . $email['document']['id'] . '.pdf';
                        $filename = 'Document_'. $email['document']['id'] .'.pdf';
                        file_put_contents($filePath, base64_decode($encodedDocument['encodedDocument']));
                        $command_upload = "curl -u '" . $username . ":" . $password . "' " . "'" . $url . $username . "/" . $dossier . "/'" . $filename . " " . "-T " . $filePath;
                        exec($command_upload, $output);

                        $command_share = "curl -u '" . $username . ":" . $password . "' -H \"OCS-APIRequest: true\" -X POST " . $url2 . " -d path='" . $dossier . $filename . "' -d shareType=3 ".$share_expiration;
                        exec($command_share, $output);
                    }
                    // END EDISSYUM - AMO01

                    if (empty($encodedDocument['errors'])) {
                        $phpmailer->addStringAttachment(base64_decode($encodedDocument['encodedDocument']), $encodedDocument['fileName']);
                    }
                }
                if (!empty($email['document']['attachments'])) {
                    $my_i = 0; // EDISSYUM - AMO01 Rajout d'une option -> mettre PJ dans mail via liens éphémères
                    foreach ($email['document']['attachments'] as $attachment) {
                        $encodedDocument = AttachmentController::getEncodedDocument(['id' => $attachment['id'], 'original' => $attachment['original']]);
                        // EDISSYUM - AMO01 Rajout d'une option -> mettre PJ dans mail via liens éphémères - Pièces jointes - Attachements attachments
                        if ($CheckboxIsCheckedAndNextcloudArgsDefined && sizeof($email['document']['attachments']) > 0) {
                            $resource_attach = DatabaseModel::select([
                                'select' => ['docserver_id', 'path', 'filename', 'title', 'format'],
                                'table' => ['res_attachments'],
                                'where' => ['res_id = ?'],
                                'data' => [$email['document']['attachments'][$my_i]['id']],
                            ]);
                            $filePath = $tmpPath . 'attachements_' . $email['document']['attachments'][$my_i]['id'] . '.' . $resource_attach[0]['format'];
                            $filename = str_replace(" ", "_", $resource_attach[0]['title']) . '.' . $resource_attach[0]['format'];
                            file_put_contents($filePath, base64_decode($encodedDocument['encodedDocument']));
                            $command_upload = "curl -u '" . $username . ":" . $password . "' " . "'" . $url . $username . "/" . $dossier . "/'" . $filename . " " . "-T " . $filePath;
                            exec($command_upload, $output);

                            $command_share = "curl -u '" . $username . ":" . $password . "' -H \"OCS-APIRequest: true\" -X POST " . $url2 . " -d path='" . $dossier . $filename . "' -d shareType=3 ".$share_expiration;
                            exec($command_share, $output);
                        }
                        // END EDISSYUM - AMO01
                        if (empty($encodedDocument['errors'])) {
                            $phpmailer->addStringAttachment(base64_decode($encodedDocument['encodedDocument']), $encodedDocument['fileName']);
                        }
                        $my_i += 1; // EDISSYUM - AMO01 Rajout d'une option -> mettre PJ dans mail via liens éphémères
                    }
                }
                if (!empty($email['document']['notes'])) {
                    $encodedDocument = NoteController::getEncodedPdfByIds(['ids' => $email['document']['notes']]);
                    // EDISSYUM - AMO01 Rajout d'une option -> mettre PJ dans mail via liens éphémères - Notes - Annotations
                    if ($CheckboxIsCheckedAndNextcloudArgsDefined) {
                        $filePath = $tmpPath . 'notes_' . $email['document']['id'] . '.pdf';
                        $filename = 'Notes_'. $email['document']['id'] .'.pdf';
                        file_put_contents($filePath, base64_decode($encodedDocument['encodedDocument']));
                        $command_upload = "curl -u '" . $username . ":" . $password . "' " . "'" . $url . $username . "/" . $dossier . "/'" . $filename . " " . "-T " . $filePath;
                        exec($command_upload, $output);

                        $command_share = "curl -u '" . $username . ":" . $password . "' -H \"OCS-APIRequest: true\" -X POST " . $url2 . " -d path='" . $dossier . $filename . "' -d shareType=3 ".$share_expiration;
                        exec($command_share, $output);
                    }
                    // END EDISSYUM - AMO01
                    if (empty($encodedDocument['errors'])) {
                        $phpmailer->addStringAttachment(base64_decode($encodedDocument['encodedDocument']), 'notes.pdf');
                    }
                }
                // EDISSYUM - AMO01 UPDATE Rajout d'une option -> mettre PJ dans mail via liens éphémères
                $replace = [
                    '&lt;' => '', '&gt;' => '', '&#039;' => '', '&amp;' => '',
                    '&quot;' => '', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'Ae',
                    '&Auml;' => 'A', 'Å' => 'A', 'Ā' => 'A', 'Ą' => 'A', 'Ă' => 'A', 'Æ' => 'Ae',
                    'Ç' => 'C', 'Ć' => 'C', 'Č' => 'C', 'Ĉ' => 'C', 'Ċ' => 'C', 'Ď' => 'D', 'Đ' => 'D',
                    'Ð' => 'D', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E',
                    'Ę' => 'E', 'Ě' => 'E', 'Ĕ' => 'E', 'Ė' => 'E', 'Ĝ' => 'G', 'Ğ' => 'G',
                    'Ġ' => 'G', 'Ģ' => 'G', 'Ĥ' => 'H', 'Ħ' => 'H', 'Ì' => 'I', 'Í' => 'I',
                    'Î' => 'I', 'Ï' => 'I', 'Ī' => 'I', 'Ĩ' => 'I', 'Ĭ' => 'I', 'Į' => 'I',
                    'İ' => 'I', 'Ĳ' => 'IJ', 'Ĵ' => 'J', 'Ķ' => 'K', 'Ł' => 'L', 'Ľ' => 'L',
                    'Ĺ' => 'L', 'Ļ' => 'L', 'Ŀ' => 'L', 'Ñ' => 'N', 'Ń' => 'N', 'Ň' => 'N',
                    'Ņ' => 'N', 'Ŋ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
                    'Ö' => 'Oe', '&Ouml;' => 'Oe', 'Ø' => 'O', 'Ō' => 'O', 'Ő' => 'O', 'Ŏ' => 'O',
                    'Œ' => 'OE', 'Ŕ' => 'R', 'Ř' => 'R', 'Ŗ' => 'R', 'Ś' => 'S', 'Š' => 'S',
                    'Ş' => 'S', 'Ŝ' => 'S', 'Ș' => 'S', 'Ť' => 'T', 'Ţ' => 'T', 'Ŧ' => 'T',
                    'Ț' => 'T', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'Ue', 'Ū' => 'U',
                    '&Uuml;' => 'Ue', 'Ů' => 'U', 'Ű' => 'U', 'Ŭ' => 'U', 'Ũ' => 'U', 'Ų' => 'U',
                    'Ŵ' => 'W', 'Ý' => 'Y', 'Ŷ' => 'Y', 'Ÿ' => 'Y', 'Ź' => 'Z', 'Ž' => 'Z',
                    'Ż' => 'Z', 'Þ' => 'T', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a',
                    'ä' => 'ae', '&auml;' => 'ae', 'å' => 'a', 'ā' => 'a', 'ą' => 'a', 'ă' => 'a',
                    'æ' => 'ae', 'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ĉ' => 'c', 'ċ' => 'c',
                    'ď' => 'd', 'đ' => 'd', 'ð' => 'd', 'è' => 'e', 'é' => 'e', 'ê' => 'e',
                    'ë' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e', 'ĕ' => 'e', 'ė' => 'e',
                    'ƒ' => 'f', 'ĝ' => 'g', 'ğ' => 'g', 'ġ' => 'g', 'ģ' => 'g', 'ĥ' => 'h',
                    'ħ' => 'h', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
                    'ĩ' => 'i', 'ĭ' => 'i', 'į' => 'i', 'ı' => 'i', 'ĳ' => 'ij', 'ĵ' => 'j',
                    'ķ' => 'k', 'ĸ' => 'k', 'ł' => 'l', 'ľ' => 'l', 'ĺ' => 'l', 'ļ' => 'l',
                    'ŀ' => 'l', 'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'ņ' => 'n', 'ŉ' => 'n',
                    'ŋ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'oe',
                    '&ouml;' => 'oe', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o', 'ŏ' => 'o', 'œ' => 'oe',
                    'ŕ' => 'r', 'ř' => 'r', 'ŗ' => 'r', 'š' => 's', 'ù' => 'u', 'ú' => 'u',
                    'û' => 'u', 'ü' => 'ue', 'ū' => 'u', '&uuml;' => 'ue', 'ů' => 'u', 'ű' => 'u',
                    'ŭ' => 'u', 'ũ' => 'u', 'ų' => 'u', 'ŵ' => 'w', 'ý' => 'y', 'ÿ' => 'y',
                    'ŷ' => 'y', 'ž' => 'z', 'ż' => 'z', 'ź' => 'z', 'þ' => 't', 'ß' => 'ss',
                    'ſ' => 'ss', 'ый' => 'iy', 'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G',
                    'Д' => 'D', 'Е' => 'E', 'Ё' => 'YO', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I',
                    'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
                    'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F',
                    'Х' => 'H', 'Ц' => 'C', 'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'SCH', 'Ъ' => '',
                    'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA', 'а' => 'a',
                    'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
                    'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l',
                    'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's',
                    'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
                    'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e',
                    'ю' => 'yu', 'я' => 'ya'
                ];

                if ($CheckboxIsCheckedAndNextcloudArgsDefined ) {
                    $shareURL = explode('<data>', json_encode(str_replace(array_keys($replace), $replace, $output), JSON_UNESCAPED_SLASHES));
                    array_shift($shareURL);
                    foreach ($shareURL as $URL) {
                        $tempURL = explode('<url>', $URL);
                        $tempNAME = explode('<file_target>', $URL);
                        array_shift($tempURL);
                        array_shift($tempNAME);
                        $array_of_PJLINKS[] = array(
                            $tempURL,
                            $tempNAME
                        );
                    }

                    foreach ($array_of_PJLINKS as $URL) {
                        $tempURL = explode('</url>', $URL[0][0]);
                        $tempNAME = explode('</file_target>', $URL[1][0]);
                        array_pop($tempURL);
                        array_pop($tempNAME);
                        $true_array_of_PJLINKS[] = array(
                            'url' => str_replace('http://', 'https://', $tempURL[0]),
                            'name' => str_replace(array_keys($replace), $replace, str_replace("/", "", $tempNAME[0]))
                        );
                    }
                    $URL_FINAL_LINKS_STR = "";

                    foreach ($true_array_of_PJLINKS as $URL_FINAL_LINKS){
                        $URL_FINAL_LINKS_STR .= " <a title='CTRL + click' href='".$URL_FINAL_LINKS['url']."'>".$URL_FINAL_LINKS['name']."</a> <br>";
                    }
                    if(strpos($email['body'],'<h5 class="PJ_LINKS">[PJ_LINKS]</h5>') !== false) {

                        $newEmailBody = str_replace('<h5 class="PJ_LINKS">[PJ_LINKS]</h5>', $textAddedAboveURLS. '<br>' . $URL_FINAL_LINKS_STR, $email['body']);
                    }
                    else {
                        $newEmailBody = $URL_FINAL_LINKS_STR . $email['body'] ;
                    }
                    EmailModel::update([
                        'set' => ['body' => $newEmailBody],
                        'where' => ['id = ?'],
                        'data' => [$email['id']]
                    ]);
                    $phpmailer->Body = $newEmailBody;
                    $phpmailer->clearAttachments();
                }
                // END EDISSYUM - AMO01
            }
        }

        $phpmailer->Timeout = 30;
        $phpmailer->SMTPDebug = 1;
        $phpmailer->Debugoutput = function ($str) {
            if (strpos($str, 'SMTP ERROR') !== false) {
                HistoryController::add([
                    'tableName'    => 'emails',
                    'recordId'     => 'email',
                    'eventType'    => 'ERROR',
                    'eventId'      => 'sendEmail',
                    'info'         => $str
                ]);
            }
        };

        $isSent = $phpmailer->send();
        if (!$isSent) {
            $history = HistoryModel::get([
                'select'    => ['info'],
                'where'     => ['user_id = ?', 'event_id = ?', 'event_type = ?'],
                'data'      => [$args['userId'], 'sendEmail', 'ERROR'],
                'orderBy'   => ['event_date DESC'],
                'limit'     => 1
            ]);

            $errors = !empty($history[0]['info']) ? $history[0]['info'] : $phpmailer->ErrorInfo;

            return ['errors' => $errors];
        }

        return ['success' => 'success'];
    }

    public static function getAvailableEmailsByUserId(array $args)
    {
        $currentUser = UserModel::getById(['select' => ['firstname', 'lastname', 'mail', 'user_id'], 'id' => $args['userId']]);

        $availableEmails = [
            [
            'entityId'  => null,
            'label'     => $currentUser['firstname'] . ' ' . $currentUser['lastname'],
            'email'     => $currentUser['mail']
            ]
        ];

        if (PrivilegeController::hasPrivilege(['privilegeId' => 'use_mail_services', 'userId' => $args['userId']])) {
            $entities = EntityModel::getWithUserEntities([
                'select' => ['entities.entity_label', 'entities.email', 'entities.entity_id', 'entities.id'],
                'where'  => ['users_entities.user_id = ?'],
                'data'   => [$args['userId']]
            ]);

            foreach ($entities as $entity) {
                if (!empty($entity['email'])) {
                    $availableEmails[] = [
                        'entityId'  => $entity['id'],
                        'label'     => $entity['entity_label'],
                        'email'     => $entity['email']
                    ];
                }
            }

            $emailsEntities = CoreConfigModel::getXmlLoaded(['path' => 'modules/sendmail/xml/externalMailsEntities.xml']);
            if (!empty($emailsEntities)) {
                $userEntities = array_column($entities, 'entity_id');
                foreach ($emailsEntities->externalEntityMail as $entityMail) {
                    $entityId = (string)$entityMail->targetEntityId;

                    if (empty($entityId)) {
                        $availableEmails[] = [
                            'entityId'  => null,
                            'label'     => (string)$entityMail->defaultName,
                            'email'     => trim((string)$entityMail->EntityMail)
                        ];
                    } elseif (in_array($entityId, $userEntities)) {
                        $entity = EntityModel::getByEntityId([
                            'select'   => ['entity_label', 'id'],
                            'entityId' => $entityId
                        ]);

                        if (!empty($entity)) {
                            $availableEmails[] = [
                                'entityId'  => $entity['id'],
                                'label'     => $entity['entity_label'],
                                'email'     => trim((string)$entityMail->EntityMail)
                            ];
                        }
                    }
                }
            }
        }

        return $availableEmails;
    }

    private static function controlCreateEmail(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId']);
        ValidatorModel::intVal($args, ['userId']);
        ValidatorModel::arrayType($args, ['data']);

        if (!Validator::stringType()->notEmpty()->validate($args['data']['status'])) {
            return ['errors' => 'Data status is not a string or empty', 'code' => 400];
        } elseif ($args['data']['status'] != 'DRAFT' && (!Validator::arrayType()->notEmpty()->validate($args['data']['sender']) || !Validator::stringType()->notEmpty()->validate($args['data']['sender']['email']))) {
            return ['errors' => 'Data sender email is not set', 'code' => 400];
        } elseif ($args['data']['status'] != 'DRAFT' && !Validator::arrayType()->notEmpty()->validate($args['data']['recipients'])) {
            return ['errors' => 'Data recipients is not an array or empty', 'code' => 400];
        } elseif (!Validator::boolType()->validate($args['data']['isHtml'])) {
            return ['errors' => 'Data isHtml is not a boolean or empty', 'code' => 400];
        }

        if (!empty($args['data']['object']) && !Validator::stringType()->length(1, 255)->validate($args['data']['object'])) {
            return ['errors' => 'Data object is not a string or is more than 255 characters', 'code' => 400];
        }

        if (!empty($args['data']['sender']['email']) && empty($args['isAcknowledgementReceipt'])) {
            $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_email_server', 'select' => ['value']]);
            $configuration = json_decode($configuration['value'], true);

            $availableEmails = EmailController::getAvailableEmailsByUserId(['userId' => $args['userId']]);
            $emails = array_column($availableEmails, 'email');
            if (!empty($configuration['from'])) {
                $emails[] = $configuration['from'];
            }
            if (!in_array($args['data']['sender']['email'], $emails)) {
                return ['errors' => 'Data sender email is not allowed', 'code' => 400];
            }
            if (!empty($args['data']['sender']['entityId'])) {
                $entities = array_column($availableEmails, 'entityId');
                if (!in_array($args['data']['sender']['entityId'], $entities)) {
                    return ['errors' => 'Data sender entityId is not allowed', 'code' => 400];
                }
            }
        }

        if (!empty($args['data']['document'] && !empty($args['data']['document']['id']))) {
            $check = Validator::intVal()->notEmpty()->validate($args['data']['document']['id']);
            $check = $check && Validator::boolType()->validate($args['data']['document']['isLinked']);
            $check = $check && Validator::boolType()->validate($args['data']['document']['original']);
            if (!$check) {
                return ['errors' => 'Data document errors', 'code' => 400];
            }
            if (!ResController::hasRightByResId(['resId' => [$args['data']['document']['id']], 'userId' => $args['userId']])) {
                return ['errors' => 'Document out of perimeter', 'code' => 403];
            }
            if (!ResourceListController::controlFingerprints(['resId' => $args['data']['document']['id']])) {
                return ['errors' => 'Document has fingerprints which do not match', 'code' => 400];
            }
            if (!empty($args['data']['document']['attachments'])) {
                if (!is_array($args['data']['document']['attachments'])) {
                    return ['errors' => 'Data document[attachments] is not an array', 'code' => 400];
                }
                foreach ($args['data']['document']['attachments'] as $attachment) {
                    $check = Validator::intVal()->notEmpty()->validate($attachment['id']);
                    $check = $check && Validator::boolType()->validate($attachment['original']);
                    if (!$check) {
                        return ['errors' => 'Data document[attachments] errors', 'code' => 400];
                    }
                    $checkAttachment = AttachmentModel::getById(['id' => $attachment['id'], 'select' => ['res_id_master']]);
                    if (empty($checkAttachment) || $checkAttachment['res_id_master'] != $args['data']['document']['id']) {
                        return ['errors' => 'Attachment out of perimeter', 'code' => 403];
                    }
                }
            }
            if (!empty($args['data']['document']['notes'])) {
                if (!is_array($args['data']['document']['notes'])) {
                    return ['errors' => 'Data document[notes] is not an array', 'code' => 400];
                }
                foreach ($args['data']['document']['notes'] as $note) {
                    if (!Validator::intVal()->notEmpty()->validate($note)) {
                        return ['errors' => 'Data document[notes] errors', 'code' => 400];
                    }
                    $checkNote = NoteModel::getById(['id' => $note, 'select' => ['identifier', 'user_id']]);
                    if (empty($checkNote) || $checkNote['identifier'] != $args['data']['document']['id']) {
                        return ['errors' => 'Note out of perimeter', 'code' => 403];
                    } elseif ($checkNote['user_id'] == $args['userId']) {
                        continue;
                    }
                    $rawUserEntities = EntityModel::getByUserId(['userId' => $args['userId'], 'select' => ['entity_id']]);
                    $userEntities = [];
                    foreach ($rawUserEntities as $rawUserEntity) {
                        $userEntities[] = $rawUserEntity['entity_id'];
                    }
                    $noteEntities = NoteEntityModel::get(['select' => ['item_id'], 'where' => ['note_id = ?'], 'data' => [$note]]);
                    if (!empty($noteEntities)) {
                        $found = false;
                        foreach ($noteEntities as $noteEntity) {
                            if (in_array($noteEntity['item_id'], $userEntities)) {
                                $found = true;
                            }
                        }
                        if (!$found) {
                            return ['errors' => 'Note out of perimeter', 'code' => 403];
                        }
                    }
                }
            }
        }

        return ['success' => 'success'];
    }
}
