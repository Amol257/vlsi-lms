#!/usr/bin/env bash
# ==============================================================================
# VLSI LMS : Workstation HLS AES-128 Video Packaging Script
# Encrypts MP4 into segment files with a random 16-byte key and produces package.
# ==============================================================================

set -euo pipefail

if [ "$#" -lt 2 ]; then
    echo "Usage: $0 <input_video.mp4> <app_key_hex_32_bytes> [output_dir]"
    echo "Example: $0 lecture1.mp4 30e1799c8cb94460b6273182f6a3c351c8434102b5af47cba6aa79c069a7c357 ./packaged_lec1"
    exit 1
fi

INPUT_VIDEO="$1"
APP_KEY="$2"
OUTPUT_DIR="${3:-./hls_output}"

if [ ! -f "$INPUT_VIDEO" ]; then
    echo "Error: Input file '$INPUT_VIDEO' does not exist."
    exit 1
fi

if ! command -v ffmpeg &> /dev/null; then
    echo "Error: ffmpeg is required on your workstation to package videos."
    exit 1
fi

if ! command -v openssl &> /dev/null; then
    echo "Error: openssl is required on your workstation to generate encryption keys."
    exit 1
fi

mkdir -p "$OUTPUT_DIR"
cd "$OUTPUT_DIR"

echo "==> Generating 16-byte AES-128 encryption key..."
openssl rand 16 > enc.key

echo "==> Creating ffmpeg key info file..."
cat <<EOF > keyinfo.txt
enc.key
enc.key
EOF

echo "==> Transcoding and slicing video into encrypted 6-second HLS segments..."
ffmpeg -y -i "$INPUT_VIDEO" \
  -c:v libx264 -preset fast -crf 22 -profile:v high -level 4.1 \
  -c:a aac -b:a 128k -ar 44100 \
  -hls_time 6 \
  -hls_playlist_type vod \
  -hls_key_info_file keyinfo.txt \
  -hls_segment_filename "segment_%04d.ts" \
  index.m3u8

# Extract duration and file size
DURATION_SEC=$(ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "$INPUT_VIDEO" | cut -d. -f1)
TOTAL_SIZE=$(du -sb . | cut -f1)

echo "==> Encrypting raw key with LMS APP_KEY for database storage..."
IV_HEX=$(openssl rand -hex 16)
RAW_KEY_HEX=$(xxd -p -c 16 enc.key)
ENC_PAYLOAD=$(openssl enc -aes-256-cbc -K "$APP_KEY" -iv "$IV_HEX" -in enc.key | base64)

# Produce packaging metadata JSON
cat <<EOF > packaging_info.json
{
  "title": "$(basename "$INPUT_VIDEO")",
  "duration_sec": $DURATION_SEC,
  "size_bytes": $TOTAL_SIZE,
  "iv_hex": "$IV_HEX",
  "key_enc": "$ENC_PAYLOAD",
  "created_at": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
}
EOF

# Clean up raw key and keyinfo so plain keys are not left in public package
rm -f keyinfo.txt

echo "========================================================================"
echo "Packaging Complete!"
echo "Directory: $OUTPUT_DIR"
echo "Encrypted Key Payload (key_enc): $ENC_PAYLOAD"
echo "Now upload this directory to the LMS server under lms_storage/hls/<random_32_chars>/"
echo "========================================================================"
