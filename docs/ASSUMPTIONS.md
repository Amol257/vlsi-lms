# Architectural Decisions and Assumptions

This document records architectural decisions, environment assumptions, and security trade-offs for the VLSI Insights LMS portal (learn.vlsiinsights.com).

## Hosting and Infrastructure
1. Hostinger Web Hosting: The production environment runs PHP 8.2+ with MySQL 8 on Hostinger web hosting with public_html/learn as the document root.
2. Server Capabilities: Standard shared hosting constraints apply. CLI ffmpeg and PHP exec() functions are assumed to be unavailable or restricted. All HLS video packaging is performed on the administrator workstation using provided packaging scripts.
3. Disk Storage: Videos and documents are saved on local disk storage outside or protected within the document root, never inside MySQL blobs.
4. Netlify Static Preview: The repository is dedicated exclusively to the Hostinger PHP and MySQL deployment. Static preview configurations such as netlify.toml have been removed to prevent deployment conflicts.

## Authentication and Sessions
1. Supabase Auth Integration: The LMS utilizes the existing Supabase project (project ref: riipijrgucqigndcjwre) shared with vlsiinsights.com.
2. Separate Sessions: Same Supabase accounts as vlsiinsights.com, separate sessions per site. There is no cross-site cookie sharing or cross-domain single sign-on mechanism.
3. Server-Side Session Verification: Client-side access tokens are posted to /auth/session.php and strictly validated against Supabase Auth endpoint (GET /auth/v1/user). Unconfirmed emails or invalid tokens are rejected.
4. Single Device Session Lock: Students are restricted to one concurrent active session. An active heartbeat mechanism terminates displaced sessions within 150 seconds. Faculty and administrator accounts are exempt from this restriction.

## Video Protection
1. HLS AES-128 Encryption: Video content is delivered using AES-128 encrypted HLS streams. This prevents casual downloading and hotlinking.
2. Honest Threat Assessment: This implementation is not hardware DRM (Widevine, FairPlay). Screen recording or physical screen capture cannot be prevented. Dynamic watermarking with student identity and single session enforcement act as active deterrence.
