#!/usr/bin/env bash
# ==============================================================================
# VLSI LMS : Workstation HLS AES-128 Video Packaging Script
# Encrypts MP4 into segment files with a random 16-byte key and produces package.
# Does NOT use APP_KEY. Stores plain key in private manifest.json for server import.
# ==============================================================================

set -euo pipefail

if [ "$#" -lt 1 ]; then
    echo "Usage: $0 <input_video.mp4> [output_dir]"
    echo "Example: $0 lecture1.mp4 ./packaged_lec1"
    exit 1
fi

INPUT_VIDEO="$1"
OUTPUT_DIR="${2:-./hls_output}"

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
DURATION_SEC=$(ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "$INPUT_VIDEO" | cut -d. -f1 || echo 0)
TOTAL_SIZE=$(du -sb . | cut -f1 || echo 0)

# Convert raw key to hex string
KEY_HEX=$(xxd -p -c 16 enc.key 2>/dev/null || od -An -tx1 enc.key | tr -d ' \n')

# Produce private manifest.json for LMS server ingest
cat <<EOF > manifest.json
{
  "title": "$(basename "$INPUT_VIDEO")",
  "duration_sec": $DURATION_SEC,
  "size_bytes": $TOTAL_SIZE,
  "key_hex": "$KEY_HEX",
  "created_at": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
}
EOF

# Clean up raw enc.key and keyinfo.txt so plain keys are never uploaded with segments
rm -f enc.key keyinfo.txt

echo "========================================================================"
echo "Packaging Complete!"
echo "Directory: $OUTPUT_DIR"
echo "WARNING: manifest.json contains the raw encryption key. Once imported into"
echo "the LMS database via admin/videos.php, delete manifest.json immediately."
echo "Never upload enc.key or leave manifest.json in public directories."
echo "========================================================================"
