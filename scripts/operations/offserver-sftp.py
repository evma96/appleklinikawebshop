#!/usr/bin/env python3
"""Pinned-key SFTP/22 transport for the existing encrypted TEST backup format."""
import argparse
import errno
import fcntl
import hashlib
import importlib.util
import json
import os
import pathlib
import re
import stat
import tempfile
import time
import uuid

spec = importlib.util.spec_from_file_location('sync', pathlib.Path(__file__).with_name('offserver-sync.py'))
sync = importlib.util.module_from_spec(spec)
spec.loader.exec_module(sync)
MEMBERS = sync.NAMES | {'SHA256SUMS'}


def connect(config):
    import paramiko
    client = paramiko.SSHClient()
    client.load_host_keys(config['known_hosts'])
    client.set_missing_host_key_policy(paramiko.RejectPolicy())
    try:
        client.connect(config['host'], port=22, username=config['username'],
                       key_filename=config['identity_file'], allow_agent=False,
                       look_for_keys=False, timeout=20, auth_timeout=30, banner_timeout=20)
        client.get_transport().set_keepalive(30)
        sftp = client.open_sftp()
        sftp.get_channel().settimeout(180)
        return client, sftp
    except Exception:
        client.close()
        raise


def exists(sftp, path):
    try:
        return sftp.lstat(path)
    except OSError as error:
        if error.errno != errno.ENOENT:
            raise
        return None


def mkdirs(sftp, directory):
    # connection() validates every component; reject symlinks even inside the jail.
    current = ''
    for part in directory.split('/'):
        current = current + '/' + part if current else part
        attributes = exists(sftp, current)
        if attributes is None:
            sftp.mkdir(current, mode=0o700)
        elif not stat.S_ISDIR(attributes.st_mode):
            raise ValueError('Unsafe destination directory')


def manifest(sftp, path):
    attributes = sftp.lstat(path + '/SHA256SUMS')
    if not stat.S_ISREG(attributes.st_mode) or not 0 < attributes.st_size < 4096:
        raise ValueError('Unsafe remote manifest')
    with sftp.open(path + '/SHA256SUMS', 'rb') as stream:
        return sync.parse_manifest(stream.read(4096).decode('ascii'))


def remote_hash(sftp, path):
    attributes = sftp.lstat(path)
    if not stat.S_ISREG(attributes.st_mode) or attributes.st_size <= 0:
        raise ValueError('Unsafe remote member')
    digest = hashlib.sha256()
    size = 0
    with sftp.open(path, 'rb') as stream:
        # SFTP/22 has no remote shell: hash the actual bytes read back over SFTP.
        stream.prefetch(attributes.st_size)
        while True:
            block = stream.read(1024 * 1024)
            if not block:
                break
            size += len(block)
            digest.update(block)
    if size != attributes.st_size:
        raise ValueError('Truncated remote read')
    return digest.hexdigest()


def verify_remote(sftp, path, expected):
    if not stat.S_ISDIR(sftp.lstat(path).st_mode):
        raise ValueError('Unsafe remote set')
    if set(sftp.listdir(path)) != MEMBERS:
        raise ValueError('Unexpected remote members')
    if manifest(sftp, path) != expected:
        raise ValueError('Remote manifest mismatch')
    for name, checksum in expected.items():
        if remote_hash(sftp, path + '/' + name) != checksum:
            raise ValueError('Remote ciphertext mismatch')


def transfer(sftp, config, folder):
    folder = pathlib.Path(folder)
    expected = sync.inventory(folder)
    mkdirs(sftp, config['directory'])
    final = config['directory'] + '/' + folder.name
    if exists(sftp, final) is not None:
        verify_remote(sftp, final, expected)
        return {'uploaded': False, 'bytes': 0, 'manifest_sha256': sync.digest(folder / 'SHA256SUMS')}
    partial = config['directory'] + '/.partial-' + folder.name + '-' + uuid.uuid4().hex
    sftp.mkdir(partial, mode=0o700)
    size = 0
    for name in sorted(sync.NAMES):
        sftp.put(str(folder / name), partial + '/' + name, confirm=True)
        sftp.chmod(partial + '/' + name, 0o600)
        size += (folder / name).stat().st_size
        if remote_hash(sftp, partial + '/' + name) != expected[name]:
            raise ValueError('Upload read-back checksum mismatch')
    # Completion marker is published only after every encrypted stream is verified.
    sftp.put(str(folder / 'SHA256SUMS'), partial + '/SHA256SUMS', confirm=True)
    sftp.chmod(partial + '/SHA256SUMS', 0o600)
    if manifest(sftp, partial) != expected:
        raise ValueError('Published manifest mismatch')
    if exists(sftp, final) is not None:
        raise ValueError('Concurrent archive; refusing overwrite')
    sftp.rename(partial, final)  # Standard SFTP rename refuses an existing target.
    return {'uploaded': True, 'bytes': size + (folder / 'SHA256SUMS').stat().st_size,
            'manifest_sha256': sync.digest(folder / 'SHA256SUMS')}


