#!/usr/bin/env python3
"""Private Mac pull/check job; no email is sent and no credential is printed."""
import fcntl,argparse,hashlib,json,logging,logging.handlers,os,pathlib,re,shutil,subprocess,tarfile,time
ROOT=pathlib.Path.home()/'AppleKlinika-Private-Backups'
DEST=ROOT/'routine-test'
KEY=ROOT/'round1-20261005/recovery-key.pem'
STATE=ROOT/'operations'
REMOTE='/opt/appleklinika/backups/routine'
FILES=('database.cms','files.cms','inventory.cms','logs.cms')

def invoke(args,timeout=40):
 p=subprocess.run(args,stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=timeout)
 if p.returncode: raise RuntimeError('command_failed:'+pathlib.Path(args[0]).name)
 return p.stdout

def verify(directory):
 lines=(directory/'SHA256SUMS').read_text().splitlines(); seen=set()
 for line in lines:
  digest,name=line.split(); assert name in FILES and re.fullmatch('[a-f0-9]{64}',digest)
  h=hashlib.sha256()
  with (directory/name).open('rb') as f:
   for block in iter(lambda:f.read(1024*1024),b''): h.update(block)
  assert h.hexdigest()==digest, 'checksum_mismatch'; seen.add(name)
 assert seen==set(FILES)
 stats={}
 for name in FILES:
  p=subprocess.Popen(['/usr/bin/openssl','cms','-decrypt','-binary','-inform','DER','-in',str(directory/name),'-inkey',str(KEY)],stdout=subprocess.PIPE,stderr=subprocess.DEVNULL)
  if name=='files.cms':
   count=0; required={'opt/appleklinika/production/compose.yaml','opt/appleklinika/production/secrets/mysql_password','var/lib/docker/volumes/appleklinika-staging_wordpress_data/_data/wp-config.php'};found=set()
   with tarfile.open(fileobj=p.stdout,mode='r|gz') as tar:
    for member in tar:
     count+=1
     if member.name in required: found.add(member.name)
   # Consume archive padding so the decryption producer can terminate cleanly.
   while p.stdout.read(1024*1024): pass
   assert found==required,'missing_recovery_material'; stats['archive_entries']=count
  elif name=='database.cms':
   data=p.stdout.read(); assert b'Dump completed' in data[-1000:]; stats['database_tables']=data.count(b'CREATE TABLE'); assert stats['database_tables']>=61; del data
  else:
   size=0
   while True:
    block=p.stdout.read(1024*1024)
    if not block:break
    size+=len(block)
   assert size>0
  assert p.wait()==0,'decryption_failed'
 stats.update(verified_epoch=int(time.time()),checksums='PASS',decryption='PASS')
 (directory/'verification.json').write_text(json.dumps(stats)+'\n')
 return stats

def main():
 parser=argparse.ArgumentParser();parser.add_argument('--self-test',action='store_true');args=parser.parse_args()
 if args.self_test:
  assert not re.fullmatch(r'\d{8}T\d{6}Z','../private')
  assert set(FILES)=={'database.cms','files.cms','inventory.cms','logs.cms'}
  print('Sync filename/path safety checks passed; no network or notification.');return
 os.umask(0o077)
 for p in (DEST,STATE):p.mkdir(mode=0o700,parents=True,exist_ok=True)
 lock=(STATE/'sync.lock').open('w')
 try:fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
 except BlockingIOError:return
 logger=logging.getLogger('appleklinika-test-ops');logger.setLevel(logging.INFO)
 handler=logging.handlers.RotatingFileHandler(STATE/'events.log',maxBytes=200000,backupCount=4);handler.setFormatter(logging.Formatter('%(asctime)s %(message)s'));logger.addHandler(handler)
 report={'checked_epoch':int(time.time()),'issues':[],'copied':[]}
 try:
  raw=invoke(['/usr/bin/ssh','-o','BatchMode=yes','-o','ConnectTimeout=10','appleklinika-test','cat /var/lib/appleklinika-ops/monitor.json']);monitor=json.loads(raw)
  if time.time()-monitor.get('checked_epoch',0)>900: report['issues'].append('remote_monitor_stale')
  report['issues']+=monitor.get('issues',[])
  names=invoke(['/usr/bin/ssh','-o','BatchMode=yes','-o','ConnectTimeout=10','appleklinika-test',"find /opt/appleklinika/backups/routine -mindepth 1 -maxdepth 1 -type d -name '20*' -printf '%f\\n'"]).decode().splitlines()
  for name in sorted(names):
   assert re.fullmatch(r'\d{8}T\d{6}Z',name),'unsafe_set_name'
   target=DEST/name
   if (target/'verification.json').exists():continue
   partial=DEST/('.partial-'+name);partial.mkdir(mode=0o700,exist_ok=True)
   for file in (*FILES,'SHA256SUMS'):
    invoke(['/usr/bin/scp','-q','-o','BatchMode=yes','-o','ConnectTimeout=10','appleklinika-test:'+REMOTE+'/'+name+'/'+file,str(partial/file)],timeout=900)
   verify(partial);partial.replace(target);report['copied'].append(name)
  valid=sorted(p for p in DEST.iterdir() if p.is_dir() and not p.is_symlink() and re.fullmatch(r'\d{8}T\d{6}Z',p.name) and (p/'verification.json').exists())
  if not valid:report['issues'].append('independent_backup_missing')
  else:
   latest=time.strptime(valid[-1].name,'%Y%m%dT%H%M%SZ');import calendar
   report['independent_backup_age_hours']=round((time.time()-calendar.timegm(latest))/3600,2)
   if report['independent_backup_age_hours']>30:report['issues'].append('independent_backup_stale')
  for expired in valid[:-30]:shutil.rmtree(expired)
 except Exception as error:
  # Never log subprocess output, SQL, archive contents or credentials.
  report['issues'].append('ssh_or_backup_verification_failure');report['failure_type']=type(error).__name__
 try:
  code=invoke(['/usr/bin/curl','--silent','--max-time','20','--output','/dev/null','--write-out','%{http_code}','https://teszt.appleklinika.com/']).decode()
  if code!='200':report['issues'].append('public_http')
 except Exception:report['issues'].append('public_http')
 report['issues']=sorted(set(report['issues']));report['status']='alert' if report['issues'] else 'ok'
 path=STATE/'status.json';old=json.loads(path.read_text()) if path.exists() else {}
 tmp=path.with_suffix('.tmp');tmp.write_text(json.dumps(report)+'\n');tmp.replace(path)
 if old.get('issues')!=report['issues'] or report['copied']:logger.info(json.dumps(report))
 print(json.dumps(report))
 if report['issues']:raise SystemExit(1)
if __name__=='__main__':main()
