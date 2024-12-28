#!/bin/bash
set -eu

gcloud pubsub topics publish line-ai-bot-trigger-test --message="test!"
