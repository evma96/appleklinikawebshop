#!/usr/bin/env bash
# Disabled-by-default reusable preparation. Existing TEST backup is unchanged.
set -Eeuo pipefail
umask 077
set +x
config=${1:?Provide an explicitly approved private configuration path}
source "$config"
if [[ "${AK_BACKUP_ENABLED:-no}" != yes ]]; then echo 'Reusable backup disabled; no environment accessed.'; exit 0; fi
[[ "$AK_ENVIRONMENT" == TEST || "$AK_ENVIRONMENT" == PRODUCTION ]]
[[ "$(hostname)" == "$AK_EXPECTED_HOSTNAME" && "$AK_EXPECTED_HOSTNAME" != CHANGE_ME ]]
[[ "$AK_SITE_HOST" != CHANGE_ME && "$AK_REPO_DIR" == /* && "$AK_BACKUP_ROOT" == /* ]]
[[ "$AK_DB_NAME" =~ ^[a-zA-Z0-9_]+$ && "$AK_DB_SECRET_FILE" == /run/secrets/* ]]
[[ "$AK_RETENTION_SETS" =~ ^[0-9]+$ && "$AK_RETENTION_SETS" -ge 2 ]]
python3 - "$config" "$AK_ARCHIVE_PATHS_FILE" "$AK_BACKUP_ROOT" <<'CHECK'
import pathlib,sys,os
config,listing,backup=map(pathlib.Path,sys.argv[1:])
assert config.stat().st_uid==os.getuid() and config.stat().st_mode & 0o077==0
assert listing.stat().st_mode & 0o022==0
paths=listing.read_text().splitlines();assert paths
for item in paths:
 p=pathlib.Path('/'+item)
 assert item and not item.startswith(('/', '-')) and '..' not in p.parts and p.exists()
 assert p.resolve()!=backup.resolve() and p.resolve() not in backup.resolve().parents
CHECK
exec 9>"$AK_LOCK_FILE"
flock -n 9 || exit 0
mkdir -p "$AK_BACKUP_ROOT" "$AK_STATE_DIR"
run=$(date -u +%Y%m%dT%H%M%SZ)
partial="$AK_BACKUP_ROOT/.partial-$run"
final="$AK_BACKUP_ROOT/$run"
[[ ! -e "$partial" && ! -e "$final" ]]
mkdir "$partial"
stopped=0
started=$(date +%s)
state() {
 AK_STATUS="$1" AK_STARTED="$started" AK_RUN="$run" python3 - <<'PY'
import os,json,pathlib,time
p=pathlib.Path(os.environ['AK_STATE_DIR'])/'backup.json'
old=json.loads(p.read_text()) if p.exists() else {}
s={'status':os.environ['AK_STATUS'],'run':os.environ['AK_RUN'],'updated_epoch':int(time.time()),'started_epoch':int(os.environ['AK_STARTED']),'last_success_epoch':old.get('last_success_epoch',0)}
if s['status']=='success': s['last_success_epoch']=int(time.time())
t=p.with_suffix('.tmp');t.write_text(json.dumps(s)+'\n');t.chmod(0o600);t.replace(p)
PY
}
finish() {
 code=$?
 trap - EXIT
 if (( stopped )); then docker start "$AK_WORDPRESS_CONTAINER" >/dev/null || code=1; fi
 if (( code != 0 )); then state failed; echo 'Encrypted backup failed; private partial set retained for inspection.' >&2; fi
 exit "$code"
}
trap finish EXIT
state running
[[ -s "$AK_RECIPIENT_CERT" ]]
openssl x509 -in "$AK_RECIPIENT_CERT" -noout -checkend 2592000 >/dev/null
[[ $(df -Pk "$AK_BACKUP_ROOT" | awk 'NR==2 {print $4}') -gt 2097152 ]]
[[ $(docker inspect -f '{{.State.Running}}' "$AK_WORDPRESS_CONTAINER") == true ]]
enc() { openssl cms -encrypt -binary -aes-256-cbc -out "$partial/$1.cms" -outform DER "$AK_RECIPIENT_CERT"; }
{
 date -u +%FT%TZ
 git -c safe.directory="$AK_REPO_DIR" -C "$AK_REPO_DIR" rev-parse HEAD
 dpkg-query -W
 docker inspect "$AK_WORDPRESS_CONTAINER" "$AK_DATABASE_CONTAINER" "$AK_PROXY_CONTAINER"
} | enc inventory
# Pause the only ordinary application writer to align the supported DB dump and files.
stopped=1
docker stop -t 30 "$AK_WORDPRESS_CONTAINER" >/dev/null
docker exec -e AK_DB_NAME="$AK_DB_NAME" -e AK_DB_SECRET_FILE="$AK_DB_SECRET_FILE" "$AK_DATABASE_CONTAINER" sh -c 'export MYSQL_PWD="$(cat "$AK_DB_SECRET_FILE")"; exec mariadb-dump -uroot --single-transaction --quick --routines --events --triggers --hex-blob --databases "$AK_DB_NAME"' | enc database
tar --acls --xattrs --numeric-owner -czf - -C / --verbatim-files-from -T "$AK_ARCHIVE_PATHS_FILE" | enc files
{
 for c in "$AK_WORDPRESS_CONTAINER" "$AK_DATABASE_CONTAINER" "$AK_PROXY_CONTAINER"; do
  printf '\nCONTAINER %s\n' "$c"
  docker logs --timestamps "$c" 2>&1
 done
} | enc logs
docker start "$AK_WORDPRESS_CONTAINER" >/dev/null
stopped=0
for f in "$partial"/*.cms; do [[ -s "$f" ]]; done
(cd "$partial" && sha256sum *.cms > SHA256SUMS && sha256sum -c SHA256SUMS)
# CMS recipient verification is possible without placing the decryption key on TEST.
openssl cms -cmsout -inform DER -in "$partial/database.cms" -noout
mv "$partial" "$final"
state success
# Delete only old successful routine sets; never the manually preserved Round 1 set.
python3 - <<'PY'
import pathlib,os,re,shutil
root=pathlib.Path(os.environ['AK_BACKUP_ROOT'])
sets=sorted(p for p in root.iterdir() if p.is_dir() and not p.is_symlink() and re.fullmatch(r'\d{8}T\d{6}Z',p.name) and (p/'SHA256SUMS').is_file())
for p in sets[:-int(os.environ['AK_RETENTION_SETS'])]: shutil.rmtree(p)
PY
printf 'Encrypted backup complete: %s (%ss)\n' "$run" "$(( $(date +%s)-started ))"
