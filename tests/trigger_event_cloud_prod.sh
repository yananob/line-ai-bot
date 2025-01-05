#!/bin/bash
set -eu

gcloud pubsub topics publish line-ai-bot-trigger --message="test!"
