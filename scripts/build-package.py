#!/usr/bin/env python3
"""Build the plugin package and publish it on the update channel the panel's plugin manager reads.

Writes dist/server-directory-v<version>.zip, dist/server-directory-latest.zip and update.json
(one entry per supported Pelican release, with the sha256 of the zip). Older archives are removed.
Usage: build-package.py [output-dir]   (default: dist; SOURCE_DATE_EPOCH fixes the timestamps)
"""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import os
import shutil
import sys
import zipfile

root = Path(__file__).resolve().parent.parent
REPOSITORY = 'https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify-web/main/'
SUPPORTED_PANELS = ['1.0.0-beta38', '1.0.0-rc.1']


def build(output: Path, epoch: int | None = None) -> Path:
    manifest = json.loads((root / 'plugin.json').read_text())
    plugin = manifest['id']
    output.mkdir(parents=True, exist_ok=True)
    archive = output / f"{plugin}-v{manifest['version']}.zip"
    # ZIP extraction preserves mtimes: each release needs newer timestamps, or compiled views look up to date.
    moment = datetime.fromtimestamp(epoch, timezone.utc) if epoch is not None else datetime.now(timezone.utc)
    release_time = moment.timetuple()[:6]
    files = [root / name for name in ['plugin.json', 'README.md', 'LICENSE'] if (root / name).is_file()]
    for name in ['src', 'resources', 'config', 'database', 'lang']:
        if (root / name).is_dir():
            files.extend(p for p in (root / name).rglob('*') if p.is_file())
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as package:
        for path in sorted(files):
            info = zipfile.ZipInfo(plugin + '/' + path.relative_to(root).as_posix(), release_time)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            package.writestr(info, path.read_bytes())
    return archive


def publish(archive: Path) -> None:
    manifest = json.loads((root / 'plugin.json').read_text())
    entry = {
        'version': manifest['version'],
        'download_url': REPOSITORY + archive.relative_to(root).as_posix(),
        'sha256': hashlib.sha256(archive.read_bytes()).hexdigest(),
    }
    (root / 'update.json').write_text(json.dumps({panel: entry for panel in SUPPORTED_PANELS}, indent=2) + '\n')
    shutil.copyfile(archive, archive.parent / f"{manifest['id']}-latest.zip")
    for old in archive.parent.glob(f"{manifest['id']}-v*.zip"):
        if old.name != archive.name:
            old.unlink()


if __name__ == '__main__':
    target = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else root / 'dist'
    epoch = int(os.environ['SOURCE_DATE_EPOCH']) if 'SOURCE_DATE_EPOCH' in os.environ else None
    built = build(target, epoch)
    if target == root / 'dist':
        publish(built)
    print(built)
