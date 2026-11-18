#!/usr/bin/env python3
"""Prefix mck89/peast into WPML\\ST\\Vendor (wpmldev-12555).
usage: prefix-peast.py <upstream peast dir> <target dir>
The four rewrites below are the whole transformation; every other byte is upstream's."""
import os, re, shutil, sys
src, dst = sys.argv[1], sys.argv[2]
P = r'WPML\ST\Vendor'
PE = P.replace('\\', '\\\\')          # as written inside a PHP double-quoted string
rules = [
    (re.compile(r'^namespace Peast', re.M), 'namespace ' + P + r'\\Peast'),
    (re.compile(r'^use Peast\\', re.M), 'use ' + P.replace('\\', r'\\') + r'\\Peast\\'),
    (re.compile(r'"(\\\\)?Peast\\\\'), lambda m: '"' + (m.group(1) or '') + PE + '\\\\Peast\\\\'),
    (re.compile(r'(?<![\\\w])\\Peast\\'), lambda m: '\\' + P + '\\Peast\\'),
]
rules[0] = (rules[0][0], lambda m: 'namespace ' + P + '\\Peast')
rules[1] = (rules[1][0], lambda m: 'use ' + P + '\\Peast\\')
if os.path.exists(dst): shutil.rmtree(dst)
n = 0
for root, _, files in os.walk(os.path.join(src, 'lib')):
    for f in files:
        if not f.endswith('.php'): continue
        s = open(os.path.join(root, f), encoding='utf-8').read()
        for rx, rep in rules: s = rx.sub(rep, s)
        out = os.path.join(dst, os.path.relpath(os.path.join(root, f), src))
        os.makedirs(os.path.dirname(out), exist_ok=True)
        open(out, 'w', encoding='utf-8', newline='').write(s); n += 1
shutil.copy(os.path.join(src, 'LICENSE'), os.path.join(dst, 'LICENSE'))
print(n, 'files')
