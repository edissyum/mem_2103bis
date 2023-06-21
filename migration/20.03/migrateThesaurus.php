<?php

use SrcCore\models\DatabaseModel;
use Tag\models\ResourceTagModel;
use Tag\models\TagModel;

require '../../vendor/autoload.php';

chdir('../..');

$customs = scandir('custom');

foreach ($customs as $custom) {
    if ($custom == 'custom.xml' || $custom == '.' || $custom == '..') {
        continue;
    }

    \SrcCore\models\DatabasePDO::reset();
    new \SrcCore\models\DatabasePDO(['customId' => $custom]);

    $migrated = 0;

    $thesaurusList = \SrcCore\models\DatabaseModel::select([
        'select' => ['*'],
        'table'  => ['thesaurus']
    ]);

    $parents = [];
    $links = [];

    $thesaurusIds = [];

    // Migrate elements in thesaurus
    $tagId = DatabaseModel::getNextSequenceValue(['sequenceId' => 'tags_id_seq']);
    foreach ($thesaurusList as $thesaurus) {
        DatabaseModel::insert([
            'table'         => 'tags',
            'columnsValues' => [
                'id'            => $tagId,
                'label'         => $thesaurus['thesaurus_name'],
                'description'   => $thesaurus['thesaurus_description'] ?? null,
                'usage'         => $thesaurus['used_for'] ?? null,
                'creation_date' => $thesaurus['creation_date'] ?? null
            ]
        ]);

        if (!empty($thesaurus['thesaurus_parent_id'])) {
            $parents[$tagId] = [
                'parent_label'     => $thesaurus['thesaurus_parent_id'],
                'tag_label'        => $thesaurus['thesaurus_name'],
                'tag_id' => $tagId
            ];
        }
        if (!empty($thesaurus['thesaurus_name_associate'])) {
            $links[$tagId] = [
                'name_association' => $thesaurus['thesaurus_name_associate'],
                'tag_label'        => $thesaurus['thesaurus_name'],
                'tag_id' => $tagId
            ];
        }

        $thesaurusIds[$thesaurus['thesaurus_id']] = $tagId;

        $migrated++;
        $tagId++;
    }

    // Migrate elements parents
    $parentMigrated = 0;
    $parentNotMigrated = 0;
    $linksMigrated = 0;
    $linksNotMigrated = 0;
    foreach ($parents as $tagId => $association) {
        $parent = TagModel::get([
            'where' => ['label = ?'],
            'data'  => [$association['parent_label']]
        ]);
        if (empty($parent[0])) {
            echo "[PARENT] Le tag '" . $association['parent_label'] . "', parent du tag '" . $association['tag_label'] . "' n'a pas été trouvé\n";
            $parentNotMigrated++;
        } else {
            TagModel::update([
                'set'   => [
                    'parent_id' => $parent[0]['id'],
                ],
                'where' => ['id = ?'],
                'data'  => [$tagId]
            ]);
            $parentMigrated++;
        }
    }

    // Migrate elements links
    foreach ($links as $link) {
        $nameList = explode(",", $link['name_association']);

        foreach ($nameList as $item) {
            $linkedTag = TagModel::get([
                'where' => ['label = ?'],
                'data' => [$item]
            ]);
            if (empty($linkedTag[0])) {
                echo "[LINK] Le tag '" . $item . "', associé au tag '" . $link['tag_label'] . "' n'a pas été trouvé\n";
                $linksNotMigrated++;
            } else {
                TagModel::update([
                    'postSet'   => ['links' => "jsonb_insert(links, '{0}', '\"{$linkedTag[0]['id']}\"')"],
                    'where'     => ['id = ?', "(links @> ?) = false"],
                    'data'      => [$tagId, "\"{$linkedTag[0]['id']}\""]
                ]);
                TagModel::update([
                    'postSet'   => ['links' => "jsonb_insert(links, '{0}', '\"{$tagId}\"')"],
                    'where'     => ['id = ?', "(links @> ?) = false"],
                    'data'      => [$linkedTag[0]['id'], "\"{$tagId}\""]
                ]);
                $linksMigrated++;
            }
        }
    }

    $thesaurusResList = \SrcCore\models\DatabaseModel::select([
        'select' => ['*'],
        'table'  => ['thesaurus_res']
    ]);

    foreach ($thesaurusResList as $item) {
        ResourceTagModel::create([
            'res_id' => $item['res_id'],
            'tag_id' => $thesaurusIds[$item['thesaurus_id']]
        ]);
    }

    // Get all empty tags
    $emptyTags = TagModel::get([
        'select'  => ['id', 'links', 'parent_id'],
        'where'   => ["replace(label, ' ', '') = ''"],
        'orderBy' => ['id desc']
    ]);

    // Remove resources links + tag links
    foreach ($emptyTags as $emptyTag) {
        ResourceTagModel::delete([
            'where' => ['tag_id = ?'],
            'data'  => [$emptyTag['id']]
        ]);

        $links = json_decode($emptyTag['links'], true);
        foreach ($links as $link) {
            TagModel::update([
                'postSet' => ['links' => "links - '{$emptyTag['id']}'"],
                'where'   => ['id = ?'],
                'data'    => [$link]
            ]);
            TagModel::update([
                'postSet' => ['links' => "links - '{$link}'"],
                'where'   => ['id = ?'],
                'data'    => [$emptyTag['id']]
            ]);
        }

        $parentId = TagModel::getById([
            'select' => ['parent_id'],
            'id'     => $emptyTag['id']
        ]);
        $parentId = $parentId['parent_id'];

        TagModel::update([
            'set'   => ['parent_id' => $parentId],
            'where' => ['parent_id = ?'],
            'data'  => [$emptyTag['id']]
        ]);
    }

    // Delete tags
    $emptyTags = array_column($emptyTags, 'id');
    $nbEmptyTags = count($emptyTags);
    if (!empty($emptyTags)) {
        TagModel::delete([
            'where' => ['id in (?)'],
            'data'  => [$emptyTags]
        ]);
    }

    printf("Migration du thesaurus dans la table tags (CUSTOM {$custom}) : " . $migrated . " termes migrés. ($parentMigrated liens de parentés migrés, $parentNotMigrated non migrés, $linksMigrated associations migrés, $linksNotMigrated non migrés, $nbEmptyTags tag(s) vide supprimé(s))\n");
}
