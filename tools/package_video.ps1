# ==============================================================================
# VLSI LMS : Workstation HLS AES-128 Video Packaging Script (PowerShell)
# Encrypts MP4 into segment files with a random 16-byte key and produces package.
# ==============================================================================

param (
    [Parameter(Mandatory=$true)]
    [string]$InputVideo,

    [Parameter(Mandatory=$true)]
    [string]$AppKeyHex,

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

Write-Host "==> Encrypting raw key with LMS APP_KEY for database storage..."
$aes = [System.Security.Cryptography.Aes]::Create()
$aes.Mode = [System.Security.Cryptography.CipherMode]::CBC
$aes.Padding = [System.Security.Cryptography.PaddingMode]::PKCS7

# Normalize key bytes from 32-byte hex
$keyBytes = New-Object byte[] 32
$cleanKeyHex = $AppKeyHex.PadRight(64, '0').Substring(0, 64)
for ($i = 0; $i -lt 32; $i++) {
    $keyBytes[$i] = [Convert]::ToByte($cleanKeyHex.Substring($i * 2, 2), 16)
}
$aes.Key = $keyBytes
$aes.GenerateIV()

$encryptor = $aes.CreateEncryptor()
$cipherBytes = $encryptor.TransformFinalBlock($rawKey, 0, $rawKey.Length)

$combined = New-Object byte[] ($aes.IV.Length + $cipherBytes.Length)
[Buffer]::BlockCopy($aes.IV, 0, $combined, 0, $aes.IV.Length)
[Buffer]::BlockCopy($cipherBytes, 0, $combined, $aes.IV.Length, $cipherBytes.Length)
$keyEnc = [Convert]::ToBase64String($combined)

# Metadata JSON
$videoInfo = @{
    title = [System.IO.Path]::GetFileName($inputFull)
    key_enc = $keyEnc
    created_at = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")
}
$videoInfo | ConvertTo-Json | Set-Content -Path "packaging_info.json" -Encoding UTF8

Remove-Item "keyinfo.txt" -Force

Pop-Location

Write-Host "========================================================================"
Write-Host "Packaging Complete!"
Write-Host "Output Directory: $resolvedOut"
Write-Host "key_enc payload: $keyEnc"
Write-Host "========================================================================"
