<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Search Controller
* @author dev@maarch.org
*/

namespace Search\controllers;

use Attachment\models\AttachmentModel;
use Attachment\controllers\AttachmentTypeController;
use Basket\models\BasketModel;
use Basket\models\RedirectBasketModel;
use Configuration\models\ConfigurationModel;
use Contact\models\ContactCivilityModel; // EDISSYUM - NCH01 Fenetre de recherche de contacts
use Contact\models\ContactCustomFieldListModel; // EDISSYUM - NCH01 Fenetre de recherche de contacts
use Contact\models\ContactGroupModel; // EDISSYUM - NCH01 Fenetre de recherche de contacts
use Contact\models\ContactModel;
use Contact\models\ContactParameterModel;
use Contact\controllers\ContactController;
use Convert\controllers\FullTextController;
use CustomField\models\CustomFieldModel;
use Docserver\models\DocserverModel;
use Doctype\models\DoctypeModel;
use Entity\models\EntityModel;
use Entity\models\ListInstanceModel;
use Folder\models\FolderModel;
use Folder\models\ResourceFolderModel;
use Group\controllers\PrivilegeController;
use Note\models\NoteEntityModel;
use Note\models\NoteModel;
use Priority\models\PriorityModel;
use RegisteredMail\models\RegisteredMailModel;
use Resource\controllers\ResourceListController;
use Resource\models\ResModel;
use Resource\models\ResourceContactModel;
use Resource\models\ResourceListModel;
use Resource\models\UserFollowedResourceModel;
use Respect\Validation\Validator;
use Search\models\SearchModel;
use Slim\Http\Request;
use Slim\Http\Response;
use SrcCore\controllers\AutoCompleteController;
use SrcCore\controllers\PreparedClauseController;
use SrcCore\models\DatabaseModel;
use SrcCore\models\TextFormatModel;
use SrcCore\models\ValidatorModel;
use Status\models\StatusModel;
use Tag\models\ResourceTagModel;
use User\controllers\UserController;
use User\models\UserModel;

