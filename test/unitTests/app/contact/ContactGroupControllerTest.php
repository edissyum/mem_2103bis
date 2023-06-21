<?php

/**
 * Copyright Maarch since 2008 under licence GPLv3.
 * See LICENCE.txt file at the root folder for more details.
 * This file is part of Maarch software.
 *
 */

use PHPUnit\Framework\TestCase;

class ContactGroupControllerTest extends TestCase
{
    private static $id = null;
    private static $id2 = null;


    public function testCreate()
    {
        $contactGroupController = new \Contact\controllers\ContactGroupController();

        //  CREATE
        $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'POST']);
        $request        = \Slim\Http\Request::createFromEnvironment($environment);

        $body = [
            'label'             => 'Groupe petition',
            'description'       => 'Groupe de petition'
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($body, $request);

        $response     = $contactGroupController->create($fullRequest, new \Slim\Http\Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsInt($responseBody['id']);
        self::$id = $responseBody['id'];

        $GLOBALS['login'] = 'bbain';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
            'label'             => 'Groupe petition 2',
            'description'       => 'Groupe de petition'
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($body, $request);

        $response     = $contactGroupController->create($fullRequest, new \Slim\Http\Response());
        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);

        $this->assertIsInt($responseBody['id']);
        self::$id2 = $responseBody['id'];


        //  READ
        $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'GET']);
        $request        = \Slim\Http\Request::createFromEnvironment($environment);
        $response       = $contactGroupController->getById($request, new \Slim\Http\Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response       = $contactGroupController->getById($request, new \Slim\Http\Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $user = \User\models\UserModel::getByLogin(['select' => ['id'], 'login' => 'superadmin']);
        $this->assertSame(self::$id, $responseBody->contactsGroup->id);
        $this->assertSame('Groupe petition', $responseBody->contactsGroup->label);
        $this->assertSame('Groupe de petition', $responseBody->contactsGroup->description);
        $this->assertSame($user['id'], $responseBody->contactsGroup->owner);
        $this->assertIsString($responseBody->contactsGroup->labelledOwner);

        // Fail
        $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'POST']);
        $request        = \Slim\Http\Request::createFromEnvironment($environment);

        $body = [
            'label'             => 'Groupe petition',
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($body, $request);

        $response     = $contactGroupController->create($fullRequest, new \Slim\Http\Response());
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body description is empty or not a string', $responseBody['errors']);
    }

    public function testGet()
    {
        $GLOBALS['login'] = 'bbain';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $contactGroupController = new \Contact\controllers\ContactGroupController();

        //  GET
        $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'GET']);
        $request        = \Slim\Http\Request::createFromEnvironment($environment);
        $response       = $contactGroupController->get($request, new \Slim\Http\Response());
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertIsArray($responseBody->contactsGroups);
        $this->assertNotNull($responseBody->contactsGroups);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testUpdate()
    {
        $contactGroupController = new \Contact\controllers\ContactGroupController();

        //  UPDATE
        $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'PUT']);
        $request        = \Slim\Http\Request::createFromEnvironment($environment);

        $body = [
            'label'             => 'Groupe petition updated',
            'description'       => 'Groupe de petition updated',
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($body, $request);

        $response     = $contactGroupController->update($fullRequest, new \Slim\Http\Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        //  READ
        $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'GET']);
        $request        = \Slim\Http\Request::createFromEnvironment($environment);
        $response       = $contactGroupController->getById($request, new \Slim\Http\Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame(self::$id, $responseBody->contactsGroup->id);
        $this->assertSame('Groupe petition updated', $responseBody->contactsGroup->label);
        $this->assertSame('Groupe de petition updated', $responseBody->contactsGroup->description);
        $this->assertIsString($responseBody->contactsGroup->labelledOwner);

        // Fail
        $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'PUT']);
        $request        = \Slim\Http\Request::createFromEnvironment($environment);

        $body = [
            'label'             => 'Groupe petition updated',
            'description'       => 'Groupe de petition updated',
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($body, $request);

        $response     = $contactGroupController->update($fullRequest, new \Slim\Http\Response(), ['id' => self::$id * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactGroupController->update($fullRequest, new \Slim\Http\Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $body = [
            'label'             => 'Groupe petition updated'
        ];
        $fullRequest = \httpRequestCustom::addContentInBody($body, $request);

        $response     = $contactGroupController->update($fullRequest, new \Slim\Http\Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body description is empty or not a string', $responseBody['errors']);
    }

    public function testAddCorrespondents()
    {
        $contactGroupController = new \Contact\controllers\ContactGroupController();

        $contacts = \Contact\models\ContactModel::get([
            'select'    => ['id'],
            'limit'     => 1
        ]);

        $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'POST']);
        $request        = \Slim\Http\Request::createFromEnvironment($environment);

        if (!empty($contacts[0])) {
            //  UPDATE

            $aArgs = [
                'correspondents'    => ['id' => $contacts[0]['id'], 'type' => 'contact']
            ];
            $fullRequest = \httpRequestCustom::addContentInBody($aArgs, $request);

            $response     = $contactGroupController->addCorrespondents($fullRequest, new \Slim\Http\Response(), ['id' => self::$id]);
            $this->assertSame(204, $response->getStatusCode());
        }

        $body = [

        ];
        $fullRequest = \httpRequestCustom::addContentInBody($body, $request);

        $response     = $contactGroupController->addCorrespondents($fullRequest, new \Slim\Http\Response(), ['id' => self::$id * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactGroupController->addCorrespondents($fullRequest, new \Slim\Http\Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactGroupController->addCorrespondents($fullRequest, new \Slim\Http\Response(), ['id' => self::$id]);
        $this->assertSame(400, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Body correspondents is empty or not an array', $responseBody['errors']);
    }

    public function testDeleteCorrespondents()
    {
        $contactGroupController = new \Contact\controllers\ContactGroupController();

        $contacts = \Contact\models\ContactModel::get([
            'select'    => ['id'],
            'limit'     => 1
        ]);

        if (!empty($contacts[0])) {
            //  UPDATE
            $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'DELETE']);
            $request        = \Slim\Http\Request::createFromEnvironment($environment);
            $body = [
                'correspondents'    => ['id' => $contacts[0]['id'], 'type' => 'contact']
            ];
            $fullRequest = \httpRequestCustom::addContentInBody($body, $request);

            $response     = $contactGroupController->deleteCorrespondents($fullRequest, new \Slim\Http\Response(), ['id' => self::$id]);
            $this->assertSame(204, $response->getStatusCode());
        }

        //  READ
        $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'GET']);
        $request        = \Slim\Http\Request::createFromEnvironment($environment);
        $response       = $contactGroupController->getById($request, new \Slim\Http\Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $user = \User\models\UserModel::getByLogin(['select' => ['id'], 'login' => 'superadmin']);
        $this->assertSame(self::$id, $responseBody->contactsGroup->id);
        $this->assertSame($user['id'], $responseBody->contactsGroup->owner);
        $this->assertIsString($responseBody->contactsGroup->labelledOwner);

        $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'DELETE']);
        $request        = \Slim\Http\Request::createFromEnvironment($environment);

        $response     = $contactGroupController->deleteCorrespondents($request, new \Slim\Http\Response(), ['id' => self::$id * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactGroupController->deleteCorrespondents($request, new \Slim\Http\Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];
    }

    public function testDelete()
    {
        $contactGroupController = new \Contact\controllers\ContactGroupController();

        //  DELETE
        $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'DELETE']);
        $request        = \Slim\Http\Request::createFromEnvironment($environment);

        // Fail
        $response     = $contactGroupController->delete($request, new \Slim\Http\Response(), ['id' => self::$id * 1000]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'bbain';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        $response     = $contactGroupController->delete($request, new \Slim\Http\Response(), ['id' => self::$id]);
        $this->assertSame(403, $response->getStatusCode());
        $responseBody = json_decode((string)$response->getBody(), true);
        $this->assertSame('Contacts group out of perimeter', $responseBody['errors']);

        $GLOBALS['login'] = 'superadmin';
        $userInfo = \User\models\UserModel::getByLogin(['login' => $GLOBALS['login'], 'select' => ['id']]);
        $GLOBALS['id'] = $userInfo['id'];

        // Sucess
        $response       = $contactGroupController->delete($request, new \Slim\Http\Response(), ['id' => self::$id]);
        $this->assertSame(204, $response->getStatusCode());

        $response       = $contactGroupController->delete($request, new \Slim\Http\Response(), ['id' => self::$id2]);
        $this->assertSame(204, $response->getStatusCode());

        //  READ
        $environment    = \Slim\Http\Environment::mock(['REQUEST_METHOD' => 'GET']);
        $request        = \Slim\Http\Request::createFromEnvironment($environment);
        $response       = $contactGroupController->getById($request, new \Slim\Http\Response(), ['id' => self::$id]);
        $responseBody   = json_decode((string)$response->getBody());

        $this->assertSame('Contacts group out of perimeter', $responseBody->errors);
    }
}
