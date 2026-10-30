#!/usr/bin/env python3
"""Encrypted TEST backup transport. Dry-run by default; no remote delete operation."""
import argparse
import datetime
import fcntl
import hashlib
import json
import os
import pathlib
import re
import shlex
import socket
import subprocess
import tempfile
import time

NAMES = {'database.cms', 'files.cms', 'inventory.cms', 'logs.cms'}
SET = r'\d{8}T\d{6}Z'


def set_epoch(name):
    if not re.fullmatch(SET, name):
        raise ValueError('Invalid set name')
    return int(datetime.datetime.strptime(name, '%Y%m%dT%H%M%SZ').replace(
        tzinfo=datetime.timezone.utc).timestamp())


def parse_manifest(text):
    expected = {}
    for line in text.splitlines():
        match = re.fullmatch(r'([0-9a-f]{64})  ([a-z]+\.cms)', line)
        if not match or match[2] in expected:
            raise ValueError('Invalid checksum manifest')
        expected[match[2]] = match[1]
    if set(expected) != NAMES:
        raise ValueError('Incomplete encrypted set')
    return expected


def digest(path):
    h = hashlib.sha256()
    with pathlib.Path(path).open('rb') as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b''):
            h.update(block)
    return h.hexdigest()


def inventory(folder):
    folder = pathlib.Path(folder)
    set_epoch(folder.name)
    if folder.is_symlink() or {p.name for p in folder.iterdir()} != NAMES | {'SHA256SUMS'}:
        raise ValueError('Unexpected/plaintext file')
    for path in folder.iterdir():
        if path.is_symlink() or not path.is_file() or not path.stat().st_size:
            raise ValueError('Unsafe member')
    expected = parse_manifest((folder / 'SHA256SUMS').read_text())
    if any(digest(folder / name) != value for name, value in expected.items()):
        raise ValueError('Checksum mismatch')
    return expected


def execute(args):
    return subprocess.run(args, check=True, capture_output=True, text=True, timeout=7200).stdout


def connection(config):
    user = config['username']
    if not re.fullmatch(r'u\d+-sub\d+', user) or config['host'] != user + '.your-storagebox.de':
        raise ValueError('Dedicated Storage Box subaccount required')
    directory = config['directory']
    if not re.fullmatch(r'[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*', directory):
        raise ValueError('Unsafe relative remote path')
    ssh = ['ssh', '-p', '23', '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
           '-o', 'IdentitiesOnly=yes', '-o', 'ConnectTimeout=20',
           '-o', 'UserKnownHostsFile=' + config['known_hosts'], '-i', config['identity_file']]
    return ssh, user + '@' + config['host']


def remote_checksums(config, name, expected, call=execute):
    set_epoch(name)
    ssh, remote = connection(config)
    prefix = config['directory'] + '/' + name + '/'
    result = call(ssh + [remote, 'sha256sum ' + ' '.join(prefix + n for n in sorted(NAMES))])
    received = {}
    for line in result.splitlines():
        match = re.fullmatch(r'([0-9a-f]{64})\s+(.+)', line)
        if not match or not match[2].startswith(prefix):
            raise ValueError('Invalid remote checksum output')
        filename = match[2][len(prefix):]
        if filename in received:
            raise ValueError('Duplicate remote member')
        received[filename] = match[1]
    if received != expected:
        raise ValueError('Remote verification mismatch')


def transfer(config, folder, call=execute):
    folder = pathlib.Path(folder)
    expected = inventory(folder)
    ssh, remote = connection(config)
    destination = config['directory'] + '/' + folder.name
    call(ssh + [remote, 'mkdir -p ' + destination])
    # Never skip a remote verification because a local ledger says it succeeded earlier.
    call(['rsync', '--recursive', '--checksum', '--chmod=F600,D700', '-e', shlex.join(ssh),
          '--', *[str(folder / n) for n in sorted(NAMES)], remote + ':' + destination + '/'])
    remote_checksums(config, folder.name, expected, call)
    call(['rsync', '--checksum', '--chmod=F600', '-e', shlex.join(ssh), '--',
          str(folder / 'SHA256SUMS'), remote + ':' + destination + '/SHA256SUMS'])
    if parse_manifest(call(ssh + [remote, 'cat ' + destination + '/SHA256SUMS'])) != expected:
        raise ValueError('Remote manifest mismatch')
    return digest(folder / 'SHA256SUMS')


def remote_inventory(config, call=execute):
    ssh, remote = connection(config)
    result = call(ssh + [remote, 'ls -1 ' + config['directory']])
    names = sorted(set(line for line in result.splitlines() if re.fullmatch(SET, line)))
    complete, incomplete = [], []
    for name in names:
        set_epoch(name)
        try:
            expected = parse_manifest(call(ssh + [remote, 'cat ' + config['directory'] + '/' + name + '/SHA256SUMS']))
            remote_checksums(config, name, expected, call)
            complete.append(name)
        except (ValueError, subprocess.SubprocessError):
            incomplete.append(name)
    return complete, incomplete


def retention_plan(names, keep, incomplete=()):
    if not isinstance(keep, int) or keep < 14:
        raise ValueError('Retention must keep at least 14 complete sets')
    names = sorted(set(names))
    for name in names:
        set_epoch(name)
    return {'keep': names[-keep:], 'review_expired': names[:-keep],
            'retain_incomplete_for_investigation': list(incomplete),
            'automatic_deletion': False}


