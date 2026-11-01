# VLSI LMS Video Pipeline and Encrypted HLS Workflow

This document outlines the video packaging, encryption, key management, and delivery architecture for the VLSI Insights LMS.

## Architecture and Threat Model

### Honest Assessment of Security Limits
1. Not Hardware DRM: This implementation uses HLS streaming encrypted with AES-128 and gated key delivery. It is not hardware Digital Rights Management (such as Apple FairPlay or Google Widevine).
2. What it stops:
   - Direct video file downloading (e.g. through "Save Video As" or direct URL inspection).
   - Link sharing: video playlists and decryption keys require active authentication and timed HMAC signatures.
   - Unauthorized access: non-enrolled users and concurrent second-device logins are blocked.
3. What it cannot stop:
   - Screen recording software (OBS, QuickTime, Nvidia ShadowPlay).
   - Physical camera capture of computer displays.
   - Dedicated reverse engineering of decrypted frames in system memory.
4. Active Deterrence:
   - Dynamic user watermark containing student email, IP address, and timestamp constantly moves across the video viewport to deter redistribution.
   - Single active session heartbeat terminates accounts if credentials are shared.

---

## Step-by-Step Workstation Packaging

Because Hostinger shared web hosting does not offer CLI `ffmpeg` or `exec()` execution, all transcoding and slicing is performed locally on the instructor or administrator workstation before uploading. The workstation packaging script does not require the server `APP_KEY`.

### Using Bash (Linux / macOS / WSL)
1. Ensure `ffmpeg` and `openssl` are installed.
2. Run the packaging utility:
   ```bash
   ./tools/package_video.sh input_lecture.mp4 ./output_package
   ```
3. The script:
   - Slices the video into 6-second `.ts` segments.
   - Generates a random 16-byte AES-128 key.
   - Outputs `index.m3u8`, `segment_*.ts`, and a private `manifest.json` containing `key_hex`.
   - Deletes `enc.key` and `keyinfo.txt` after packaging so no plaintext keys are uploaded with segments.

### Using PowerShell (Windows)
1. Ensure `ffmpeg` is accessible in PowerShell PATH.
2. Run the packaging script:
   ```powershell
   .\tools\package_video.ps1 -InputVideo "lecture.mp4" -OutputDir ".\output_package"
   ```

---

## Server Ingestion and Key Gating

### Ingestion Flow
1. Upload the packaged directory via SFTP to `media/incoming/<package_name>`.
2. In the Admin Portal, navigate to **Video Library** (`/admin/videos.php`) and click **Scan and Ingest**.
3. The server reads `key_hex` from `manifest.json` and encrypts it using Libsodium (`sodium_crypto_secretbox`) with the server `APP_KEY` as `base64(nonce + ciphertext)` stored in `videos.key_enc`.
4. The server moves the package directory into `lms_storage/hls/<random_32_chars>/` (protected by `.htaccess`), purges `manifest.json`, and records the video in the database.

### Delivery and Key Exchange
1. Client requests playlist: `/api/video_playlist.php?id=N`.
2. Server verifies enrollment, generates a timed HMAC token, rewrites the playlist key URI to `/api/video_key.php?id=N&t=TOKEN`, and proxies segment URLs.
3. Client requests key: `/api/video_key.php?id=N&t=TOKEN`.
4. Server validates HMAC token, verifies single session concurrency, checks rate limits, decrypts `key_enc` via `sodium_crypto_secretbox_open`, and returns the 16-byte raw AES key. The key is never logged.
5. The player in `student/watch.php` decrypts segments on-the-fly inside the browser using self-hosted `hls.js`.

---

## Segment Streaming Modes and Shared Hosting Load

The configuration key `video.segments_mode` supports two operating modes:

### Mode 1: `php` (Default Tokenized Streaming Proxy)
- Segments are streamed through `/api/video_segment.php`.
- Every segment request validates the timed HMAC token and confirms active session concurrency.
- `session_write_close()` is executed immediately after authentication to release session locks and prevent serial request stalls.
- HTTP Range requests (206 Partial Content) are supported for precise seeking.
- Output buffering and gzip compression are disabled to stream data in 64 KB chunks.

### Mode 2: `static` (High-Concurrency Direct Serving)
- Playlist rewrites segment URLs to direct web paths `/media/hls/<hls_dir>/segment_N.ts`.
- Segments are served directly by Apache without invoking PHP workers.
- Trade-off: Segment requests bypass per-chunk PHP auth checks, but all segments remain AES-128 encrypted and cannot be decrypted without the gated key from `/api/video_key.php`.

### Shared Hosting Capacity and Load Testing
- Hostinger shared hosting environments enforce concurrent PHP process limits (typically 20 to 30 simultaneous workers).
- In `php` mode, multiple students watching 6-second segments simultaneously can saturate the PHP worker pool.
- A load test with 10 simultaneous students is required before running large-scale live cohorts.
- If worker saturation or HTTP 503/508 errors occur during high concurrency, switch `video.segments_mode` to `static` in `config.local.php`.
