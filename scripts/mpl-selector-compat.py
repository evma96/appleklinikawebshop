#!/usr/bin/env python3
"""Bounded 4.2.8 compatibility fix: bind markercluster to its bundled Leaflet.

The upstream Browserify markercluster module uses free global L, while its parent
uses require('leaflet'). Another map widget can replace global L before jQuery
ready. Use the existing bundled module; change no provider/licensing behavior.
"""
import hashlib
import pathlib
import sys

ORIGINAL = '714a33b2a0e621bb7c8600865b67d4ec5b86558963f8546fb614bda0f7937f1c'
ANCHOR = b'6:[function(require,module,exports){\n'
FIX = ANCHOR + b'var L = require(7); // Apple Klinika: bind the bundled Leaflet, not mutable window.L.\n'

def apply(directory):
    path = pathlib.Path(directory) / 'hungarian-pickup-points-for-woocommerce/assets/js/frontend.min.js'
    data = path.read_bytes()
    original = data.replace(FIX, ANCHOR)
    if hashlib.sha256(original).hexdigest() != ORIGINAL or original.count(ANCHOR) != 1:
        raise SystemExit('Unexpected vendor release/source; review compatibility before installing.')
    if data == original:
        path.write_bytes(original.replace(ANCHOR, FIX))
    return hashlib.sha256(path.read_bytes()).hexdigest()

if __name__ == '__main__':
    print('Leaflet compatibility asset SHA-256: ' + apply(sys.argv[1]))
