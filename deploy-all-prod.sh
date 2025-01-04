#!/bin/bash
set -eu

bash ./deploy-http-prod.sh
bash ./deploy-event-prod.sh
