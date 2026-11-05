#!/usr/bin/env python3
"""Download the licensed coursework originals for a local, offline presentation."""

import argparse
import concurrent.futures
import hashlib
import json
import pathlib
import urllib.parse
import urllib.request


def main():
    root = pathlib.Path(__file__).resolve().parents[1]
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--destination', type=pathlib.Path, default=root / 'store/storage/app/public')
    parser.add_argument('--dry-run', action='store_true')
    args = parser.parse_args()
    destination = args.destination.resolve()
    manifest = root / 'store/packages/ClassyFashion/src/Resources/coursework-catalogue/assets.json'
    assets = json.loads(manifest.read_text())

    for asset in assets:
        parsed = urllib.parse.urlparse(asset['url'])
        target = (destination / asset['path']).resolve()
        if parsed.scheme != 'https' or parsed.hostname != 'res.cloudinary.com':
            raise ValueError('Unexpected image host.')
        if not asset['path'].startswith(('products/catalogue-20261104/', 'storefront/editorial/20261104/')) or not target.is_relative_to(destination):
            raise ValueError('Unexpected image path.')

    if args.dry_run:
        print(f'{len(assets)} licensed originals validated; destination: {destination}. No changes made.')
        return

    def fetch(asset):
        target = destination / asset['path']
        if target.exists():
            if hashlib.sha256(target.read_bytes()).hexdigest() != asset['sha256']:
                raise ValueError('Existing photo differs; move it aside before retrying: ' + str(target))
            return
        with urllib.request.urlopen(asset['url'], timeout=60) as response:
            contents = response.read(10 * 1024 * 1024)
        if hashlib.sha256(contents).hexdigest() != asset['sha256']:
            raise ValueError('Downloaded photo checksum differs: ' + asset['path'])
        target.parent.mkdir(parents=True, exist_ok=True)
        temporary = target.with_suffix(target.suffix + '.partial')
        temporary.write_bytes(contents)
        temporary.replace(target)

    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
        list(pool.map(fetch, assets))
    print(f'{len(assets)} originals verified in {destination}.')


if __name__ == '__main__':
    main()
