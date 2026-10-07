#!/usr/bin/env python3
"""Adversarial exact-byte extraction proofs without private upstream fixtures."""
import base64
import copy
import hashlib
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
spec = importlib.util.spec_from_file_location('free_import', Path(__file__).resolve().parents[2] / 'scripts/free-import.py')
mod = importlib.util.module_from_spec(spec); spec.loader.exec_module(mod)
h = lambda b: hashlib.sha256(b).hexdigest()
class Extraction(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(); self.root = Path(self.temp.name); (self.root / 'config').mkdir()
        self.old = mod.ROOT; mod.ROOT = self.root; self.data = b'canonical shared / forbidden branch / canonical tail'
        self.recipe = {'input_sha256': h(self.data), 'selected_sha256': h(b'canonical shared / safe / canonical tail'), 'spans': [{'start':19,'end':35,'replacement':base64.b64encode(b'safe').decode()}]}
        self.row = {'source':'kernel.php','extraction':'kernel.php'}
    def tearDown(self): mod.ROOT = self.old; self.temp.cleanup()
    def run_recipe(self, recipe=None, data=None):
        encoded = json.dumps({'kernel.php': recipe or self.recipe}).encode(); (self.root / 'config/free-core-extractions.json').write_bytes(encoded)
        manifest = {'extractions':{'id':'byte-spans-v1','path':'config/free-core-extractions.json','sha256':h(encoded)}}
        return mod.extract(self.data if data is None else data, self.row, manifest)
    def test_exact_selected_bytes(self): self.assertEqual(b'canonical shared / safe / canonical tail', self.run_recipe())
    def test_source_drift(self):
        with self.assertRaisesRegex(ValueError, 'source mismatch'): self.run_recipe(data=self.data+b'x')
    def test_bad_bounds_order_and_types(self):
        for change in [{'start':-1}, {'end':100}, {'start':True}, {'end':1}, {'replacement':'*'}]:
            r=copy.deepcopy(self.recipe); r['spans'][0].update(change)
            with self.assertRaises(ValueError): self.run_recipe(r)
        r=copy.deepcopy(self.recipe); r['spans'].append(copy.deepcopy(r['spans'][0]))
        with self.assertRaisesRegex(ValueError, 'span'): self.run_recipe(r)
    def test_output_hash(self):
        r=copy.deepcopy(self.recipe); r['selected_sha256']='0'*64
        with self.assertRaisesRegex(ValueError, 'result drift'): self.run_recipe(r)
    def test_recipe_binding(self):
        self.run_recipe()
        with self.assertRaisesRegex(ValueError, 'recipe drift'): mod.extract(self.data, self.row, {'extractions':{'id':'byte-spans-v1','path':'config/free-core-extractions.json','sha256':'0'*64}})
    def test_source_identity(self):
        self.row['source']='other.php'
        with self.assertRaisesRegex(ValueError, 'source mismatch'): self.run_recipe()
if __name__ == '__main__': unittest.main()
