#!/bin/bash
set -eu

export FUNCTION_TARGET=main

export OPENAI_API_KEY=$(gcloud secrets versions access latest --secret=openai-api-key-line-ai-bot)
echo $OPENAI_API_KEY

composer start

unset OPENAI_API_KEY
