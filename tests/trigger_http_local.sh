#!/bin/bash
set -eu

curl -X POST \
    -H "context-type: application:json" \
    -d '{"events": [{"source": {"type": "group", "groupId": "TARGET_ID_TEST"}, "type":"message", "message": {"text": "今日の天気は？"}, "replyToken": "REPLY_TOKEN"}]}' \
    http://localhost:8080
