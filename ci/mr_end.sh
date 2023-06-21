#!/bin/bash

BRANCH=`echo $CI_COMMIT_MESSAGE | grep -oP "'(.*?)'" | head -1 | tr -d "'"`

ISSUE_ID=`echo $BRANCH | grep -oP "/[0-9]*/" | head -1 | tr -d "/"`

if [[ ! -z $ISSUE_ID ]]
then

    for row in $(curl --header "PRIVATE-TOKEN: $TOKEN_GITLAB" "https://labs.maarch.org/api/v4/projects/$CI_PROJECT_ID/merge_requests?state=merged&in=source_branch&search=$ISSUE_ID" | jq -r '.[] | @base64'); do
        _jq() {
        echo ${row} | base64 --decode | jq -r ${1}
        }

        URL=$(_jq '.web_url')

        NOTE_MESSAGE="[MERGE REQUEST] Mergé sur **$CI_COMMIT_REF_NAME** ($URL)"
    done

    BODY="{\"issue\":{\"notes\":\"$NOTE_MESSAGE\",\"private_notes\":false}}"

    echo $BODY

    curl -H 'Content-Type:application/json' -H "X-Redmine-API-Key:$REDMINE_API_KEY" -d "$BODY" -X PUT https://forge.maarch.org/issues/$ISSUE_ID.json
else
    echo "NO US FOUND !"
fi
