#!/bin/bash
set -eu

export FUNCTION_TARGET=main

SECRETS=("OPENAI_KEY_LINE_AI_BOT" "LINE_TOKENS_N_TARGETS" "FIREBASE_CONFIG")

for secret in "${SECRETS[@]}"; do
    export "$secret"="$(gcloud secrets versions access latest --secret="$secret")"
    # echo "$secret: ${!secret}"
done

composer start

for secret in "${SECRETS[@]}"; do
    unset "$secret"
done
