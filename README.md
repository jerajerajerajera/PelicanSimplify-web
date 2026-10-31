# Deckzwabbers Website (server-directory)

Pelican plugin for the Deckzwabbers server showcase: visibility, screenshots, modpacks, client releases and join addresses.
Works with [Simplify](https://github.com/jerajerajerajera/simplify) when it is installed.

## Updates

The panel's plugin manager reads `update.json` on `main` (`update_url` in `plugin.json`) and installs the zip it points to, after checking its sha256.

## Release

1. Raise `version` in `plugin.json`.
2. `python3 scripts/build-package.py` (writes `dist/server-directory-v<version>.zip`, `dist/server-directory-latest.zip` and `update.json`).
3. `python3 tests/package-layout.py && php tests/public-address.php`
4. Commit everything, including `dist/` and `update.json`, and merge to `main`.
