#!/usr/bin/env python3
"""Bounded, read-only TEST monitor. Status contains counts, never log bodies/secrets."""
import argparse,datetime,json,os,pathlib,re,shutil,socket,ssl,subprocess,time

def run(args,timeout=25):
 p=subprocess.run(args,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,timeout=timeout)
 return p.returncode,p.stdout,p.stderr

def classify(f):
 issues=[]
 if f.get('disk_percent',100)>=85 or f.get('disk_free_bytes',0)<2*1024**3: issues.append('disk_space')
 if not f.get('containers_ok'): issues.append('container_or_service')
 if not f.get('http_ok'): issues.append('http')
 if f.get('php_errors',0): issues.append('php_application_errors')
 if f.get('smtp_errors',0): issues.append('smtp_failures')
 if f.get('backup_status')=='failed' or f.get('backup_age_hours',999)>30: issues.append('backup_failure_or_stale')
 if f.get('certificate_days',0)<14: issues.append('certificate_expiry')
 return sorted(issues)

def collect():
 now=time.time(); disk=shutil.disk_usage('/')
 f={'disk_percent':round(100*disk.used/disk.total,1),'disk_free_bytes':disk.free}
 names=['appleklinika-staging-'+n+'-1' for n in ('wordpress','mariadb','caddy')]
 rc,out,_=run(['docker','inspect',*names]); containers=json.loads(out) if not rc else []
 f['containers_ok']=len(containers)==3 and all(c['State']['Running'] and c['State'].get('Health',{}).get('Status','healthy')=='healthy' for c in containers)
 rc,out,_=run(['curl','--max-time','15','--silent','--output','/dev/null','--write-out','%{http_code}','--resolve','teszt.appleklinika.com:443:127.0.0.1','https://teszt.appleklinika.com/'])
 f['http_ok']=rc==0 and out=='200'
 rc,out,err=run(['docker','logs','--since','6m','--tail','3000','appleklinika-staging-wordpress-1'])
 logs=out+err
 f['php_errors']=len(re.findall(r'PHP (?:Fatal error|Parse error|Warning)|Uncaught (?:Error|Exception)',logs))
 f['smtp_errors']=logs.count('[appleklinika-test-mail] SMTP send failed')
 f['error_scan_ok']=rc==0
 if rc: f['php_errors']=1
 state=pathlib.Path('/var/lib/appleklinika-ops/backup.json')
 backup=json.loads(state.read_text()) if state.exists() else {}
 f['backup_status']=backup.get('status','missing')
 f['backup_age_hours']=round((now-backup.get('last_success_epoch',0))/3600,2)
 # A bounded, active backup can briefly pause HTTP; this is intentional maintenance.
 f['backup_maintenance']=backup.get('status')=='running' and now-backup.get('started_epoch',0)<1800
 if f['backup_maintenance']:
  f['containers_ok']=all(c['State']['Running'] for c in containers if not c['Name'].endswith('wordpress-1')) and len(containers)==3
  f['http_ok']=True
 try:
  with socket.create_connection(('127.0.0.1',443),timeout=10) as conn:
   with ssl.create_default_context().wrap_socket(conn,server_hostname='teszt.appleklinika.com') as tls:
    f['certificate_days']=round((ssl.cert_time_to_seconds(tls.getpeercert()['notAfter'])-now)/86400,1)
 except (OSError,ssl.SSLError,KeyError): f['certificate_days']=0
 return f

def main():
 p=argparse.ArgumentParser();p.add_argument('--self-test',action='store_true');a=p.parse_args()
 if a.self_test:
  good={'disk_percent':20,'disk_free_bytes':10*1024**3,'containers_ok':True,'http_ok':True,'backup_status':'success','backup_age_hours':1,'certificate_days':40}
  assert classify(good)==[]
  cases=[('disk_percent',90,'disk_space'),('containers_ok',False,'container_or_service'),('http_ok',False,'http'),('php_errors',1,'php_application_errors'),('smtp_errors',1,'smtp_failures'),('backup_status','failed','backup_failure_or_stale'),('backup_age_hours',31,'backup_failure_or_stale'),('certificate_days',7,'certificate_expiry')]
  for k,v,expected in cases: assert expected in classify(dict(good,**{k:v}))
  print('9 monitor classification checks passed; no service was stopped or message sent.');return
 os.umask(0o077);root=pathlib.Path('/var/lib/appleklinika-ops');root.mkdir(exist_ok=True)
 f=collect();issues=classify(f);report={'checked_epoch':int(time.time()),'status':'alert' if issues else 'ok','issues':issues,'checks':f}
 path=root/'monitor.json';old=json.loads(path.read_text()) if path.exists() else {}
 tmp=path.with_suffix('.tmp');tmp.write_text(json.dumps(report)+'\n');tmp.replace(path)
 if old.get('issues')!=issues: print(json.dumps({'status':report['status'],'issues':issues}),flush=True)
 alert=pathlib.Path(__file__).with_name('monitor-alert.py')
 delivered=subprocess.run(['python3',str(alert)],timeout=55).returncode if alert.exists() else 1
 if delivered: print('Human alert transport needs attention; no unauthenticated fallback.',flush=True)
 if issues or delivered: raise SystemExit(1)
if __name__=='__main__': main()
