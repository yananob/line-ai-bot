#!/bin/bash
set -eu

bash ./deploy-http-prod.sh
bash ./deploy-topic-prod.sh
