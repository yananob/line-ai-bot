#!/bin/bash
set -eu

bash ./deploy_http_prod.sh
bash ./deploy_event_prod.sh
