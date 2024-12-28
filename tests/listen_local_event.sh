#!/bin/bash
set -eu

export FUNCTION_TARGET=trigger
export FUNCTION_SIGNATURE_TYPE=cloudevent
php -S localhost:8081 vendor/bin/router.php
