$pumlPath = Join-Path $PSScriptRoot '..\docs\usecases.puml' | Resolve-Path
$pngPath = Join-Path $PSScriptRoot '..\docs\usecases.png'
$svgPath = Join-Path $PSScriptRoot '..\docs\usecases.svg'

$text = Get-Content -Path $pumlPath -Raw -Encoding UTF8
$bytes = [System.Text.Encoding]::UTF8.GetBytes($text)

$ms = New-Object System.IO.MemoryStream
$ds = New-Object System.IO.Compression.DeflateStream($ms, [System.IO.Compression.CompressionMode]::Compress, $true)
$ds.Write($bytes, 0, $bytes.Length)
$ds.Close()
$compressed = $ms.ToArray()
$ms.Close()

function Encode6Bit($b) {
    if ($b -lt 10) { return [char](48 + $b) }
    $b -= 10
    if ($b -lt 26) { return [char](65 + $b) }
    $b -= 26
    if ($b -lt 26) { return [char](97 + $b) }
    $b -= 26
    if ($b -eq 0) { return '-' }
    if ($b -eq 1) { return '_' }
    return '?'
}

function Append3Bytes($b1, $b2, $b3) {
    $c1 = ($b1 -shr 2) -band 0x3F
    $c2 = ((($b1 -band 0x3) -shl 4) -bor (($b2 -shr 4) -band 0xF)) -band 0x3F
    $c3 = ((($b2 -band 0xF) -shl 2) -bor (($b3 -shr 6) -band 0x3)) -band 0x3F
    $c4 = $b3 -band 0x3F
    return (Encode6Bit $c1) + (Encode6Bit $c2) + (Encode6Bit $c3) + (Encode6Bit $c4)
}

$sb = New-Object System.Text.StringBuilder
for ($i = 0; $i -lt $compressed.Length; $i += 3) {
    $b1 = $compressed[$i]
    $b2 = 0
    $b3 = 0
    if ($i + 1 -lt $compressed.Length) { $b2 = $compressed[$i+1] }
    if ($i + 2 -lt $compressed.Length) { $b3 = $compressed[$i+2] }
    $sb.Append((Append3Bytes $b1 $b2 $b3)) | Out-Null
}
$encoded = $sb.ToString()

$base = 'http://www.plantuml.com/plantuml'
$pngUrl = $base + '/png/' + $encoded
$svgUrl = $base + '/svg/' + $encoded

Write-Host "Fetching PNG from $pngUrl"
try {
    Invoke-WebRequest -Uri $pngUrl -UseBasicParsing -OutFile $pngPath -TimeoutSec 30
    Write-Host "Saved PNG to $pngPath"
} catch {
    Write-Error "Failed to fetch PNG: $_"
    exit 1
}

Write-Host "Fetching SVG from $svgUrl"
try {
    Invoke-WebRequest -Uri $svgUrl -UseBasicParsing -OutFile $svgPath -TimeoutSec 30
    Write-Host "Saved SVG to $svgPath"
} catch {
    Write-Error "Failed to fetch SVG: $_"
    exit 1
}

Write-Host 'Done'
