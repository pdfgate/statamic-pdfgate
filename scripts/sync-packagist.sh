#!/usr/bin/env bash

set -euo pipefail

event_name="${EVENT_NAME:-}"
sync_mode="${SYNC_MODE:-}"
packagist_url="${PACKAGIST_API_URL:-https://packagist.org/api/update-package}"
repository_url="${PACKAGIST_REPOSITORY_URL:-https://github.com/pdfgate/statamic-pdfgate}"
curl_bin="${CURL_BIN:-curl}"

if [ -z "$sync_mode" ]; then
  if [ "$event_name" = "release" ]; then
    sync_mode="live"
  else
    sync_mode="dry-run"
  fi
fi

payload=$(printf '{"repository":"%s"}' "$repository_url")

case "$sync_mode" in
  dry-run)
    echo "Packagist sync mode: dry-run"
    echo "Target URL: $packagist_url"
    echo "Request method: POST"
    echo "Request header: Content-Type: application/json"
    echo "Request header: Authorization: Bearer <redacted>"
    echo "Request payload: $payload"
    echo "Dry run complete; no network request sent."
    ;;
  live)
    packagist_username="${PACKAGIST_USERNAME:-}"
    packagist_token="${PACKAGIST_TOKEN:-}"

    if [ -z "$packagist_username" ] || [ -z "$packagist_token" ]; then
      echo "PACKAGIST_USERNAME and PACKAGIST_TOKEN must be configured for live Packagist sync." >&2
      exit 1
    fi

    response_file=$(mktemp)
    trap 'rm -f "$response_file"' EXIT

    status_code=$("$curl_bin" --silent --show-error \
      --output "$response_file" \
      --write-out "%{http_code}" \
      --request POST \
      --header "Content-Type: application/json" \
      --header "Authorization: Bearer ${packagist_username}:${packagist_token}" \
      --data "$payload" \
      "$packagist_url")

    if [ "$status_code" -lt 200 ] || [ "$status_code" -ge 300 ]; then
      echo "Packagist sync failed with status $status_code" >&2
      cat "$response_file"
      exit 1
    fi

    echo "Packagist sync successful"
    cat "$response_file"
    ;;
  *)
    echo "Unsupported sync mode '$sync_mode'. Expected dry-run or live." >&2
    exit 1
    ;;
esac
