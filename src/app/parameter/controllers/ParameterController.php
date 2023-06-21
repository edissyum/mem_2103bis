<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

* @brief   ParametersController
* @author  dev <dev@maarch.org>
* @ingroup core
*/

/**
 * @brief Parameter Controller
 * @author dev@maarch.org
 */

namespace Parameter\controllers;

use Group\controllers\PrivilegeController;
use History\controllers\HistoryController;
use Parameter\models\ParameterModel;
use Respect\Validation\Validator;
use Slim\Http\Request;
use Slim\Http\Response;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\DatabaseModel;
use Configuration\models\ConfigurationModel; // EDISSYUM - AMO01 - One_Line - Rajout d'une option -> mettre PJ dans mail via liens éphémères

class ParameterController
{
    public function get(Request $request, Response $response)
    {
        $where = [];
        $data  = [];
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_parameters', 'userId' => $GLOBALS['id']])) {
            $where = ['id = ?'];
            $data  = ['traffic_record_summary_sheet'];
        }

        $parameters = ParameterModel::get(['where' => $where, 'data' => $data]);

        foreach ($parameters as $key => $parameter) {
            if (!empty($parameter['param_value_string'])) {
                $parameters[$key]['value'] = $parameter['param_value_string'];
            } elseif (is_int($parameter['param_value_int'])) {
                $parameters[$key]['value'] = $parameter['param_value_int'];
            } elseif (!empty($parameter['param_value_date'])) {
                $parameters[$key]['value'] = $parameter['param_value_date'];
            }
        }

        $parameterIds = array_column($parameters, 'id');
        if (!in_array('loginpage_message', $parameterIds)) {
            $parameters[] = [
                "description"        => null,
                "id"                 => "loginpage_message",
                "param_value_date"   => null,
                "param_value_int"    => null,
                "param_value_string" => "",
                "value"              => ""
            ];
        }
        if (!in_array('homepage_message', $parameterIds)) {
            $parameters[] = [
                "description"        => null,
                "id"                 => "homepage_message",
                "param_value_date"   => null,
                "param_value_int"    => null,
                "param_value_string" => "",
                "value"              => ""
            ];
        }

