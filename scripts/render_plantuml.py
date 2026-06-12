import zlib, urllib.request, sys, os

puml_path = os.path.join(os.path.dirname(__file__), '..', 'docs', 'class-diagram.puml')
png_path = os.path.join(os.path.dirname(__file__), '..', 'docs', 'class-diagram.png')
svg_path = os.path.join(os.path.dirname(__file__), '..', 'docs', 'class-diagram.svg')

puml_path = os.path.abspath(puml_path)
png_path = os.path.abspath(png_path)
svg_path = os.path.abspath(svg_path)

with open(puml_path, 'r', encoding='utf-8') as f:
    text = f.read()

def encode_plantuml(s: str) -> str:
    data = zlib.compress(s.encode('utf-8'))
    data = data[2:-4]
    return _encode64(data)

def _encode64(data: bytes) -> str:
    res = []
    def encode6bit(b):
        if b < 10:
            return chr(48 + b)
        b -= 10
        if b < 26:
            return chr(65 + b)
        b -= 26
        if b < 26:
            return chr(97 + b)
        b -= 26
        if b == 0:
            return '-'
        if b == 1:
            return '_'
        return '?'

    def append3bytes(b1, b2, b3):
        c1 = (b1 >> 2) & 0x3F
        c2 = ((b1 & 0x3) << 4) | ((b2 >> 4) & 0xF)
        c3 = ((b2 & 0xF) << 2) | ((b3 >> 6) & 0x3)
        c4 = b3 & 0x3F
        return encode6bit(c1) + encode6bit(c2) + encode6bit(c3) + encode6bit(c4)

    i = 0
    n = len(data)
    while i < n:
        b1 = data[i]
        b2 = data[i+1] if i+1 < n else 0
        b3 = data[i+2] if i+2 < n else 0
        res.append(append3bytes(b1, b2, b3))
        i += 3
    return ''.join(res)

encoded = encode_plantuml(text)

base = 'http://www.plantuml.com/plantuml'
png_url = base + '/png/' + encoded
svg_url = base + '/svg/' + encoded

print('Fetching PNG from', png_url)
with urllib.request.urlopen(png_url, timeout=30) as resp:
    data = resp.read()
    with open(png_path, 'wb') as out:
        out.write(data)
print('Saved PNG to', png_path)

print('Fetching SVG from', svg_url)
with urllib.request.urlopen(svg_url, timeout=30) as resp:
    data = resp.read()
    with open(svg_path, 'wb') as out:
        out.write(data)
print('Saved SVG to', svg_path)

print('Done')
