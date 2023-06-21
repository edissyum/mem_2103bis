<?php
/*
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*/

/**
* @brief   Contains all the various functions of this application.
*
* @file
* @author Claire Figueras <dev@maarch.org>
* @date $date$
* @version $Revision$
* @ingroup core
*/

/**
* @brief   Contains all the various functions of this application.
*
* <ul>
*  <li>The toolkit of the Maarch framework</li>
*  <li>Management of variables format</li>
*  <li>Management of date format</li>
* </ul>
* @ingroup core
*/
class functions
{
    /**
    *
    * @deprecated
         */
    private $f_page;


    /**
    * Protects string to insert in the database
    *
    * @param  $string string String to format
    * @return Formated date
    */
    public function protect_string_db($string, $databasetype = '', $full='yes')
    {
        if (isset($_SESSION['config']['databasetype']) && !empty($_SESSION['config']['databasetype'])) {
            $databasetype = $_SESSION['config']['databasetype'];
        }
        if ($databasetype  == "SQLSERVER") {
            $string = str_replace("'", "''", $string);
            $string = str_replace("\\", "", $string);
        } elseif ($databasetype  == "ORACLE") {
            $string = str_replace("'", "''", $string);
            $string = str_replace("\\", "", $string);
        } elseif (($databasetype  == "MYSQL")) {
            $string = addslashes($string);
        } elseif (($databasetype  == "POSTGRESQL")) {
            $string = str_replace("&#039;", "'", $string);
            $string = pg_escape_string($string);
        }

        if ($full == 'yes') {
            $string=str_replace(';', ' ', $string);
            $string=str_replace('--', '-', $string);
        }
        
        return $string;
    }


    /**
    * Cleans html string, replacing entities by utf-8 code
    *
    * @param  $var string  String to clean
    * @return Cleaned string
    */
    public function wash_html($var, $mode="UNICODE")
    {
        if ($mode == "UNICODE") {
            $var = str_replace("<br/>", "\\n", $var);
            $var = str_replace("<br />", "\\n", $var);
            $var = str_replace("<br/>", "\\n", $var);
            $var = str_replace("&nbsp;", " ", $var);
            $var = str_replace("&eacute;", "\u00e9", $var);
            $var = str_replace("&egrave;", "\u00e8", $var);
            $var = str_replace("&ecirc;", "\00ea", $var);
            $var = str_replace("&agrave;", "\u00e0", $var);
            $var = str_replace("&acirc;", "\u00e2", $var);
            $var = str_replace("&icirc;", "\u00ee", $var);
            $var = str_replace("&ocirc;", "\u00f4", $var);
            $var = str_replace("&ucirc;", "\u00fb", $var);
            $var = str_replace("&acute;", "\u0027", $var);
            $var = str_replace("&deg;", "\u00b0", $var);
            $var = str_replace("&rsquo;", "\u2019", $var);
        } elseif ($mode == 'NO_ACCENT') {
            $var = str_replace("<br/>", "\\n", $var);
            $var = str_replace("<br />", "\\n", $var);
            $var = str_replace("<br/>", "\\n", $var);
            $var = str_replace("&nbsp;", " ", $var);
            $var = str_replace("&eacute;", "e", $var);
            $var = str_replace("&egrave;", "e", $var);
            $var = str_replace("&ecirc;", "e", $var);
            $var = str_replace("&agrave;", "a", $var);
            $var = str_replace("&acirc;", "a", $var);
            $var = str_replace("&icirc;", "i", $var);
            $var = str_replace("&ocirc;", "o", $var);
            $var = str_replace("&ucirc;", "u", $var);
            $var = str_replace("&acute;", "", $var);
            $var = str_replace("&deg;", "o", $var);
            $var = str_replace("&rsquo;", "'", $var);

            // AT LAST
            $var = str_replace("&", " et ", $var);
        } else {
            $var = str_replace("<br/>", "\\n", $var);
            $var = str_replace("<br />", "\\n", $var);
            $var = str_replace("<br/>", "\\n", $var);
            $var = str_replace("&nbsp;", " ", $var);
            $var = str_replace("&eacute;", "é", $var);
            $var = str_replace("&egrave;", "è", $var);
            $var = str_replace("&ecirc;", "ê", $var);
            $var = str_replace("&agrave;", "à", $var);
            $var = str_replace("&acirc;", "â", $var);
            $var = str_replace("&icirc;", "î", $var);
            $var = str_replace("&ocirc;", "ô", $var);
            $var = str_replace("&ucirc;", "û", $var);
            $var = str_replace("&acute;", "", $var);
            $var = str_replace("&deg;", "°", $var);
            $var = str_replace("&rsquo;", "'", $var);
        }
        return $var;
    }

