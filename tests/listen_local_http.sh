#!/bin/bash
set -eu

export FUNCTION_TARGET=main

export OPENAI_KEY_LINE_AI_BOT=$(gcloud secrets versions access latest --secret=OPENAI_KEY_LINE_AI_BOT)
export LINE_TOKENS_N_TARGETS=$(gcloud secrets versions access latest --secret=LINE_TOKENS_N_TARGETS)
export FIREBASE_CONFIG=$(gcloud secrets versions access latest --secret=FIREBASE_CONFIG)
# echo $FIREBASE_CONFIG

composer start

unset OPENAI_KEY_LINE_AI_BOT
unset LINE_TOKENS_N_TARGETS
unset FIREBASE_CONFIG
