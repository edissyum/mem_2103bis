<?php
/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.

*
* @brief   TileControllerTest
*
* @author  dev <dev@maarch.org>
* @ingroup core
*/

use PHPUnit\Framework\TestCase;

class TileControllerTest extends TestCase
{
    private static $basket = null;
    private static $folder = null;
    private static $shortcut = null;
    private static $followedMail = null;
    private static $myLastResources = null;
    private static $externalSignatoryBook = null;

    public function testCreate()
    {
        $GLOBALS['login'] = 'jjane';
        $userInfo         = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']    = $userInfo['id'];

        \SrcCore\models\DatabaseModel::delete([
            'table' => 'tiles',
            'where' => ['user_id = ?'],
            'data'  => [6]
        ]);

        $tileController = new \Home\controllers\TileController();

        $environment = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'POST']);
        $request     = \Slim\Http\Request::createFromEnvironment($environment);

        // Basket Error
        $aArgs = [
            'parameters' => ['basketId' => 1, 'groupId' => 2],
            'color'     => '#90caf9',
            'position'  => 0,
            'type'      => 'basket',
            'view'      => 'list',
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($aArgs, $request);

        $response     = $tileController->create($fullRequest, new \Slim\Http\Response());
        $responseBody = json_decode((string) $response->getBody());
        $this->assertSame('Basket is not linked to this group', $responseBody->errors);
    
        // Basket Error
        $aArgs = [
            'parameters' => ['basketId' => 1, 'groupId' => 1],
            'color'     => '#90caf9',
            'position'  => 0,
            'type'      => 'basket',
            'view'      => 'list',
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($aArgs, $request);

        $response     = $tileController->create($fullRequest, new \Slim\Http\Response());
        $responseBody = json_decode((string) $response->getBody());
        
        $this->assertSame('User is not linked to this group', $responseBody->errors);

        // Basket
        $aArgs = [
            'parameters' => ['basketId' => 4, 'groupId' => 4],
            'color'     => '#90caf9',
            'position'  => 0,
            'type'      => 'basket',
            'view'      => 'list',
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($aArgs, $request);

        $response     = $tileController->create($fullRequest, new \Slim\Http\Response());
        $responseBody = json_decode((string) $response->getBody());
        
        $this->assertNotNull($responseBody->id);
        $this->assertIsInt($responseBody->id);
        self::$basket = $responseBody->id;

        // Folder
        $aArgs = [
            'parameters' => ['folderId' => 5],
            'color'     => '#e6ee9c',
            'position'  => 1,
            'type'      => 'folder',
            'view'      => 'summary',
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($aArgs, $request);

        $response     = $tileController->create($fullRequest, new \Slim\Http\Response());
        $responseBody = json_decode((string) $response->getBody());
        
        $this->assertNotNull($responseBody->id);
        $this->assertIsInt($responseBody->id);
        self::$folder = $responseBody->id;

        // Shortcut
        $aArgs = [
            'parameters' => ['privilegeId' => 'adv_search_mlb'],
            'color'     => '#90caf9',
            'position'  => 2,
            'type'      => 'shortcut',
            'view'      => 'summary',
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($aArgs, $request);

        $response     = $tileController->create($fullRequest, new \Slim\Http\Response());
        $responseBody = json_decode((string) $response->getBody());

        $this->assertNotNull($responseBody->id);
        $this->assertIsInt($responseBody->id);
        self::$shortcut = $responseBody->id;

        // FollowedMail
        $aArgs = [
            'parameters' => ['chartType' => 'vertical-bar', 'chartMode' => 'status'],
            'color'     => '#a5d6a7',
            'position'  => 3,
            'type'      => 'followedMail',
            'view'      => 'chart',
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($aArgs, $request);

        $response     = $tileController->create($fullRequest, new \Slim\Http\Response());
        $responseBody = json_decode((string) $response->getBody());
        
        $this->assertNotNull($responseBody->id);
        $this->assertIsInt($responseBody->id);
        self::$followedMail = $responseBody->id;

        // Last Resources
        $aArgs = [
            'parameters' => ['chartType' => 'pie', 'chartMode' => 'destination'],
            'color'     => '#ce93d8',
            'position'  => 4,
            'type'      => 'myLastResources',
            'view'      => 'chart',
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($aArgs, $request);

        $response     = $tileController->create($fullRequest, new \Slim\Http\Response());
        $responseBody = json_decode((string) $response->getBody());

        $this->assertNotNull($responseBody->id);
        $this->assertIsInt($responseBody->id);
        self::$myLastResources = $responseBody->id;

        // External signatory book
        $aArgs = [
            'color'     => '#90caf9',
            'position'  => 5,
            'type'      => 'externalSignatoryBook',
            'view'      => 'list',
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($aArgs, $request);

        $response     = $tileController->create($fullRequest, new \Slim\Http\Response());
        $responseBody = json_decode((string) $response->getBody());

        $this->assertNotNull($responseBody->id);
        $this->assertIsInt($responseBody->id);
        self::$externalSignatoryBook = $responseBody->id;

        $GLOBALS['login'] = 'superadmin';
        $userInfo         = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']    = $userInfo['id'];
    }

    public function testGet()
    {
        $GLOBALS['login'] = 'jjane';
        $userInfo         = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']    = $userInfo['id'];

        $tileController = new \Home\controllers\TileController();

        //  READ
        $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'GET']);
        $request        = \Slim\Http\Request::createFromEnvironment($environment);

        $response     = $tileController->get($request, new \Slim\Http\Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($responseBody['tiles']);
        $this->assertNotEmpty($responseBody['tiles']);
        $this->assertSame(6, count($responseBody['tiles']));

        foreach ($responseBody['tiles'] as $tile) {
            $this->assertIsInt($tile['id']);
            $this->assertIsInt($tile['userId']);
            $this->assertNotNull($tile['type']);
            $this->assertNotNull($tile['view']);
            $this->assertIsInt($tile['position']);
            $this->assertNotNull($tile['color']);
        }

        $GLOBALS['login'] = 'superadmin';
        $userInfo         = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']    = $userInfo['id'];
    }

    public function testGetById()
    {
        $GLOBALS['login'] = 'jjane';
        $userInfo         = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']    = $userInfo['id'];

        $tileController = new \Home\controllers\TileController();

        //  READ
        $environment = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'GET']);
        $request     = \Slim\Http\Request::createFromEnvironment($environment);

        // Basket
        $response     = $tileController->getById($request, new \Slim\Http\Response(), ['id' => self::$basket]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(self::$basket, $responseBody['tile']['id']);
        $this->assertIsArray($responseBody['tile']);
        $this->assertSame('#90caf9', $responseBody['tile']['color']);
        $this->assertSame('basket', $responseBody['tile']['type']);
        $this->assertSame('list', $responseBody['tile']['view']);
        $this->assertSame(0, $responseBody['tile']['position']);
        $this->assertSame(6, $responseBody['tile']['user_id']);
        $this->assertIsArray($responseBody['tile']['parameters']);
        $this->assertSame(4, $responseBody['tile']['parameters']['groupId']);
        $this->assertSame(4, $responseBody['tile']['parameters']['basketId']);

        // Folder
        $response     = $tileController->getById($request, new \Slim\Http\Response(), ['id' => self::$folder]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(self::$folder, $responseBody['tile']['id']);
        $this->assertIsArray($responseBody['tile']);
        $this->assertSame('#e6ee9c', $responseBody['tile']['color']);
        $this->assertSame('folder', $responseBody['tile']['type']);
        $this->assertSame('summary', $responseBody['tile']['view']);
        $this->assertSame(1, $responseBody['tile']['position']);
        $this->assertSame(6, $responseBody['tile']['user_id']);
        $this->assertIsArray($responseBody['tile']['parameters']);
        $this->assertSame(5, $responseBody['tile']['parameters']['folderId']);

        // Shortcut
        $response     = $tileController->getById($request, new \Slim\Http\Response(), ['id' => self::$shortcut]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(self::$shortcut, $responseBody['tile']['id']);
        $this->assertIsArray($responseBody['tile']);
        $this->assertSame('#90caf9', $responseBody['tile']['color']);
        $this->assertSame('shortcut', $responseBody['tile']['type']);
        $this->assertSame('summary', $responseBody['tile']['view']);
        $this->assertSame(2, $responseBody['tile']['position']);
        $this->assertSame(6, $responseBody['tile']['user_id']);
        $this->assertIsArray($responseBody['tile']['parameters']);
        $this->assertSame('adv_search_mlb', $responseBody['tile']['parameters']['privilegeId']);

        // FollowedMail
        $response     = $tileController->getById($request, new \Slim\Http\Response(), ['id' => self::$followedMail]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(self::$followedMail, $responseBody['tile']['id']);
        $this->assertIsArray($responseBody['tile']);
        $this->assertSame('#a5d6a7', $responseBody['tile']['color']);
        $this->assertSame('followedMail', $responseBody['tile']['type']);
        $this->assertSame('chart', $responseBody['tile']['view']);
        $this->assertSame(3, $responseBody['tile']['position']);
        $this->assertSame(6, $responseBody['tile']['user_id']);
        $this->assertIsArray($responseBody['tile']['parameters']);
        $this->assertSame('vertical-bar', $responseBody['tile']['parameters']['chartType']);
        $this->assertSame('status', $responseBody['tile']['parameters']['chartMode']);

        // Last Resources
        $response     = $tileController->getById($request, new \Slim\Http\Response(), ['id' => self::$myLastResources]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(self::$myLastResources, $responseBody['tile']['id']);
        $this->assertIsArray($responseBody['tile']);
        $this->assertSame('#ce93d8', $responseBody['tile']['color']);
        $this->assertSame('myLastResources', $responseBody['tile']['type']);
        $this->assertSame('chart', $responseBody['tile']['view']);
        $this->assertSame(4, $responseBody['tile']['position']);
        $this->assertSame(6, $responseBody['tile']['user_id']);
        $this->assertIsArray($responseBody['tile']['parameters']);
        $this->assertSame('pie', $responseBody['tile']['parameters']['chartType']);
        $this->assertSame('destination', $responseBody['tile']['parameters']['chartMode']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo         = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']    = $userInfo['id'];
    }

    public function testUpdatePosition()
    {
        $GLOBALS['login'] = 'jjane';
        $userInfo         = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']    = $userInfo['id'];

        $tileController = new \Home\controllers\TileController();

        //  UPDATE
        $environment = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'PUT']);
        $request     = \Slim\Http\Request::createFromEnvironment($environment);

        $aArgs = [
            'tiles' => [
                ['id' => self::$basket, 'position' => 5],
                ['id' => self::$folder, 'position' => 4],
                ['id' => self::$shortcut, 'position' => 3],
                ['id' => self::$followedMail, 'position' => 2],
                ['id' => self::$myLastResources, 'position' => 1],
                ['id' => self::$externalSignatoryBook, 'position' => 0]
            ]
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($aArgs, $request);

        $response     = $tileController->updatePositions($fullRequest, new \Slim\Http\Response());
        $this->assertSame(204, $response->getStatusCode());

        $GLOBALS['login'] = 'superadmin';
        $userInfo         = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']    = $userInfo['id'];
    }

    public function testUpdate()
    {
        $GLOBALS['login'] = 'jjane';
        $userInfo         = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']    = $userInfo['id'];

        $tileController = new \Home\controllers\TileController();

        //  UPDATE
        $environment = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'PUT']);
        $request     = \Slim\Http\Request::createFromEnvironment($environment);

        $aArgs = [
            'color' => '#b0bec5',
            'view'  => 'list'
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($aArgs, $request);

        $response = $tileController->update($fullRequest, new \Slim\Http\Response(), ['id' => self::$externalSignatoryBook]);
        $this->assertSame(204, $response->getStatusCode());

        // External signatory book
        $response     = $tileController->getById($request, new \Slim\Http\Response(), ['id' => self::$externalSignatoryBook]);
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertSame(self::$externalSignatoryBook, $responseBody['tile']['id']);
        $this->assertIsArray($responseBody['tile']);
        $this->assertSame('#b0bec5', $responseBody['tile']['color']);
        $this->assertSame('externalSignatoryBook', $responseBody['tile']['type']);
        $this->assertSame('list', $responseBody['tile']['view']);
        $this->assertSame(0, $responseBody['tile']['position']);
        $this->assertSame(6, $responseBody['tile']['user_id']);
        $this->assertIsArray($responseBody['tile']['parameters']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo         = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']    = $userInfo['id'];
    }

    public function testDelete()
    {
        $GLOBALS['login'] = 'jjane';
        $userInfo         = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']    = $userInfo['id'];

        $tileController = new \Home\controllers\TileController();

        $environment = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'DELETE']);
        $request     = \Slim\Http\Request::createFromEnvironment($environment);

        $response  = $tileController->delete($request, new \Slim\Http\Response(), ['id' => self::$externalSignatoryBook]);
        $this->assertSame(204, $response->getStatusCode());

        $response = $tileController->getById($request, new \Slim\Http\Response(), ['id' => self::$externalSignatoryBook]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Tile out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo         = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id']    = $userInfo['id'];
    }
}