class SearchController
{
    public function get(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'adv_search_mlb', 'userId' => $GLOBALS['id']]) && !$body['linkedResource']) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        } else {
            $adminSearch = ConfigurationModel::getByPrivilege(['privilege' => 'admin_search', 'select' => ['value']]);
            if (empty($adminSearch)) {
                return $response->withStatus(400)->withJson(['errors' => 'No admin_search configuration found', 'lang' => 'noAdminSearchConfiguration']);
            }
        }

        ini_set('memory_limit', -1);

        $userdataClause = SearchController::getUserDataClause(['userId' => $GLOBALS['id'], 'login' => $GLOBALS['login']]);
        $searchWhere    = $userdataClause['searchWhere'];
        $searchData     = $userdataClause['searchData'];

        if (!empty($body['meta']['values'])) {
            $body['meta']['values'] = trim($body['meta']['values']);
        }
        $searchClause = SearchController::getQuickFieldClause(['body' => $body, 'searchWhere' => $searchWhere, 'searchData' => $searchData]);
        if (empty($searchClause)) {
            return $response->withJson(['resources' => [], 'count' => 0, 'allResources' => []]);
        }
        $searchWhere = $searchClause['searchWhere'];
        $searchData  = $searchClause['searchData'];

        $searchClause = SearchController::getMainFieldsClause(['body' => $body, 'searchWhere' => $searchWhere, 'searchData' => $searchData]);
        if (empty($searchClause)) {
            return $response->withJson(['resources' => [], 'count' => 0, 'allResources' => []]);
        }
        $searchWhere = $searchClause['searchWhere'];
        $searchData  = $searchClause['searchData'];

        $searchClause = SearchController::getListFieldsClause(['body' => $body, 'searchWhere' => $searchWhere, 'searchData' => $searchData]);
        if (empty($searchClause)) {
            return $response->withJson(['resources' => [], 'count' => 0, 'allResources' => []]);
        }
        $searchWhere = $searchClause['searchWhere'];
        $searchData  = $searchClause['searchData'];

        $searchClause = SearchController::getCustomFieldsClause(['body' => $body, 'searchWhere' => $searchWhere, 'searchData' => $searchData]);
        if (empty($searchClause)) {
            return $response->withJson(['resources' => [], 'count' => 0, 'allResources' => []]);
        }
        $searchWhere = $searchClause['searchWhere'];
        $searchData  = $searchClause['searchData'];

        $searchClause = SearchController::getRegisteredMailsClause(['body' => $body, 'searchWhere' => $searchWhere, 'searchData' => $searchData]);
        if (empty($searchClause)) {
            return $response->withJson(['resources' => [], 'count' => 0, 'allResources' => []]);
        }
        $searchWhere = $searchClause['searchWhere'];
        $searchData  = $searchClause['searchData'];

        $searchClause = SearchController::getFulltextClause(['body' => $body, 'searchWhere' => $searchWhere, 'searchData' => $searchData]);
        if (empty($searchClause)) {
            return $response->withJson(['resources' => [], 'count' => 0, 'allResources' => []]);
        }
        $searchWhere = $searchClause['searchWhere'];
        $searchData  = $searchClause['searchData'];
        $matchingFullTextResources = $searchClause['matchingResources'];

        $nonSearchableStatuses = StatusModel::get(['select' => ['id'], 'where' => ['can_be_searched = ?'], 'data' => ['Y']]);
        if (!empty($nonSearchableStatuses)) {
            $nonSearchableStatuses = array_column($nonSearchableStatuses, 'id');
            $searchWhere[] = 'status in (?)';
            $searchData[]  = $nonSearchableStatuses;
        }

        $queryParams = $request->getQueryParams();

        // Begin transaction for temporarySearchData
        DatabaseModel::beginTransaction();
        SearchModel::createTemporarySearchData(['where' => $searchWhere, 'data' => $searchData, 'order' => $queryParams['order']]);

        $filters = [];
        if (empty($queryParams['filters'])) {
            $filters = SearchController::getFilters(['body' => $body]);
        }

        $searchClause = SearchController::getFiltersClause(['body' => $body]);
        if (empty($searchClause)) {
            return $response->withJson(['resources' => [], 'count' => 0, 'allResources' => []]);
        }
        $searchWhere = $searchClause['searchWhere'];
        $searchData  = $searchClause['searchData'];

        $limit = 25;
        if (!empty($queryParams['limit']) && is_numeric($queryParams['limit'])) {
            $limit = (int)$queryParams['limit'];
        }
        $offset = 0;
        if (!empty($queryParams['offset']) && is_numeric($queryParams['offset'])) {
            $offset = (int)$queryParams['offset'];
        }
        $order   = !in_array($queryParams['orderDir'], ['ASC', 'DESC']) ? '' : $queryParams['orderDir'];
        $orderBy = str_replace(['chrono', 'typeLabel', 'creationDate', 'category', 'destUser', 'processLimitDate', 'entityLabel'], ['order_alphanum(alt_identifier)', 'type_label', 'creation_date', 'category_id', '(lastname, firstname)', 'process_limit_date', 'entity_label'], $queryParams['order']);
        $orderBy = !in_array($orderBy, ['order_alphanum(alt_identifier)', 'status', 'subject', 'type_label', 'creation_date', 'category_id', '(lastname, firstname)', 'process_limit_date', 'entity_label', 'priority']) ? ['creation_date'] : ["{$orderBy} {$order}"];

        $allResources = SearchModel::getTemporarySearchData([
            'select'  => ['res_id'],
            'where'   => $searchWhere,
            'data'    => $searchData,
            'orderBy' => $orderBy
        ]);
        DatabaseModel::commitTransaction();
        if (empty($allResources[$offset])) {
            return $response->withJson(['resources' => [], 'count' => 0, 'allResources' => [], 'filters' => $filters]);
        }
        $allResources = array_column($allResources, 'res_id');

        $resIds = [];
        $order  = 'CASE res_id ';
        for ($i = $offset; $i < ($offset + $limit); $i++) {
            if (empty($allResources[$i])) {
                break;
            }
            $order .= "WHEN {$allResources[$i]} THEN {$i} ";
            $resIds[] = $allResources[$i];
        }
        $order .= 'END';

        $configuration = json_decode($adminSearch['value'], true);
        $listDisplay   = $configuration['listDisplay']['subInfos'];
        //EDISSYUM - EME01 Fix sur l'affichage du nom du dossier (emplacement fixe) d'un courrier
        foreach ($listDisplay as $itemDisplay) {
            if($itemDisplay['value'] == "getFolders"){
                $displayFolder = true ;
            } else {
                $displayFolder = false ;
            }
        }
        // END EDISSYUM - EME01

        $selectData = ResourceListController::getSelectData(['listDisplay' => $listDisplay]);

        $resources = ResourceListModel::getOnResource([
            'select'    => $selectData['select'],
            'table'     => $selectData['tableFunction'],
            'leftJoin'  => $selectData['leftJoinFunction'],
            'where'     => ['res_letterbox.res_id in (?)'],
            'data'      => [$resIds],
            'orderBy'   => [$order]
        ]);
        if (empty($resources)) {
            return $response->withJson(['resources' => [], 'count' => 0, 'allResources' => []]);
        }

        $attachments = AttachmentModel::get([
            'select'    => ['COUNT(res_id)', 'res_id_master'],
            'where'     => ['res_id_master in (?)', 'status not in (?)', 'attachment_type not in (?)', '((status = ? AND typist = ?) OR status != ?)'],
            'data'      => [$resIds, ['DEL', 'OBS'], AttachmentTypeController::UNLISTED_ATTACHMENT_TYPES, 'TMP', $GLOBALS['id'], 'TMP'],
            'groupBy'   => ['res_id_master']
        ]);

        $followedDocuments = UserFollowedResourceModel::get([
            'select' => ['res_id'],
            'where'  => ['user_id = ?'],
            'data'   => [$GLOBALS['id']],
        ]);
        $trackedMails = array_column($followedDocuments, 'res_id');

        $formattedResources = ResourceListController::getFormattedResources([
            'resources'     => $resources,
            'userId'        => $GLOBALS['id'],
            'attachments'   => $attachments,
            'checkLocked'   => false,
            'listDisplay'   => $listDisplay,
            'trackedMails'  => $trackedMails
        ]);

        $ids = array_column($formattedResources, 'resId');
        $matchingResources = SearchController::getAttachmentsInsider(['resources' => $ids, 'body' => $body]);
        $matchingResources = array_merge($matchingResources, $matchingFullTextResources);
        foreach ($formattedResources as $key => $formattedResource) {
            $formattedResources[$key]['inAttachments'] = in_array($formattedResource['resId'], $matchingResources);
        }

        return $response->withJson([
            'resources'         => $formattedResources,
            'count'             => count($allResources),
            'allResources'      => $allResources,
            'defaultTab'        => $configuration['listEvent']['defaultTab'],
            'displayFolderTags' => in_array('getFolders', array_column($listDisplay, 'value')),
            'templateColumns'   => $configuration['listDisplay']['templateColumns'],
            'filters'           => $filters,
            'displayFolder'     => $displayFolder  //EDISSYUM - EME01 Fix sur l'affichage du nom du dossier (emplacement fixe) d'un courrier  | ajouter 'displayFolder'     => $displayFolder
        ]);
    }

    // EDISSYUM - NCH01 Fenetre de recherche de contacts
    public function getContacts(Request $request, Response $response)
    {
        $body = $request->getParsedBody();

        if (!PrivilegeController::hasPrivilege(['privilegeId' => 'admin_search_contacts', 'userId' => $GLOBALS['id']])) {
            return $response->withStatus(403)->withJson(['errors' => 'Service forbidden']);
        } else {
            $adminSearchContacts = ConfigurationModel::getByPrivilege(['privilege' => 'admin_search_contacts', 'select' => ['value']]);
            if (empty($adminSearchContacts)) {
                return $response->withStatus(400)->withJson(['errors' => 'No admin_search_contacts configuration found', 'lang' => 'noAdminSearchContactsConfiguration']);
            }
        }

        ini_set('memory_limit', -1);

        if (!empty($body['meta']['values'])) {
            $body['meta']['values'] = trim($body['meta']['values']);
        }

        $searchClause = SearchController::getContactsQuickFieldClause(['body' => $body]);
        $searchWhere = $searchClause['searchWhere'];
        $searchData  = $searchClause['searchData'];

        $searchClause = SearchController::getContactsMainFieldsClause(['body' => $body, 'searchWhere' => $searchWhere, 'searchData' => $searchData]);
        $searchWhere = $searchClause['searchWhere'];
        $searchData  = $searchClause['searchData'];

        $searchClause = SearchController::getContactsCustomFieldsClause(['body' => $body, 'searchWhere' => $searchWhere, 'searchData' => $searchData]);
        $searchWhere = $searchClause['searchWhere'];
        $searchData  = $searchClause['searchData'];

        if (!isset($searchWhere)) {
            $searchWhere = ['1=?'];
        }
        if (!isset($searchData)) {
            $searchData = ['1'];
        }

        $queryParams = $request->getQueryParams();

        // Begin transaction for temporarySearchData
        DatabaseModel::beginTransaction();
        SearchModel::createTemporarySearchDataContact(['where' => $searchWhere, 'data' => $searchData, 'order' => $queryParams['order']]);

        $filters = [];
        if (empty($queryParams['filters'])) {
            $filters = SearchController::getFiltersContacts(['body' => $body]);
        }
        $searchClause = SearchController::getFiltersClauseContacts(['body' => $body]);

        if (empty($searchClause)) {
            return $response->withJson(['contacts' => [], 'count' => 0, 'allContacts' => []]);
        }
        $searchWhere = $searchClause['searchWhere'];
        $searchData  = $searchClause['searchData'];

        $limit = 25;
        if (!empty($queryParams['limit']) && is_numeric($queryParams['limit'])) {
            $limit = (int)$queryParams['limit'];
        }
        $offset = 0;
        if (!empty($queryParams['offset']) && is_numeric($queryParams['offset'])) {
            $offset = (int)$queryParams['offset'];
        }
        $order   = !in_array($queryParams['orderDir'], ['ASC', 'DESC']) ? '' : $queryParams['orderDir'];
        $orderBy = !in_array($queryParams['order'], ['lastname', 'firstname', 'company']) ? ['creation_date'] : ["{$queryParams['order']} {$order}"];

        $allContacts = SearchModel::getTemporaryContactSearchData([
            'select'  => ['contact_id'],
            'where'   => $searchWhere,
            'data'    => $searchData,
            'orderBy' => $orderBy
        ]);

        DatabaseModel::commitTransaction();
        if (empty($allContacts[$offset])) {
            return $response->withJson(['contacts' => [], 'count' => 0, 'allContacts' => [], 'filters' => $filters]);
        }
        $allContacts = array_column($allContacts, 'contact_id');

        $contactIds = [];
        $order  = 'CASE contacts.id ';
        for ($i = $offset; $i < ($offset + $limit); $i++) {
            if (empty($allContacts[$i])) {
                break;
            }
            $order .= "WHEN {$allContacts[$i]} THEN {$i} ";
            $contactIds[] = $allContacts[$i];
        }
        $order .= 'END';
        $configuration = json_decode($adminSearchContacts['value'], true);
        $listDisplay   = $configuration['listDisplay']['subInfos'];
        $selectData = ResourceListController::getSelectDataContacts(['listDisplay' => $listDisplay]);

        $contacts = DatabaseModel::select([
            'select'    => $selectData['select'],
            'table'     => array_merge(['contacts'], $selectData['tableFunction']),
            'left_join' => empty($selectData['leftJoinFunction']) ? [] : $selectData['leftJoinFunction'],
            'where'     => ['contacts.id in (?)'],
            'data'      => empty($contactIds) ? [] : [$contactIds],
            'order_by'  => empty($order) ? [] : [$order],
        ]);

        if (empty($contacts)) {
            return $response->withJson(['contacts' => [], 'count' => 0, 'allResources' => [], 'filters' => $filters]);
        }

        $formattedResources = ResourceListController::getFormattedResourcesContacts([
            'contacts'     => $contacts,
            'userId'        => $GLOBALS['id'],
            'listDisplay'   => $listDisplay
        ]);

        return $response->withJson([
            'contacts'          => $formattedResources,
            'count'             => count($allContacts),
            'allContacts'       => $allContacts,
            'defaultTab'        => $configuration['listEvent']['defaultTab'],
            'templateColumns'   => $configuration['listDisplay']['templateColumns'],
            'filters'           => $filters,
        ]);
    }

    private static function getWhere($where, $identifier) {
        foreach ($where as $key => $value) {
            if (strpos($key, 'contactCustomField_') !== false) {
                $customId = str_replace('contactCustomField_', '', $key);
                $where[$key] = "custom_fields #>> '{" . $customId . "}' in (?)";
            }

            if ($identifier == $key || (strpos($key, 'contactCustomField_') !== false && strpos($identifier, $key) !== false)) {
                unset($where[$key]);
            }
        }
        return $where;
    }

    private static function getData($data, $identifier) {
        foreach ($data as $key => $value) {
            if ($identifier == $key || (strpos($key, 'contactCustomField_') !== false && strpos($identifier, $key) !== false)) {
                unset($data[$key]);
            }
        }
        return $data;
    }

    private static function mapContact($field) {
        $mapping = [
            'civility'              => 'civility',
            'firstname'             => 'firstname',
            'lastname'              => 'lastname',
            'company'               => 'company',
            'department'            => 'department',
            'function'              => 'function',
            'addressNumber'         => 'address_number',
            'addressStreet'         => 'address_street',
            'addressAdditional1'    => 'address_additional1',
            'addressAdditional2'    => 'address_additional2',
            'addressPostcode'       => 'address_postcode',
            'addressTown'           => 'address_town',
            'addressCountry'        => 'address_country',
            'email'                 => 'email',
            'phone'                 => 'phone',
            'notes'                 => 'notes',
            'sector'                => 'sector'
        ];

        if ($mapping[$field]) {
            $field = $mapping[$field];
        }
        return $field;
    }

    private static function getFiltersContacts(array $args)
    {
        ValidatorModel::arrayType($args, ['body', 'resources']);

        $body = $args['body'];
        $queryWhere  = [];
        $queryData  = [];
        $return = [];
        $filtrableParameters = ContactParameterModel::get(['select' => ['identifier'], 'where' => ['filtrable = ?'], 'data' => [true]]);

        foreach ($filtrableParameters as $parameter) {
            $parameter['identifier'] = self::mapContact($parameter['identifier']);
            if (!empty($body['filters'][$parameter['identifier']]['values']) && is_array($body['filters'][$parameter['identifier']]['values'])) {
                $datas = [];
                foreach ($body['filters'][$parameter['identifier']]['values'] as $filter) {
                    if ($filter['selected']) {
                        $datas[] = $filter['id'];
                    }
                }
                if (!empty($datas)) {
                    if (in_array(null, $datas)) {
                        $tmpWhere = '(' . $parameter['identifier'] . ' in (?) OR ' . $parameter['identifier'] . ' is NULL)';
                    } else {
                        $tmpWhere = $parameter['identifier'] . ' in (?)';
                    }

                    $queryWhere[$parameter['identifier']]  = $tmpWhere;
                    $queryData[$parameter['identifier']]   = $datas;
                }
            }
            $datas = [];
            $select = $parameter['identifier'];
            if (strpos($parameter['identifier'], 'contactCustomField_') !== false) {
                $customId = str_replace('contactCustomField_', '', $parameter['identifier']);
                $select = "custom_fields #>> '{" . $customId . "}' as " . $parameter['identifier'];
            }

            $rawDatas = SearchModel::getTemporaryContactSearchData([
                'select'  => ['count(1)', $select],
                'where'   => self::getWhere($queryWhere, $select),
                'data'    => self::getData($queryData, $select),
                'groupBy' => [$parameter['identifier']]
            ]);

            if (!empty($body['filters'][$parameter['identifier']]['values']) && is_array($body['filters'][$parameter['identifier']]['values'])) {
                foreach ($body['filters'][$parameter['identifier']]['values'] as $filter) {
                    $count = 0;
                    foreach ($rawDatas as $value) {
                        if ($filter['id'] === $value[strtolower($parameter['identifier'])]) {
                            $count = $value['count'];
                        }
                    }
                    $datas[] = [
                        'id'        => $filter['id'],
                        'label'     => $filter['label'],
                        'count'     => $count,
                        'selected'  => $filter['selected']
                    ];
                }
                $return[$parameter['identifier']] = [
                    'values'    => $datas,
                    'expand'    => $body['filters'][$parameter['identifier']]['expand']
                ];
            } elseif (!empty($rawDatas)) {
                if ($parameter['identifier'] == 'civility') {
                    $resources = array_column($rawDatas, $parameter['identifier']);
                    $data      = ContactCivilityModel::get(['select' => ['label', 'id'], 'where' => ['id in (?)'], 'data' => [$resources]]);
                    $data      = array_column($data, 'label', 'id');
                }
                foreach ($rawDatas as $value) {
                    $label = null;
                    if (!empty($value[strtolower($parameter['identifier'])])) {
                        $label = $data[$value[strtolower($parameter['identifier'])]];
                    }
                    if ($value[strtolower($parameter['identifier'])]) {
                        $datas[] = [
                            'id'        => $value[strtolower($parameter['identifier'])],
                            'label'     => $label ?? $value[strtolower($parameter['identifier'])],
                            'count'     => $value['count'],
                            'selected'  => false
                        ];
                    }
                }
                $return[$parameter['identifier']] = [
                    'values'    => $datas,
                    'expand'    => false
                ];
            }
            if (empty($return[$parameter['identifier']]['values'])) {
                $return[$parameter['identifier']]['values'] = [];
            }
        }

        if ($queryWhere) {
            foreach ($filtrableParameters as $parameter) {
                $parameter['identifier'] = self::mapContact($parameter['identifier']);
                $select = $parameter['identifier'];
                $isContactCustom = false;
                if (strpos($parameter['identifier'], 'contactCustomField_') !== false) {
                    $customId = str_replace('contactCustomField_', '', $parameter['identifier']);
                    $select = "custom_fields #>> '{" . $customId . "}' as " . $parameter['identifier'];
                    $isContactCustom = true;
                }

                $rawDatas = SearchModel::getTemporaryContactSearchData([
                    'select'  => ['count(1)', $select],
                    'where'   => self::getWhere($queryWhere, $select),
                    'data'    => self::getData($queryData, $select),
                    'groupBy' => [$parameter['identifier']]
                ]);

                $filterExists = [];
                $filterExists[$parameter['identifier']] = [];
                if (!empty($return[$parameter['identifier']]['values']) && is_array($return[$parameter['identifier']]['values'])) {
                    for ($i = 0; $i < count($return[$parameter['identifier']]['values']); $i++) {
                        foreach ($rawDatas as $data) {
                            $identifier = $parameter['identifier'];
                            if ($isContactCustom) {
                                $identifier = strtolower($identifier);
                            }
                            if ($data[$identifier] === $return[$parameter['identifier']]['values'][$i]['id']) {
                                $filterExists[$parameter['identifier']][] = $return[$parameter['identifier']]['values'][$i]['id'];
                                $return[$parameter['identifier']]['values'][$i]['count'] = $data['count'];
                            } else {
                                if (!in_array($return[$parameter['identifier']]['values'][$i]['id'], $filterExists[$parameter['identifier']])) {
                                    $return[$parameter['identifier']]['values'][$i]['count'] = 0;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $return;
    }

    public function getConfigurationContacts(Request $request, Response $response)
    {
        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_search_contacts']);
        $configuration = json_decode($configuration['value'], true);

        return $response->withJson(['configuration' => $configuration]);
    }

    public static function getFiltersClauseContacts(array $args)
    {
        ValidatorModel::arrayType($args, ['body']);

        $body        = $args['body'];
        $searchWhere = [];
        $searchData  = [];

        $filtrableParameters = ContactParameterModel::get(['select' => ['identifier'], 'where' => ['filtrable = ?'], 'data' => [true]]);

        foreach ($filtrableParameters as $parameter) {
            $parameter['identifier'] = self::mapContact($parameter['identifier']);
            if (!empty($body['filters'])) {
                if (!empty($body['filters'][$parameter['identifier']]['values']) && is_array($body['filters'][$parameter['identifier']]['values'])) {
                    $data = [];
                    foreach ($body['filters'][$parameter['identifier']]['values'] as $filter) {
                        if ($filter['selected']) {
                            $data[] = $filter['id'];
                        }
                    }
                    if (!empty($data)) {
                        if (strpos($parameter['identifier'], 'contactCustomField_') !== false) {
                            $customId = str_replace('contactCustomField_', '', $parameter['identifier']);
                            $searchWhere[] = "custom_fields #>> '{" . $customId . "}' in (?)";
                        } else {
                            $searchWhere[] = $parameter['identifier'] . ' in (?)';
                        }
                        $searchData[] = $data;
                    }
                }
            }
        }

        return ['searchWhere' => $searchWhere, 'searchData' => $searchData];
    }

    public static function getRecursiveContacts($contactGroups, &$arrayOfContactId) {
        foreach ($contactGroups as $group) {
            if ($group['correspondent_type'] == 'contactGroup') {
                $newContactGroups = ContactGroupModel::getWithList([
                    'select'    => ['contacts_groups.id', 'label', 'correspondent_type', 'correspondent_id'],
                    'where'     => ['contacts_groups.id in (?)'],
                    'data'      => [$group['correspondent_id']]
                ]);
                self::getRecursiveContacts($newContactGroups, $arrayOfContactId);
            } else if ($group['correspondent_type'] == 'contact') {
                $arrayOfContactId[] = $group['correspondent_id'];
            }
        }
        return $arrayOfContactId;
    }

    public static function getContactsMainFieldsClause(array $args)
    {
        ValidatorModel::arrayType($args, ['body', 'searchWhere', 'searchData']);

        $body = $args['body'];

        if (!empty($body['civility']) && !empty($body['civility']['values']) && is_array($body['civility']['values'])) {
            $args['searchWhere'][] = 'civility in (?)';
            $args['searchData'][] = $body['civility']['values'];
        }

        if (!empty($body['contactsGroups']) && !empty($body['contactsGroups']['values']) && is_array($body['contactsGroups']['values'])) {
            $contactGroups = ContactGroupModel::getWithList([
                'select'    => ['contacts_groups.id', 'label', 'correspondent_type', 'correspondent_id'],
                'where'     => ['contacts_groups.id in (?)'],
                'data'      => [$body['contactsGroups']['values']]
            ]);
            $arrayOfContactId = [];
            $arrayOfContactId = self::getRecursiveContacts($contactGroups, $arrayOfContactId);
            if (!empty($arrayOfContactId)) {
                $args['searchWhere'][] = 'contacts.id in (?)';
                $args['searchData'][] = $arrayOfContactId;
            } else {
                $args['searchWhere'][] = '1 = ?';
                $args['searchData'][] = '0';
            }
        }

        if (!empty($body['lastname']) && !empty($body['lastname']['values']) && is_string($body['lastname']['values'])) {
            if ($body['lastname']['values'][0] == '"' && $body['lastname']['values'][strlen($body['lastname']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(lastname = ?)";
                $lastname = trim($body['lastname']['values'], '"');
                $args['searchData'][] = $lastname;
            } else {
                $fields = ['lastname'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['lastname']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                foreach ($requestData['data'] as $data) {
                    $args['searchData'][] = $data;
                }
            }
        }
        if (!empty($body['firstname']) && !empty($body['firstname']['values']) && is_string($body['firstname']['values'])) {
            if ($body['firstname']['values'][0] == '"' && $body['firstname']['values'][strlen($body['firstname']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(firstname = ?)";
                $firstname = trim($body['firstname']['values'], '"');
                $args['searchData'][] = $firstname;
            } else {
                $fields = ['firstname'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['firstname']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                foreach ($requestData['data'] as $data) {
                    $args['searchData'][] = $data;
                }
            }
        }
        if (!empty($body['company']) && !empty($body['company']['values']) && is_string($body['company']['values'])) {
            if ($body['company']['values'][0] == '"' && $body['company']['values'][strlen($body['company']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(company = ?)";
                $company = trim($body['company']['values'], '"');
                $args['searchData'][] = $company;
            } else {
                $fields = ['company'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['company']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                foreach ($requestData['data'] as $data) {
                    $args['searchData'][] = $data;
                }
            }
        }
        if (!empty($body['email']) && !empty($body['email']['values']) && is_string($body['email']['values'])) {
            if ($body['email']['values'][0] == '"' && $body['email']['values'][strlen($body['email']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(email = ?)";
                $email = trim($body['email']['values'], '"');
                $args['searchData'][] = $email;
            } else {
                $fields = ['email'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['email']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);

                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                foreach ($requestData['data'] as $data) {
                    $args['searchData'][] = $data;
                }
            }
        }
        if (!empty($body['phone']) && !empty($body['phone']['values']) && is_string($body['phone']['values'])) {
            if ($body['phone']['values'][0] == '"' && $body['phone']['values'][strlen($body['phone']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(phone = ?)";
                $phone = trim($body['phone']['values'], '"');
                $args['searchData'][] = $phone;
            } else {
                $fields = ['phone'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['phone']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                foreach ($requestData['data'] as $data) {
                    $args['searchData'][] = $data;
                }
            }
        }
        if (!empty($body['function']) && !empty($body['function']['values']) && is_string($body['function']['values'])) {
            if ($body['function']['values'][0] == '"' && $body['function']['values'][strlen($body['function']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(function = ?)";
                $function = trim($body['function']['values'], '"');
                $args['searchData'][] = $function;
            } else {
                $fields = ['function'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['function']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                foreach ($requestData['data'] as $data) {
                    $args['searchData'][] = $data;
                }
            }
        }
        if (!empty($body['department']) && !empty($body['department']['values']) && is_string($body['department']['values'])) {
            if ($body['department']['values'][0] == '"' && $body['department']['values'][strlen($body['department']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(service = ?)";
                $department = trim($body['department']['values'], '"');
                $args['searchData'][] = $department;
            } else {
                $fields = ['department'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['department']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                foreach ($requestData['data'] as $data) {
                    $args['searchData'][] = $data;
                }
            }
        }
        if (!empty($body['sector']) && !empty($body['sector']['values']) && is_string($body['sector']['values'])) {
            if ($body['sector']['values'][0] == '"' && $body['sector']['values'][strlen($body['sector']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(service = ?)";
                $sector = trim($body['department']['values'], '"');
                $args['searchData'][] = $sector;
            } else {
                $fields = ['sector'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['sector']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                foreach ($requestData['data'] as $data) {
                    $args['searchData'][] = $data;
                }
            }
        }
        if (!empty($body['addressnumber']) && !empty($body['addressnumber']['values']) && is_string($body['addressnumber']['values'])) {
            if ($body['addressnumber']['values'][0] == '"' && $body['addressnumber']['values'][strlen($body['addressnumber']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(addressnumber = ?)";
                $addressnumber = trim($body['addressnumber']['values'], '"');
                $args['searchData'][] = $addressnumber;
            } else {
                $fields = ['address_number'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['addressnumber']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                foreach ($requestData['data'] as $data) {
                    $args['searchData'][] = $data;
                }
            }
        }
        if (!empty($body['addressstreet']) && !empty($body['addressstreet']['values']) && is_string($body['addressstreet']['values'])) {
            if ($body['addressstreet']['values'][0] == '"' && $body['addressstreet']['values'][strlen($body['addressstreet']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(addressstreet = ?)";
                $addressstreet = trim($body['addressstreet']['values'], '"');
                $args['searchData'][] = $addressstreet;
            } else {
                $fields = ['address_street'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['addressstreet']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                foreach ($requestData['data'] as $data) {
                    $args['searchData'][] = $data;
                }
            }
        }
        if (!empty($body['addresspostcode']) && !empty($body['addresspostcode']['values']) && is_string($body['addresspostcode']['values'])) {
            if ($body['addresspostcode']['values'][0] == '"' && $body['addresspostcode']['values'][strlen($body['addresspostcode']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(addresspostcode = ?)";
                $addresspostcode = trim($body['addresspostcode']['values'], '"');
                $args['searchData'][] = $addresspostcode;
            } else {
                $fields = ['address_postcode'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['addresspostcode']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                $args['searchData'][] = $requestData['data'][0];
            }
        }
        if (!empty($body['addresstown']) && !empty($body['addresstown']['values']) && is_string($body['addresstown']['values'])) {
            if ($body['addresstown']['values'][0] == '"' && $body['addresstown']['values'][strlen($body['addresstown']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(addresstown = ?)";
                $addresstown = trim($body['addresstown']['values'], '"');
                $args['searchData'][] = $addresstown;
            } else {
                $fields = ['address_town'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['addresstown']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                foreach ($requestData['data'] as $data) {
                    $args['searchData'][] = $data;
                }
            }
        }
        if (!empty($body['addresscountry']) && !empty($body['addresscountry']['values']) && is_string($body['addresscountry']['values'])) {
            if ($body['addresscountry']['values'][0] == '"' && $body['addresscountry']['values'][strlen($body['addresscountry']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(addresscountry = ?)";
                $addresscountry = trim($body['addresscountry']['values'], '"');
                $args['searchData'][] = $addresscountry;
            } else {
                $fields = ['address_country'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['addresscountry']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                foreach ($requestData['data'] as $data) {
                    $args['searchData'][] = $data;
                }
            }
        }
        if (!empty($body['addressadditional1']) && !empty($body['addressadditional1']['values']) && is_string($body['addressadditional1']['values'])) {
            if ($body['addressadditional1']['values'][0] == '"' && $body['addressadditional1']['values'][strlen($body['addressadditional1']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(addressadditional1 = ?)";
                $addressadditional1 = trim($body['addressadditional1']['values'], '"');
                $args['searchData'][] = $addressadditional1;
            } else {
                $fields = ['address_additional1'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['addressadditional1']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                foreach ($requestData['data'] as $data) {
                    $args['searchData'][] = $data;
                }
            }
        }
        if (!empty($body['addressadditional2']) && !empty($body['addressadditional2']['values']) && is_string($body['addressadditional2']['values'])) {
            if ($body['addressadditional2']['values'][0] == '"' && $body['addressadditional2']['values'][strlen($body['addressadditional2']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(addressadditional2 = ?)";
                $addressadditional2 = trim($body['addressadditional2']['values'], '"');
                $args['searchData'][] = $addressadditional2;
            } else {
                $fields = ['address_additional2'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['addressadditional2']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $args['searchWhere'][] = $subjectGlue;
                foreach ($requestData['data'] as $data) {
                    $args['searchData'][] = $data;
                }
            }
        }

        if (!empty($body['creationDate']) && !empty($body['creationDate']['values']) && is_array($body['creationDate']['values'])) {
            if (Validator::date()->notEmpty()->validate($body['creationDate']['values']['start'])) {
                $args['searchWhere'][] = 'creation_date >= ?';
                $args['searchData'][] = $body['creationDate']['values']['start'];
            }
            if (Validator::date()->notEmpty()->validate($body['creationDate']['values']['end'])) {
                $args['searchWhere'][] = 'creation_date <= ?';
                $args['searchData'][] = TextFormatModel::getEndDayDate(['date' => $body['creationDate']['values']['end']]);
            }
        }

        if (!empty($body['modificationDate']) && !empty($body['modificationDate']['values']) && is_array($body['modificationDate']['values'])) {
            if (Validator::date()->notEmpty()->validate($body['modificationDate']['values']['start'])) {
                $args['searchWhere'][] = 'modification_date >= ?';
                $args['searchData'][] = $body['modificationDate']['values']['start'];
            }
            if (Validator::date()->notEmpty()->validate($body['modificationDate']['values']['end'])) {
                $args['searchWhere'][] = 'modification_date <= ?';
                $args['searchData'][] = TextFormatModel::getEndDayDate(['date' => $body['modificationDate']['values']['end']]);
            }
        }

        return ['searchWhere' => $args['searchWhere'], 'searchData' => $args['searchData']];
    }

    public static function getContactsCustomFieldsClause(array $args)
    {
        ValidatorModel::arrayType($args, ['body', 'searchWhere', 'searchData']);

        $body = $args['body'];

        foreach ($body as $key => $value) {
            if (strpos($key, 'contactCustomField_') !== false) {
                $customFieldId = substr($key, 19);
                $customField = ContactCustomFieldListModel::getById(['select' => ['type'], 'id' => $customFieldId]);

                if (empty($customField)) {
                    continue;
                }
                if ($customField['type'] == 'string') {
                    if (!empty($value) && !empty($value['values']) && is_string($value['values'])) {
                        if ($value['values'][0] == '"' && $value['values'][strlen($value['values']) - 1] == '"') {
                            $args['searchWhere'][] = "custom_fields->>'{$customFieldId}' = ?";
                            $subject = trim($value['values'], '"');
                            $args['searchData'][] = $subject;
                        } else {
                            $fields = ["custom_fields->>'{$customFieldId}'"];
                            $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                            $requestData = AutoCompleteController::getDataForRequest([
                                'search'        => $value['values'],
                                'fields'        => $fields,
                                'where'         => [],
                                'data'          => [],
                                'fieldsNumber'  => 1,
                                'itemMinLength' => 1
                            ]);

                            if (!isset($args['searchWhere'])) {
                                $args['searchWhere'] = [];
                            }
                            if (!isset($args['searchData'])) {
                                $args['searchData'] = [];
                            }
                            $args['searchWhere'] = array_merge($args['searchWhere'], $requestData['where']);
                            $args['searchData'] = array_merge($args['searchData'], $requestData['data']);
                        }
                    }
                } elseif ($customField['type'] == 'integer') {
                    if (!empty($value) && !empty($value['values']) && is_array($value['values'])) {
                        if (Validator::intVal()->notEmpty()->validate($value['values']['start'])) {
                            $args['searchWhere'][] = "(custom_fields->>'{$customFieldId}')::float >= ?";
                            $args['searchData'][] = $value['values']['start'];
                        }
                        if (Validator::intVal()->notEmpty()->validate($value['values']['end'])) {
                            $args['searchWhere'][] = "(custom_fields->>'{$customFieldId}')::float <= ?";
                            $args['searchData'][] = $value['values']['end'];
                        }
                    }
                } elseif ($customField['type'] == 'radio' || $customField['type'] == 'select') {
                    if (!empty($value) && !empty($value['values']) && is_array($value['values'])) {
                        if (in_array(null, $value['values'])) {
                            $args['searchWhere'][] = "(custom_fields->>'{$customFieldId}' in (?) OR custom_fields->>'{$customFieldId}' is NULL)";
                        } else {
                            $args['searchWhere'][] = "custom_fields->>'{$customFieldId}' in (?)";
                        }
                        $args['searchData'][] = $value['values'];
                    }
                } elseif ($customField['type'] == 'checkbox') {
                    if (!empty($value) && !empty($value['values']) && is_array($value['values'])) {
                        $where = '';
                        foreach ($value['values'] as $item) {
                            if (!empty($where)) {
                                $where .= ' OR ';
                            }
                            $where .= "custom_fields->'{$customFieldId}' @> ?";
                            $args['searchData'][] = "\"{$item}\"";
                        }

                        $args['searchWhere'][] = $where;
                    }
                } elseif ($customField['type'] == 'date') {
                    if (Validator::date()->notEmpty()->validate($value['values']['start'])) {
                        $args['searchWhere'][] = "(custom_fields->>'{$customFieldId}')::timestamp >= ?";
                        $args['searchData'][] = $value['values']['start'];
                    }
                    if (Validator::date()->notEmpty()->validate($value['values']['end'])) {
                        $args['searchWhere'][] = "(custom_fields->>'{$customFieldId}')::timestamp <= ?";
                        $args['searchData'][] = TextFormatModel::getEndDayDate(['date' => $value['values']['end']]);
                    }
                } elseif ($customField['type'] == 'banAutocomplete') {
                    if (!empty($value) && !empty($value['values']) && is_array($value['values'])) {
                        $where = '';
                        foreach ($value['values'] as $item) {
                            if (!empty($where)) {
                                $where .= ' OR ';
                            }
                            $where .= "custom_fields->'{$customFieldId}'->0->>'id' = ?";
                            $args['searchData'][] = "{$item['id']}";
                        }
                        $args['searchWhere'][] = $where;
                    }
                } elseif ($customField['type'] == 'contact') {
                    if (!empty($value['values']) && is_array($value['values']) && is_array($value['values'][0])) {
                        $contactSearchWhere = [];
                        foreach ($value['values'] as $contactValue) {
                            $contactSearchWhere[] = "custom_fields->'{$customFieldId}' @> ?";
                            $args['searchData'][] = '[{"id": ' . $contactValue['id'] . ', "type": "' . $contactValue['type'] . '"}]';
                        }
                        $args['searchWhere'][] = '(' . implode(' or ', $contactSearchWhere) . ')';
                    } elseif (!empty($value['values']) && is_array($value['values']) && is_string($value['values'][0])) {
                        $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => ['company']]);

                        $requestData = AutoCompleteController::getDataForRequest([
                            'search'       => $value['values'],
                            'fields'       => $fields,
                            'fieldsNumber' => 1
                        ]);

                        $contacts = ContactModel::get([
                            'select'    => ['id'],
                            'where'     => $requestData['where'],
                            'data'      => $requestData['data']
                        ]);
                        $contactIds = array_column($contacts, 'id');
                        if (empty($contactIds)) {
                            return null;
                        }

                        $contactsStandalone = [];
                        foreach ($contactIds as $contactIdStandalone) {
                            $contactsStandalone[] = "custom_fields->'{$customFieldId}' @> ?";
                            $args['searchData'][] = '[{"id": ' . $contactIdStandalone . ', "type": "contact"}]';
                        }
                        $args['searchWhere'][] = '(' . implode(' or ', $contactsStandalone) . ')';
                    }
                }
            }
        }
        return ['searchWhere' => $args['searchWhere'], 'searchData' => $args['searchData']];
    }

    public static function getContactsQuickFieldClause(array $args)
    {
        ValidatorModel::arrayType($args, ['body']);

        $body = $args['body'];

        if (!empty($body['meta']) && !empty($body['meta']['values']) && is_string($body['meta']['values'])) {
            if ($body['meta']['values'][0] == '"' && $body['meta']['values'][strlen($body['meta']['values']) - 1] == '"') {
                $quick = trim($body['meta']['values'], '"');
                $quickWhere = "lastname = ? OR firstname = ? OR email = ? OR company = ? OR phone = ?";

                $args['searchData'] = [$quick, $quick, $quick, $quick, $quick];
                $args['searchWhere'][] = '(' . $quickWhere . ')';
            } else {
                $quick = trim($body['meta']['values']);

                $fields = ['lastname', 'firstname', 'email', 'company', 'phone'];
                $fieldsNumber = count($fields);
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestDataContact = AutoCompleteController::getDataForRequest([
                    'search'       => $quick,
                    'fields'       => $fields,
                    'where'        => [],
                    'data'         => [],
                    'fieldsNumber' => $fieldsNumber,
                    'longField'    => true
                ]);

                if (!empty($requestDataContact['where'])) {
                    $whereClause[]      = implode(' AND ', $requestDataContact['where']);
                    $args['searchData'] = $requestDataContact['data'];
                }

                if (!empty($whereClause)) {
                    $args['searchWhere'][] = '(' . implode(' OR ', $whereClause) . ')';
                }
            }
        }

        return ['searchWhere' => $args['searchWhere'], 'searchData' => $args['searchData']];
    }
    // END EDISSYUM - NCH01

    public function getConfiguration(Request $request, Response $response)
    {
        $configuration = ConfigurationModel::getByPrivilege(['privilege' => 'admin_search']);
        $configuration = json_decode($configuration['value'], true);

        return $response->withJson(['configuration' => $configuration]);
    }

    public static function getUserDataClause(array $args)
    {
        ValidatorModel::notEmpty($args, ['userId', 'login']);
        ValidatorModel::intVal($args, ['userId']);
        ValidatorModel::stringType($args, ['login']);

        if (UserController::isRoot(['id' => $args['userId']])) {
            $whereClause = '1=?';
            $dataClause  = [1];
        } else {
            if (empty($args['mode']) || $args['mode'] == 'folders') {
                $folder = PrivilegeController::hasPrivilege(['privilegeId' => 'include_folders_and_followed_resources_perimeter', 'userId' => $args['userId']]);
                if ($folder) {
                    $whereClause = '(res_id in (select res_id from users_followed_resources where user_id = ?))';
                    $dataClause[] = $args['userId'];

                    $entities = UserModel::getEntitiesById(['id' => $args['userId'], 'select' => ['entities.id']]);
                    $entities = array_column($entities, 'id');
                    if (empty($entities)) {
                        $entities = [0];
                    }

                    $foldersClause = 'res_id in (select res_id from folders LEFT JOIN entities_folders ON folders.id = entities_folders.folder_id LEFT JOIN resources_folders ON folders.id = resources_folders.folder_id ';
                    $foldersClause .= "WHERE entities_folders.entity_id in (?) OR folders.user_id = ? OR keyword = 'ALL_ENTITIES')";
                    $whereClause .= " OR ({$foldersClause})";
                    $dataClause[] = $entities;
                    $dataClause[] = $args['userId'];
                } else {
                    $whereClause = '1=?';
                    $dataClause  = [0];
                }
            } else {
                $whereClause = '1=?';
                $dataClause  = [0];
            }

            if (empty($args['mode']) || $args['mode'] == 'groups') {
                $groups = UserModel::getGroupsById(['id' => $args['userId']]);
                $groupsClause = '';
                foreach ($groups as $key => $group) {
                    if (!empty($group['where_clause'])) {
                        $groupClause = PreparedClauseController::getPreparedClause(['clause' => $group['where_clause'], 'userId' => $args['userId']]);
                        if ($key > 0) {
                            $groupsClause .= ' or ';
                        }
                        $groupsClause .= "({$groupClause})";
                    }
                }
                if (!empty($groupsClause)) {
                    $whereClause .= " OR ({$groupsClause})";
                }
            }

            if (empty($args['mode']) || $args['mode'] == 'baskets') {
                $baskets = BasketModel::getBasketsByLogin(['login' => $args['login']]);
                $basketsClause = '';
                foreach ($baskets as $basket) {
                    if (!empty($basket['basket_clause']) && $basket['allowed']) {
                        $basketClause = PreparedClauseController::getPreparedClause(['clause' => $basket['basket_clause'], 'userId' => $args['userId']]);
                        if (!empty($basketsClause)) {
                            $basketsClause .= ' or ';
                        }
                        $basketsClause .= "({$basketClause})";
                    }
                }
                $assignedBaskets = RedirectBasketModel::getAssignedBasketsByUserId(['userId' => $args['userId']]);
                foreach ($assignedBaskets as $basket) {
                    if (!empty($basket['basket_clause'])) {
                        $basketClause = PreparedClauseController::getPreparedClause(['clause' => $basket['basket_clause'], 'userId' => $basket['owner_user_id']]);
                        if (!empty($basketsClause)) {
                            $basketsClause .= ' or ';
                        }
                        $basketsClause .= "({$basketClause})";
                    }
                }
                if (!empty($basketsClause)) {
                    $whereClause .= " OR ({$basketsClause})";
                }
            }
        }

        return ['searchWhere' => ["({$whereClause})"], 'searchData' => $dataClause];
    }

    public static function getQuickFieldClause(array $args)
    {
        ValidatorModel::notEmpty($args, ['searchWhere', 'searchData']);
        ValidatorModel::arrayType($args, ['body', 'searchWhere', 'searchData']);

        $body = $args['body'];

        if (!empty($body['meta']) && !empty($body['meta']['values']) && is_string($body['meta']['values'])) {
            if ($body['meta']['values'][0] == '"' && $body['meta']['values'][strlen($body['meta']['values']) - 1] == '"') {
                $quick = trim($body['meta']['values'], '"');
                $quickWhere = "subject = ? OR replace(alt_identifier, ' ', '') = ? OR barcode = ?";
                $quickWhere .= " OR res_id in (select res_id_master from res_attachments where (title = ? OR identifier = ?) and status in ('TRA', 'A_TRA', 'FRZ') and attachment_type not in (?))";

                $whiteStrippedChrono = str_replace(' ', '', $quick);
                $args['searchData'] = array_merge($args['searchData'], [$quick, $whiteStrippedChrono, $quick, $quick, $quick, AttachmentTypeController::HIDDEN_ATTACHMENT_TYPES]);

                if (ctype_digit($quick)) {
                    $quickWhere .= ' OR res_id = ?';
                    $args['searchData'][] = $quick;
                }

                $args['searchWhere'][] = '(' . $quickWhere . ')';
            } else {
                $quick = trim($body['meta']['values']);
                $quickWhiteStripped = str_replace(' ', '', $quick);

                $fields = ['subject'];
                $fieldsNumber = count($fields);
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestDataDocument = AutoCompleteController::getDataForRequest([
                    'search'       => $quick,
                    'fields'       => $fields,
                    'where'        => [],
                    'data'         => [],
                    'fieldsNumber' => $fieldsNumber,
                    'longField'    => true
                ]);

                $fieldsWhiteStripped = ['replace(alt_identifier, \' \', \'\')', 'replace(barcode, \' \', \'\')'];
                $fieldsWhiteStrippedNumber = count($fieldsWhiteStripped);
                $fieldsWhiteStripped = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fieldsWhiteStripped]);
                $requestDataDocumentWhiteStripped = AutoCompleteController::getDataForRequest([
                    'search'       => $quickWhiteStripped,
                    'fields'       => $fieldsWhiteStripped,
                    'where'        => [],
                    'data'         => [],
                    'fieldsNumber' => $fieldsWhiteStrippedNumber,
                    'longField'    => false
                ]);

                $fields = ['title', 'identifier'];
                $fieldsNumber = count($fields);
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestDataAttachment = AutoCompleteController::getDataForRequest([
                    'search'       => $quick,
                    'fields'       => $fields,
                    'where'        => [],
                    'data'         => [],
                    'fieldsNumber' => $fieldsNumber,
                    'longField'    => true
                ]);

                if (!empty($requestDataDocument['where'])) {
                    $whereClause[]      = implode(' AND ', $requestDataDocument['where']);
                    $args['searchData'] = array_merge($args['searchData'], $requestDataDocument['data']);
                }
                if (!empty($requestDataDocumentWhiteStripped['where'])) {
                    $whereClause[]      = implode(' AND ', $requestDataDocumentWhiteStripped['where']);
                    $args['searchData'] = array_merge($args['searchData'], $requestDataDocumentWhiteStripped['data']);
                }
                if (!empty($requestDataAttachment['where'])) {
                    $whereClause[]      = 'res_id in (select res_id_master from res_attachments where (' . implode(' AND ', $requestDataAttachment['where']) . ') and status in (\'TRA\', \'A_TRA\', \'FRZ\') and attachment_type <> \'summary_sheet\')';
                    $args['searchData'] = array_merge($args['searchData'], $requestDataAttachment['data']);
                }

                if (ctype_digit(trim($quick))) {
                    $whereClause[] = 'res_id = ?';
                    $args['searchData'][] = trim($body['meta']['values']);
                }

                if (!empty($whereClause)) {
                    $args['searchWhere'][] = '(' . implode(' OR ', $whereClause) . ')';
                }
            }
        }

        return ['searchWhere' => $args['searchWhere'], 'searchData' => $args['searchData']];
    }

    public static function getMainFieldsClause(array $args)
    {
        ValidatorModel::notEmpty($args, ['searchWhere', 'searchData']);
        ValidatorModel::arrayType($args, ['body', 'searchWhere', 'searchData']);

        $body = $args['body'];

        if (!empty($body['subject']) && !empty($body['subject']['values']) && is_string($body['subject']['values'])) {
            if ($body['subject']['values'][0] == '"' && $body['subject']['values'][strlen($body['subject']['values']) - 1] == '"') {
                $args['searchWhere'][] = "(subject = ? OR res_id in (select res_id_master from res_attachments where title = ? and status in ('TRA', 'A_TRA', 'FRZ') and attachment_type not in (?)))";
                $subject = trim($body['subject']['values'], '"');
                $args['searchData'][] = $subject;
                $args['searchData'][] = $subject;
                $args['searchData'][] = AttachmentTypeController::HIDDEN_ATTACHMENT_TYPES;
            } else {
                $fields = ['subject'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestData = AutoCompleteController::getDataForRequest([
                    'search'        => $body['subject']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 1,
                    'longField'     => true
                ]);
                $subjectGlue = implode(' AND ', $requestData['where']);
                $attachmentField = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => ['title']]);
                $subjectGlue = "(($subjectGlue) OR res_id in (select res_id_master from res_attachments where {$attachmentField} and status in ('TRA', 'A_TRA', 'FRZ') and attachment_type not in (?)))";
                $args['searchWhere'][] = $subjectGlue;
                $args['searchData'] = array_merge($args['searchData'], $requestData['data']);

                $args['searchData'][] = "%{$body['subject']['values']}%";
                $args['searchData'][] = AttachmentTypeController::HIDDEN_ATTACHMENT_TYPES;
            }
        }
        if (!empty($body['chrono']) && !empty($body['chrono']['values']) && is_string($body['chrono']['values'])) {
            $args['searchWhere'][] = '(replace(alt_identifier, \' \', \'\') ilike ? OR res_id in (select res_id_master from res_attachments where identifier ilike ? and status in (\'TRA\', \'A_TRA\', \'FRZ\') and attachment_type <> \'summary_sheet\'))';
            $whiteStrippedChrono = str_replace(' ', '', $body['chrono']['values']);
            $args['searchData'][] = "%{$whiteStrippedChrono}%";
            $args['searchData'][] = "%{$body['chrono']['values']}%";
        }
        if (!empty($body['barcode']) && !empty($body['barcode']['values']) && is_string($body['barcode']['values'])) {
            $args['searchWhere'][] = 'barcode ilike ?';
            $args['searchData'][] = "%{$body['barcode']['values']}%";
        }
        if (!empty($body['resId']) && !empty($body['resId']['values']) && is_array($body['resId']['values'])) {
            if (Validator::intVal()->notEmpty()->validate($body['resId']['values']['start'])) {
                $args['searchWhere'][] = 'res_id >= ?';
                $args['searchData'][] = $body['resId']['values']['start'];
            }
            if (Validator::intVal()->notEmpty()->validate($body['resId']['values']['end'])) {
                $args['searchWhere'][] = 'res_id <= ?';
                $args['searchData'][] = $body['resId']['values']['end'];
            }
        }
        if (!empty($body['doctype']) && !empty($body['doctype']['values']) && is_array($body['doctype']['values'])) {
            $args['searchWhere'][] = 'type_id in (?)';
            $args['searchData'][] = $body['doctype']['values'];
        }
        if (!empty($body['category']) && !empty($body['category']['values']) && is_array($body['category']['values'])) {
            $args['searchWhere'][] = 'category_id in (?)';
            $args['searchData'][] = $body['category']['values'];
        }
        if (!empty($body['status']) && !empty($body['status']['values']) && is_array($body['status']['values'])) {
            if (in_array(null, $body['status']['values'])) {
                $args['searchWhere'][] = '(status in (select id from status where identifier in (?)) OR status is NULL)';
            } else {
                $args['searchWhere'][] = 'status in (select id from status where identifier in (?))';
            }
            $args['searchData'][] = $body['status']['values'];
        }
        if (!empty($body['priority']) && !empty($body['priority']['values']) && is_array($body['priority']['values'])) {
            if (in_array(null, $body['priority']['values'])) {
                $args['searchWhere'][] = '(priority in (?) OR priority is NULL)';
            } else {
                $args['searchWhere'][] = 'priority in (?)';
            }
            $args['searchData'][] = $body['priority']['values'];
        }
        if (!empty($body['confidentiality']) && !empty($body['confidentiality']['values']) && is_array($body['confidentiality']['values'])) {
            $confidentialityData = [];
            $confidentialityData[] = in_array(true, $body['confidentiality']['values'], true) ? 'Y' : '0';
            $confidentialityData[] = in_array(false, $body['confidentiality']['values'], true) ? 'N' : '0';
            if (in_array(null, $body['confidentiality']['values'])) {
                $args['searchWhere'][] = '(confidentiality in (?) OR confidentiality is NULL)';
            } else {
                $args['searchWhere'][] = 'confidentiality in (?)';
            }
            $args['searchData'][] = $confidentialityData;
        }
        if (!empty($body['binding']) && !empty($body['binding']['values']) && is_array($body['binding']['values'])) {
            $bindingData  = [];
            $bindingWhere = [];
            if (in_array(true, $body['binding']['values'], true)) {
                $bindingData[] = 'true';
            }
            if (in_array(false, $body['binding']['values'], true)) {
                $bindingData[] = 'false';
            }
            if (!empty($bindingData)) {
                $args['searchData'][] = $bindingData;
                $bindingWhere[]       = 'binding in (?)';
            }
            if (in_array(null, $body['binding']['values'], true)) {
                $bindingWhere[] = 'binding is NULL';
            }
            $args['searchWhere'][] = '(' . implode(' OR ', $bindingWhere) . ')';
        }
        if (!empty($body['retentionFrozen']) && !empty($body['retentionFrozen']['values']) && is_array($body['retentionFrozen']['values'])) {
            $retentionFrozenData  = [];
            $retentionFrozenWhere = [];
            if (in_array(true, $body['retentionFrozen']['values'], true)) {
                $retentionFrozenData[] = 'true';
            }
            if (in_array(false, $body['retentionFrozen']['values'], true)) {
                $retentionFrozenData[] = 'false';
            }
            if (!empty($retentionFrozenData)) {
                $args['searchData'][]   = $retentionFrozenData;
                $retentionFrozenWhere[] = 'retention_frozen in (?)';
            }
            if (in_array(null, $body['retentionFrozen']['values'], true)) {
                $retentionFrozenWhere[] = 'retention_frozen is NULL';
            }
            $args['searchWhere'][] = '(' . implode(' OR ', $retentionFrozenWhere) . ')';
        }
        if (!empty($body['initiator']) && !empty($body['initiator']['values']) && is_array($body['initiator']['values'])) {
            if (in_array(null, $body['initiator']['values'])) {
                $args['searchWhere'][] = '(initiator in (select entity_id from entities where id in (?)) OR initiator is NULL)';
            } else {
                $args['searchWhere'][] = 'initiator in (select entity_id from entities where id in (?))';
            }
            $args['searchData'][] = $body['initiator']['values'];
        }
        if (!empty($body['destination']) && !empty($body['destination']['values']) && is_array($body['destination']['values'])) {
            if (in_array(null, $body['destination']['values'])) {
                $args['searchWhere'][] = '(destination in (select entity_id from entities where id in (?)) OR destination is NULL)';
            } else {
                $args['searchWhere'][] = 'destination in (select entity_id from entities where id in (?))';
            }
            $args['searchData'][] = $body['destination']['values'];
        }
        if (!empty($body['creationDate']) && !empty($body['creationDate']['values']) && is_array($body['creationDate']['values'])) {
            if (Validator::date()->notEmpty()->validate($body['creationDate']['values']['start'])) {
                $args['searchWhere'][] = 'creation_date >= ?';
                $args['searchData'][] = $body['creationDate']['values']['start'];
            }
            if (Validator::date()->notEmpty()->validate($body['creationDate']['values']['end'])) {
                $args['searchWhere'][] = 'creation_date <= ?';
                $args['searchData'][] = TextFormatModel::getEndDayDate(['date' => $body['creationDate']['values']['end']]);
            }
        }
        if (!empty($body['documentDate']) && !empty($body['documentDate']['values']) && is_array($body['documentDate']['values'])) {
            if (Validator::date()->notEmpty()->validate($body['documentDate']['values']['start'])) {
                $args['searchWhere'][] = 'doc_date >= ?';
                $args['searchData'][] = $body['documentDate']['values']['start'];
            }
            if (Validator::date()->notEmpty()->validate($body['documentDate']['values']['end'])) {
                $args['searchWhere'][] = 'doc_date <= ?';
                $args['searchData'][] = TextFormatModel::getEndDayDate(['date' => $body['documentDate']['values']['end']]);
            }
        }
        if (!empty($body['arrivalDate']) && !empty($body['arrivalDate']['values']) && is_array($body['arrivalDate']['values'])) {
            if (Validator::date()->notEmpty()->validate($body['arrivalDate']['values']['start'])) {
                $args['searchWhere'][] = 'admission_date >= ?';
                $args['searchData'][] = $body['arrivalDate']['values']['start'];
            }
            if (Validator::date()->notEmpty()->validate($body['arrivalDate']['values']['end'])) {
                $args['searchWhere'][] = 'admission_date <= ?';
                $args['searchData'][] = TextFormatModel::getEndDayDate(['date' => $body['arrivalDate']['values']['end']]);
            }
        }
        if (!empty($body['departureDate']) && !empty($body['departureDate']['values']) && is_array($body['departureDate']['values'])) {
            if (Validator::date()->notEmpty()->validate($body['departureDate']['values']['start'])) {
                $args['searchWhere'][] = 'departure_date >= ?';
                $args['searchData'][] = $body['departureDate']['values']['start'];
            }
            if (Validator::date()->notEmpty()->validate($body['departureDate']['values']['end'])) {
                $args['searchWhere'][] = 'departure_date <= ?';
                $args['searchData'][] = TextFormatModel::getEndDayDate(['date' => $body['departureDate']['values']['end']]);
            }
        }
        if (!empty($body['processLimitDate']) && !empty($body['processLimitDate']['values']) && is_array($body['processLimitDate']['values'])) {
            if (Validator::date()->notEmpty()->validate($body['processLimitDate']['values']['start'])) {
                $args['searchWhere'][] = 'process_limit_date >= ?';
                $args['searchData'][] = $body['processLimitDate']['values']['start'];
            }
            if (Validator::date()->notEmpty()->validate($body['processLimitDate']['values']['end'])) {
                $args['searchWhere'][] = 'process_limit_date <= ?';
                $args['searchData'][] = TextFormatModel::getEndDayDate(['date' => $body['processLimitDate']['values']['end']]);
            }
        }
        if (!empty($body['closingDate']) && !empty($body['closingDate']['values']) && is_array($body['closingDate']['values'])) {
            if (Validator::date()->notEmpty()->validate($body['closingDate']['values']['start'])) {
                $args['searchWhere'][] = 'closing_date >= ?';
                $args['searchData'][] = $body['closingDate']['values']['start'];
            }
            if (Validator::date()->notEmpty()->validate($body['closingDate']['values']['end'])) {
                $args['searchWhere'][] = 'closing_date <= ?';
                $args['searchData'][] = TextFormatModel::getEndDayDate(['date' => $body['closingDate']['values']['end']]);
            }
        }
        if (!empty($body['senders']) && !empty($body['senders']['values']) && is_array($body['senders']['values']) && is_array($body['senders']['values'][0])) {
            $where = '';
            $data = [];
            foreach ($body['senders']['values'] as $value) {
                if (!empty($where)) {
                    $where .= ' OR ';
                }
                $where .= '(item_id = ? AND type = ?)';
                $data[] = $value['id'];
                $data[] = $value['type'];
            }
            $data[] = 'sender';
            $sendersMatch = ResourceContactModel::get([
                'select'    => ['res_id'],
                'where'     => ["({$where})", 'mode = ?'],
                'data'      => $data
            ]);
            if (empty($sendersMatch)) {
                return null;
            }
            $sendersMatch = array_column($sendersMatch, 'res_id');
            $args['searchWhere'][] = 'res_id in (?)';
            $args['searchData'][] = $sendersMatch;
        }
        if (!empty($body['senders']) && !empty($body['senders']['values']) && is_array($body['senders']['values']) && is_string($body['senders']['values'][0])) {
            if (mb_strlen($body['senders']['values'][0]) < 3) {
                return null;
            }
            $searchableParameters = ContactParameterModel::get(['select' => ['identifier'], 'where' => ['searchable = ?'], 'data' => [true]]);
            $searchableParameters = array_column($searchableParameters, 'identifier');
            $searchableParameters = array_map(function ($parameter) {
                if (strpos($parameter, 'contactCustomField_') !== false) {
                    $customFieldId = explode('_', $parameter)[1];
                    return "custom_fields->>'{$customFieldId}'";
                } else {
                    return ContactController::MAPPING_FIELDS[$parameter];
                }
            }, $searchableParameters);
            $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $searchableParameters]);

            $requestData = AutoCompleteController::getDataForRequest([
                'search'       => trim($body['senders']['values'][0]),
                'fields'       => $fields,
                'fieldsNumber' => count($searchableParameters)
            ]);

            $contacts = ContactModel::get([
                'select' => ['id'],
                'where'  => $requestData['where'],
                'data'   => $requestData['data']
            ]);
            $contactIds = array_column($contacts, 'id');
            if (empty($contactIds)) {
                return null;
            } else {
                $sendersMatch = ResourceContactModel::get([
                    'select'    => ['res_id'],
                    'where'     => ['item_id in (?)', 'type = ?', 'mode = ?'],
                    'data'      => [$contactIds, 'contact', 'sender']
                ]);
                $resourceBySenders = array_column($sendersMatch, 'res_id');
                if (empty($resourceBySenders)) {
                    return null;
                } else {
                    $args['searchWhere'][] = 'res_id in (?)';
                    $args['searchData'][] = $resourceBySenders;
                }
            }
        }
        if (!empty($body['recipients']) && !empty($body['recipients']['values']) && is_array($body['recipients']['values']) && is_array($body['recipients']['values'][0])) {
            $where = '';
            $data = [];
            foreach ($body['recipients']['values'] as $value) {
                if (!empty($where)) {
                    $where .= ' OR ';
                }
                $where .= '(item_id = ? AND type = ?)';
                $data[] = $value['id'];
                $data[] = $value['type'];
            }
            $data[] = 'recipient';
            $recipientsMatch = ResourceContactModel::get([
                'select'    => ['res_id'],
                'where'     => ["({$where})", 'mode = ?'],
                'data'      => $data
            ]);
            if (empty($recipientsMatch)) {
                return null;
            }
            $recipientsMatch = array_column($recipientsMatch, 'res_id');
            $args['searchWhere'][] = 'res_id in (?)';
            $args['searchData'][] = $recipientsMatch;
        }
        if (!empty($body['recipients']) && !empty($body['recipients']['values']) && is_array($body['recipients']['values']) && is_string($body['recipients']['values'][0])) {
            if (mb_strlen($body['recipients']['values'][0]) < 3) {
                return null;
            }
            $searchableParameters = ContactParameterModel::get(['select' => ['identifier'], 'where' => ['searchable = ?'], 'data' => [true]]);
            $searchableParameters = array_column($searchableParameters, 'identifier');
            $searchableParameters = array_map(function ($parameter) {
                if (strpos($parameter, 'contactCustomField_') !== false) {
                    $customFieldId = explode('_', $parameter)[1];
                    return "custom_fields->>'{$customFieldId}'";
                } else {
                    return ContactController::MAPPING_FIELDS[$parameter];
                }
            }, $searchableParameters);
            $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $searchableParameters]);

            $requestData = AutoCompleteController::getDataForRequest([
                'search'       => trim($body['recipients']['values'][0]),
                'fields'       => $fields,
                'fieldsNumber' => count($searchableParameters)
            ]);

            $contacts = ContactModel::get([
                'select' => ['id'],
                'where'  => $requestData['where'],
                'data'   => $requestData['data']
            ]);
            $contactIds = array_column($contacts, 'id');
            if (empty($contactIds)) {
                return null;
            } else {
                $recipientsMatch = ResourceContactModel::get([
                    'select'    => ['res_id'],
                    'where'     => ['item_id in (?)', 'type = ?', 'mode = ?'],
                    'data'      => [$contactIds, 'contact', 'recipient']
                ]);
                $resourceByRecipients = array_column($recipientsMatch, 'res_id');
                if (empty($resourceByRecipients)) {
                    return null;
                } else {
                    $args['searchWhere'][] = 'res_id in (?)';
                    $args['searchData'][] = $resourceByRecipients;
                }
            }
        }
        if (!empty($body['tags']) && !empty($body['tags']['values']) && is_array($body['tags']['values'])) {
            if (!(in_array(null, $body['tags']['values']) && count($body['tags']['values']) === 1)) {
                $tagsMatch = ResourceTagModel::get([
                    'select'    => ['res_id'],
                    'where'     => ['tag_id in (?)'],
                    'data'      => [$body['tags']['values']]
                ]);
            }
            if (empty($tagsMatch) && !in_array(null, $body['tags']['values'])) {
                return null;
            }
            if (empty($tagsMatch)) {
                $args['searchWhere'][] = 'res_id not in (select distinct res_id from resources_tags)';
            } elseif (in_array(null, $body['tags']['values'])) {
                $args['searchWhere'][] = '(res_id in (?) OR res_id not in (select distinct res_id from resources_tags))';
                $tagsMatch = array_column($tagsMatch, 'res_id');
                $args['searchData'][] = $tagsMatch;
            } else {
                $args['searchWhere'][] = 'res_id in (?)';
                $tagsMatch = array_column($tagsMatch, 'res_id');
                $args['searchData'][] = $tagsMatch;
            }
        }
        if (!empty($body['folders']) && !empty($body['folders']['values']) && is_array($body['folders']['values'])) {
            if (!(in_array(null, $body['folders']['values']) && count($body['folders']['values']) === 1)) {
                $foldersMatch = ResourceFolderModel::get([
                    'select'    => ['res_id'],
                    'where'     => ['folder_id in (?)'],
                    'data'      => [$body['folders']['values']]
                ]);
            }
            if (empty($foldersMatch) && !in_array(null, $body['folders']['values'])) {
                return null;
            }
            if (empty($foldersMatch)) {
                $args['searchWhere'][] = 'res_id not in (select distinct res_id from resources_folders)';
            } elseif (in_array(null, $body['folders']['values'])) {
                $args['searchWhere'][] = '(res_id in (?) OR res_id not in (select distinct res_id from resources_folders))';
                $foldersMatch = array_column($foldersMatch, 'res_id');
                $args['searchData'][] = $foldersMatch;
            } else {
                $args['searchWhere'][] = 'res_id in (?)';
                $foldersMatch = array_column($foldersMatch, 'res_id');
                $args['searchData'][] = $foldersMatch;
            }
        }
        if (!empty($body['notes']) && !empty($body['notes']['values']) && is_string($body['notes']['values'])) {
            $allNotes = NoteModel::get([
                'select'    => ['identifier', 'id'],
                'where'     => ['note_text ilike ?'],
                'data'      => ["%{$body['notes']['values']}%"]
            ]);
            if (empty($allNotes)) {
                return null;
            }

            $rawUserEntities = EntityModel::getByUserId(['userId' => $GLOBALS['id'], 'select' => ['entity_id']]);
            $userEntities    = array_column($rawUserEntities, 'entity_id');

            $notesMatch = [];
            foreach ($allNotes as $note) {
                if ($note['user_id'] == $GLOBALS['id']) {
                    $notesMatch[] = $note['identifier'];
                    continue;
                }

                $noteEntities = NoteEntityModel::getWithEntityInfo(['select' => ['item_id', 'short_label'], 'where' => ['note_id = ?'], 'data' => [$note['id']]]);
                if (!empty($noteEntities)) {
                    foreach ($noteEntities as $noteEntity) {
                        $note['entities_restriction'][] = ['short_label' => $noteEntity['short_label'], 'item_id' => [$noteEntity['item_id']]];

                        if (in_array($noteEntity['item_id'], $userEntities)) {
                            $notesMatch[] = $note['identifier'];
                            continue 2;
                        }
                    }
                } else {
                    $notesMatch[] = $note['identifier'];
                }
            }

            $args['searchWhere'][] = 'res_id in (?)';
            $args['searchData'][]  = $notesMatch;
        }

        if (!empty($body['attachment_type']) && !empty($body['attachment_type']['values']) && is_array($body['attachment_type']['values'])) {
            $args['searchWhere'][] = 'res_id in (select DISTINCT res_id_master from res_attachments where attachment_type in (?) and status in (\'TRA\', \'A_TRA\', \'FRZ\', \'SIGN\'))';
            $args['searchData'][]  = $body['attachment_type']['values'];
        }
        if (!empty($body['attachment_creationDate']) && !empty($body['attachment_creationDate']['values']) && is_array($body['attachment_creationDate']['values'])) {
            if (Validator::date()->notEmpty()->validate($body['attachment_creationDate']['values']['start'])) {
                $args['searchWhere'][] = 'res_id in (select DISTINCT res_id_master from res_attachments where creation_date >= ? and attachment_type not in (?) and status in (\'TRA\', \'A_TRA\', \'FRZ\', \'SIGN\'))';
                $args['searchData'][]  = $body['attachment_creationDate']['values']['start'];
                $args['searchData'][]  = AttachmentTypeController::HIDDEN_ATTACHMENT_TYPES;
            }
            if (Validator::date()->notEmpty()->validate($body['attachment_creationDate']['values']['end'])) {
                $args['searchWhere'][] = 'res_id in (select DISTINCT res_id_master from res_attachments where creation_date <= ? and attachment_type not in (?) and status in (\'TRA\', \'A_TRA\', \'FRZ\', \'SIGN\'))';
                $args['searchData'][]  = TextFormatModel::getEndDayDate(['date' => $body['attachment_creationDate']['values']['end']]);
                $args['searchData'][]  = AttachmentTypeController::HIDDEN_ATTACHMENT_TYPES;
            }
        }
        if (!empty($body['groupSign']) && !empty($body['groupSign']['values']) && is_array($body['groupSign']['values'])) {
            $where = 'res_id in (select DISTINCT res_id from listinstance where signatory = ? AND (item_id in (select DISTINCT user_id from usergroup_content where group_id in (?)) or delegate in (select DISTINCT user_id from usergroup_content where group_id in (?))))';
            $args['searchData'][] = 'true';
            $args['searchData'][] = $body['groupSign']['values'];
            $args['searchData'][] = $body['groupSign']['values'];

            if (in_array(null, $body['groupSign']['values'])) {
                $where .= ' or res_id in (select DISTINCT res_id from listinstance where signatory = ? AND item_id not in (select DISTINCT user_id from usergroup_content))';
                $args['searchData'][] = 'true';
            }
            $args['searchWhere'][] = $where;
        }
        if (!empty($body['senderDepartment']) && !empty($body['senderDepartment']['values']) && is_array($body['senderDepartment']['values'])) {
            $departments = '';
            $withEmpty = false;
            foreach ($body['senderDepartment']['values'] as $value) {
                if (!is_numeric($value)) {
                    if ($value == null) {
                        $withEmpty = true;
                    }
                    continue;
                }
                if (!empty($departments)) {
                    $departments .= ', ';
                }
                $departments .= "'{$value}%'";
            }
            if (empty($departments) && !$withEmpty) {
                return null;
            }
            $where = [];
            if (!empty($departments)) {
                $where[] = "address_postcode like any (array[{$departments}])";
            }
            if ($withEmpty) {
                $where[] = "address_postcode IS NULL or address_postcode = ''";
            }
            $where = implode(' OR ', $where);
            $contacts = ContactModel::get([
                'select' => ['id'],
                'where'  => [$where]
            ]);
            $contactIds = array_column($contacts, 'id');
            if (empty($contactIds)) {
                return null;
            } else {
                $sendersMatch = ResourceContactModel::get([
                    'select'    => ['res_id'],
                    'where'     => ['item_id in (?)', 'type = ?', 'mode = ?'],
                    'data'      => [$contactIds, 'contact', 'sender']
                ]);
                $sendersMatch = array_column($sendersMatch, 'res_id');
                if (empty($sendersMatch)) {
                    return null;
                } else {
                    $args['searchWhere'][] = 'res_id in (?)';
                    $args['searchData'][] = $sendersMatch;
                }
            }
        }

        return ['searchWhere' => $args['searchWhere'], 'searchData' => $args['searchData']];
    }

    public static function getListFieldsClause(array $args)
    {
        ValidatorModel::notEmpty($args, ['searchWhere', 'searchData']);
        ValidatorModel::arrayType($args, ['body', 'searchWhere', 'searchData']);

        $body = $args['body'];

        foreach ($body as $key => $value) {
            if (strpos($key, 'role_') !== false) {
                $roleId = substr($key, 5);

                if (!empty($value['values']) && is_array($value['values'])) {
                    $where = '';
                    $data = [];
                    foreach ($value['values'] as $itemValue) {
                        if (!empty($where)) {
                            $where .= ' OR ';
                        }
                        $where .= '((item_id = ? AND item_type = ?)';
                        $data[] = $itemValue['id'];
                        $data[] = $itemValue['type'] == 'user' ? 'user_id' : 'entity_id';

                        if ($itemValue['type'] == 'user') {
                            $where .= ' OR delegate = ?';
                            $data[] = $itemValue['id'];
                        }
                        $where .= ')';
                    }
                    if ($roleId == 'sign') {
                        $data[] = 'true';
                        $rolesMatch = ListInstanceModel::get([
                            'select'    => ['res_id'],
                            'where'     => ["({$where})", 'signatory = ?'],
                            'data'      => $data
                        ]);
                    } elseif ($roleId == 'visa') {
                        $data = array_merge($data, ['VISA_CIRCUIT', 'false', 'false', 'true']);
                        $rolesMatch = ListInstanceModel::get([
                            'select' => ['res_id'],
                            'where'  => ["({$where})", 'difflist_type = ?', 'signatory = ?', '(requested_signature = ? or (requested_signature = ? and process_date is not null))'],
                            'data'   => $data
                        ]);
                    } // EDISSYUM - NCH01 Ajout du typist dans la recherche
                    elseif ($roleId == 'typist') {
                        $parameters_actions = \Parameter\models\ParameterModel::getById(['id' => 'ActionQualifID']);
                        $actions = explode(',', $parameters_actions['param_value_string']);
                        $rolesMatch = [];
                        for ($w = 0; $w <= count($actions); $w++){
                            $res_id_history = \History\models\HistoryModel::get([
                                'select' => ['record_id'],
                                'where'  => ['event_type = ?', 'user_id = ?'],
                                'data'   => [$actions[$w], $data[0]]
                            ]);
                            if ($res_id_history[0]){
                                for($z = 0; $z <= count($res_id_history); $z++){
                                    if(isset($res_id_history[$z]['record_id']) && !empty($res_id_history[$z]['record_id'])){
                                        array_push($rolesMatch, ['res_id' => $res_id_history[$z]['record_id']]);
                                    }
                                }
                            }
                        }
                    }
                    // END EDISSYUM - NCH01
                    else {
                        $data[] = $roleId;
                        $rolesMatch = ListInstanceModel::get([
                            'select'    => ['res_id'],
                            'where'     => ["({$where})", 'item_mode = ?'],
                            'data'      => $data
                        ]);
                    }
                    if (in_array(null, $value['values'])) {
                        $args['searchWhere'][] = 'res_id not in (select res_id from listinstance where item_mode = ?)';
                        $args['searchData'][] = $roleId;
                    } elseif (empty($rolesMatch)) {
                        return null;
                    }

                    if (!empty($rolesMatch)) {
                        $rolesMatch            = array_column($rolesMatch, 'res_id');
                        $args['searchWhere'][] = 'res_id in (?)';
                        $args['searchData'][]  = $rolesMatch;
                    }
                }
            }
        }

        return ['searchWhere' => $args['searchWhere'], 'searchData' => $args['searchData']];
    }

    public static function getCustomFieldsClause(array $args)
    {
        ValidatorModel::notEmpty($args, ['searchWhere', 'searchData']);
        ValidatorModel::arrayType($args, ['body', 'searchWhere', 'searchData']);

        $body = $args['body'];

        foreach ($body as $key => $value) {
            if (strpos($key, 'indexingCustomField_') !== false) {
                $customFieldId = substr($key, 20);
                $customField = CustomFieldModel::getById(['select' => ['type'], 'id' => $customFieldId]);
                if (empty($customField)) {
                    continue;
                }
                if ($customField['type'] == 'string') {
                    if (!empty($value) && !empty($value['values']) && is_string($value['values'])) {
                        if ($value['values'][0] == '"' && $value['values'][strlen($value['values']) - 1] == '"') {
                            $args['searchWhere'][] = "custom_fields->>'{$customFieldId}' = ?";
                            $subject = trim($value['values'], '"');
                            $args['searchData'][] = $subject;
                        } else {
                            $fields = ["custom_fields->>'{$customFieldId}'"];
                            $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                            $requestData = AutoCompleteController::getDataForRequest([
                                'search'        => $value['values'],
                                'fields'        => $fields,
                                'where'         => [],
                                'data'          => [],
                                'fieldsNumber'  => 1
                            ]);
                            $args['searchWhere'] = array_merge($args['searchWhere'], $requestData['where']);
                            $args['searchData'] = array_merge($args['searchData'], $requestData['data']);
                        }
                    }
                } elseif ($customField['type'] == 'integer') {
                    if (!empty($value) && !empty($value['values']) && is_array($value['values'])) {
                        if (Validator::intVal()->notEmpty()->validate($value['values']['start'])) {
                            $args['searchWhere'][] = "(custom_fields->>'{$customFieldId}')::float >= ?"; // EDISSYUM - OBR01 Correctif pour la recherche sur les chiffres
                            $args['searchData'][] = $value['values']['start'];
                        }
                        if (Validator::intVal()->notEmpty()->validate($value['values']['end'])) {
                            $args['searchWhere'][] = "(custom_fields->>'{$customFieldId}')::float >= ?"; // EDISSYUM - OBR01 Correctif pour la recherche sur les chiffres
                            $args['searchData'][] = $value['values']['end'];
                        }
                    }
                } elseif ($customField['type'] == 'radio' || $customField['type'] == 'select') {
                    if (!empty($value) && !empty($value['values']) && is_array($value['values'])) {
                        if (in_array(null, $value['values'])) {
                            $args['searchWhere'][] = "(custom_fields->>'{$customFieldId}' in (?) OR custom_fields->>'{$customFieldId}' is NULL)";
                        } else {
                            $args['searchWhere'][] = "custom_fields->>'{$customFieldId}' in (?)";
                        }
                        $args['searchData'][] = $value['values'];
                    }
                } elseif ($customField['type'] == 'checkbox') {
                    if (!empty($value) && !empty($value['values']) && is_array($value['values'])) {
                        $where = '';
                        foreach ($value['values'] as $item) {
                            if (!empty($where)) {
                                $where .= ' OR ';
                            }
                            $where .= "custom_fields->'{$customFieldId}' @> ?";
                            $args['searchData'][] = "\"{$item}\"";
                        }

                        $args['searchWhere'][] = $where;
                    }
                } elseif ($customField['type'] == 'date') {
                    if (Validator::date()->notEmpty()->validate($value['values']['start'])) {
                        $args['searchWhere'][] = "(custom_fields->>'{$customFieldId}')::timestamp >= ?";
                        $args['searchData'][] = $value['values']['start'];
                    }
                    if (Validator::date()->notEmpty()->validate($value['values']['end'])) {
                        $args['searchWhere'][] = "(custom_fields->>'{$customFieldId}')::timestamp <= ?";
                        $args['searchData'][] = TextFormatModel::getEndDayDate(['date' => $value['values']['end']]);
                    }
                } elseif ($customField['type'] == 'banAutocomplete') {
                    if (!empty($value) && !empty($value['values']) && is_array($value['values'])) {
                        $where = '';
                        foreach ($value['values'] as $item) {
                            if (!empty($where)) {
                                $where .= ' OR ';
                            }
                            $where .= "custom_fields->'{$customFieldId}'->0->>'id' = ?";
                            $args['searchData'][] = "{$item['id']}";
                        }
                        $args['searchWhere'][] = $where;
                    }
                } elseif ($customField['type'] == 'contact') {
                    if (!empty($value['values']) && is_array($value['values']) && is_array($value['values'][0])) {
                        $contactSearchWhere = [];
                        foreach ($value['values'] as $contactValue) {
                            $contactSearchWhere[] = "custom_fields->'{$customFieldId}' @> ?";
                            $args['searchData'][] = '[{"id": ' . $contactValue['id'] . ', "type": "' . $contactValue['type'] . '"}]';
                        }
                        $args['searchWhere'][] = '(' . implode(' or ', $contactSearchWhere) . ')';
                    } elseif (!empty($value['values']) && is_array($value['values']) && is_string($value['values'][0])) {
                        $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => ['company']]);

                        $requestData = AutoCompleteController::getDataForRequest([
                            'search'       => $value['values'],
                            'fields'       => $fields,
                            'fieldsNumber' => 1
                        ]);

                        $contacts = ContactModel::get([
                            'select'    => ['id'],
                            'where'     => $requestData['where'],
                            'data'      => $requestData['data']
                        ]);
                        $contactIds = array_column($contacts, 'id');
                        if (empty($contactIds)) {
                            return null;
                        }

                        $contactsStandalone = [];
                        foreach ($contactIds as $contactIdStandalone) {
                            $contactsStandalone[] = "custom_fields->'{$customFieldId}' @> ?";
                            $args['searchData'][] = '[{"id": ' . $contactIdStandalone . ', "type": "contact"}]';
                        }
                        $args['searchWhere'][] = '(' . implode(' or ', $contactsStandalone) . ')';
                    }
                }
            }
        }

        return ['searchWhere' => $args['searchWhere'], 'searchData' => $args['searchData']];
    }

    public static function getRegisteredMailsClause(array $args)
    {
        ValidatorModel::notEmpty($args, ['searchWhere', 'searchData']);
        ValidatorModel::arrayType($args, ['body', 'searchWhere', 'searchData']);

        $body = $args['body'];

        if (!empty($body['registeredMail_reference']) && !empty($body['registeredMail_reference']['values']) && is_string($body['registeredMail_reference']['values'])) {
            $registeredMailsMatch = RegisteredMailModel::get([
                'select'    => ['res_id'],
                'where'     => ['reference ilike ?'],
                'data'      => ["%{$body['registeredMail_reference']['values']}%"]
            ]);
            if (empty($registeredMailsMatch)) {
                return null;
            }
            $registeredMailsMatch = array_column($registeredMailsMatch, 'res_id');
            $args['searchWhere'][] = 'res_id in (?)';
            $args['searchData'][] = $registeredMailsMatch;
        }
        if (!empty($body['registeredMail_issuingSite']) && !empty($body['registeredMail_issuingSite']['values']) && is_array($body['registeredMail_issuingSite']['values'])) {
            $registeredMailsMatch = RegisteredMailModel::get([
                'select'    => ['res_id'],
                'where'     => ['issuing_site in (?)'],
                'data'      => [$body['registeredMail_issuingSite']['values']]
            ]);
            if (empty($registeredMailsMatch)) {
                return null;
            }
            $registeredMailsMatch = array_column($registeredMailsMatch, 'res_id');
            $args['searchWhere'][] = 'res_id in (?)';
            $args['searchData'][] = $registeredMailsMatch;
        }
        if (!empty($body['registeredMail_receivedDate']) && !empty($body['registeredMail_receivedDate']['values']) && is_array($body['registeredMail_receivedDate']['values'])) {
            $where = [];
            $data = [];
            if (Validator::date()->notEmpty()->validate($body['registeredMail_receivedDate']['values']['start'])) {
                $where[] = 'received_date >= ?';
                $data[] = $body['registeredMail_receivedDate']['values']['start'];
            }
            if (Validator::date()->notEmpty()->validate($body['registeredMail_receivedDate']['values']['end'])) {
                $where[] = 'received_date <= ?';
                $data[] = TextFormatModel::getEndDayDate(['date' => $body['registeredMail_receivedDate']['values']['end']]);
            }

            $registeredMailsMatch = RegisteredMailModel::get([
                'select'    => ['res_id'],
                'where'     => $where,
                'data'      => $data
            ]);
            if (empty($registeredMailsMatch)) {
                return null;
            }
            $registeredMailsMatch = array_column($registeredMailsMatch, 'res_id');
            $args['searchWhere'][] = 'res_id in (?)';
            $args['searchData'][] = $registeredMailsMatch;
        }
        if (!empty($body['registeredMail_recipient']) && !empty($body['registeredMail_recipient']['values']) && is_array($body['registeredMail_recipient']['values']) && is_array($body['registeredMail_recipient']['values'][0])) {
            $contactsIds = array_column($body['registeredMail_recipient']['values'], 'id');
            $contacts = ContactModel::get([
                'select'    => ['company', 'lastname', 'address_number', 'address_street', 'address_postcode', 'address_country'],
                'where'     => ['id in (?)'],
                'data'      => [$contactsIds]
            ]);
            if (empty($contacts)) {
                return null;
            }
            $where = '';
            $data = [];
            foreach ($contacts as $contact) {
                if (!empty($where)) {
                    $where .= ' OR ';
                }
                $columnMatch = 'company';
                if (!empty($contact['lastname'])) {
                    $columnMatch = 'lastname';
                }
                $where .= "(recipient->>'{$columnMatch}' = ? AND recipient->>'addressNumber' = ? AND recipient->>'addressStreet' = ? AND recipient->>'addressPostcode' = ? AND recipient->>'addressCountry' = ?)";
                $data = array_merge($data, [$contact[$columnMatch], $contact['address_number'], $contact['address_street'], $contact['address_postcode'], $contact['address_country']]);
            }
            $registeredMailsMatch = RegisteredMailModel::get([
                'select'    => ['res_id'],
                'where'     => [$where],
                'data'      => $data
            ]);
            if (empty($registeredMailsMatch)) {
                return null;
            }
            $registeredMailsMatch = array_column($registeredMailsMatch, 'res_id');
            $args['searchWhere'][] = 'res_id in (?)';
            $args['searchData'][] = $registeredMailsMatch;
        }
        if (!empty($body['registeredMail_recipient']) && !empty($body['registeredMail_recipient']['values']) && is_array($body['registeredMail_recipient']['values']) && is_string($body['registeredMail_recipient']['values'][0])) {
            $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => ["recipient->>'company'"]]);
            $requestData = AutoCompleteController::getDataForRequest([
                'search'       => $body['registeredMail_recipient']['values'][0],
                'fields'       => $fields,
                'fieldsNumber' => 1
            ]);

            $registeredMailsMatch = RegisteredMailModel::get([
                'select'    => ['res_id'],
                'where'     => $requestData['where'],
                'data'      => $requestData['data']
            ]);
            if (empty($registeredMailsMatch)) {
                return null;
            }
            $registeredMailsMatch = array_column($registeredMailsMatch, 'res_id');
            $args['searchWhere'][] = 'res_id in (?)';
            $args['searchData'][] = $registeredMailsMatch;
        }

        return ['searchWhere' => $args['searchWhere'], 'searchData' => $args['searchData']];
    }

    public static function getFulltextClause(array $args)
    {
        ValidatorModel::notEmpty($args, ['searchWhere', 'searchData']);
        ValidatorModel::arrayType($args, ['body', 'searchWhere', 'searchData']);

        $matchingResources = [];
        if (!empty($args['body']['fulltext']['values'])) {
            $args['body']['fulltext']['values'] = TextFormatModel::normalize(['string' => $args['body']['fulltext']['values']]);
            // regex /\b[^"*~\s]{1,2}\b/
            // \b: word boundary
            // [^...]: reverse character class (captures only what is not ...)
            // "*~: Zend Lucene Search meta characters
            // \s: blank space
            // {1,2}: 1 or 2 characters of this character class
            // result: captures words of 1 or 2 characters that are not space, not counting Zend Lucene Search meta characters
            $args['body']['fulltext']['values'] = preg_replace('/\b[^"*~\s]{1,2}\b/u', '', $args['body']['fulltext']['values']);
            $args['body']['fulltext']['values'] = preg_replace('/\s+/u', ' ', $args['body']['fulltext']['values']); // squeezing spaces
            if (strpos($args['body']['fulltext']['values'], "'") === false && ($args['body']['fulltext']['values'][0] != '"' || $args['body']['fulltext']['values'][strlen($args['body']['fulltext']['values']) - 1] != '"')) {
                $query_fulltext = explode(" ", trim($args['body']['fulltext']['values']));
                foreach ($query_fulltext as $key => $value) {
                    if (strpos($value, "*") !== false && (strlen(substr($value, 0, strpos($value, "*"))) < 4 || preg_match("([,':!+])", $value) === 1)) {
                        return null;
                    }
                    $query_fulltext[$key] = $value . "*";
                }
                $args['body']['fulltext']['values'] = implode(" ", $query_fulltext);
            }

            \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());
            \Zend_Search_Lucene_Search_QueryParser::setDefaultOperator(\Zend_Search_Lucene_Search_QueryParser::B_AND);
            \Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding('utf-8');

            $whereRequest = [];
            foreach (['letterbox_coll', 'attachments_coll'] as $tmpCollection) {
                $fullTextDocserver = DocserverModel::getCurrentDocserver(['collId' => $tmpCollection, 'typeId' => 'FULLTEXT']);
                $pathToLuceneIndex = $fullTextDocserver['path_template'];

                if (is_dir($pathToLuceneIndex) && !FullTextController::isDirEmpty($pathToLuceneIndex)) {
                    $index     = \Zend_Search_Lucene::open($pathToLuceneIndex);
                    $hits      = $index->find($args['body']['fulltext']['values']);
                    $listIds   = [];
                    $cptIds    = 0;
                    foreach ($hits as $hit) {
                        if ($cptIds < 500) {
                            $listIds[] = $hit->Id;
                        } else {
                            break;
                        }
                        $cptIds ++;
                    }

                    if (empty($listIds)) {
                        continue;
                    }

                    if ($tmpCollection == 'attachments_coll') {
                        $idMasterDatas = AttachmentModel::get([
                            'select' => ['DISTINCT res_id_master'],
                            'where'  => ['res_id in (?)', 'status in (?)'],
                            'data'   => [$listIds, ['A_TRA', 'FRZ', 'TRA']]
                        ]);

                        $listIds = array_column($idMasterDatas, 'res_id_master');
                        $matchingResources = $listIds;
                    }

                    if (!empty($listIds)) {
                        $whereRequest[] = " res_id in (?) ";
                        $args['searchData'][] = $listIds;
                    }
                }
            }

            if (!empty($whereRequest)) {
                $args['searchWhere'][] = '(' . implode(" or ", $whereRequest) . ')';
            } else {
                return null;
            }
        }
        return ['searchWhere' => $args['searchWhere'], 'searchData' => $args['searchData'], 'matchingResources' => $matchingResources];
    }

    public static function getFiltersClause(array $args)
    {
        ValidatorModel::arrayType($args, ['body']);

        $body        = $args['body'];
        $searchWhere = [];
        $searchData  = [];

        if (!empty($body['filters'])) {
            if (!empty($body['filters']['doctypes']['values']) && is_array($body['filters']['doctypes']['values'])) {
                $doctypes = [];
                foreach ($body['filters']['doctypes']['values'] as $filter) {
                    if ($filter['selected']) {
                        $doctypes[] = $filter['id'];
                    }
                }
                if (!empty($doctypes)) {
                    $searchWhere[] = 'type_id in (?)';
                    $searchData[]  = $doctypes;
                }
            }
            if (!empty($body['filters']['categories']['values']) && is_array($body['filters']['categories']['values'])) {
                $categories = [];
                foreach ($body['filters']['categories']['values'] as $filter) {
                    if ($filter['selected']) {
                        $categories[] = $filter['id'];
                    }
                }
                if (!empty($categories)) {
                    $searchWhere[] = 'category_id in (?)';
                    $searchData[]  = $categories;
                }
            }
            if (!empty($body['filters']['priorities']['values']) && is_array($body['filters']['priorities']['values'])) {
                $priorities = [];
                foreach ($body['filters']['priorities']['values'] as $filter) {
                    if ($filter['selected']) {
                        $priorities[] = $filter['id'];
                    }
                }
                if (!empty($priorities)) {
                    if (in_array(null, $priorities)) {
                        $searchWhere[] = '(priority in (?) OR priority is NULL)';
                    } else {
                        $searchWhere[] = 'priority in (?)';
                    }
                    $searchData[] = $priorities;
                }
            }
            if (!empty($body['filters']['statuses']['values']) && is_array($body['filters']['statuses']['values'])) {
                $statuses = [];
                foreach ($body['filters']['statuses']['values'] as $filter) {
                    if ($filter['selected']) {
                        $statuses[] = $filter['id'];
                    }
                }
                if (!empty($statuses)) {
                    if (in_array(null, $statuses)) {
                        $searchWhere[] = '(status in (?) OR status is NULL)';
                    } else {
                        $searchWhere[] = 'status in (?)';
                    }
                    $searchData[] = $statuses;
                }
            }
            if (!empty($body['filters']['entities']['values']) && is_array($body['filters']['entities']['values'])) {
                $entities = [];
                foreach ($body['filters']['entities']['values'] as $filter) {
                    if ($filter['selected']) {
                        $entities[] = $filter['id'];
                    }
                }
                if (!empty($entities)) {
                    if (in_array(null, $entities)) {
                        $searchWhere[] = '(destination in (?) OR destination is NULL)';
                    } else {
                        $searchWhere[] = 'destination in (?)';
                    }
                    $searchData[] = $entities;
                }
            }
            if (!empty($body['filters']['folders']['values']) && is_array($body['filters']['folders']['values'])) {
                $folders = [];
                foreach ($body['filters']['folders']['values'] as $filter) {
                    if ($filter['selected']) {
                        $folders[] = $filter['id'];
                    }
                }
                if (!empty($folders)) {
                    $searchWhere[] = 'res_id in (select distinct res_id from resources_folders where folder_id in (?))';
                    $searchData[]  = $folders;
                }
            }
        }

        return ['searchWhere' => $searchWhere, 'searchData' => $searchData];
    }

    private static function getFilters(array $args)
    {
        ValidatorModel::arrayType($args, ['body', 'resources']);

        $body = $args['body'];

        $where     = [];
        $queryData = [];

        $wherePriorities = $where;
        $whereCategories = $where;
        $whereStatuses   = $where;
        $whereEntities   = $where;
        $whereDocTypes   = $where;
        $whereFolders    = $where;
        $dataPriorities  = $queryData;
        $dataCategories  = $queryData;
        $dataStatuses    = $queryData;
        $dataEntities    = $queryData;
        $dataDocTypes    = $queryData;
        $dataFolders     = $queryData;

        if (!empty($body['filters']['priorities']['values']) && is_array($body['filters']['priorities']['values'])) {
            $priorities = [];
            foreach ($body['filters']['priorities']['values'] as $filter) {
                if ($filter['selected']) {
                    $priorities[] = $filter['id'];
                }
            }
            if (!empty($priorities)) {
                if (in_array(null, $priorities)) {
                    $tmpWhere = '(priority in (?) OR priority is NULL)';
                } else {
                    $tmpWhere = 'priority in (?)';
                }

                $whereCategories[]  = $tmpWhere;
                $whereStatuses[]    = $tmpWhere;
                $whereEntities[]    = $tmpWhere;
                $whereDocTypes[]    = $tmpWhere;
                $whereFolders[]     = $tmpWhere;

                $dataCategories[]   = $priorities;
                $dataStatuses[]     = $priorities;
                $dataEntities[]     = $priorities;
                $dataDocTypes[]     = $priorities;
                $dataFolders[]      = $priorities;
            }
        }
        if (!empty($body['filters']['categories']['values']) && is_array($body['filters']['categories']['values'])) {
            $categories = [];
            foreach ($body['filters']['categories']['values'] as $filter) {
                if ($filter['selected']) {
                    $categories[] = $filter['id'];
                }
            }
            if (!empty($categories)) {
                $tmpWhere = 'category_id in (?)';

                $wherePriorities[]  = $tmpWhere;
                $whereStatuses[]    = $tmpWhere;
                $whereEntities[]    = $tmpWhere;
                $whereDocTypes[]    = $tmpWhere;
                $whereFolders[]     = $tmpWhere;

                $dataPriorities[]   = $categories;
                $dataStatuses[]     = $categories;
                $dataEntities[]     = $categories;
                $dataDocTypes[]     = $categories;
                $dataFolders[]      = $categories;
            }
        }
        if (!empty($body['filters']['statuses']['values']) && is_array($body['filters']['statuses']['values'])) {
            $statuses = [];
            foreach ($body['filters']['statuses']['values'] as $filter) {
                if ($filter['selected']) {
                    $statuses[] = $filter['id'];
                }
            }
            if (!empty($statuses)) {
                if (in_array(null, $statuses)) {
                    $tmpWhere = '(status in (?) OR status is NULL)';
                } else {
                    $tmpWhere = 'status in (?)';
                }

                $wherePriorities[]  = $tmpWhere;
                $whereCategories[]  = $tmpWhere;
                $whereEntities[]    = $tmpWhere;
                $whereDocTypes[]    = $tmpWhere;
                $whereFolders[]     = $tmpWhere;

                $dataPriorities[]   = $statuses;
                $dataCategories[]   = $statuses;
                $dataEntities[]     = $statuses;
                $dataDocTypes[]     = $statuses;
                $dataFolders[]      = $statuses;
            }
        }
        if (!empty($body['filters']['doctypes']['values']) && is_array($body['filters']['doctypes']['values'])) {
            $doctypes = [];
            foreach ($body['filters']['doctypes']['values'] as $filter) {
                if ($filter['selected']) {
                    $doctypes[] = $filter['id'];
                }
            }
            if (!empty($doctypes)) {
                $tmpWhere = 'type_id in (?)';

                $wherePriorities[]  = $tmpWhere;
                $whereCategories[]  = $tmpWhere;
                $whereEntities[]    = $tmpWhere;
                $whereStatuses[]    = $tmpWhere;
                $whereFolders[]     = $tmpWhere;

                $dataPriorities[]   = $doctypes;
                $dataCategories[]   = $doctypes;
                $dataEntities[]     = $doctypes;
                $dataStatuses[]     = $doctypes;
                $dataFolders[]      = $doctypes;
            }
        }
        if (!empty($body['filters']['entities']['values']) && is_array($body['filters']['entities']['values'])) {
            $entities = [];
            foreach ($body['filters']['entities']['values'] as $filter) {
                if ($filter['selected']) {
                    $entities[] = $filter['id'];
                }
            }
            if (!empty($entities)) {
                if (in_array(null, $entities)) {
                    $tmpWhere = '(destination in (?) OR destination is NULL)';
                } else {
                    $tmpWhere = 'destination in (?)';
                }

                $wherePriorities[]  = $tmpWhere;
                $whereCategories[]  = $tmpWhere;
                $whereDocTypes[]    = $tmpWhere;
                $whereStatuses[]    = $tmpWhere;
                $whereFolders[]     = $tmpWhere;

                $dataPriorities[]   = $entities;
                $dataCategories[]   = $entities;
                $dataDocTypes[]     = $entities;
                $dataStatuses[]     = $entities;
                $dataFolders[]      = $entities;
            }
        }

        if (!empty($body['filters']['folders']['values']) && is_array($body['filters']['folders']['values'])) {
            $folders = [];
            foreach ($body['filters']['folders']['values'] as $filter) {
                if ($filter['selected']) {
                    $folders[] = $filter['id'];
                }
            }
            if (!empty($folders)) {
                $tmpWhere = 'res_id in (select distinct res_id from resources_folders where folder_id in (?))';

                $wherePriorities[]  = $tmpWhere;
                $whereCategories[]  = $tmpWhere;
                $whereDocTypes[]    = $tmpWhere;
                $whereStatuses[]    = $tmpWhere;
                $whereEntities[]    = $tmpWhere;

                $dataPriorities[]   = $folders;
                $dataCategories[]   = $folders;
                $dataDocTypes[]     = $folders;
                $dataStatuses[]     = $folders;
                $dataEntities[]     = $folders;
            }
        }

        $priorities = [];
        $rawPriorities = SearchModel::getTemporarySearchData([
            'select'  => ['count(1)', 'priority'],
            'where'   => $wherePriorities,
            'data'    => $dataPriorities,
            'groupBy' => ['priority']
        ]);
        if (!empty($body['filters']['priorities']['values']) && is_array($body['filters']['priorities']['values'])) {
            foreach ($body['filters']['priorities']['values'] as $filter) {
                $count = 0;
                foreach ($rawPriorities as $value) {
                    if ($filter['id'] === $value['priority']) {
                        $count = $value['count'];
                    }
                }
                $priorities[] = [
                    'id'        => $filter['id'],
                    'label'     => $filter['label'],
                    'count'     => $count,
                    'selected'  => $filter['selected']
                ];
            }
            $priorities = [
                'values'    => $priorities,
                'expand'    => $body['filters']['priorities']['expand']
            ];
        } elseif (!empty($rawPriorities)) {
            $resourcesPriorities = array_column($rawPriorities, 'priority');
            $prioritiesData      = PriorityModel::get(['select' => ['label', 'id'], 'where' => ['id in (?)'], 'data' => [$resourcesPriorities]]);
            $prioritiesData      = array_column($prioritiesData, 'label', 'id');
            foreach ($rawPriorities as $value) {
                $label = null;
                if (!empty($value['priority'])) {
                    $label = $prioritiesData[$value['priority']];
                }

                $priorities[] = [
                    'id'        => $value['priority'],
                    'label'     => $label ?? '_UNDEFINED',
                    'count'     => $value['count'],
                    'selected'  => false
                ];
            }
            $priorities = [
                'values'    => $priorities,
                'expand'    => false
            ];
        }

        $categories = [];
        $rawCategories = SearchModel::getTemporarySearchData([
            'select'  => ['count(1)', 'category_id'],
            'where'   => $whereCategories,
            'data'    => $dataCategories,
            'groupBy' => ['category_id']
        ]);
        if (!empty($body['filters']['categories']['values']) && is_array($body['filters']['categories']['values'])) {
            foreach ($body['filters']['categories']['values'] as $filter) {
                $count = 0;
                foreach ($rawCategories as $value) {
                    if ($filter['id'] === $value['category_id']) {
                        $count = $value['count'];
                    }
                }
                $categories[] = [
                    'id'        => $filter['id'],
                    'label'     => $filter['label'],
                    'count'     => $count,
                    'selected'  => $filter['selected']
                ];
            }
            $categories = [
                'values' => $categories,
                'expand' => $body['filters']['categories']['expand']
            ];
        } else {
            foreach ($rawCategories as $value) {
                $label = ResModel::getCategoryLabel(['categoryId' => $value['category_id']]);
                $categories[] = [
                    'id'        => $value['category_id'],
                    'label'     => empty($label) ? '_UNDEFINED' : $label,
                    'count'     => $value['count'],
                    'selected'  => false
                ];
            }
            $categories = [
                'values'    => $categories,
                'expand'    => false
            ];
        }

        $statuses = [];
        $rawStatuses = SearchModel::getTemporarySearchData([
            'select'  => ['count(1)', 'status'],
            'where'   => $whereStatuses,
            'data'    => $dataStatuses,
            'groupBy' => ['status']
        ]);
        if (!empty($body['filters']['statuses']['values']) && is_array($body['filters']['statuses']['values'])) {
            foreach ($body['filters']['statuses']['values'] as $filter) {
                $count = 0;
                foreach ($rawStatuses as $value) {
                    if ($filter['id'] === $value['status']) {
                        $count = $value['count'];
                    }
                }
                $statuses[] = [
                    'id'        => $filter['id'],
                    'label'     => $filter['label'],
                    'count'     => $count,
                    'selected'  => $filter['selected']
                ];
            }
            $statuses = [
                'values'    => $statuses,
                'expand'    => $body['filters']['statuses']['expand']
            ];
        } elseif (!empty($rawStatuses)) {
            $resourcesStatuses = array_column($rawStatuses, 'status');
            $statusesData      = StatusModel::get(['select' => ['label_status', 'id'], 'where' => ['id in (?)'], 'data' => [$resourcesStatuses]]);
            $statusesData      = array_column($statusesData, 'label_status', 'id');
            foreach ($rawStatuses as $value) {
                $label = null;
                if (!empty($value['status'])) {
                    $label = $statusesData[$value['status']];
                }

                $statuses[] = [
                    'id'        => $value['status'],
                    'label'     => $label ?? '_UNDEFINED',
                    'count'     => $value['count'],
                    'selected'  => false
                ];
            }
            $statuses = [
                'values'    => $statuses,
                'expand'    => false
            ];
        }

        $docTypes = [];
        $rawDocTypes = SearchModel::getTemporarySearchData([
            'select'  => ['count(1)', 'type_id'],
            'where'   => $whereDocTypes,
            'data'    => $dataDocTypes,
            'groupBy' => ['type_id']
        ]);
        if (!empty($body['filters']['doctypes']['values']) && is_array($body['filters']['doctypes']['values'])) {
            foreach ($body['filters']['doctypes']['values'] as $filter) {
                $count = 0;
                foreach ($rawDocTypes as $value) {
                    if ($filter['id'] === $value['type_id']) {
                        $count = $value['count'];
                    }
                }
                $docTypes[] = [
                    'id'        => $filter['id'],
                    'label'     => $filter['label'],
                    'count'     => $count,
                    'selected'  => $filter['selected']
                ];
            }
            $docTypes = [
                'values'    => $docTypes,
                'expand'    => $body['filters']['doctypes']['expand']
            ];
        } elseif (!empty($rawDocTypes)) {
            $resourcesDoctypes = array_column($rawDocTypes, 'type_id');
            $doctypesData      = DoctypeModel::get(['select' => ['description', 'type_id'], 'where' => ['type_id in (?)'], 'data' => [$resourcesDoctypes]]);
            $doctypesData      = array_column($doctypesData, 'description', 'type_id');
            foreach ($rawDocTypes as $value) {
                $label = $doctypesData[$value['type_id']];

                $docTypes[] = [
                    'id'        => $value['type_id'],
                    'label'     => $label ?? '_UNDEFINED',
                    'count'     => $value['count'],
                    'selected'  => false
                ];
            }
            $docTypes = [
                'values'    => $docTypes,
                'expand'    => false
            ];
        }

        $entities = [];
        $rawEntities = SearchModel::getTemporarySearchData([
            'select'  => ['count(1)', 'destination'],
            'where'   => $whereEntities,
            'data'    => $dataEntities,
            'groupBy' => ['destination']
        ]);
        if (!empty($body['filters']['entities']['values']) && is_array($body['filters']['entities']['values'])) {
            foreach ($body['filters']['entities']['values'] as $filter) {
                $count = 0;
                foreach ($rawEntities as $value) {
                    if ($filter['id'] === $value['destination']) {
                        $count = $value['count'];
                    }
                }
                $entities[] = [
                    'id'        => $filter['id'],
                    'label'     => $filter['label'],
                    'count'     => $count,
                    'selected'  => $filter['selected']
                ];
            }
            $entities = [
                'values'    => $entities,
                'expand'    => $body['filters']['entities']['expand']
            ];
        } elseif (!empty($rawEntities)) {
            $resourcesEntities = array_column($rawEntities, 'destination');
            $entitiesData      = EntityModel::get(['select' => ['entity_label', 'entity_id'], 'where' => ['entity_id in (?)'], 'data' => [$resourcesEntities]]);
            $entitiesData      = array_column($entitiesData, 'entity_label', 'entity_id');
            foreach ($rawEntities as $value) {
                $label = null;
                if (!empty($value['destination'])) {
                    $label = $entitiesData[$value['destination']];
                }

                $entities[] = [
                    'id'        => $value['destination'],
                    'label'     => $label ?? '_UNDEFINED',
                    'count'     => $value['count'],
                    'selected'  => false
                ];
            }
            $entities = [
                'values'    => $entities,
                'expand'    => false
            ];
        }

        $resources = SearchModel::getTemporarySearchData([
            'select' => ['res_id'],
            'where'  => $whereFolders,
            'data'   => $dataFolders
        ]);
        $resources = !empty($resources) ? array_column($resources, 'res_id') : [0];

        $userEntities = EntityModel::getWithUserEntities([
            'select' => ['entities.id'],
            'where'  => ['users_entities.user_id = ?'],
            'data'   => [$GLOBALS['id']]
        ]);
        $userEntities = !empty($userEntities) ? array_column($userEntities, 'id') : [0];

        $chunkedResources = array_chunk($resources, 30000);
        $rawFolders = [];
        foreach ($chunkedResources as $resources) {
            $tmpRawFolders = FolderModel::getWithEntitiesAndResources([
                'select'  => ['folders.id', 'folders.label', 'count(DISTINCT resources_folders.res_id) as count'],
                'where'   => ['resources_folders.res_id in (?)', '(folders.user_id = ? OR entities_folders.entity_id in (?) or keyword = ?)'],
                'data'    => [$resources, $GLOBALS['id'], $userEntities, 'ALL_ENTITIES'],
                'groupBy' => ['folders.id', 'folders.label']
            ]);
            foreach ($tmpRawFolders as $folders) {
                $rawFolders[$folders['id']] = [
                    'id'    => $folders['id'],
                    'label' => $folders['label'],
                    'count' => $folders['count'] + $rawFolders[$folders['id']]['count']
                ];
            }
        }

        $folders = [];
        if (!empty($body['filters']['folders']['values']) && is_array($body['filters']['folders']['values'])) {
            foreach ($body['filters']['folders']['values'] as $filter) {
                $count = 0;
                foreach ($rawFolders as $value) {
                    if ($filter['id'] == $value['id']) {
                        $count = $value['count'];
                    }
                }
                $folders[] = [
                    'id'        => $filter['id'],
                    'label'     => $filter['label'],
                    'count'     => $count,
                    'selected'  => $filter['selected']
                ];
            }
            $folders = [
                'values'    => $folders,
                'expand'    => $body['filters']['folders']['expand']
            ];
        } else {
            foreach ($rawFolders as $value) {
                $folders[] = [
                    'id'        => $value['id'],
                    'label'     => $value['label'],
                    'count'     => $value['count'],
                    'selected'  => false
                ];
            }
            $folders = [
                'values'    => $folders,
                'expand'    => false
            ];
        }

        if (empty($priorities['values'])) {
            $priorities['values'] = [];
        }
        if (empty($categories['values'])) {
            $categories['values'] = [];
        }
        if (empty($statuses['values'])) {
            $statuses['values'] = [];
        }
        if (empty($docTypes['values'])) {
            $docTypes['values'] = [];
        }
        if (empty($entities['values'])) {
            $entities['values'] = [];
        }
        if (empty($folders['values'])) {
            $folders['values'] = [];
        }

        usort($priorities['values'], ['Resource\controllers\ResourceListController', 'compareSortOnLabel']);
        usort($categories['values'], ['Resource\controllers\ResourceListController', 'compareSortOnLabel']);
        usort($statuses['values'], ['Resource\controllers\ResourceListController', 'compareSortOnLabel']);
        usort($docTypes['values'], ['Resource\controllers\ResourceListController', 'compareSortOnLabel']);
        usort($entities['values'], ['Resource\controllers\ResourceListController', 'compareSortOnLabel']);
        usort($folders['values'], ['Resource\controllers\ResourceListController', 'compareSortOnLabel']);

        return ['priorities' => $priorities, 'categories' => $categories, 'statuses' => $statuses, 'doctypes' => $docTypes, 'entities' => $entities, 'folders' => $folders];
    }

    private static function getAttachmentsInsider(array $args)
    {
        ValidatorModel::notEmpty($args, ['resources']);
        ValidatorModel::arrayType($args, ['resources', 'body']);

        $body = $args['body'];

        $where = ['res_id in (?)'];
        $data = [$args['resources']];
        $wherePlus = '';

        if (!empty($body['subject']) && !empty($body['subject']['values']) && is_string($body['subject']['values'])) {
            if ($body['subject']['values'][0] == '"' && $body['subject']['values'][strlen($body['subject']['values']) - 1] == '"') {
                $wherePlus = 'res_id in (select res_id_master from res_attachments where title = ? and status in (\'TRA\', \'A_TRA\', \'FRZ\') and attachment_type <> \'summary_sheet\')';
                $subject   = trim($body['subject']['values'], '"');
                $data[]    = $subject;
            } else {
                $attachmentField = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => ['title']]);
                $wherePlus = "res_id in (select res_id_master from res_attachments where {$attachmentField} and status in ('TRA', 'A_TRA', 'FRZ') and attachment_type not in (?))";
                $data[]    = "%{$body['subject']['values']}%";
                $data[]    = AttachmentTypeController::HIDDEN_ATTACHMENT_TYPES;
            }
        }
        if (!empty($body['chrono']) && !empty($body['chrono']['values']) && is_string($body['chrono']['values'])) {
            if (!empty($wherePlus)) {
                $wherePlus .= ' OR ';
            }
            $wherePlus .= 'res_id in (select res_id_master from res_attachments where identifier ilike ? and status in (\'TRA\', \'A_TRA\', \'FRZ\') and attachment_type <> \'summary_sheet\')';
            $data[] = "%{$body['chrono']['values']}%";
        }
        if (!empty($body['meta']) && !empty($body['meta']['values']) && is_string($body['meta']['values'])) {
            if ($body['meta']['values'][0] == '"' && $body['meta']['values'][strlen($body['meta']['values']) - 1] == '"') {
                if (!empty($wherePlus)) {
                    $wherePlus .= ' OR ';
                }
                $quick = trim($body['meta']['values'], '"');
                $wherePlus .= "res_id in (select res_id_master from res_attachments where (title = ? OR identifier = ?) and status in ('TRA', 'A_TRA', 'FRZ') and attachment_type not in (?))";
                $data[] = $quick;
                $data[] = $quick;
                $data[] = AttachmentTypeController::HIDDEN_ATTACHMENT_TYPES;
            } else {
                $fields = ['title', 'identifier'];
                $fields = AutoCompleteController::getInsensitiveFieldsForRequest(['fields' => $fields]);
                $requestDataAttachment = AutoCompleteController::getDataForRequest([
                    'search'        => $body['meta']['values'],
                    'fields'        => $fields,
                    'where'         => [],
                    'data'          => [],
                    'fieldsNumber'  => 2
                ]);

                if (!empty($requestDataAttachment['where'])) {
                    if (!empty($wherePlus)) {
                        $wherePlus .= ' OR ';
                    }

                    $wherePlus .= 'res_id in (select res_id_master from res_attachments where (' . implode(' OR ', $requestDataAttachment['where']) . ') and status in (\'TRA\', \'A_TRA\', \'FRZ\') and attachment_type <> \'summary_sheet\')';
                    $data = array_merge($data, $requestDataAttachment['data']);
                }
            }
        }
        if (empty($wherePlus)) {
            return [];
        }

        $where[] = "({$wherePlus})";
        $matchingResources = ResModel::get([
            'select'    => ['res_id'],
            'where'     => $where,
            'data'      => $data
        ]);
        $matchingResources = array_column($matchingResources, 'res_id');

        return $matchingResources;
    }
}
