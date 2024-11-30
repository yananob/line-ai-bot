#!/bin/bash
set -eu

DEPLOY_DIR=_deploy
export SCRIPT_NAME=line-ai-bot
# remove "/" on the right side
SCRIPT_NAME=`php -r '$result=getenv("SCRIPT_NAME"); echo substr($result, -1) === "/" ? rtrim($result, "/") : $result;'`

echo "Checking ${SCRIPT_NAME}"
# pushd ${SCRIPT_NAME}

# Check existance of .gcloudignore
if ! test -f ".gcloudignore"; then
    echo ".gcloudignore doesn't exist. Please create it."
    exit 1
fi

# # Check existance of specific deploy.sh
# if test -f "deploy.sh"; then
#     echo "Specific deploy.sh for this app exists. Please run it instead of this shell."
#     exit 1
# fi

# check existance of config.sample.json & config.json
if test -f "configs/config.json.sample"; then
    if test ! -f "configs/config.json"; then
        echo "Config.json.sample exists. Please make config.json for this app."
        exit 1
    fi
fi
# popd

echo "Starting to deploy ${SCRIPT_NAME}"

rm -rf ./${DEPLOY_DIR}
mkdir -p ${DEPLOY_DIR}
pushd ${DEPLOY_DIR}

rsync -vaL --exclude-from=../_cf-common/deploy/rsync_exclude.conf ../ ./

echo "-------- deploying http --------"
gcloud functions deploy ${SCRIPT_NAME} \
    --gen2 \
    --runtime=php82 \
    --region=us-west1 \
    --source=. \
    --entry-point=main \
    --trigger-http \
    --allow-unauthenticated \
    --max-instances 1

popd
rm -rf ./${DEPLOY_DIR}