def prune(sftp, config, managed):
    keep = config['retention_sets']
    if not isinstance(keep, int) or keep < 30:
        raise ValueError('Keep at least 30 complete remote sets')
    names = sorted(n for n in sftp.listdir(config['directory']) if re.fullmatch(sync.SET, n))
    complete = []
    for name in names:
        sync.set_epoch(name)
        path = config['directory'] + '/' + name
        if not stat.S_ISDIR(sftp.lstat(path).st_mode):
            raise ValueError('Unsafe archive directory')
        if set(sftp.listdir(path)) == MEMBERS:
            manifest(sftp, path)
            complete.append(name)
    candidates = [n for n in complete[:-keep] if n in managed]
    if candidates:
        # Do not replace known-good old backups with corrupt retained archives.
        for name in complete[-keep:]:
            path = config['directory'] + '/' + name
            verify_remote(sftp, path, manifest(sftp, path))
    removed = []
    for name in candidates:
        # Never prune pre-existing archives, unknown files or partial uploads.
        if name not in managed:
            continue
        path = config['directory'] + '/' + name
        if remote_hash(sftp, path + '/SHA256SUMS') != managed[name]:
            raise ValueError('Retention ownership mismatch')
        verify_remote(sftp, path, manifest(sftp, path))
        for filename in sorted(MEMBERS - {'SHA256SUMS'}):
            sftp.remove(path + '/' + filename)
        sftp.remove(path + '/SHA256SUMS')
        sftp.rmdir(path)
        removed.append(name)
    return removed


def download(sftp, config, name, target):
    sync.set_epoch(name)
    target = pathlib.Path(target)
    if target.exists() or target.is_symlink() or target.name != name:
        raise ValueError('New timestamp destination required')
    parent = target.parent
    if not parent.is_dir() or parent.is_symlink() or parent.stat().st_uid != os.getuid() or parent.stat().st_mode & 0o077:
        raise ValueError('Private recovery parent required')
    source = config['directory'] + '/' + name
    if not stat.S_ISDIR(sftp.lstat(source).st_mode) or set(sftp.listdir(source)) != MEMBERS:
        raise ValueError('Unsafe remote set')
    expected = manifest(sftp, source)
    partial = pathlib.Path(tempfile.mkdtemp(prefix='.download-', dir=parent))
    folder = partial / name
    folder.mkdir(mode=0o700)
    for filename in sorted(MEMBERS):
        if not stat.S_ISREG(sftp.lstat(source + '/' + filename).st_mode):
            raise ValueError('Unsafe remote member')
        sftp.get(source + '/' + filename, str(folder / filename))
        (folder / filename).chmod(0o600)
    if sync.inventory(folder) != expected:
        raise ValueError('Downloaded checksums mismatch')
    folder.rename(target)
    partial.rmdir()
    return {'status': 'download_verified', 'set': name,
            'bytes': sum(p.stat().st_size for p in target.iterdir())}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('config')
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--action', choices=['upload', 'download'], default='upload')
    parser.add_argument('--set')
    parser.add_argument('--destination')
    args = parser.parse_args()
    os.umask(0o077)
    config = json.loads(pathlib.Path(args.config).read_text())
    if not args.apply or config.get('enabled') is not True:
        print(json.dumps({'status': 'disabled_or_dry_run', 'connection': False}))
        return
    sync.private_file(args.config)
    sync.guard(config, args.action)
    if config.get('protocol') != 'sftp' or config.get('port') != 22:
        raise ValueError('Only SFTP port 22 supported')
    if args.action == 'download':
        if not args.set or not args.destination:
            raise ValueError('Set and destination required')
        client, sftp = connect(config)
        try:
            print(json.dumps(download(sftp, config, args.set, args.destination)))
        finally:
            client.close()
        return
    state_path = pathlib.Path(config['state_file'])
    state_path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    if state_path.parent.is_symlink() or state_path.parent.stat().st_mode & 0o077:
        raise ValueError('Private state directory required')
    lock_path = state_path.with_suffix('.lock')
    if lock_path.is_symlink():
        raise ValueError('Unsafe lock')
    with lock_path.open('a') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            print('Another sync is active; no duplicate transfer.')
            return
        previous = json.loads(state_path.read_text()) if state_path.exists() else {}
        state = dict(previous, status='running', checked_epoch=int(time.time()), started_epoch=int(time.time()))
        state.setdefault('managed_sets', {})
        sync.write_state(state_path, state)
        client = None
        try:
            folders = sorted(p for p in pathlib.Path(config['backup_root']).iterdir()
                             if re.fullmatch(sync.SET, p.name) and p.is_dir())
            if not folders:
                raise ValueError('No backup available')
            folder = folders[-1]  # One newest complete set per run; no historical bulk upload.
            if not 0 <= time.time() - sync.set_epoch(folder.name) <= 30 * 3600:
                raise ValueError('Newest backup is stale')
            sync.inventory(folder)
            client, sftp = connect(config)
            result = transfer(sftp, config, folder)
            if result['uploaded']:
                state['managed_sets'][folder.name] = result['manifest_sha256']
                sync.write_state(state_path, state)
            removed = prune(sftp, config, state['managed_sets'])
            for name in removed:
                state['managed_sets'].pop(name, None)
            state.update(status='success', checked_epoch=int(time.time()),
                         latest_backup_epoch=sync.set_epoch(folder.name), set=folder.name,
                         transfer=result, pruned_sets=removed, checksum_method='sftp_ciphertext_readback_sha256')
        except Exception as error:
            state.update(status='failed', checked_epoch=int(time.time()), error_type=type(error).__name__)
        finally:
            if client:
                client.close()
        sync.write_state(state_path, state)
        print(json.dumps({'status': state['status'], 'set': state.get('set'), 'transfer': state.get('transfer')}))
        if state['status'] != 'success':
            raise SystemExit(1)


if __name__ == '__main__':
    try:
        main()
    except Exception:
        print('SFTP backup operation failed; inspect private status. No secrets logged.')
        raise SystemExit(1)
