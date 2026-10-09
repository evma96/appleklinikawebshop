#!/usr/bin/env python3
"""LOCAL fixture: real encryption/archive, stubbed Docker/OS inventory; no providers."""
import json, os, pathlib, shutil, subprocess, sys, tempfile, time
ROOT=pathlib.Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='ak-backup-fixture-')as directory:
    d=pathlib.Path(directory).resolve();bin=d/'bin';bin.mkdir();source=d/'fixture-source';source.mkdir();(source/'content').write_text('private fixture media')
    key=d/'key.pem';cert=d/'cert.pem'
    subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(key),'-out',str(cert),'-days','365','-subj','/CN=LOCAL backup fixture'],check=True,capture_output=True)
    script='''#!{python}
import os,sys,pathlib,json,tarfile,subprocess
name=pathlib.Path(sys.argv[0]).name;args=sys.argv[1:]
if name=='docker':
 with open(os.environ['FIXTURE_CALLS'],'a')as f:f.write(json.dumps(args)+'\\n')
 if args[0]=='inspect': print('true' if '-f'in args else 'fixture inventory')
 elif args[0]=='exec':
  if os.environ.get('FIXTURE_FAIL')=='yes':sys.exit(1)
  print('CREATE DATABASE fixture; -- private fixture database')
 elif args[0]=='logs':print('fixture operational log')
elif name=='git':print('a'*40)
elif name=='dpkg-query':print('fixture-package 1')
elif name=='tar':
 with tarfile.open(fileobj=sys.stdout.buffer,mode='w|gz')as t:
  for member in pathlib.Path(args[args.index('-T')+1]).read_text().splitlines():t.add('/'+member,arcname=member)
elif name=='sha256sum':sys.exit(subprocess.run(['shasum','-a','256',*args]).returncode)
'''.format(python=sys.executable)
    for name in ['docker','git','dpkg-query','tar','flock']+([]if shutil.which('sha256sum')else['sha256sum']):
        p=bin/name;p.write_text(script);p.chmod(0o700)
    archive_paths=d/'paths';archive_paths.write_text(str(source).lstrip('/')+'\n');archive_paths.chmod(0o600)
    backup=d/'backup';state=d/'state';env=dict(os.environ,PATH=str(bin)+':'+os.environ['PATH'],FIXTURE_CALLS=str(d/'calls'))
    values={'AK_BACKUP_ENABLED':'yes','AK_ENVIRONMENT':'TEST','AK_EXPECTED_HOSTNAME':subprocess.check_output(['hostname'],text=True).strip(),'AK_SITE_HOST':'fixture.invalid','AK_REPO_DIR':str(source),'AK_BACKUP_ROOT':str(backup),'AK_STATE_DIR':str(state),'AK_LOCK_FILE':str(d/'lock'),'AK_RECIPIENT_CERT':str(cert),'AK_ARCHIVE_PATHS_FILE':str(archive_paths),'AK_RETENTION_SETS':'2','AK_WORDPRESS_CONTAINER':'fixture-wp','AK_DATABASE_CONTAINER':'fixture-db','AK_PROXY_CONTAINER':'fixture-proxy','AK_DB_NAME':'fixture','AK_DB_SECRET_FILE':'/run/secrets/fixture'}
    import shlex
    config=d/'config';config.write_text('\n'.join('export '+k+'='+shlex.quote(v)for k,v in values.items()));config.chmod(0o600)
    result=subprocess.run(['bash',str(ROOT/'scripts/operations/reusable-backup.sh'),str(config)],env=env,capture_output=True,text=True)
    if result.returncode:print(result.stderr);raise SystemExit(result.returncode)
    report=json.loads((state/'backup.json').read_text());assert report['status']=='success'
    folder=backup/report['run'];assert len(list(folder.glob('*.cms')))==4
    assert b'private fixture' not in (folder/'database.cms').read_bytes()
    decrypted=subprocess.check_output(['openssl','cms','-decrypt','-binary','-inform','DER','-in',str(folder/'database.cms'),'-inkey',str(key)])
    assert b'private fixture database'in decrypted
    archive=subprocess.check_output(['openssl','cms','-decrypt','-binary','-inform','DER','-in',str(folder/'files.cms'),'-inkey',str(key)])
    import io,tarfile
    with tarfile.open(fileobj=io.BytesIO(archive),mode='r:gz')as tar:
        assert tar.extractfile(str(source).lstrip('/')+'/content').read()==b'private fixture media'
    calls=[json.loads(x)for x in (d/'calls').read_text().splitlines()];assert [x[0]for x in calls].count('stop')==1 and [x[0]for x in calls].count('start')==1
    time.sleep(1.1)
    result=subprocess.run(['bash',str(ROOT/'scripts/operations/reusable-backup.sh'),str(config)],env=dict(env,FIXTURE_FAIL='yes'),capture_output=True,text=True)
    assert result.returncode!=0 and json.loads((state/'backup.json').read_text())['status']=='failed'
    assert json.loads((d/'calls').read_text().splitlines()[-1])[0]=='start'
    assert len([p for p in backup.iterdir()if not p.name.startswith('.')])==1
    print('9 reusable backup checks passed: real encrypted streams/restore, consistent pause, failure restart, no failed-set publication; Docker mocked.')
