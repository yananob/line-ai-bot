#!/bin/bash
set -eu

curl -X POST \
    -H "context-type: application:json" \
    -d '{"events": [{"source": {"type": "group", "groupId": "TARGET_ID_AUTOTEST"}, "type":"message", "message": {"text": "「蘭奢待」とは？"}, "replyToken": "REPLY_TOKEN"}]}' \
    http://localhost:8080
