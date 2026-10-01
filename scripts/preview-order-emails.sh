#!/bin/sh
set -eu
cd "$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
make test-email-presentation
preview_dir="$PWD/.local-runtime/email-previews"
mkdir -p "$preview_dir"
docker compose cp wordpress:/tmp/appleklinika-email-preview/. "$preview_dir/"
printf '%s\n' 'LOCAL-only email preview: http://127.0.0.1:18789/' 'Fictional, unsaved orders. No email or provider requests. Stop with Ctrl-C.'
python3 -m http.server 18789 --bind 127.0.0.1 --directory "$preview_dir"