        return $response->withJson(['parameters' => $parameters]);
    }

    public function getById(Request $request, Response $response, array $aArgs)
    {
        // EDISSYUM - NCH01 Rajout de la confidentialité des contacts | rajout de contactsConfidentiality
        // EDISSYUM - NCH01 Amélioration de l'écran d'impression en masse | rajout de summarySheetMandatory
        // EDISSYUM - EME01 Ajout d'une fenêtre pour administrer la recherche des dossiers | rajout de showFoldersByDefault
        if (!in_array($aArgs['id'], ['minimumVisaRole', 'maximumSignRole', 'suggest_links_n_days_ago', 'workflowSignatoryRole', 'contactsConfidentiality', 'summarySheetMandatory', 'showFoldersByDefault']) && !PrivilegeController::hasPrivilege(['privilegeId' => 'admin_parameters', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $parameter = ParameterModel::getById(['id' => $aArgs['id']]);

        if (empty($parameter)) {
            return $response->withStatus(400)->withJson(['errors' => 'Parameter not found']);
        }

        return $response->withJson(['parameter' => $parameter]);
    }

    public function create(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_parameters', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $data = $request->getParams();

        $check = Validator::stringType()->notEmpty()->validate($data['id']) && preg_match("/^[\w-]*$/", $data['id']);
        $check = $check && (empty($data['param_value_int']) || Validator::intVal()->validate($data['param_value_int']));
        $check = $check && (empty($data['param_value_string']) || Validator::stringType()->validate($data['param_value_string']));
        if (!$check) {
            return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
        }

        $parameter = ParameterModel::getById(['id' => $data['id']]);
        if (!empty($parameter)) {
            return $response->withStatus(400)->withJson(['errors' => _PARAMETER_ID_ALREADY_EXISTS]);
        }

        ParameterModel::create($data);
        if (strpos($data['id'], 'chrono_') !== false) {
            DatabaseModel::createSequence(['id' => $data['id'] . '_seq', 'value' => $data['param_value_int']]);
        }
        HistoryController::add([
            'tableName' => 'parameters',
            'recordId'  => $data['id'],
            'eventType' => 'ADD',
            'info'      => _PARAMETER_CREATION . " : {$data['id']}",
            'moduleId'  => 'parameter',
            'eventId'   => 'parameterCreation',
        ]);

        return $response->withJson(['success' => 'success']);
    }

    public function update(Request $request, Response $response, array $args)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_parameters', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        $body = $request->getParsedBody();

        $customId = CoreConfigModel::getCustomId();
        if (in_array($args['id'], ['logo', 'bodyImage'])) {
            if (empty($customId)) {
                return $response->withStatus(400)->withJson(['errors' => 'A custom is needed for this operation']);
            }

            $tmpPath = CoreConfigModel::getTmpPath();
            if (!is_dir("custom/{$customId}/img")) {
                mkdir("custom/{$customId}/img", 0755, true);
            }
            if ($args['id'] == 'logo') {
                if (strpos($body['image'], 'data:image/svg+xml;base64,') === false) {
                    return $response->withStatus(400)->withJson(['errors' => 'Body image is not a base64 image']);
                }
                $tmpFileName  = $tmpPath . 'parameter_logo_' . rand() . '_file.svg';
                $body['logo'] = str_replace('data:image/svg+xml;base64,', '', $body['image']);
                $file         = base64_decode($body['logo']);
                file_put_contents($tmpFileName, $file);

                $size = strlen($file);
                if ($size > 5000000) {
                    return $response->withStatus(400)->withJson(['errors' => 'Logo size is not allowed']);
                }
                copy($tmpFileName, "custom/{$customId}/img/logo.svg");
            } elseif ($args['id'] == 'bodyImage') {
                if (strpos($body['image'], 'data:image/jpeg;base64,') === false) {
                    if (!is_file("dist/{$body['image']}")) {
                        return $response->withStatus(400)->withJson(['errors' => 'Body image does not exist']);
                    }
                    copy("dist/{$body['image']}", "custom/{$customId}/img/bodylogin.jpg");
                } else {
                    $tmpFileName   = $tmpPath . 'parameter_body_' . rand() . '_file.jpg';
                    $body['image'] = str_replace('data:image/jpeg;base64,', '', $body['image']);
                    $file          = base64_decode($body['image']);
                    file_put_contents($tmpFileName, $file);

                    $size       = strlen($file);
                    $imageSizes = getimagesize($tmpFileName);
                    if ($imageSizes[0] < 1920 || $imageSizes[1] < 1080) {
                        return $response->withStatus(400)->withJson(['errors' => 'Body image is not wide enough']);
                    } elseif ($size > 10000000) {
                        return $response->withStatus(400)->withJson(['errors' => 'Body size is not allowed']);
                    }
                    copy($tmpFileName, "custom/{$customId}/img/bodylogin.jpg");
                }
            }
            if (!empty($tmpFileName) && is_file($tmpFileName)) {
                unset($tmpFileName);
            }
        } elseif (in_array($args['id'], ['applicationName', 'maarchUrl'])) {
            $config = CoreConfigModel::getJsonLoaded(['path' => 'apps/maarch_entreprise/xml/config.json']);
            $config['config'][$args['id']] = $body[$args['id']];
            if (file_exists("custom/{$customId}/apps/maarch_entreprise/xml/config.json")) {
                $fp = fopen("custom/{$customId}/apps/maarch_entreprise/xml/config.json", 'w');
            } else {
                $fp = fopen("apps/maarch_entreprise/xml/config.json", 'w');
            }
            fwrite($fp, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fclose($fp);
        } elseif (in_array($args['id'], ['bindingDocumentFinalAction', 'nonBindingDocumentFinalAction'])) {
            $parameter = ParameterModel::getById(['id' => $args['id']]);
            if (empty($parameter)) {
                return $response->withStatus(400)->withJson(['errors' => 'Parameter not found']);
            }
            if (!in_array($body['param_value_string'], ['restrictAccess', 'transfer', 'copy', 'delete'])) {
                return $response->withStatus(400)->withJson(['errors' => 'param_value_string must be between : restrictAccess, transfer, copy, delete']);
            }
            ParameterModel::update([
                'description'        => '',
                'param_value_string' => $body['param_value_string'],
                'id'                 => $args['id']
            ]);
        } else {
            if (in_array($args['id'], ['minimumVisaRole', 'maximumSignRole'])) {
                if (!Validator::intVal()->validate($body['param_value_int']) || $body['param_value_int'] < 0) {
                    return $response->withStatus(400)->withJson(['errors' => $args['id'] . ' must be a positive numeric']);
                }
            }
            $parameter = ParameterModel::getById(['id' => $args['id']]);
            if (empty($parameter)) {
                if (!in_array($args['id'], ['loginpage_message', 'homepage_message'])) {
                    return $response->withStatus(400)->withJson(['errors' => 'Parameter not found']);
                }
                ParameterModel::create(['id' => $args['id']]);
                if (strpos($args['id'], 'chrono_') !== false) {
                    DatabaseModel::createSequence(['id' => $args['id'] . '_seq']);
                }
            }

            $check = (empty($body['param_value_int']) || Validator::intVal()->validate($body['param_value_int']));
            $check = $check && (empty($body['param_value_string']) || Validator::stringType()->validate($body['param_value_string']));
            if (!$check) {
                return $response->withStatus(400)->withJson(['errors' => 'Bad Request']);
            }

            $body['id'] = $args['id'];
            ParameterModel::update($body);
            if (strpos($body['id'], 'chrono_') !== false) {
                DatabaseModel::updateSequence(['id' => $body['id'] . '_seq', 'value' => $body['param_value_int']]);
            }
        }

        HistoryController::add([
            'tableName' => 'parameters',
            'recordId'  => $args['id'],
            'eventType' => 'UP',
            'info'      => _PARAMETER_MODIFICATION . " : {$args['id']}",
            'moduleId'  => 'parameter',
            'eventId'   => 'parameterModification',
        ]);

        return $response->withStatus(204);
    }

    public function delete(Request $request, Response $response, array $aArgs)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_parameters', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        }

        ParameterModel::delete(['id' => $aArgs['id']]);
        if (strpos($aArgs['id'], 'chrono_') !== false) {
            DatabaseModel::deleteSequence(['id' => $aArgs['id'] . '_seq']);
        }
        HistoryController::add([
            'tableName' => 'parameters',
            'recordId'  => $aArgs['id'],
            'eventType' => 'DEL',
            'info'      => _PARAMETER_SUPPRESSION . " : {$aArgs['id']}",
            'moduleId'  => 'parameter',
            'eventId'   => 'parameterSuppression',
        ]);

        $parameters = ParameterModel::get();
        foreach ($parameters as $key => $parameter) {
            if (!empty($parameter['param_value_string'])) {
                $parameters[$key]['value'] = $parameter['param_value_string'];
            } elseif (!empty($parameter['param_value_int'])) {
                $parameters[$key]['value'] = $parameter['param_value_int'];
            } elseif (!empty($parameter['param_value_date'])) {
                $parameters[$key]['value'] = $parameter['param_value_date'];
            }
        }

        return $response->withJson(['parameters' => $parameters]);
    }

    // EDISSYUM - AMO01 Rajout d'une option -> mettre PJ dans mail via liens éphémères
    public static function check(Request $request, Response $response)
    {
        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_parameters', 'userId' => $GLOBALS['id']])) {
            return false;
        }
        $res = self::checkNextcloudConnection();
        if(empty($res['error'])) {
            return $response->withStatus(200)->withJson(['result' => " OK "]);
        }
        return $response->withStatus(403)->withJson(['error' => print_r($res['error'],True)]);
    }

    public static function checkNextcloudConnection() {
        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_attachments_hosts', 'select' => ['value']]);
        $configuration = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];

        $username = $configuration['nextcloud']['username'];
        $password = $configuration['nextcloud']['password'];
        $dossier = $configuration['nextcloud']['folderNextcloud']. "/";
        $url = $configuration['nextcloud']['url'] . '/remote.php/dav/files/'.$username.'/'.$dossier;
        $test_valid = "curl -u '" . $username . ":" . $password . "' '".$url . "/'"." -X 'PROPFIND' " ;
        exec($test_valid, $output);
        $output_string = implode('', $output);

        if(strpos($output_string,'<d:status>HTTP/1.1 200 OK</d:status>')!== false)
            return ['result' => 'OK'];
        elseif(strpos($output_string,'<s:message>No public access to this resource., Username or password was incorrect, No \'Authorization: Bearer\' header found. Either the client didn\'t send one, or the server is mis-configured, Username or password was incorrect</s:message>')!== false)
            $result = 'WrongUSERorPASS';
        elseif(strpos($output_string,'could not be located</s:message></d:error>')!== false)
            $result = 'WrongFolder';
        else
            $result = "WrongURL";

        return ['error' => $result];
    }
    // END EDISSYUM - AMO01

}
