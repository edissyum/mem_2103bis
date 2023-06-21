#!/bin/bash

# EXCLUDE TMA BRANCH
IS_TMA=$(echo $CI_COMMIT_TAG | grep -o '[.]*_TMA[.]*')

if [[ -n $IS_TMA ]]; then
  echo "On TMA branch ! Skipping..."
  exit 0
fi

tag=$CI_COMMIT_TAG

echo "tag:$tag"

structures=$(echo $CI_COMMIT_TAG | tr "." "\n")

IT=1
for item in $structures; do
  if [ $IT = 1 ]; then
    major_version=$item
  fi

  if [ $IT = 2 ]; then
    major_version="$major_version.$item"
  fi

  if [ $IT = 3 ]; then
    current_num_tag=$item
  fi

  IT=$((IT + 1))
done

previous_num_tag=$((current_num_tag - 1))
next_num_tag=$((current_num_tag + 1))

previous_tag="$major_version.$previous_num_tag"
next_tag="$major_version.$next_num_tag"

echo "previoustag:$previous_tag"

for row in $(curl --header "PRIVATE-TOKEN: $TOKEN_GITLAB" "https://labs.maarch.org/api/v4/projects/$CI_PROJECT_ID/milestones?search=$CI_COMMIT_TAG" | jq -r '.[] | @base64'); do
  _jq() {
    echo ${row} | base64 --decode | jq -r ${1}
  }

  ID=$(_jq '.id')

  echo $ID

  BODY="{\"id\":\"$ID\",\"state_event\":\"close\"}"

  curl -v -H 'Content-Type:application/json' -H "PRIVATE-TOKEN:$TOKEN_GITLAB" -d "$BODY" -X PUT https://labs.maarch.org/api/v4/projects/$CI_PROJECT_ID/milestones/$ID

done

BODY="{\"id\":\"$CI_PROJECT_ID\",\"title\":\"$next_tag\"}"

# CREATE NEXT TAG MILESTONE
curl -v -H 'Content-Type:application/json' -H "PRIVATE-TOKEN:$TOKEN_GITLAB" -d "$BODY" -X POST https://labs.maarch.org/api/v4/projects/$CI_PROJECT_ID/milestones

# GENERATE RAW CHANGELOG
COMMIT_LOG_FILE="tmp.txt"
ISSUES_IDS_FILE="tmp2.txt"
SORTED_UNIQUE_ISSUES_IDS="tmp3.txt"
FINAL_LOG="tmp4.txt"

CONTENT=""

ls -al

echo "Set user git : $GITLAB_USER_NAME <$GITLAB_USER_EMAIL>"

git config --global user.email "$GITLAB_USER_EMAIL" && git config --global user.name "$GITLAB_USER_NAME"

git remote set-url origin "https://gitlab-ci-token:${TOKEN_GITLAB}@${GITLAB_URL}/${CI_PROJECT_PATH}"

git branch -D $major_version
git pull origin $major_version
git checkout $major_version

echo "git log $previous_tag..$CI_COMMIT_TAG --pretty=format:'%s' --grep='Update referential' --all-match"

REF_UPDATED=$(git log $previous_tag..$CI_COMMIT_TAG --pretty=format:'%s' --grep='Update referential' --all-match)

echo "git log $previous_tag..$CI_COMMIT_TAG --pretty=format:'%s' --grep='FEAT' --all-match"

git log $previous_tag..$CI_COMMIT_TAG --pretty=format:'%s' --grep='FEAT' --all-match >$COMMIT_LOG_FILE
echo '' >>$COMMIT_LOG_FILE

while IFS= read -r line; do
  ISSUE_ID=$(echo $line | grep -o 'FEAT #[0-9]*' | grep -o '[0-9]*')
  echo "$ISSUE_ID" >>$ISSUES_IDS_FILE
done <"$COMMIT_LOG_FILE"

echo "git log $previous_tag..$CI_COMMIT_TAG --pretty=format:'%s' --grep='FIX' --all-match"

git log $previous_tag..$CI_COMMIT_TAG --pretty=format:'%s' --grep='FIX' --all-match >$COMMIT_LOG_FILE
echo '' >>$COMMIT_LOG_FILE

while IFS= read -r line; do
  ISSUE_ID=$(echo $line | grep -o 'FIX #[0-9]*' | grep -o '[0-9]*')
  echo "$ISSUE_ID" >>$ISSUES_IDS_FILE
done <"$COMMIT_LOG_FILE"

sort -u $ISSUES_IDS_FILE >$SORTED_UNIQUE_ISSUES_IDS

while IFS= read -r line; do
  echo "=================="
  echo $line
  curl -H "X-Redmine-API-Key: ${REDMINE_API_KEY}" -H 'Content-Type: application/json' -X GET https://forge.maarch.org/issues/$line.json >issue_$line.json
  # echo `cat issue_$line.json`
  SUBJECT=$(cat issue_$line.json | jq -r '.issue.subject')
  TRACKER=$(cat issue_$line.json | jq -r '.issue.tracker.name')
  ID=$(cat issue_$line.json | jq -r '.issue.id')
  echo ""
  echo "ID : $ID"
  echo "TRACKER : $TRACKER"
  echo "SUBJECT : $SUBJECT"
  echo "=================="

  if [ ! -z $ID ]; then
    echo "* **$TRACKER [#$ID](https://forge.maarch.org/issues/$ID)** - $SUBJECT" >>$FINAL_LOG
  fi
done <"$SORTED_UNIQUE_ISSUES_IDS"

if [[ ! -z $REF_UPDATED ]]; then
  echo "* **Fonctionnalité** - Mise à jour de la BAN 75" >>$FINAL_LOG
fi

sort -u $FINAL_LOG >>changelog.txt

while IFS= read -r line; do
  CONTENT="$CONTENT\n$line"
done <"changelog.txt"

echo $CONTENT

# Update tag release
# Replace all " by \" in $CONTENT
CONTENT=${CONTENT//\"/\\\"}
BODY="{\"tag_name\":\"$CI_COMMIT_TAG\",\"description\":\"$CONTENT\"}"

echo "$BODY"
curl -v -H 'Content-Type:application/json' -H "PRIVATE-TOKEN:$TOKEN_GITLAB" -d "$BODY" -X POST https://labs.maarch.org/api/v4/projects/$CI_PROJECT_ID/releases > result.json
cat result.json

# NOTIFY TAG IN SLACK
curl -X POST --data-urlencode "payload={\"channel\": \"$CHANNEL_SLACK_NOTIFICATION\", \"username\": \"$USERNAME_SLACK_NOTIFICATION\", \"text\": \"Jalon mis à jour à la version $tag!\nVeuillez rédiger le <$CI_PROJECT_URL/tags/$tag/release/edit|changelog> et définir une date de sortie.\", \"icon_emoji\": \":cop:\"}" $URL_SLACK_NOTIFICATION

# Update files version
cp package.json tmp_package.json

jq -r ".version |= \"$next_tag\"" tmp_package.json >package.json

rm tmp_package.json

git add -f package.json

mkdir -p ci/build/
mv $COMMIT_LOG_FILE ci/build/
mv $ISSUES_IDS_FILE ci/build/
mv $SORTED_UNIQUE_ISSUES_IDS ci/build/
mv $FINAL_LOG ci/build/

# sed -i -e "s/$CI_COMMIT_TAG/$next_tag/g" sql/test.sql

# git add -f sql/test.sql

git status

git commit -m "Update next tag version files : $next_tag"

git status

git push origin $major_version
