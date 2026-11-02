"""Exercise backup/restore guards with real archives and isolated database clients."""
import hashlib
import io
import os
from pathlib import Path
import subprocess
import tarfile
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class BackupTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        clients = self.root / 'bin'
        clients.mkdir()
        script = '''#!/usr/bin/env python3
import os,sys
from pathlib import Path
name=Path(sys.argv[0]).name
with open(os.environ['CLIENT_LOG'],'a') as f:f.write(name+'\\n')
if name=='pg_dump':
 if os.environ.get('DUMP_FAIL'):sys.exit(1)
 Path(next(x.split('=',1)[1] for x in sys.argv if x.startswith('--file='))).write_bytes(b'isolated-test-dump')
elif name in ('psql','mariadb'):print(os.environ.get('TABLE_COUNT','0'))
elif name=='mariadb-dump':print('-- isolated mysql dump')
'''
        for name in ['pg_dump', 'pg_restore', 'psql', 'mariadb', 'mariadb-dump']:
            path = clients / name
            path.write_text(script)
            path.chmod(0o755)
        self.media = self.root / 'media'
        (self.media / 'public' / 'products').mkdir(parents=True)
        (self.media / 'private').mkdir()
        (self.media / 'public' / 'products' / 'photo.png').write_bytes(b'original-photo')
        (self.media / 'private' / 'invoice.pdf').write_bytes(b'private-invoice')
        self.env = dict(os.environ, PATH=str(clients) + os.pathsep + os.environ['PATH'],
                        DB_CONNECTION='pgsql', DB_HOST='direct.example.test', DB_DATABASE='test',
                        DB_USERNAME='test', DB_PASSWORD='test-only-do-not-use', DB_SSLMODE='require',
                        MEDIA_DIR=str(self.media), BACKUP_DIR=str(self.root / 'backups'),
                        MEDIA_EXPORT_COMPLETE='true', CLIENT_LOG=str(self.root / 'clients.log'))

    def run_script(self, name, *args, **updates):
        return subprocess.run(['sh', str(ROOT / 'scripts' / name), *map(str, args)],
                              env=self.env | updates, capture_output=True, text=True)

    def backup(self, **updates):
        result = self.run_script('backup.sh', **updates)
        self.assertEqual(result.returncode, 0, result.stderr)
        return next((self.root / 'backups').iterdir())

    def test_postgresql_roundtrip_preserves_public_and_private_bytes(self):
        backup = self.backup()
        target = self.root / 'restored'
        result = self.run_script('restore.sh', backup, MEDIA_DIR=str(target))
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual((target / 'public/products/photo.png').read_bytes(), b'original-photo')
        self.assertEqual((target / 'private/invoice.pdf').read_bytes(), b'private-invoice')

    def test_mysql_format_remains_supported(self):
        backup = self.backup(DB_CONNECTION='mysql')
        self.assertTrue((backup / 'db.sql').is_file())
        result = self.run_script('restore.sh', backup, DB_CONNECTION='mysql', MEDIA_DIR=str(self.root / 'restored'))
        self.assertEqual(result.returncode, 0, result.stderr)

    def test_pooler_and_plaintext_connections_are_rejected(self):
        self.assertNotEqual(self.run_script('backup.sh', DB_HOST='ep-example-pooler.neon.tech').returncode, 0)
        self.assertNotEqual(self.run_script('backup.sh', DB_SSLMODE='disable').returncode, 0)
        self.assertFalse((self.root / 'clients.log').exists())

    def test_failed_dump_never_marks_a_backup_complete(self):
        result = self.run_script('backup.sh', DUMP_FAIL='1')
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(list((self.root / 'backups').glob('*/COMPLETE')))

    def test_corrupt_backup_is_rejected_before_database_access(self):
        backup = self.backup()
        (backup / 'db.dump').write_bytes(b'corrupt')
        log = self.root / 'clients.log'
        log.write_text('')
        result = self.run_script('restore.sh', backup, MEDIA_DIR=str(self.root / 'restored'))
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(log.read_text(), '')

    def test_nonempty_database_is_not_restored(self):
        backup = self.backup()
        log = self.root / 'clients.log'
        log.write_text('')
        result = self.run_script('restore.sh', backup, TABLE_COUNT='3', MEDIA_DIR=str(self.root / 'restored'))
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(log.read_text(), 'psql\n')

    def test_archive_cannot_escape_the_media_destination(self):
        backup = self.backup()
        archive_path = backup / 'media.tar.gz'
        with tarfile.open(archive_path, 'w:gz') as archive:
            member = tarfile.TarInfo('../escaped.txt')
            member.size = 1
            archive.addfile(member, io.BytesIO(b'x'))
        (backup / 'SHA256SUMS').write_text(''.join(hashlib.sha256((backup / name).read_bytes()).hexdigest() + '  ' + name + '\n'
                                                for name in ['db.dump', 'media.tar.gz', 'database-driver']))
        log = self.root / 'clients.log'
        log.write_text('')
        result = self.run_script('restore.sh', backup, MEDIA_DIR=str(self.root / 'restored'))
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(log.read_text(), '')
        self.assertFalse((self.root / 'escaped.txt').exists())


if __name__ == '__main__':
    unittest.main()
