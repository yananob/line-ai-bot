#!/bin/bash
set -eu

curl -X POST \
    -H "context-type: application:json" \
    -d '{"events": [{"source": {"type": "group", "groupId": "TARGET_ID_TEST_ZZ"}, "message": {"text": "今日はとても疲れた！"}, "replyToken": "REPLY_TOKEN"}]}' \
    http://localhost:8080
