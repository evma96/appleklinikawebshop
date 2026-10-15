#!/usr/bin/env python3
"""Offline regressions: fake SSH/rsync; ephemeral OpenSSL fixture, no provider calls."""
import hashlib
import importlib.util
import io
import json
import os
import pathlib
import socket
import subprocess
import tarfile
import tempfile
import unittest

ROOT = pathlib.Path(__file__).parent

def load(name, file):
    spec = importlib.util.spec_from_file_location(name, ROOT / file)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module

sync = load('sync', 'offserver-sync.py')
monitor = load('monitor', 'test-monitor.py')
verify = load('verify', 'verify-encrypted-set.py')

class TransportTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = pathlib.Path(self.temp.name)
        self.folder = self.root / '20261015T030000Z'
        self.folder.mkdir()
        for name in sync.NAMES:
            (self.folder / name).write_bytes(('encrypted-fixture-' + name).encode())
        self.manifest()
        self.config = {'host': 'u123-sub1.your-storagebox.de', 'username': 'u123-sub1',
                       'directory': 'appleklinika/test/routine', 'known_hosts': str(self.root / 'known'),
                       'identity_file': str(self.root / 'key'), 'expected_hostname': socket.gethostname(),
                       'environment': 'TEST', 'state_file': str(self.root / 'state.json')}
        for name in ('known', 'key'):
            (self.root / name).write_text('fixture only')
            (self.root / name).chmod(0o600)
        self.calls = []

    def tearDown(self):
        self.temp.cleanup()

    def manifest(self):
        (self.folder / 'SHA256SUMS').write_text(''.join(sync.digest(self.folder / n) + '  ' + n + '\n' for n in sorted(sync.NAMES)))

    def fake(self, args):
        self.calls.append(args)
        last = args[-1]
        if last.startswith('sha256sum '):
            return ''.join(sync.digest(self.folder / n) + '  ' + self.config['directory'] + '/' + self.folder.name + '/' + n + '\n' for n in sorted(sync.NAMES))
        if last.startswith('cat '):
            return (self.folder / 'SHA256SUMS').read_text()
        if args[0] == 'rsync' and '@' in args[args.index('--') + 1]:
            target = pathlib.Path(last)
            n = pathlib.PurePosixPath(args[args.index('--') + 1]).name
            (target / n).write_bytes((self.folder / n).read_bytes())
        return ''

    def test_manifest_and_plaintext_refusal(self):
        self.assertEqual(len(sync.inventory(self.folder)), 4)
        (self.folder / 'private.sql').write_text('fixture')
        with self.assertRaises(ValueError): sync.inventory(self.folder)

    def test_corruption_refusal(self):
        (self.folder / 'database.cms').write_text('corrupt')
        with self.assertRaises(ValueError): sync.inventory(self.folder)

    def test_symlink_refusal(self):
        (self.folder / 'SHA256SUMS').rename(self.root / 'manifest')
        (self.folder / 'SHA256SUMS').symlink_to(self.root / 'manifest')
        with self.assertRaises(ValueError): sync.inventory(self.folder)

    def test_transfer_publish_last_and_recheck_retry(self):
        first = sync.transfer(self.config, self.folder, self.fake)
        self.assertEqual(len(first), 64)
        self.assertTrue(self.calls[-2][-1].endswith('/SHA256SUMS'))
        self.assertIn('StrictHostKeyChecking=yes', str(self.calls))
        self.assertNotIn('--delete', str(self.calls))
        sync.transfer(self.config, self.folder, self.fake)
        self.assertEqual(sum(c[-1].startswith('sha256sum ') for c in self.calls), 2)

    def test_remote_corruption_never_publishes_manifest(self):
        def bad(args):
            if args[-1].startswith('sha256sum '): return ''
            return self.fake(args)
        with self.assertRaises(ValueError): sync.transfer(self.config, self.folder, bad)
        self.assertFalse(any(c[0] == 'rsync' and c[-1].endswith('/SHA256SUMS') for c in self.calls))

    def test_dedicated_subaccount_and_path_guard(self):
        sync.connection(self.config)
        for patch in ({'host': 'u123.your-storagebox.de'}, {'username': 'u123'}, {'directory': '../escape'}, {'directory': '/home'}, {'directory': 'x;whoami'}):
            with self.assertRaises(ValueError): sync.connection(dict(self.config, **patch))

    def test_environment_and_host_guard(self):
        sync.guard(self.config, 'upload')
        for patch in ({'environment': 'PRODUCTION'}, {'expected_hostname': 'other'}):
            with self.assertRaises(ValueError): sync.guard(dict(self.config, **patch), 'upload')
        with self.assertRaises(ValueError): sync.guard(dict(self.config, environment='RECOVERY'), 'upload')
        sync.guard(dict(self.config, environment='RECOVERY'), 'download')

    def test_private_permissions(self):
        (self.root / 'key').chmod(0o644)
        with self.assertRaises(ValueError): sync.guard(self.config, 'upload')

    def test_retention_never_deletes_and_keeps_floor(self):
        names = ['202609%02dT030000Z' % n for n in range(1, 31)]
        plan = sync.retention_plan(names, 14, ['20261001T030000Z'])
        self.assertEqual(len(plan['keep']), 14)
        self.assertEqual(len(plan['review_expired']), 16)
        self.assertFalse(plan['automatic_deletion'])
        with self.assertRaises(ValueError): sync.retention_plan(names, 1)

    def test_old_backup_not_freshened_by_check(self):
        now = sync.set_epoch(self.folder.name)
        self.assertIsNone(sync.health({'status': 'success', 'checked_epoch': now, 'latest_backup_epoch': now}, now))
        self.assertIsNotNone(sync.health({'status': 'success', 'checked_epoch': now, 'latest_backup_epoch': now - 31 * 3600}, now))
        self.assertIsNotNone(sync.health({'status': 'failed'}, now))
        self.assertIsNotNone(sync.health({}, now))

    def test_download_checks_and_no_overwrite(self):
        destination = self.root / 'recover'
        destination.mkdir(mode=0o700)
        target = destination / self.folder.name
        self.assertEqual(sync.download(self.config, self.folder.name, target, self.fake)['status'], 'download_verified')
        self.assertEqual(sync.inventory(target), sync.inventory(self.folder))
        with self.assertRaises(ValueError): sync.download(self.config, self.folder.name, target, self.fake)

    def test_disabled_and_dry_run_never_connect(self):
        config = self.root / 'config.json'
        config.write_text(json.dumps({'enabled': True, 'host': 'INVALID'}))
        result = subprocess.run(['python3', str(ROOT / 'offserver-sync.py'), str(config)], capture_output=True, text=True)
        self.assertEqual(result.returncode, 0)
        self.assertFalse(json.loads(result.stdout)['connection'])
        config.write_text('{"enabled":false}')
        result = subprocess.run(['python3', str(ROOT / 'offserver-sync.py'), str(config), '--apply'], capture_output=True, text=True)
        self.assertEqual(result.returncode, 0)
        self.assertIn('disabled', result.stdout)

    def test_monitor_opt_in_failed_stale_and_corrupt(self):
        path = self.root / 'monitor-config.json'
        now = sync.set_epoch(self.folder.name)
        self.assertFalse(monitor.offserver_status(path, now))
        path.write_text('{"enabled": false}')
        self.assertFalse(monitor.offserver_status(path, now))
        path.write_text(json.dumps(dict(self.config, enabled=True)))
        self.assertTrue(monitor.offserver_status(path, now))
        state = {'status': 'success', 'checked_epoch': now, 'latest_backup_epoch': now}
        pathlib.Path(self.config['state_file']).write_text(json.dumps(state))
        self.assertFalse(monitor.offserver_status(path, now))
        self.assertTrue(monitor.offserver_status(path, now, True))
        self.assertTrue(monitor.offserver_status(path, now + 31 * 3600))
        path.write_text('broken')
        self.assertTrue(monitor.offserver_status(path, now))
        self.assertIn('remote_backup_failure_or_stale', monitor.classify({'offserver_problem': True}))

    def test_actual_fixture_cms_decrypt_no_extraction(self):
        key, cert = self.root / 'fixture-key.pem', self.root / 'fixture-cert.pem'
        subprocess.run(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '2',
                        '-subj', '/CN=Offline fixture only', '-keyout', str(key), '-out', str(cert)],
                       check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        key.chmod(0o600)
        archive = io.BytesIO()
        with tarfile.open(fileobj=archive, mode='w:gz') as tar:
            for name in ('opt/appleklinika/production/compose.yaml', 'opt/appleklinika/production/secrets/mysql_password', 'var/lib/docker/volumes/appleklinika-staging_wordpress_data/_data/wp-config.php'):
                info = tarfile.TarInfo(name); info.size = 7
                tar.addfile(info, io.BytesIO(b'fixture'))
        data = {'files.cms': archive.getvalue(), 'database.cms': b'CREATE TABLE fixture (id int);\n-- Dump completed\n',
                'logs.cms': b'fixture logs', 'inventory.cms': b'fixture runtime inventory'}
        for name, content in data.items():
            subprocess.run(['openssl', 'cms', '-encrypt', '-binary', '-aes-256-cbc', '-outform', 'DER',
                            '-out', str(self.folder / name), str(cert)], input=content, check=True,
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        self.manifest()
        report = verify.verify(self.folder, key, cert)
        self.assertEqual(report['status'], 'checksum_and_decrypt_pass')
        self.assertEqual(report['streams']['database.cms']['tables'], 1)
        self.assertFalse((self.root / 'wordpress').exists())

if __name__ == '__main__':
    unittest.main()
