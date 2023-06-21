<?php
// EDISSYUM - AMO01 - PJ_LINKS : Script Purge Nextcloud
chdir('../../..');
require 'vendor/autoload.php';
use Configuration\models\ConfigurationModel;
use SrcCore\models\CurlModel;
use SrcCore\models\DatabaseModel;
use SrcCore\models\PasswordModel;
use SrcCore\models\ValidatorModel;

function tag_contents($string, $tag_open, $tag_close){
    $result = [];
    foreach (explode($tag_open, $string) as $key => $value) {
        if (strpos($value, $tag_close) !== FALSE) {
            $result[] = substr($value, 0, strpos($value, $tag_close));
        }
    }
    return $result;
}

$customId = "mem_21_03";

list($configurationNC,$customId) = NextcloudScript::getConfig($argv);
$fieldsFilled = (isset($configurationNC['url']) && !empty($configurationNC['url'])) && (isset($configurationNC['password']) && !empty($configurationNC['password'])) && (isset($configurationNC['username']) && !empty($configurationNC['username']));

if ($fieldsFilled) {
    $res = NextcloudScript::checkNextcloudConnection($configurationNC);
    $configurationXml = NextcloudScript::getXmlLoaded(['path' => 'bin/external/nextcloud/config.xml', 'customId' => $customId]);
    if (empty($configurationXml)) {
        NextcloudScript::writeLog(['message' => "[ERROR] [CLOSE_RESOURCE] File bin/external/nextcloud/config.xml does not exist"]);
        exit();
    } elseif (empty($configurationXml->daysToExpire)) {
        NextcloudScript::writeLog(['message' => "[ERROR] [CLOSE_RESOURCE] File bin/external/nextcloud/config.xml is not filled enough"]);
        return;
    }
    $xmlNextcloudConfig['daysToExpire'] = (string)$configurationXml->daysToExpire;
    if ($res['result'] == "OK") {
        $output_final = '';
        foreach ($res['output'] as $out) {
            if (strpos($out, 'remote.php')) {
                $output_final = $out;
            }
        }
        $foldersAndFiles = tag_contents($output_final , "<d:response>" , "</d:response>");
        $hrefs = tag_contents($output_final , "<d:href>" , "</d:href>"); // contains absolute path of each file
        $datesLastModified = tag_contents($output_final , "<d:getlastmodified>" , "</d:getlastmodified>");
        $toDelete = [];
        if (count($hrefs) == count($datesLastModified)) {
            $expired_date = date('Y-m-d H:m:s', strtotime("-".$xmlNextcloudConfig['daysToExpire']." days"));
            echo "Date d'expiration calculée (".$xmlNextcloudConfig['daysToExpire']." jours) : " . date("Y-m-d H:i:s", strtotime($expired_date)) . "\n";
            for ($i = 1; $i < count($hrefs); $i++) { // do not use [0], it refers to absolute path of nextcloud-user folder

                if (date("Y-m-d H:i:s", strtotime($expired_date)) > date("Y-m-d H:i:s", strtotime($datesLastModified[$i]))) {
                    $toDelete[] = array('link' => $hrefs[$i], 'date' => $datesLastModified[$i]);
                    echo " /!\ va être supprimé. /!\ " . "href : " . $hrefs[$i] .' Dernière modification : '. date("Y-m-d H:i:s", strtotime($datesLastModified[$i])) . "\n";
                } else {
                    echo "href : " . $hrefs[$i] .' Dernière modification : '. date("Y-m-d H:i:s", strtotime($datesLastModified[$i])) . "\n";
                }
            }
        }
        if (count($toDelete) > 0) {
            NextcloudScript::delFilesAndFolders($configurationNC,$toDelete);
        }
    } else {
        var_dump($res['error']);
    }
} else {
    var_dump('champs requis non-remplis');
}



class NextcloudScript
{
    public static function getConfig($args)
    {
        $customId = null;
        if (!empty($args[1]) && $args[1] == '--customId' && !empty($args[2])) {
            $customId = $args[2];
        }
        \SrcCore\models\DatabasePDO::reset();
        new \SrcCore\models\DatabasePDO(['customId' => $customId]);
        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_attachments_hosts', 'select' => ['value']]);
        $configuration = !empty($configuration['value']) ? json_decode($configuration['value'], true) : [];
        return array($configuration['nextcloud'],$customId);
    }

    public static function checkNextcloudConnection($configurationNC)
    {
        $username = $configurationNC['username'];
        $password = $configurationNC['password'];
        $dossier = $configurationNC['folderNextcloud'];
        $url = $configurationNC['url'] . '/remote.php/dav/files/'.$username.'/'.$dossier. "/";
        $test_valid = "curl -s -u '" . $username . ":" . $password . "' '".$url . "/'"." -X 'PROPFIND' -H 'Content-Type: text/xml' -i" ;
        exec($test_valid, $output);
        $output_string = implode('', $output);

        if (strpos($output_string,'<d:status>HTTP/1.1 200 OK</d:status>')!== false) {
            return ['result' => 'OK', 'output' => $output];
        } elseif (strpos($output_string,'Username or password was incorrect')!== false) {
            $result = 'WrongUSERorPASS';
        } elseif (strpos($output_string,'could not be located')!== false) {
            $result = 'WrongFolder';
        } else {
            $result = "WrongURL";
        }
        return ['error' => $result];
    }
    public static function delFilesAndFolders($configurationNC,$toDelete)
    {
        $username = $configurationNC['username'];
        $password = $configurationNC['password'];
        foreach ($toDelete as $file) {
            $url = $configurationNC['url'] . $file['link'];
            $curl_delete = "curl -s -u '" . $username . ":" . $password . "' '".$url . "/'"." -X 'DELETE' -H 'Content-Type: text/xml' -i" ;
            exec($curl_delete, $output);
        }
    }

    public static function getXmlLoaded(array $args)
    {
        if (!empty($args['customId']) && file_exists("custom/{$args['customId']}/{$args['path']}")) {
            $path = "custom/{$args['customId']}/{$args['path']}";
        }
        if (empty($path)) {
            $path = $args['path'];
        }
        $xmlfile = null;
        if (file_exists($path)) {
            $xmlfile = simplexml_load_file($path);
        }
        return $xmlfile;
    }

    public static function writeLog(array $args)
    {
        $file = fopen('bin/external/nextcloud/nextcloudScript.log', 'a');
        fwrite($file, '[' . date('Y-m-d H:i:s') . '] ' . $args['message'] . PHP_EOL);
        fclose($file);
        if (strpos($args['message'], '[ERROR]') === 0) {
            \SrcCore\controllers\LogsController::add([
                'isTech'    => true,
                'moduleId'  => 'nextcloud',
                'level'     => 'ERROR',
                'tableName' => '',
                'recordId'  => 'Nextcloud',
                'eventType' => 'Nextcloud',
                'eventId'   => $args['message']
            ]);
        } else {
            \SrcCore\controllers\LogsController::add([
                'isTech'    => true,
                'moduleId'  => 'nextcloud',
                'level'     => 'INFO',
                'tableName' => '',
                'recordId'  => 'Nextcloud',
                'eventType' => 'Nextcloud',
                'eventId'   => $args['message']
            ]);
        }
        \History\models\BatchHistoryModel::create(['info' => $args['message'], 'module_name' => 'nextcloud']);
    }
}
// END EDISSYUM - AMO01 - PJ_LINKS : Script Purge Nextcloud
?>
