#!/usr/bin/env python3
"""Build the golden fixtures for the PHP port of the divi5 layout library.

Run once from the plugin root:

    python3 -I tests/golden/build_fixtures.py

It imports the divi5-layouts skill read-only, interprets every case in
cases.json and writes tests/golden/fixtures/<case>.html (block markup) or
<case>.json (JSON results). Nothing is written anywhere else.

Case format:
    {"name": str,
     "calls": [{"fn": str, "args": [...], "kwargs": {...}}, ...],
     "result": int | [int, ...]    # call index(es) to output; default = last call
     "output": "serialize" | "string" | "json"}   # default "serialize"

"fn" names a divi5 helper, or "patterns.<name>" for a composition pattern
(patterns.py). Args and kwargs may contain {"$ref": n}, replaced by the result
of call n, and {"$theme": {...overrides}}, replaced by patterns.Theme(**overrides)
(JSON lists become tuples, since Theme.card_radius is a tuple).
Every case starts from set_builder_version("5.19.1").
"""
import json
import os
import sys

sys.dont_write_bytecode = True  # never write __pycache__ into the skill folder
sys.path.insert(0, os.path.expanduser('~/.claude/skills/divi5-layouts/scripts'))

import divi5  # noqa: E402
import patterns  # noqa: E402

BUILDER_VERSION = "5.19.1"
HERE = os.path.dirname(os.path.abspath(__file__))
FIXTURES = os.path.join(HERE, "fixtures")


def resolve(value, results):
    if isinstance(value, dict):
        if set(value) == {"$ref"}:
            return results[value["$ref"]]
        if set(value) == {"$theme"}:
            overrides = resolve(value["$theme"], results)
            return patterns.Theme(**{k: tuple(v) if isinstance(v, list) else v for k, v in overrides.items()})
        return {k: resolve(v, results) for k, v in value.items()}
    if isinstance(value, list):
        return [resolve(v, results) for v in value]
    return value


def run_case(case):
    divi5.set_builder_version(BUILDER_VERSION)
    results = []
    for call in case["calls"]:
        name = call["fn"]
        if name.startswith("patterns."):
            fn = getattr(patterns, name[len("patterns."):])
        else:
            fn = getattr(divi5, name)
        args = resolve(call.get("args", []), results)
        kwargs = resolve(call.get("kwargs", {}), results)
        results.append(fn(*args, **kwargs))
    picked = case.get("result", len(results) - 1)
    value = [results[i] for i in picked] if isinstance(picked, list) else results[picked]
    output = case.get("output", "serialize")
    if output == "serialize":
        return divi5.serialize(value), ".html"
    if output == "string":
        return value, ".html"
    if output == "json":
        return json.dumps(value, ensure_ascii=False, separators=(",", ":")), ".json"
    raise ValueError(f"Unknown output {output!r}")


def main():
    with open(os.path.join(HERE, "cases.json"), encoding="utf-8") as fh:
        cases = json.load(fh)
    os.makedirs(FIXTURES, exist_ok=True)
    names = set()
    for case in cases:
        name = case["name"]
        if name in names:
            raise ValueError(f"Duplicate case {name!r}")
        names.add(name)
        content, ext = run_case(case)
        with open(os.path.join(FIXTURES, name + ext), "w", encoding="utf-8", newline="") as fh:
            fh.write(content)
    divi5.set_builder_version(BUILDER_VERSION)
    print(f"Wrote {len(cases)} fixtures to {FIXTURES}")


if __name__ == "__main__":
    main()
