#!/bin/bash

structures=$(echo $CI_COMMIT_REF_NAME | tr "/" "\n")

TRACKER=""

US=""

BRANCH=""

IT=1
for item in $structures
do
    if [ $IT = 1 ]; then
        TRACKER=$item
    fi

    if [ $IT = 2 ]; then
        US=$item
    fi

    if [ $IT = 3 ]; then
        BRANCH=$item
    fi

    IT=$((IT+1))
done


if [[ -z $TRACKER ]] || [[ -z $US ]] || [[ -z $BRANCH ]]
then
    echo "Bad structure to find US ! => [TRACKER]/[US_ID]/[TARGET_BRANCH]"
else

    echo $TRACKER
    echo $US
    echo $BRANCH

    echo "GET https://forge.maarch.org/issues/$US.json"

    curl -H "X-Redmine-API-Key: ${REDMINE_API_KEY}" -H 'Content-Type: application/json' -X GET https://forge.maarch.org/issues/$US.json > issue_$US.json

    SUBJECT=`cat issue_$US.json | jq -r '.issue.subject'`

    BODY="{\"id\":\"$CI_PROJECT_ID\",\"source_branch\":\"$CI_COMMIT_REF_NAME\",\"target_branch\":\"$BRANCH\",\"title\":\"Draft: [$US] $SUBJECT\",\"description\":\"$SUBJECT\n=> https://forge.maarch.org/issues/$US\",\"remove_source_branch\":\"true\"}"

    echo $BODY

    echo "POST https://labs.maarch.org/api/v4/projects/$CI_PROJECT_ID/merge_requests"

    curl -v -H "PRIVATE-TOKEN: $TOKEN_GITLAB" -H "Content-Type: application/json" -X POST -d "$BODY" "https://labs.maarch.org/api/v4/projects/$CI_PROJECT_ID/merge_requests"
fi