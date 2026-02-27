#!/bin/bash
set -eu

source ./tests/secrets.sh
source ./_cf-common/test/export_secrets.sh ${SECRETS[*]}

echo "Running PHPStan..."
./vendor/bin/phpstan analyze -c phpstan.neon

TEST_TARGET=""
if [ $# -eq 1 ];then
    TEST_TARGET=$1.php
fi

echo "Running PHPUnit $TEST_TARGET..."
./vendor/bin/phpunit --colors=auto --display-notices --display-warnings --display-errors tests/$TEST_TARGET

source ./_cf-common/test/unset_secrets.sh ${SECRETS[*]}
