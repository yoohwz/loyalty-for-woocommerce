#!/usr/bin/env python3
"""Native PHP fixture HTTP/cache boundary; no WordPress correctness claim."""
import http.client
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import unittest

ROUTER = Path(__file__).resolve().parents[2] / 'tests/runtime/browser-router.php'

class RouterTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp = tempfile.TemporaryDirectory(prefix='loyf-router-')
        cls.root = Path(cls.tmp.name)
        assets = cls.root / 'wp-content/plugins/probe'
        assets.mkdir(parents=True)
        (assets / 'style.css').write_text('body { color: red; }')
        (assets / 'script.js').write_text('window.fixture = true;')
        private = "<?php header('Cache-Control: private, no-store'); echo 'private-customer';"
        (cls.root / 'index.php').write_text(private)
        (assets / 'private.php').write_text(private)
        (assets / 'private.json').write_text('{"customer":1}')
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0)); cls.port = sock.getsockname()[1]
        cls.log = tempfile.TemporaryFile()
        cls.server = subprocess.Popen([shutil.which('php'), '-S', f'127.0.0.1:{cls.port}', '-t', str(cls.root), str(ROUTER)], stdout=cls.log, stderr=cls.log)
        deadline = time.monotonic() + 5
        while True:
            try:
                cls.request('/'); break
            except OSError:
                if time.monotonic() >= deadline:
                    cls.server.terminate(); cls.server.wait(); raise
                time.sleep(0.05)

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate(); cls.server.wait(timeout=5)
        cls.log.close(); cls.tmp.cleanup()

    @classmethod
    def request(cls, path, method='GET'):
        conn = http.client.HTTPConnection('127.0.0.1', cls.port, timeout=5)
        conn.request(method, path, headers={'Cookie':'fixture=private'})
        response = conn.getresponse()
        result = (response.status, {k.lower():v for k,v in response.getheaders()}, response.read())
        conn.close(); return result

    def test_public_bytes_mime_and_head(self):
        for name, mime in [('style.css','text/css'), ('script.js','application/javascript')]:
            path = '/wp-content/plugins/probe/' + name + '?ver=frozen'
            status, headers, body = self.request(path)
            self.assertEqual(200, status)
            self.assertEqual((self.root / 'wp-content/plugins/probe' / name).read_bytes(), body)
            self.assertEqual(mime, headers['content-type'].split(';')[0])
            self.assertEqual('public, max-age=3600', headers['cache-control'])
            _, head, body = self.request(path, 'HEAD')
            self.assertEqual(b'', body); self.assertEqual(headers['content-length'], head['content-length'])

    def test_private_php_rest_and_missing_file_remain_native(self):
        for path in ['/', '/?rest_route=/fixture', '/wp-content/plugins/probe/private.php', '/wp-content/plugins/probe/missing.css']:
            _, headers, body = self.request(path)
            self.assertEqual('private, no-store', headers.get('cache-control'))
            self.assertEqual(b'private-customer', body)

    def test_unlisted_json_and_mutation_method_are_not_freshness_cached(self):
        for path, method in [('/wp-content/plugins/probe/private.json','GET'), ('/wp-content/plugins/probe/style.css','POST')]:
            _, headers, _ = self.request(path, method)
            self.assertNotIn('public', headers.get('cache-control', ''))

if __name__ == '__main__':
    unittest.main()
