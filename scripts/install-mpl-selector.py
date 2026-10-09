#!/usr/bin/env python3
"""Install the pinned, unmodified free selector. Does not activate it or license PRO."""
import argparse
import hashlib
import io
import json
from pathlib import Path
import shutil
import tempfile
import urllib.request
import zipfile

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('plugins_directory', type=Path)
args = parser.parse_args()
manifest = json.loads((Path(__file__).resolve().parents[1] / 'docker/mpl-selector.json').read_text())
destination = args.plugins_directory.resolve() / manifest['slug']
if destination.exists():
    raise SystemExit('Destination exists. Review/remove an obsolete copy explicitly; installer never overwrites.')
with urllib.request.urlopen(manifest['url'], timeout=60) as response:
    data = response.read(30 * 1024 * 1024)
if hashlib.sha256(data).hexdigest() != manifest['sha256']:
    raise SystemExit('Release checksum mismatch; nothing installed.')
with tempfile.TemporaryDirectory(dir=args.plugins_directory) as temporary:
    root = Path(temporary).resolve()
    with zipfile.ZipFile(io.BytesIO(data)) as archive:
        for info in archive.infolist():
            target = (root / info.filename).resolve()
            if not target.is_relative_to(root / manifest['slug']) or (info.external_attr >> 16) & 0o170000 == 0o120000:
                raise SystemExit('Unsafe archive member.')
        archive.extractall(root)
    shutil.move(str(root / manifest['slug']), destination)
print(f"Installed {manifest['slug']} {manifest['version']}; SHA-256 verified; not activated.")
