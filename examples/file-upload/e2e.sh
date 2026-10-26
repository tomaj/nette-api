#!/usr/bin/env bash
# End-to-end check: one file and many files sent as multipart/form-data.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run composer install first" >&2; exit 1; }
source ../ci/lib.sh
serve 8092
files="$(mktemp -d)"
# a 1x1 PNG, a text file and a 200 kB file
printf '\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\1\0\0\0\1\x08\x06\0\0\0\x1f\x15\xc4\x89\0\0\0\rIDATx\x9cc\xf8\x0f\0\0\x01\x01\0\x05\x18\xd8N\0\0\0\0IEND\xaeB`\x82' > "$files/a.png"
echo 'hello' > "$files/a.txt"
head -c 200000 /dev/zero > "$files/big.png"

request -F "avatar=@$files/a.png" -F user=ada "$BASE/api/v1/avatar"
expect_status 201 "png"
expect_body '"user":"ada","type":"image/png"' "png"
ok "a PNG is stored, the type is detected from the content"

request -F "avatar=@$files/a.txt;type=image/png" -F user=ada "$BASE/api/v1/avatar"
expect_status 415 "fake type"
expect_body 'text/plain is not allowed' "fake type"
ok "a text file sent as image/png answers 415"

request -F "avatar=@$files/big.png" -F user=ada "$BASE/api/v1/avatar"
expect_status 413 "too big"
ok "a file over 100 kB answers 413"

request -F user=ada "$BASE/api/v1/avatar"
expect_status 400 "no file"
expect_body '"avatar":["Field is required"]' "no file"
ok "a missing file answers 400 from nette-api"

request -F "files[]=@$files/a.txt" -F "files[]=@$files/a.png" "$BASE/api/v1/attachments"
expect_status 200 "many"
expect_body '{"files":[{"name":"a.txt","size":6,"ok":true},{"name":"a.png"' "many"
ok "setMulti() gives one array per uploaded file"
rm -rf "$files"
