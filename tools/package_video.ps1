# ==============================================================================
# VLSI LMS : Workstation HLS AES-128 Video Packaging Script (PowerShell)
# Encrypts MP4 into segment files with a random 16-byte key and produces package.
# Does NOT use APP_KEY. Stores plain key in private manifest.json for server import.
# ==============================================================================

param (
    [Parameter(Mandatory=$true)]
    [string]$InputVideo,

    [string]$OutputDir = "./hls_output"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $InputVideo)) {
    Write-Error "Input file '$InputVideo' does not exist."
    exit 1
}

if (-not (Get-Command ffmpeg -ErrorAction SilentlyContinue)) {
    Write-Error "ffmpeg is required on your workstation to package videos."
    exit 1
}

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null
$resolvedOut = (Resolve-Path $OutputDir).Path
$inputFull = (Resolve-Path $InputVideo).Path

Push-Location $resolvedOut

Write-Host "==> Generating 16-byte AES-128 encryption key..."
$rawKey = New-Object byte[] 16
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($rawKey)
[System.IO.File]::WriteAllBytes("enc.key", $rawKey)

$keyHex = ($rawKey | ForEach-Object { $_.ToString("x2") }) -join ""

Write-Host "==> Creating ffmpeg keyinfo file..."
"enc.key`nenc.key" | Set-Content -Path "keyinfo.txt" -Encoding ASCII

Write-Host "==> Transcoding and slicing video into encrypted 6-second HLS segments..."
ffmpeg -y -i "$inputFull" `
  -c:v libx264 -preset fast -crf 22 -profile:v high -level 4.1 `
  -c:a aac -b:a 128k -ar 44100 `
  -hls_time 6 `
  -hls_playlist_type vod `
  -hls_key_info_file keyinfo.txt `
  -hls_segment_filename "segment_%04d.ts" `
  index.m3u8

# Extract duration and file size
$durationSec = 0
if (Get-Command ffprobe -ErrorAction SilentlyContinue) {
    try {
        $rawDur = ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "$inputFull"
        $durationSec = [int][Math]::Floor([double]$rawDur)
    } catch {
        $durationSec = 0
    }
}

$totalSize = 0
Get-ChildItem -File | ForEach-Object { $totalSize += $_.Length }

# Write private manifest.json
$manifest = @{
    title = [System.IO.Path]::GetFileName($inputFull)
    duration_sec = $durationSec
    size_bytes = $totalSize
    key_hex = $keyHex
    created_at = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")
}
$manifest | ConvertTo-Json | Set-Content -Path "manifest.json" -Encoding UTF8

# Remove plaintext key files
Remove-Item "enc.key", "keyinfo.txt" -Force -ErrorAction SilentlyContinue

Pop-Location

Write-Host "========================================================================"
Write-Host "Packaging Complete!"
Write-Host "Output Directory: $resolvedOut"
Write-Host "WARNING: manifest.json contains the raw encryption key. Once imported into"
Write-Host "the LMS database via admin/videos.php, delete manifest.json immediately."
Write-Host "Never upload enc.key or leave manifest.json in public directories."
Write-Host "========================================================================"
