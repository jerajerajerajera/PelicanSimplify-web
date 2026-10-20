from pathlib import Path
import hashlib, importlib.util, json, tempfile, zipfile
root = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location('build_package', root / 'scripts' / 'build-package.py')
builder = importlib.util.module_from_spec(spec); spec.loader.exec_module(builder)

manifest = json.loads((root / 'plugin.json').read_text())
plugin = manifest['id']
assert manifest['update_url'] == builder.REPOSITORY + 'update.json', 'the plugin manager reads update.json on main'
channel = json.loads((root / 'update.json').read_text())
assert sorted(channel) == sorted(builder.SUPPORTED_PANELS), 'one entry per supported Pelican release'
assert manifest['panel_version'].lstrip('^') in channel
published = root / 'dist' / f"{plugin}-v{manifest['version']}.zip"
assert published.is_file(), 'the archive for this version is committed'
for entry in channel.values():
    assert entry['version'] == manifest['version'], 'update.json announces the current version'
    assert entry['download_url'] == builder.REPOSITORY + 'dist/' + published.name
    assert entry['sha256'] == hashlib.sha256(published.read_bytes()).hexdigest()
assert (root / 'dist' / f'{plugin}-latest.zip').read_bytes() == published.read_bytes()
assert [p.name for p in (root / 'dist').glob(f'{plugin}-v*.zip')] == [published.name], 'only the current archive is kept'

with tempfile.TemporaryDirectory() as temp:
    with zipfile.ZipFile(builder.build(Path(temp), 1_800_000_000)) as package:
        names = package.namelist()
        assert json.loads(package.read(f'{plugin}/plugin.json')) == manifest
        assert f"{plugin}/src/{manifest['class']}.php" in names
        assert f'{plugin}/config/server-directory.php' in names
        assert all(n.startswith(plugin + '/') for n in names)
        assert not any(part in n for n in names for part in ['/tests/', '/deploy/', '/scripts/', '.env'])
print('Package layout checks passed.')
