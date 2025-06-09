#!/bin/bash
set -eu

gcloud pubsub topics publish line-ai-bot-event --message="test!"