    /**
    *  Compares to date
    *
    * @param  $date1 date First date
    * @param  $date2 date Second date
    * @return date1 if the first date is the greater, date2 if the second date or "equal" otherwise
    */
    public function compare_date($date1, $date2)
    {
        $date1 = strtotime($date1);
        $date2 = strtotime($date2);
        if ($date1 > $date2) {
            $result = "date1";
        } elseif ($date1 < $date2) {
            $result = "date2";
        } elseif ($date1 = $date2) {
            $result = "equal";
        }
        return $result;
    }

    /**
    *  Checks if a directory is empty
    *
    * @param  $dir string The directory to check
    * @return bool True if empty, False otherwise
    */
    public function isDirEmpty($dir)
    {
        $dir = opendir($dir);
        $isEmpty = true;
        while (($entry = readdir($dir)) !== false) {
            if ($entry !== '.' && $entry !== '..'  && $entry !== '.svn') {
                $isEmpty = false;
                break;
            }
        }
        closedir($dir);
        return $isEmpty;
    }
    
    /**
    * Convert an object to an array
    * @param  $object object to convert
    */
    public function object2array($object)
    {
        $return = null;
        if (is_array($object)) {
            foreach ($object as $key => $value) {
                $return[$key] = $this->object2array($value);
            }
        } else {
            if (is_object($object)) {
                $var = get_object_vars($object);
                if ($var) {
                    foreach ($var as $key => $value) {
                        $return[$key] = ($key && !$value) ? null : $this->object2array($value);
                    }
                } else {
                    return $object;
                }
            } else {
                return $object;
            }
        }
        return $return;
    }

    /**
    * Function to encode an url in base64
    */
    public function base64UrlEncode($data)
    {
        return strtr(base64_encode($data), '+/', '-_,');
    }

    /**
    * Function to decode an url encoded in base64
    */
    public function base64UrlDecode($base64)
    {
        return base64_decode(strtr($base64, '-_,', '+/'));
    }

    /**
    * Encrypt a text
    * @param $text string to encrypt
    */
    public function encrypt($sensitiveData)
    {
        $publicKeyPath = $this->getPublicKeyPath();
        if (file_exists($publicKeyPath)) {
            $pubKey = openssl_pkey_get_public('file://'.$publicKeyPath);
            if (!$pubKey) {
                return false;
            } else {
                $encryptedData = "";
                openssl_public_encrypt($sensitiveData, $encryptedData, $pubKey);
                //base 64 encode to use it in url
                return $this->base64UrlEncode($encryptedData);
            }
        } else {
            return false;
        }
    }

    /**
    * Decrypt a text
    * @param $text string to decrypt
    */
    public function decrypt($encryptedData)
    {
        $privateKeyPath = $this->getPrivateKeyPath();
        if (file_exists($privateKeyPath)) {
            $passphrase = "";
            $privateKey = openssl_pkey_get_private('file://'.$privateKeyPath, $passphrase);
            if (!$privateKey) {
                return false;
            } else {
                $decryptedData = "";
                openssl_private_decrypt($this->base64UrlDecode($encryptedData), $decryptedData, $privateKey);
                return $decryptedData;
            }
        } else {
            return false;
        }
    }

