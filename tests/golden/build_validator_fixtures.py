#!/usr/bin/env python3
"""Build the expected reports for the PHP port of the divi5 layout validator.

Run from the plugin root:

    python3 -I tests/golden/build_validator_fixtures.py

It imports the divi5-layouts skill's validate.py read-only, runs validate_doc on
every tests/golden/validator/<name>.json and writes <name>.expected.json next to
it: {"errors": [...], "warnings": [...], "info": [...], "counts": {...}}.
Nothing is written anywhere else.

validate.py reads the skill's references/module-catalog.json. The plugin bundles
a byte copy as knowledge/module-catalog.json; this script refuses to run if the
two differ, so Python and PHP check against the same catalog.

Layouts named nocatalog_*.json are validated with no catalog (validate.py's
CATALOG_PATH points at a missing file for that run); the PHP test passes an
empty catalog for them.
"""
import glob
import json
import os
import sys

sys.dont_write_bytecode = True  # never write __pycache__ into the skill folder
SKILL = os.path.expanduser('~/.claude/skills/divi5-layouts')
sys.path.insert(0, os.path.join(SKILL, 'scripts'))

import validate  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
PLUGIN = os.path.dirname(os.path.dirname(HERE))
CASES = os.path.join(HERE, 'validator')
BUNDLED = os.path.join(PLUGIN, 'knowledge', 'module-catalog.json')


def main():
    with open(validate.CATALOG_PATH, 'rb') as fh:
        skill_catalog = fh.read()
    with open(BUNDLED, 'rb') as fh:
        if fh.read() != skill_catalog:
            sys.exit(f'{BUNDLED} is not a byte copy of {validate.CATALOG_PATH}')

    real_path = validate.CATALOG_PATH
    files = sorted(f for f in glob.glob(os.path.join(CASES, '*.json')) if not f.endswith('.expected.json'))
    for path in files:
        name = os.path.basename(path)[:-len('.json')]
        with open(path, encoding='utf-8') as fh:
            doc = json.load(fh)
        validate.CATALOG_PATH = os.path.join(CASES, 'no-such-catalog.json') if name.startswith('nocatalog_') else real_path
        try:
            rep, counts = validate.validate_doc(doc)
        finally:
            validate.CATALOG_PATH = real_path
        out = {'errors': rep.errors, 'warnings': rep.warnings, 'info': rep.info, 'counts': counts}
        with open(os.path.join(CASES, name + '.expected.json'), 'w', encoding='utf-8', newline='') as fh:
            json.dump(out, fh, ensure_ascii=False, indent=1)
            fh.write('\n')
    print(f'Wrote {len(files)} expected reports to {CASES}')


if __name__ == '__main__':
    main()
