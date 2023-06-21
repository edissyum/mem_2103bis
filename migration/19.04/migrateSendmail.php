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
    $path = "custom/{$custom}/modules/sendmail/batch/config/config.xml";
    if (file_exists($path)) {
        $xmlfile = simplexml_load_file($path);
    }

    if ($xmlfile) {
        $pass = '';
        if (!empty((string)$xmlfile->MAILER->smtp_password)) {
            $pass = \SrcCore\models\PasswordModel::encrypt(['password' => (string)$xmlfile->MAILER->smtp_password]);
        }

        $data = [
            'type'     => (string)$xmlfile->MAILER->type,
            'host'     => (string)$xmlfile->MAILER->smtp_host,
            'port'     => (int)$xmlfile->MAILER->smtp_port,
            'user'     => (string)$xmlfile->MAILER->smtp_user,
            'password' => $pass,
            'auth'     => (string)$xmlfile->MAILER->smtp_auth == 'true' ? true : false,
            'from'     => (string)$xmlfile->MAILER->mailfrom,
            'secure'   => 'ssl',
            'charset'  => 'utf-8'
        ];
        $data = json_encode($data);
        \Configuration\models\ConfigurationModel::update(['set' => ['value' => $data], 'where' => ['service = ?'], 'data' => ['admin_email_server']]);

        $migrated++;
    }

    $sendmails = \SrcCore\models\DatabaseModel::select([
        'select'    => ['*'],
        'table'     => ['sendmail'],
        'order_by'  => ['creation_date']
    ]);

    $aValues = [];
    $countMail = 0;
    foreach ($sendmails as $sendmail) {
        if (empty($sendmail['user_id'])) {
            continue;
        }
        $user = \User\models\UserModel::getByLogin(['login' => $sendmail['user_id'], 'select' => ['id']]);
        $sender = explode(',', $sendmail['sender_email']);
        if (empty($sender[1])) {
            $sender = ['email' => $sender[0]];
        } else {
            $entity = \Entity\models\EntityModel::getByEntityId(['entityId' => $sender[0], 'select' => ['id']]);
            $sender = ['email' => $sender[1], 'entityId' => $entity['id']];
        }
        $recipients = explode(',', $sendmail['to_list']);
        $cc = explode(',', $sendmail['cc_list']);
        $cc = empty($cc[0]) ? [] : $cc;
        $cci = explode(',', $sendmail['cci_list']);
        $cci = empty($cci[0]) ? [] : $cci;

        $document = [
            'id'        => $sendmail['res_id'],
            'isLinked'  => $sendmail['is_res_master_attached'] == 'Y',
            'original'  => false,
        ];

        $attachments = [];

        $rawAttachments = explode(',', $sendmail['res_attachment_id_list']);
        if (!empty($rawAttachments[0])) {
            foreach ($rawAttachments as $rawAttachment) {
                $id = $rawAttachment;
                $original = true;
                if (strpos($rawAttachment, '#') !== false) {
                    $id = substr($rawAttachment, 0, strpos($rawAttachment, '#'));
                    $original = false;
                }
                $attachments[] = [
                    'id'        => (int)$id,
                    'isVersion' => false,
                    'original'  => $original,
                ];
            }
        }

        $rawVersionAttachments = explode(',', $sendmail['res_version_att_id_list']);
        if (!empty($rawVersionAttachments[0])) {
            foreach ($rawVersionAttachments as $rawAttachment) {
                $id = $rawAttachment;
                $original = true;
                if (strpos($rawAttachment, '#') !== false) {
                    $id = substr($rawAttachment, 0, strpos($rawAttachment, '#'));
                    $original = false;
                }
                $attachments[] = [
                    'id'        => (int)$id,
                    'isVersion' => true,
                    'original'  => $original,
                ];
            }
        }
        if (!empty($attachments)) {
            $document['attachments'] = $attachments;
        }
        $notes = explode(',', $sendmail['note_id_list']);
        if (!empty($notes[0])) {
            $document['notes'] = [];
            foreach ($notes as $note) {
                $document['notes'][] = (int)$note;
            }
        }

        if ($sendmail['email_status'] == 'S') {
            $status = 'SENT';
        } elseif ($sendmail['email_status'] == 'D') {
            $status = 'DRAFT';
        } elseif ($sendmail['email_status'] == 'W') {
            $status = 'WAITING';
        } else {
            $status = 'ERROR';
        }

        // EDISSYUM - NCH01 Fix pour éviter une erreur lors de la migration des emails
        if ($user['id'] !== NULL) {
            $aValues[] = [
                $user['id'],
                json_encode($sender),
                json_encode($recipients),
                empty($cc) ? '[]' : json_encode($cc),
                empty($cci) ? '[]' : json_encode($cci),
                empty($sendmail['email_object']) ? null : $sendmail['email_object'],
                empty($sendmail['email_body']) ? null : $sendmail['email_body'],
                empty($document) ? null : json_encode($document),
                $sendmail['is_html'] == 'Y' ? 'true' : 'false',
                $status,
                empty($sendmail['message_exchange_id']) ? null : $sendmail['message_exchange_id'],
                $sendmail['creation_date'],
                empty($sendmail['send_date']) ? null : $sendmail['send_date']
            ];

            $countMail++;

            if ($countMail % 50 == 0) {
                \SrcCore\models\DatabaseModel::insertMultiple([
                    'table' => 'emails',
                    'columns'       => ['user_id', 'sender', 'recipients', 'cc', 'cci', 'object', 'body', 'document', 'is_html', 'status', 'message_exchange_id', 'creation_date', 'send_date'],
                    'values' => $aValues
                ]);
                $aValues = [];
            }
        }
    }

    if (!empty($aValues)) {
        \SrcCore\models\DatabaseModel::insertMultiple([
            'table'         => 'emails',
            'columns'       => ['user_id', 'sender', 'recipients', 'cc', 'cci', 'object', 'body', 'document', 'is_html', 'status', 'message_exchange_id', 'creation_date', 'send_date'],
            'values'        => $aValues
        ]);
    }

    printf($countMail . " email(s) migré(s) du custom {$custom} vers la nouvelle table.\n");
}

printf($migrated . " custom(s) avec une configuration sendmail trouvé(s) et migré(s).\n");