def download(config, name, target, call=execute):
    set_epoch(name)
    target = pathlib.Path(target)
    if target.exists() or target.is_symlink():
        raise ValueError('Download destination already exists')
    if (not target.parent.is_dir() or target.parent.is_symlink() or target.name != name
            or target.parent.stat().st_uid != os.getuid() or target.parent.stat().st_mode & 0o077):
        raise ValueError('Use a new timestamp directory inside a private recovery workspace')
    ssh, remote = connection(config)
    prefix = config['directory'] + '/' + name + '/'
    manifest = call(ssh + [remote, 'cat ' + prefix + 'SHA256SUMS'])
    expected = parse_manifest(manifest)
    remote_checksums(config, name, expected, call)
    # Failed downloads remain private and unpublished; never overwrite an existing set.
    partial = pathlib.Path(tempfile.mkdtemp(prefix='.download-', dir=target.parent))
    folder = partial / name
    folder.mkdir(mode=0o700)
    # One explicit remote source per invocation also supports older recovery clients.
    for filename in sorted(NAMES | {'SHA256SUMS'}):
        call(['rsync', '--checksum', '--chmod=F600', '-e', shlex.join(ssh), '--',
              remote + ':' + prefix + filename, str(folder) + '/'])
    if inventory(folder) != expected:
        raise ValueError('Downloaded manifest mismatch')
    folder.rename(target)
    partial.rmdir()
    return {'status': 'download_verified', 'set': name}


def health(state, now=None):
    now = int(time.time()) if now is None else now
    try:
        age = now - state['latest_backup_epoch']
        checked = now - state['checked_epoch']
        running = state['status'] == 'running' and 0 <= now - state.get('started_epoch', 0) <= 2 * 3600
        if (state['status'] != 'success' and not running) or not 0 <= age <= 30 * 3600 or not 0 <= checked <= 30 * 3600:
            return 'remote_backup_failure_or_stale'
    except (KeyError, TypeError):
        return 'remote_backup_failure_or_stale'
    return None


def private_file(path):
    path = pathlib.Path(path)
    if path.is_symlink() or not path.is_file() or path.stat().st_uid != os.getuid() or path.stat().st_mode & 0o077:
        raise ValueError('Unsafe private configuration permissions')


def guard(config, action):
    if config.get('environment') not in ({'TEST'} if action == 'upload' else {'TEST', 'RECOVERY'}):
        raise ValueError('Production operations are not supported')
    if config.get('expected_hostname') != socket.gethostname():
        raise ValueError('Host guard mismatch')
    connection(config)
    for key in ('identity_file', 'known_hosts'):
        private_file(config[key])


def write_state(path, state):
    path = pathlib.Path(path)
    path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    if path.parent.stat().st_mode & 0o077 or path.is_symlink():
        raise ValueError('Unsafe state directory')
    with tempfile.NamedTemporaryFile(mode='w', dir=path.parent, delete=False) as stream:
        json.dump(state, stream)
        stream.write('\n')
    os.replace(stream.name, path)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('config')
    parser.add_argument('--action', choices=['upload', 'download', 'retention-plan'], default='upload')
    parser.add_argument('--apply', action='store_true', help='Perform enabled TEST/RECOVERY operations; never delete remotely')
    parser.add_argument('--set')
    parser.add_argument('--destination')
    args = parser.parse_args()
    os.umask(0o077)
    config = json.loads(pathlib.Path(args.config).read_text())
    if not args.apply:
        print(json.dumps({'mode': 'dry-run', 'action': args.action, 'connection': False,
                          'enabled': config.get('enabled') is True, 'remote_delete': False}))
        return
    if config.get('enabled') is not True:
        print('Off-server sync disabled; no connection made.')
        return
    private_file(args.config)
    guard(config, args.action)
    if args.action == 'download':
        if not args.set or not args.destination:
            raise ValueError('Set and private destination required')
        print(json.dumps(download(config, args.set, args.destination)))
        return
    if args.action == 'retention-plan':
        complete, incomplete = remote_inventory(config)
        print(json.dumps(retention_plan(complete, config['retention_sets'], incomplete)))
        return
    state_path = pathlib.Path(config['state_file'])
    state_path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    if state_path.parent.stat().st_mode & 0o077:
        raise ValueError('Unsafe state directory')
    lock_path = state_path.with_suffix('.lock')
    if lock_path.is_symlink():
        raise ValueError('Unsafe lock')
    with lock_path.open('a') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            print('Another sync is running; no second transfer started.')
            return
        state = {'status': 'running', 'checked_epoch': int(time.time()), 'verified': {}}
        write_state(state_path, state)
        try:
            for folder in sorted(pathlib.Path(config['backup_root']).iterdir()):
                if folder.is_dir() and re.fullmatch(SET, folder.name):
                    state['verified'][folder.name] = transfer(config, folder)
            if not state['verified']:
                raise ValueError('No complete backup sets')
            state.update(status='success', checked_epoch=int(time.time()),
                         latest_backup_epoch=max(set_epoch(n) for n in state['verified']))
            if health(state):
                raise ValueError('Newest source backup is stale')
        except Exception:
            state.update(status='failed', checked_epoch=int(time.time()))
        write_state(state_path, state)
        print(json.dumps({'operation': 'encrypted_offserver_sync', 'status': state['status'],
                          'sets_verified': len(state['verified'])}))
        if state['status'] != 'success':
            raise SystemExit(1)


if __name__ == '__main__':
    try:
        main()
    except Exception:
        # Never emit configuration values, remote stderr or subprocess arguments.
        print('Off-server operation failed; inspect private configuration and monitoring state.')
        raise SystemExit(1)