    /**
    * return the path of the private key path
    */
    public function getPrivateKeyPath()
    {
        if (file_exists($_SESSION['config']['corepath'].'custom'.DIRECTORY_SEPARATOR.$_SESSION['custom_override_id'].DIRECTORY_SEPARATOR.'apps'.DIRECTORY_SEPARATOR.$_SESSION['config']['app_id'].DIRECTORY_SEPARATOR.'xml'.DIRECTORY_SEPARATOR.'config.xml')) {
            $path = $_SESSION['config']['corepath'].'custom'.DIRECTORY_SEPARATOR.$_SESSION['custom_override_id'].DIRECTORY_SEPARATOR.'apps'.DIRECTORY_SEPARATOR.$_SESSION['config']['app_id'].DIRECTORY_SEPARATOR.'xml'.DIRECTORY_SEPARATOR.'config.xml';
        } else {
            $path = 'apps'.DIRECTORY_SEPARATOR.$_SESSION['config']['app_id'].DIRECTORY_SEPARATOR.'xml'.DIRECTORY_SEPARATOR.'config.xml';
        }
        $xmlconfig = simplexml_load_file($path);
        $CRYPT = $xmlconfig->CRYPT;
        return (string) $CRYPT->pathtoprivatekey;
    }

    /**
    * return the path of the public key path
    */
    public function getPublicKeyPath()
    {
        if (file_exists($_SESSION['config']['corepath'].'custom'.DIRECTORY_SEPARATOR.$_SESSION['custom_override_id'].DIRECTORY_SEPARATOR.'apps'.DIRECTORY_SEPARATOR.$_SESSION['config']['app_id'].DIRECTORY_SEPARATOR.'xml'.DIRECTORY_SEPARATOR.'config.xml')) {
            $path = $_SESSION['config']['corepath'].'custom'.DIRECTORY_SEPARATOR.$_SESSION['custom_override_id'].DIRECTORY_SEPARATOR.'apps'.DIRECTORY_SEPARATOR.$_SESSION['config']['app_id'].DIRECTORY_SEPARATOR.'xml'.DIRECTORY_SEPARATOR.'config.xml';
        } else {
            $path = 'apps'.DIRECTORY_SEPARATOR.$_SESSION['config']['app_id'].DIRECTORY_SEPARATOR.'xml'.DIRECTORY_SEPARATOR.'config.xml';
        }
        $xmlconfig = simplexml_load_file($path);
        $CRYPT = $xmlconfig->CRYPT;
        return $CRYPT->pathtopublickey;
    }

    /**
    * Return the file's extention of a file
    * @param  $sFullPath string path of the file
    */
    public function extractFileExt($sFullPath)
    {
        $sName = $sFullPath;
        if (strpos($sName, ".") == 0) {
            $extractFileExt = "";
        } else {
            $extractFileExt = explode(".", $sName);
        }
        if ($extractFileExt <> '') {
            return $extractFileExt[count($extractFileExt) - 1];
        }
        return '';
    }

    /**
    * Browse each file and folder in the folder and return true if the folder is not empty
    * @param  $folder path string of the folder
    */
    public function isDirNotEmpty($folder)
    {
        $foundDoc = false;
        $classScan = dir($folder);
        while (($fileScan = $classScan->read()) != false) {
            if ($fileScan == '.' || $fileScan == '..' || $fileScan == '.svn') {
                continue;
            } else {
                $foundDoc = true;
                break;
            }
        }
        return $foundDoc;
    }

    /**
    * xss mitigation functions
    * Return protected chars
    * @param  $data to encode
    * @param  $encoding ut8 by default
    */
    public static function xssafe($data, $encoding='UTF-8')
    {
        if (!is_array($data)) {
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML401, $encoding);
        } else {
            return $data;
        }
    }

    /**
    * xss mitigation functions
    * Return protected chars
    * @param  $data to encode
    */
    public static function xecho($data)
    {
        echo functions::xssafe($data);
    }
}
