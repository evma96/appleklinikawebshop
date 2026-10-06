#!/usr/bin/env bash
# TEST only. No private values are printed; backup streams are encrypted before disk.
set -Eeuo pipefail
umask 077
source /etc/appleklinika-ops/backup.env
[[ "$AK_ENVIRONMENT" == TEST && "$AK_SITE_HOST" == teszt.appleklinika.com ]]
[[ "$AK_COMPOSE_DIR" == /opt/appleklinika/production ]]
exec 9>/run/lock/appleklinika-test-backup.lock
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
 if (( code != 0 )); then state failed; echo 'TEST backup failed; private partial set retained for inspection.' >&2; fi
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
 git -c safe.directory=/opt/appleklinika/repo -C /opt/appleklinika/repo rev-parse HEAD
 dpkg-query -W
 docker inspect "$AK_WORDPRESS_CONTAINER" "$AK_DATABASE_CONTAINER" "$AK_PROXY_CONTAINER"
} | enc inventory
# Pause the only ordinary application writer to align the supported DB dump and files.
stopped=1
docker stop -t 30 "$AK_WORDPRESS_CONTAINER" >/dev/null
docker exec "$AK_DATABASE_CONTAINER" sh -c 'export MYSQL_PWD="$(cat /run/secrets/mysql_root_password)"; exec mariadb-dump -uroot --single-transaction --quick --routines --events --triggers --hex-blob --databases appleklinika' | enc database
tar --acls --xattrs --numeric-owner -czf - -C / opt/appleklinika/production opt/appleklinika/repo var/lib/docker/volumes/appleklinika-staging_wordpress_data/_data var/lib/docker/volumes/appleklinika-staging_caddy_data/_data var/lib/docker/volumes/appleklinika-staging_caddy_config/_data etc/ssh etc/wireguard etc/systemd/system etc/appleklinika-ops usr/local/lib/appleklinika-ops etc/apt/sources.list.d etc/fstab etc/ufw root/.ssh home/deploy/.ssh | enc files
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
printf 'TEST encrypted backup complete: %s (%ss)\n' "$run" "$(( $(date +%s)-started ))"
