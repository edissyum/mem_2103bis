<?php
/*
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*/

/****************************************************************************/
/*                                                                          */
/*                                                                          */
/*               THIS PAGE CAN NOT BE OVERWRITTEN IN A CUSTOM               */
/*                                                                          */
/*                                                                          */
/* **************************************************************************/

/**
 * @defgroup core Framework core
 */

/**
 * @brief   Contains all the functions to load core and modules
 *
 * @file
 *
 * @author  Laurent Giovannoni  <dev@maarch.org>
 * @date $date$
 *
 * @version $Revision$
 * @ingroup core
 */

/**
 * @brief   Contains all the functions to load core and modules
 *
 * <ul>
 * <li>Loads core tables into session</li>
 * <li>Loads modules settings into session</li>
 * <li>Builds the application menu</li>
 *  <li>Management and building the framework</li>
 *  <li>Modules services loading</li>
 *  <li>Execution of the module services </li>
 *</ul>
 *
 * @ingroup core
 */
class core_tools extends functions
{
    /**
     * Loads language variables into session.
     */
    public static function load_lang($lang = 'fr', $maarch_directory = '', $maarch_apps = '')
    {
        if (isset($_SESSION['config']['lang']) && !empty($_SESSION['config']['lang'])) {
            $lang = $_SESSION['config']['lang'];
        }
        if (isset($_SESSION['config']['corepath']) && !empty($_SESSION['config']['corepath'])) {
            $maarch_directory = $_SESSION['config']['corepath'];
        }
        if (isset($_SESSION['config']['app_id']) && !empty($_SESSION['config']['app_id'])) {
            $maarch_apps = $_SESSION['config']['app_id'];
        }
        //Loading custom lang file if present, this means that language constants are defined in the custom language file before other language files
        if (isset($_SESSION['custom_override_id']) && !empty($_SESSION['custom_override_id'])) {
            self::load_lang_custom_override($_SESSION['custom_override_id']);
        }

        if (isset($lang) && file_exists($maarch_directory.'apps/maarch_entreprise/lang'.DIRECTORY_SEPARATOR.$lang.'.php')) {
            include $maarch_directory.'apps/maarch_entreprise/lang'.DIRECTORY_SEPARATOR.$lang.'.php';
        } else {
            $_SESSION['error'] = 'Language file missing';
        }
        if (isset($_SESSION['modules'])) {
            self::load_lang_modules($_SESSION['modules']);
        }
    }

    /**
     * Loads language variables of each module.
     *
     * @param $modules array Enabled modules of the application
     */
    private static function load_lang_modules($modules)
    {
        for ($i = 0; $i < count($modules); ++$i) {
            $file_path = 'custom'
                .DIRECTORY_SEPARATOR.$_SESSION['custom_override_id']
                .DIRECTORY_SEPARATOR.'modules'.DIRECTORY_SEPARATOR
                .$modules[$i]['moduleid'].DIRECTORY_SEPARATOR
                .'lang'.DIRECTORY_SEPARATOR
                .$_SESSION['config']['lang'].'.php';
            if (!file_exists($file_path)) {
                $file_path = 'modules'
                .DIRECTORY_SEPARATOR.$modules[$i]['moduleid']
                .DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR
                .$_SESSION['config']['lang'].'.php';
            }
            if (isset($_SESSION['config']['lang']) && file_exists($file_path)) {
                include $file_path;
            } elseif ($_SESSION['config']['debug'] === 'true') {
                $_SESSION['info'] .= 'Language file missing for module : '
                .$modules[$i]['moduleid'].'<br/>';
            }
        }
    }

    private static function load_lang_custom_override($custom_id)
    {
        $pathname = $_SESSION['config']['corepath'].'custom'.DIRECTORY_SEPARATOR.$custom_id.DIRECTORY_SEPARATOR.$_SESSION['config']['lang'].'.php';

        if (file_exists($pathname)) {
            include $pathname;
        }
    }
}
