#!/bin/bash
set -eu

gcloud pubsub topics publish line-ai-bot-test-event --message="test!"
