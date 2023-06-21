<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

/**
 * @brief Configuration Controller
 * @author dev@maarch.org
 */

namespace Configuration\controllers;

use Attachment\models\AttachmentTypeModel;
use Basket\models\BasketModel;
use Configuration\models\ConfigurationModel;
use ContentManagement\controllers\Office365SharepointController;
use Doctype\models\DoctypeModel;
use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use IndexingModel\models\IndexingModelModel;
use MessageExchange\controllers\ReceiveMessageExchangeController;
use Priority\models\PriorityModel;
use Respect\Validation\Validator;
use Slim\Http\Request;
use Slim\Http\Response;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\PasswordModel;
use Status\models\StatusModel;

class ConfigurationController
{
    public function getByPrivilege(Request $request, Response $response, array $args)
    {
        if (in_array($args['privilege'], ['admin_sso'])) {
            if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_connections', 'userId' => $GLOBALS['id']])) {
                return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
            }
        } elseif ($args['privilege'] == 'admin_document_editors') {
            if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_parameters', 'userId' => $GLOBALS['id']])) {
                return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
            }
        } elseif ($args['privilege'] != 'admin_attachments_hosts' && !PrivilegeController::hasPrivilege(['privilegeId' => $args['privilege'], 'userId' => $GLOBALS['id']])) { // AMO01 Rajout d'une option -> mettre PJ dans mail via liens éphémères - Ajout variable admin_attachments_hosts
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        } elseif (!PrivilegeController::hasPrivilege(['privilegeId' => $args['privilege'], 'userId' => $GLOBALS['id']]) && $args['privilege'] != 'admin_attachments_hosts') { // AMO01 Rajout d'une option -> mettre PJ dans mail via liens éphémères - Ajout variable admin_attachments_hosts
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => $args['privilege']]);
        $configuration['value'] = json_decode($configuration['value'], true);
        if ($args['privilege'] == 'admin_email_server') {
            if (!empty($configuration['value']['password'])) {
                $configuration['value']['password'] = '';
                $configuration['value']['passwordAlreadyExists'] = true;
            } else {
                $configuration['value']['passwordAlreadyExists'] = false;
            }
        }

        return $response->withJson(['configuration' => $configuration]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        if (in_array($args['privilege'], ['admin_sso'])) {
            if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_connections', 'userId' => $GLOBALS['id']])) {
                return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
            }
        } elseif ($args['privilege'] == 'admin_document_editors') {
            if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_parameters', 'userId' => $GLOBALS['id']])) {
                return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
            }
        } elseif (!PrivilegeController::hasPrivilege(['privilegeId' => $args['privilege'], 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        if (!in_array($args['privilege'], ['admin_email_server', 'admin_search', 'admin_sso', 'admin_document_editors',
            'admin_parameters_watermark', 'admin_shippings', 'admin_attachments_hosts', 'admin_search_contacts', 'admin_search_folders' // EDISSYUM - NCH01 Fenetre de recherche de contacts Ajout admin_search_contacts // EDISSYUM - EME01  Ajout d'une fenêtre pour administrer la recherche des dossiers | Ajouter  'admin_search_folders'
        ])) { // EDISSYUM - AMO01 Rajout d'une option -> mettre PJ dans mail via liens éphémères - Ajout variable admin_attachments_hosts
            return $response->withStatus(403)->withJson(['errors' => 'Unknown privilege']);
        }

        $data = $request->getParsedBody();

        if ($args['privilege'] == 'admin_email_server') {
            if ($data['auth'] && empty($data['password'])) {
                $configuration = ConfigurationModel::getByPrivilege(['privilege' => $args['privilege']]);
                $configuration['value'] = json_decode($configuration['value'], true);
                if (!empty($configuration['value']['password'])) {
                    $data['password'] = $configuration['value']['password'];
                }
            } elseif ($data['auth'] && !empty($data['password'])) {
                $data['password'] = PasswordModel::encrypt(['password' => $data['password']]);
            }
            $check = ConfigurationController::checkMailer($data);
            if (!empty($check['errors'])) {
                return $response->withStatus($check['code'])->withJson(['errors' => $check['errors']]);
            }
            $data['charset'] = empty($data['charset']) ? 'utf-8' : $data['charset'];
            unset($data['passwordAlreadyExists']);
        } elseif ($args['privilege'] == 'admin_search' || $args['privilege'] == 'admin_search_folders') {  // EDISSYUM - EME01  Ajout d'une fenêtre pour administrer la recherche des dossiers | ajouter  || $args['privilege'] == 'admin_search_folders' dans le elseif
            if (!Validator::notEmpty()->arrayType()->validate($data['listDisplay'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body listDisplay is empty or not an array']);
            }
            if (isset($data['listDisplay']['subInfos']) && !Validator::arrayType()->validate($data['listDisplay']['subInfos'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body listDisplay[subInfos] is not set or not an array']);
            }
            if (!Validator::intVal()->validate($data['listDisplay']['templateColumns'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body listDisplay[templateColumns] is not set or not an array']);
            }
            foreach ($data['listDisplay']['subInfos'] as $value) {
                if (!Validator::stringType()->notEmpty()->validate($value['value'])) {
                    return $response->withStatus(400)->withJson(['errors' => 'Body listDisplay[subInfos][value] is empty or not a string']);
                } elseif (!isset($value['cssClasses']) || !is_array($value['cssClasses'])) {
                    return $response->withStatus(400)->withJson(['errors' => 'Body listDisplay[subInfos][cssClasses] is not set or not an array']);
                }
            }

            if (empty($data['listEvent']['defaultTab'])) {
                $data['listEvent']['defaultTab'] = 'dashboard';
            }

            $data = ['listDisplay' => $data['listDisplay'], 'listEvent' => $data['listEvent']];
        } elseif ($args['privilege'] == 'admin_sso') {
            if (!empty($data['url']) && !Validator::stringType()->validate($data['url'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body url is empty or not a string']);
            }
            if (!Validator::notEmpty()->arrayType()->validate($data['mapping'])) {
                return $response->withStatus(400)->withJson(['errors' => 'Body mapping is empty or not an array']);
            }
            foreach ($data['mapping'] as $key => $mapping) {
                if (!Validator::notEmpty()->stringType()->validate($mapping['ssoId'])) {
                    return $response->withStatus(400)->withJson(['errors' => "Body mapping[$key]['ssoId'] is empty or not a string"]);
                }
                if (!Validator::notEmpty()->stringType()->validate($mapping['maarchId'])) {
                    return $response->withStatus(400)->withJson(['errors' => "Body mapping[$key]['maarchId'] is empty or not a string"]);
                }
            }
        } elseif ($args['privilege'] == 'admin_document_editors') {
            if (!Validator::notEmpty()->arrayType()->validate($data)) {
                return $response->withStatus(400)->withJson(['errors' => 'Body is empty or not an array']);
            }
            foreach ($data as $key => $editor) {
                if ($key == 'java') {
                    $data[$key] = [];
                } elseif ($key == 'onlyoffice') {
                    if (!Validator::notEmpty()->stringType()->validate($editor['uri'] ?? null)) {
                        return $response->withStatus(400)->withJson(['errors' => "Body onlyoffice['uri'] is empty or not a string"]);
                    } elseif (!Validator::notEmpty()->intVal()->validate($editor['port'] ?? null)) {
                        return $response->withStatus(400)->withJson(['errors' => "Body onlyoffice['port'] is empty or not numeric"]);
                    } elseif (!Validator::boolType()->validate($editor['ssl'] ?? null)) {
                        return $response->withStatus(400)->withJson(['errors' => "Body onlyoffice['ssl'] is empty or not a boolean"]);
                    }
                    $data[$key]['authorizationHeader'] = $editor['authorizationHeader'] ?? '';
                    $data[$key]['token'] = $editor['token'] ?? '';
                } elseif ($key == 'collaboraonline') {
                    if (!Validator::notEmpty()->stringType()->validate($editor['uri'] ?? null)) {
                        return $response->withStatus(400)->withJson(['errors' => "Body collaboraonline['uri'] is empty or not a string"]);
                    } elseif (!Validator::notEmpty()->intVal()->validate($editor['port'] ?? null)) {
                        return $response->withStatus(400)->withJson(['errors' => "Body collaboraonline['port'] is empty or not numeric"]);
                    } elseif (!Validator::boolType()->validate($editor['ssl'] ?? null)) {
                        return $response->withStatus(400)->withJson(['errors' => "Body collaboraonline['ssl'] is not set or not a boolean"]);
                    }
                } elseif ($key == 'office365sharepoint') {
                    if (!Validator::notEmpty()->stringType()->validate($editor['tenantId'] ?? null)) {
                        return $response->withStatus(400)->withJson(['errors' => "Body office365sharepoint['tenantId'] is empty or not a string"]);
                    } elseif (!Validator::notEmpty()->stringType()->validate($editor['clientId'] ?? null)) {
                        return $response->withStatus(400)->withJson(['errors' => "Body office365sharepoint['clientId'] is empty or not a string"]);
                    } elseif (!Validator::notEmpty()->stringType()->validate($editor['clientSecret'] ?? null)) {
                        return $response->withStatus(400)->withJson(['errors' => "Body office365sharepoint['clientSecret'] is empty or not a string"]);
                    } elseif (!Validator::notEmpty()->stringType()->validate($editor['siteUrl'] ?? null)) {
                        return $response->withStatus(400)->withJson(['errors' => "Body office365sharepoint['siteUrl'] is empty or not a string"]);
                    }
                    $data[$key]['siteId'] = Office365SharepointController::getSiteId([
                        'tenantId'     => $editor['tenantId'],
                        'clientId'     => $editor['clientId'],
                        'clientSecret' => $editor['clientSecret'],
                        'siteUrl'      => $editor['siteUrl']
                    ]);
                }
            }
        } elseif ($args['privilege'] == 'admin_shippings') {
            if (!Validator::notEmpty()->arrayType()->validate($data)) {
                return $response->withStatus(400)->withJson(['errors' => 'Body is empty or not an array']);
            } elseif (!Validator::stringType()->validate($data['uri'] ?? null)) {
                return $response->withStatus(400)->withJson(['errors' => "Body uri is empty or not a string"]);
            } elseif (!Validator::stringType()->validate($data['authUri'] ?? null)) {
                return $response->withStatus(400)->withJson(['errors' => "Body authUri is empty or not a string"]);
            } elseif (!Validator::boolType()->validate($data['enabled'] ?? null)) {
                return $response->withStatus(400)->withJson(['errors' => "Body enabled is not set or not a boolean"]);
            }
            $data = [
                'uri'     => rtrim($data['uri'], '/'),
                'authUri' => rtrim($data['authUri'], '/'),
                'enabled' => $data['enabled']
            ];
        }

        $data = json_encode($data, JSON_UNESCAPED_SLASHES);
        if (empty(ConfigurationModel::getByPrivilege(['privilege' => $args['privilege'], 'select' => [1]]))) {
            ConfigurationModel::create(['value' => $data, 'privilege' => $args['privilege']]);
        } else {
            ConfigurationModel::update(['set' => ['value' => $data], 'where' => ['privilege = ?'], 'data' => [$args['privilege']]]);
        }

        HistoryController::add([
            'tableName' => 'configurations',
            'recordId'  => $args['privilege'],
            'eventType' => 'UP',
            'eventId'   => 'configurationUp',
            'info'       => _CONFIGURATION_UPDATED . ' : ' . $args['privilege']
        ]);

        return $response->withJson(['success' => 'success']);
    }

    private static function checkMailer(array $args)
    {
        if (!Validator::stringType()->notEmpty()->validate($args['type'])) {
            return ['errors' => 'Configuration type is missing', 'code' => 400];
        }
        if (!Validator::email()->notEmpty()->validate($args['from'])) {
            return ['errors' => 'Configuration from is missing or not well formatted', 'code' => 400];
        }
        
        if (in_array($args['type'], ['smtp', 'mail'])) {
            $check = Validator::stringType()->notEmpty()->validate($args['host']);
            $check = $check && Validator::intVal()->notEmpty()->validate($args['port']);
            $check = $check && Validator::boolType()->validate($args['auth']);
            if ($args['auth']) {
                $check = $check && Validator::stringType()->notEmpty()->validate($args['user']);
                $check = $check && Validator::stringType()->notEmpty()->validate($args['password']);
            }
            $check = $check && Validator::stringType()->validate($args['secure']);
            if (!$check) {
                return ['errors' => "Configuration data is missing or not well formatted", 'code' => 400];
            }
        }

        return ['success' => 'success'];
    }

    public function getM2MConfiguration(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_parameters', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $xmlConfig = ReceiveMessageExchangeController::readXmlConfig();

        $attachmentType = AttachmentTypeModel::getByTypeId(['select' => ['id'], 'typeId' => $xmlConfig['res_attachments']['attachment_type']]);
        $status         = StatusModel::getById(['select' => ['identifier'], 'id' => $xmlConfig['res_letterbox']['status']]);

        $config = [
            "metadata" => [
                'typeId'           => (int)$xmlConfig['res_letterbox']['type_id'],
                'statusId'         => (int)$status['identifier'],
                'priorityId'       => $xmlConfig['res_letterbox']['priority'],
                'indexingModelId'  => (int)$xmlConfig['res_letterbox']['indexingModelId'],
                'attachmentTypeId' => (int)$attachmentType['id']
            ],
            'basketToRedirect' => $xmlConfig['basketRedirection_afterUpload'][0],
            'communications' => [
                'email' => $xmlConfig['m2m_communication_type']['email'],
                'uri'   => $xmlConfig['m2m_communication_type']['url']
            ]
        ];


        $config['annuary']['enabled']      = $xmlConfig['annuaries']['enabled'] == "true" ? true : false;
        $config['annuary']['organization'] = $xmlConfig['annuaries']['organization'] ?? null;

        if (!is_array($xmlConfig['annuaries']['annuary'])) {
            $xmlConfig['annuaries']['annuary'] = [$xmlConfig['annuaries']['annuary']];
        }
        foreach ($xmlConfig['annuaries']['annuary'] as $value) {
            $config['annuary']['annuaries'][] = [
                'uri'      => (string)$value->uri,
                'baseDN'   => (string)$value->baseDN,
                'login'    => (string)$value->login,
                'password' => (string)$value->password,
                'ssl'      => (string)$value->ssl == "true" ? true : false
            ];
        }

        return $response->withJson(['configuration' => $config]);
    }

    public function updateM2MConfiguration(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_parameters', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();
        $body = $body['configuration'];
        
        if (empty($body)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body is empty']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['basketToRedirect'] ?? null)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body basketToRedirect is empty, not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['metadata']['priorityId'] ?? null)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body[metadata] priorityId is empty or not a string']);
        }

        foreach (['attachmentTypeId', 'indexingModelId', 'statusId', 'typeId'] as $value) {
            if (!Validator::intVal()->notEmpty()->validate($body['metadata'][$value] ?? null)) {
                return $response->withStatus(400)->withJson(['errors' => 'Body[metadata] ' . $value . ' is empty, not a string']);
            }
        }

        $basket = BasketModel::getByBasketId(['select' => [1], 'basketId' => $body['basketToRedirect']]);
        if (empty($basket)) {
            return $response->withStatus(400)->withJson(['errors' => 'Basket not found', 'lang' => 'basketDoesNotExist']);
        }

        $priority = PriorityModel::getById(['select' => [1], 'id' => $body['metadata']['priorityId']]);
        if (empty($priority)) {
            return $response->withStatus(400)->withJson(['errors' => 'Priority not found', 'lang' => 'priorityDoesNotExist']);
        }

        $attachmentType = AttachmentTypeModel::getById(['select' => ['type_id'], 'id' => $body['metadata']['attachmentTypeId']]);
        if (empty($attachmentType)) {
            return $response->withStatus(400)->withJson(['errors' => 'Attachment type not found', 'lang' => 'attachmentTypeDoesNotExist']);
        }

        $indexingModel = IndexingModelModel::getById(['select' => [1], 'id' => $body['metadata']['indexingModelId']]);
        if (empty($indexingModel)) {
            return $response->withStatus(400)->withJson(['errors' => 'Indexing model not found', 'lang' => 'indexingModelDoesNotExist']);
        }

        $status = StatusModel::getByIdentifier(['select' => ['id'], 'identifier' => $body['metadata']['statusId']]);
        if (empty($status)) {
            return $response->withStatus(400)->withJson(['errors' => 'Status not found', 'lang' => 'statusDoesNotExist']);
        }

        $doctype = DoctypeModel::getById(['select' => [1], 'id' => $body['metadata']['typeId']]);
        if (empty($doctype)) {
            return $response->withStatus(400)->withJson(['errors' => 'Doctype not found', 'lang' => 'typeIdDoesNotExist']);
        }

        $customId    = CoreConfigModel::getCustomId();
        $defaultPath = "apps/maarch_entreprise/xml/m2m_config.xml";
        if (!empty($customId)) {
            $path = "custom/{$customId}/{$defaultPath}";
            if (!file_exists($path)) {
                copy($defaultPath, $path);
            }
        } else {
            $path = $defaultPath;
        }

        $communication = [];
        foreach ($body['communications'] as $value) {
            if (!empty($value)) {
                $communication[] = $value;
            }
        }

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => $path]);
        $loadedXml->res_letterbox->type_id           = $body['metadata']['typeId'];
        $loadedXml->res_letterbox->status            = $status[0]['id'];
        $loadedXml->res_letterbox->priority          = $body['metadata']['priorityId'];
        $loadedXml->res_letterbox->indexingModelId   = $body['metadata']['indexingModelId'];
        $loadedXml->res_attachments->attachment_type = $attachmentType['type_id'];
        $loadedXml->basketRedirection_afterUpload    = $body['basketToRedirect'];
        $loadedXml->m2m_communication                = implode(',', $communication);

        unset($loadedXml->annuaries);
        $loadedXml->annuaries->enabled      = $body['annuary']['enabled'] ? 'true' : 'false';
        $loadedXml->annuaries->organization = $body['annuary']['organization'] ?? '';

        if ($body['annuary']['enabled'] && !empty($body['annuary']['annuaries'])) {
            foreach ($body['annuary']['annuaries'] as $value) {
                $annuary = $loadedXml->annuaries->addChild('annuary');
                $annuary->addChild('uri', $value['uri']);
                $annuary->addChild('baseDN', $value['baseDN']);
                $annuary->addChild('login', $value['login']);
                $annuary->addChild('password', $value['password']);
                $annuary->addChild('ssl', $value['ssl'] ? 'true' : 'false');
            }
        }

        $res = ConfigurationController::formatXml($loadedXml);
        $fp = fopen($path, "w+");
        if ($fp) {
            fwrite($fp, $res);
        }

        return $response->withStatus(204);
    }

    public static function formatXml($simpleXMLElement)
    {
        $xmlDocument = new \DOMDocument('1.0');
        $xmlDocument->preserveWhiteSpace = false;
        $xmlDocument->formatOutput = true;
        $xmlDocument->loadXML($simpleXMLElement->asXML());

        return $xmlDocument->saveXML();
    }

    public function getWatermarkConfiguration(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_parameters', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_parameters_watermark']);
        if (empty($configuration)) {
            return $response->withJson(['configuration' => null]);
        }

        $configuration['value'] = json_decode($configuration['value'], true);

        return $response->withJson(['configuration' => $configuration['value']]);
    }

    public function updateWatermarkConfiguration(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_parameters', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        if (empty($body)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body is empty']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['text'] ?? null)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body text is empty, not a string']);
        } elseif (!Validator::stringType()->notEmpty()->validate($body['font'] ?? null)) {
            return $response->withStatus(400)->withJson(['errors' => 'Body font is empty, not a string']);
        } elseif (!Validator::arrayType()->notEmpty()->validate($body['color'] ?? null) || count($body['color']) != 3) {
            return $response->withStatus(400)->withJson(['errors' => 'Body color is empty or is not an array or does not have values']);
        }

        foreach (['posX', 'posY', 'angle', 'opacity', 'size'] as $value) {
            if (!Validator::numeric()->validate($body[$value] ?? null)) {
                return $response->withStatus(400)->withJson(['errors' => 'Body '.$value.' is not an integer']);
            }
        }

        foreach ($body as $key => $value) {
            if (!in_array($key, ['enabled', 'posX', 'posY', 'angle', 'opacity', 'size', 'text', 'font', 'color'])) {
                unset($body[$key]);
            }
        }

        $body['enabled'] = $body['enabled'] ?? false;
        $value           = json_encode($body);

        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_parameters_watermark']);
        if (empty($configuration)) {
            ConfigurationModel::create(['privilege' => 'admin_parameters_watermark', 'value' => $value]);
        } else {
            ConfigurationModel::update(['set' => ['value' => $value], 'where' => ['privilege = ?'], 'data' => ['admin_parameters_watermark']]);
        }

        return $response->withStatus(204);
    }

    public function getSedaExportConfiguration(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_parameters', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $config = CoreConfigModel::getJsonLoaded(['path' => CoreConfigModel::getConfigPath()]);

        if (empty($config['exportSeda'])) {
            return $response->withJson(['exportSeda' => []]);
        }
        $config = $config['exportSeda'];
        unset($config['token']);

        return $response->withJson(['exportSeda' => $config]);
    }
}
