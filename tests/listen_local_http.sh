#!/bin/bash
set -eu

# Export credentials
SECRETS=("OPENAI_KEY_LINE_AI_BOT" "LINE_TOKENS_N_TARGETS" "FIREBASE_CONFIG")
for secret in "${SECRETS[@]}"; do
    export "$secret"="$(gcloud secrets versions access latest --secret="$secret")"
    # echo "$secret: ${!secret}"
done

# Launch function
export FUNCTION_TARGET=main
composer start

# Remove credentials
for secret in "${SECRETS[@]}"; do
    unset "$secret"
done
