#!/usr/bin/env python3
"""Offline SFTP regressions: filesystem fake, no network or provider calls."""
import importlib.util
import pathlib
import shutil
import tempfile
import unittest

ROOT = pathlib.Path(__file__).parent
spec = importlib.util.spec_from_file_location('sftpbackup', ROOT / 'offserver-sftp.py')
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

class Reader:
    def __init__(self, path): self.stream = path.open('rb')
    def __enter__(self): return self
    def __exit__(self, *args): self.stream.close()
    def prefetch(self, *args): pass
    def read(self, size=-1): return self.stream.read(size)

class Fake:
    def __init__(self, root): self.root, self.uploads = root, []
    def lstat(self, p): return (self.root / p).lstat()
    def listdir(self, p): return [n.name for n in (self.root / p).iterdir()]
    def mkdir(self, p, mode): (self.root / p).mkdir(mode=mode)
    def chmod(self, p, mode): (self.root / p).chmod(mode)
    def put(self, source, dest, confirm=True):
        self.uploads.append(dest); shutil.copyfile(source, self.root / dest)
    def get(self, source, dest): shutil.copyfile(self.root / source, dest)
    def open(self, p, mode): return Reader(self.root / p)
    def rename(self, old, new):
        if (self.root / new).exists(): raise FileExistsError()
        (self.root / old).rename(self.root / new)
    def remove(self, p): (self.root / p).unlink()
    def rmdir(self, p): (self.root / p).rmdir()

class Tests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = pathlib.Path(self.temp.name)
        self.folder = self.root / '20261030T120758Z'; self.folder.mkdir()
        for n in m.sync.NAMES: (self.folder / n).write_bytes(b'ciphertext fixture ' + n.encode())
        (self.folder / 'SHA256SUMS').write_text(''.join(m.sync.digest(self.folder / n)+'  '+n+'\n' for n in sorted(m.sync.NAMES)))
        self.remote = self.root / 'remote'; self.remote.mkdir()
        self.sftp = Fake(self.remote)
        self.config = {'directory':'appleklinika/test/routine', 'retention_sets':30}
    def tearDown(self): self.temp.cleanup()
    def test_upload_checksum_manifest_last_retry_no_overwrite(self):
        result = m.transfer(self.sftp,self.config,self.folder)
        self.assertTrue(result['uploaded'])
        self.assertTrue(self.sftp.uploads[-1].endswith('/SHA256SUMS'))
        before = list(self.sftp.uploads)
        self.assertFalse(m.transfer(self.sftp,self.config,self.folder)['uploaded'])
        self.assertEqual(before,self.sftp.uploads)
    def test_corrupt_existing_archive_rejected_not_overwritten(self):
        m.transfer(self.sftp,self.config,self.folder)
        p=self.remote/self.config['directory']/self.folder.name/'database.cms'; p.write_bytes(b'bad')
        with self.assertRaises(ValueError): m.transfer(self.sftp,self.config,self.folder)
        self.assertEqual(p.read_bytes(),b'bad')
    def test_corrupt_upload_not_published(self):
        original=self.sftp.put
        def corrupt(src,dst,confirm=True):
            original(src,dst,confirm); (self.remote/dst).write_bytes(b'bad')
        self.sftp.put=corrupt
        with self.assertRaises(ValueError): m.transfer(self.sftp,self.config,self.folder)
        self.assertFalse((self.remote/self.config['directory']/self.folder.name).exists())
    def test_plaintext_rejected_before_upload(self):
        (self.folder/'db.sql').write_text('private fixture')
        with self.assertRaises(ValueError): m.transfer(self.sftp,self.config,self.folder)
        self.assertEqual(self.sftp.uploads,[])
    def test_remote_symlink_rejected(self):
        (self.remote/'appleklinika').symlink_to(self.root,target_is_directory=True)
        with self.assertRaises(ValueError): m.transfer(self.sftp,self.config,self.folder)
    def test_download_isolated_checksums_and_existing_refused(self):
        m.transfer(self.sftp,self.config,self.folder)
        parent=self.root/'restore';parent.mkdir(mode=0o700); target=parent/self.folder.name
        self.assertEqual(m.download(self.sftp,self.config,self.folder.name,target)['status'],'download_verified')
        self.assertEqual(m.sync.inventory(target),m.sync.inventory(self.folder))
        with self.assertRaises(ValueError): m.download(self.sftp,self.config,self.folder.name,target)
    def test_download_corruption_not_published(self):
        m.transfer(self.sftp,self.config,self.folder)
        (self.remote/self.config['directory']/self.folder.name/'files.cms').write_bytes(b'bad')
        parent=self.root/'restore';parent.mkdir(mode=0o700);target=parent/self.folder.name
        with self.assertRaises(ValueError): m.download(self.sftp,self.config,self.folder.name,target)
        self.assertFalse(target.exists())
    def test_retention_preserves_30_and_unmanaged_and_partial(self):
        m.transfer(self.sftp,self.config,self.folder)
        base=self.remote/self.config['directory']; managed={}
        for day in range(1,32):
            name='202608%02dT030000Z'%day
            shutil.copytree(base/self.folder.name,base/name)
            managed[name]=m.sync.digest(base/name/'SHA256SUMS')
        managed.pop('20260801T030000Z')
        (base/'.partial-fixture').mkdir()
        removed=m.prune(self.sftp,self.config,managed)
        self.assertEqual(removed,['20260802T030000Z'])
        self.assertTrue((base/'20260801T030000Z').exists())
        self.assertTrue((base/'.partial-fixture').exists())
    def test_retention_does_not_keep_corrupt_in_place_of_good(self):
        m.transfer(self.sftp,self.config,self.folder)
        base=self.remote/self.config['directory']; managed={}
        for day in range(1,31):
            name='202609%02dT030000Z'%day;shutil.copytree(base/self.folder.name,base/name)
            managed[name]=m.sync.digest(base/name/'SHA256SUMS')
        (base/self.folder.name/'files.cms').write_bytes(b'bad')
        with self.assertRaises(ValueError):m.prune(self.sftp,self.config,managed)
        self.assertTrue((base/'20260901T030000Z').exists())
    def test_retention_floor(self):
        with self.assertRaises(ValueError):m.prune(self.sftp,dict(self.config,retention_sets=14),{})
    def test_running_monitor_has_bounded_grace_not_stale_success(self):
        now=m.sync.set_epoch(self.folder.name)
        state={'status':'running','started_epoch':now-60,'checked_epoch':now-60,'latest_backup_epoch':now-24*3600}
        self.assertIsNone(m.sync.health(state,now))
        self.assertIsNotNone(m.sync.health(dict(state,started_epoch=now-7201),now))
        self.assertIsNotNone(m.sync.health(dict(state,latest_backup_epoch=now-31*3600),now))
        self.assertIsNotNone(m.sync.health({'status':'running','started_epoch':now},now))

if __name__=='__main__': unittest.main()
