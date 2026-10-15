#!/usr/bin/env python3
"""Read-only recovery verification. Decrypt streams; do not extract or print private data."""
import argparse
import importlib.util
import json
import os
import pathlib
import subprocess
import tarfile

spec = importlib.util.spec_from_file_location('offserver', pathlib.Path(__file__).with_name('offserver-sync.py'))
sync = importlib.util.module_from_spec(spec)
spec.loader.exec_module(sync)


def verify(folder, key, cert):
    folder = pathlib.Path(folder)
    sync.inventory(folder)
    sync.private_file(key)
    summary = {}
    required_members = {'opt/appleklinika/production/compose.yaml',
                        'opt/appleklinika/production/secrets/mysql_password',
                        'var/lib/docker/volumes/appleklinika-staging_wordpress_data/_data/wp-config.php'}
    for name in sorted(sync.NAMES):
        with subprocess.Popen(['openssl', 'cms', '-decrypt', '-binary', '-inform', 'DER',
                               '-in', str(folder / name), '-recip', str(cert), '-inkey', str(key)],
                              stdout=subprocess.PIPE, stderr=subprocess.DEVNULL) as process:
            try:
                if name == 'files.cms':
                    count, links, required = 0, 0, set()
                    with tarfile.open(fileobj=process.stdout, mode='r|gz') as archive:
                        for member in archive:
                            path = pathlib.PurePosixPath(member.name)
                            if path.is_absolute() or '..' in path.parts:
                                raise ValueError('Unsafe archive path')
                            count += 1
                            links += int(member.issym() or member.islnk())
                            if str(path) in required_members:
                                required.add(str(path))
                    # Drain CMS padding/trailer before checking openssl exit status.
                    while process.stdout.read(1024 * 1024):
                        pass
                    if required != required_members:
                        raise ValueError('Incomplete restore archive')
                    summary[name] = {'entries': count, 'links_to_review_before_extraction': links}
                else:
                    size, tables, tail = 0, 0, b''
                    for line in process.stdout:
                        size += len(line)
                        if line.startswith(b'CREATE TABLE '):
                            tables += 1
                        tail = (tail + line)[-4096:]
                    if not size or (name == 'database.cms' and (not tables or b'Dump completed' not in tail)):
                        raise ValueError('Incomplete decrypted stream')
                    summary[name] = {'decrypted_bytes': size}
                    if name == 'database.cms':
                        summary[name]['tables'] = tables
                if process.wait() != 0:
                    raise ValueError('Decryption failed')
            except Exception:
                process.kill()
                process.wait()
                raise
    return {'status': 'checksum_and_decrypt_pass', 'set': folder.name, 'streams': summary,
            'database_import_or_application_restore': 'not_performed'}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('folder')
    parser.add_argument('--key', required=True)
    parser.add_argument('--cert', required=True)
    args = parser.parse_args()
    os.umask(0o077)
    try:
        print(json.dumps(verify(args.folder, args.key, args.cert)))
    except Exception:
        print('Recovery verification failed; no decrypted data was printed or extracted.')
        raise SystemExit(1)


if __name__ == '__main__':
    main()
