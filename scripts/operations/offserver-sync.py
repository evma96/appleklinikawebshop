#!/usr/bin/env python3
"""Disabled-by-default encrypted set transfer to a dedicated Storage Box subaccount."""
import argparse, hashlib, json, os, pathlib, re, shlex, subprocess, time
NAMES = {'database.cms','files.cms','inventory.cms','logs.cms'}

def inventory(folder):
    folder=pathlib.Path(folder)
    if folder.is_symlink() or not re.fullmatch(r'\d{8}T\d{6}Z',folder.name): raise ValueError('Invalid set')
    if {p.name for p in folder.iterdir()} != NAMES|{'SHA256SUMS'}: raise ValueError('Unexpected/plaintext file')
    expected={}
    for line in (folder/'SHA256SUMS').read_text().splitlines():
        match=re.fullmatch(r'([0-9a-f]{64})  ([a-z]+\.cms)',line)
        if not match or match[2] in expected: raise ValueError('Invalid checksum manifest')
        expected[match[2]]=match[1]
    if set(expected)!=NAMES: raise ValueError('Incomplete encrypted set')
    for name,digest in expected.items():
        p=folder/name
        if p.is_symlink() or not p.stat().st_size: raise ValueError('Unsafe encrypted member')
        h=hashlib.sha256()
        with p.open('rb')as f:
            for block in iter(lambda:f.read(1024*1024),b''):h.update(block)
        if h.hexdigest()!=digest: raise ValueError('Checksum mismatch')
    return expected

def execute(args):
    return subprocess.run(args,check=True,capture_output=True,text=True,timeout=7200).stdout

def transfer(config,folder,call=execute):
    expected=inventory(folder)
    if not re.fullmatch(r'u\d+(?:-sub\d+)?',config['username']) or not re.fullmatch(r'u\d+\.your-storagebox\.de',config['host']): raise ValueError('Unexpected Storage Box account')
    destination=config['directory']+'/'+folder.name
    if not re.fullmatch(r'[A-Za-z0-9_-]+/\d{8}T\d{6}Z',destination): raise ValueError('Unsafe remote path')
    ssh=['ssh','-p','23','-o','BatchMode=yes','-o','StrictHostKeyChecking=yes','-o','IdentitiesOnly=yes','-o','ConnectTimeout=20','-o','UserKnownHostsFile='+config['known_hosts'],'-i',config['identity_file']]
    remote=config['username']+'@'+config['host']
    call(ssh+[remote,'mkdir -p '+destination])
    call(['rsync','--recursive','--checksum','--chmod=F600,D700','-e',shlex.join(ssh),'--',*[str(folder/n)for n in sorted(NAMES)],remote+':'+destination+'/'])
    result=call(ssh+[remote,'sha256sum '+' '.join(destination+'/'+n for n in sorted(NAMES))])
    received={}
    for line in result.splitlines():
        digest,name=line.split(None,1);received[pathlib.PurePosixPath(name.strip()).name]=digest
    if received!=expected: raise ValueError('Remote verification mismatch')
    # Publish completeness only after every encrypted member was verified remotely.
    call(['rsync','--checksum','--chmod=F600','-e',shlex.join(ssh),'--',str(folder/'SHA256SUMS'),remote+':'+destination+'/SHA256SUMS'])
    return hashlib.sha256((folder/'SHA256SUMS').read_bytes()).hexdigest()

def self_test():
    import tempfile
    with tempfile.TemporaryDirectory()as d:
        folder=pathlib.Path(d)/'20260101T000000Z';folder.mkdir();lines=[]
        for n in sorted(NAMES):
            data=('fixture-'+n).encode();(folder/n).write_bytes(data);lines.append(hashlib.sha256(data).hexdigest()+'  '+n)
        (folder/'SHA256SUMS').write_text('\n'.join(lines)+'\n');expected=inventory(folder);assert len(expected)==4
        calls=[]
        def fake(args):
            calls.append(args)
            return '\n'.join(h+'  private/'+folder.name+'/'+n for n,h in expected.items()) if args[-1].startswith('sha256sum ') else ''
        cfg={'username':'u123-sub1','host':'u123.your-storagebox.de','directory':'private','identity_file':'/private/key','known_hosts':'/private/known_hosts'}
        assert len(transfer(cfg,folder,fake))==64
        assert len(calls)==4 and '--delete' not in str(calls) and 'StrictHostKeyChecking=yes' in str(calls)
        (folder/'plain.sql').write_text('fixture')
        try:inventory(folder);raise AssertionError('Plaintext allowed')
        except ValueError:pass
        (folder/'plain.sql').unlink();(folder/'database.cms').write_bytes(b'changed')
        try:inventory(folder);raise AssertionError('Changed archive allowed')
        except ValueError:pass
    print('5 encrypted-only/remote-checksum/strict-SSH transfer assertions passed; network mocked.')

def main():
    parser=argparse.ArgumentParser();parser.add_argument('config',nargs='?');parser.add_argument('--self-test',action='store_true');args=parser.parse_args()
    if args.self_test:return self_test()
    path=pathlib.Path(args.config);config=json.loads(path.read_text())
    if config.get('enabled') is not True:print('Off-server sync disabled; no connection made.');return
    for private in [path,pathlib.Path(config['identity_file']),pathlib.Path(config['known_hosts'])]:
        if private.stat().st_uid!=os.getuid() or private.stat().st_mode & 0o077:raise SystemExit('Unsafe private configuration permissions')
    os.umask(0o077);state_path=pathlib.Path(config['state_file']);state_path.parent.mkdir(parents=True,exist_ok=True)
    state=json.loads(state_path.read_text()) if state_path.exists() else {'verified':{}}
    try:
        for folder in sorted(pathlib.Path(config['backup_root']).iterdir()):
            if not folder.is_dir() or not re.fullmatch(r'\d{8}T\d{6}Z',folder.name):continue
            digest=hashlib.sha256((folder/'SHA256SUMS').read_bytes()).hexdigest()
            if state['verified'].get(folder.name)==digest:continue
            state['verified'][folder.name]=transfer(config,folder)
        if not state['verified']:raise ValueError('No complete backup sets')
        state.update(status='success',last_success_epoch=int(time.time()))
    except Exception:
        state.update(status='failed',checked_epoch=int(time.time()))
    temporary=state_path.with_suffix('.tmp');temporary.write_text(json.dumps(state)+'\n');temporary.replace(state_path)
    print('Encrypted off-server transfer: '+state['status'])
    if state['status']!='success':raise SystemExit(1)
if __name__=='__main__':main()
