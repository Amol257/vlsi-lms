# Google Meet Webinar Runbook for VLSI Insights

This runbook specifies operational guidelines, instructor requirements, and session management protocols for hosting webinars on the VLSI LMS platform.

## Pre-Session Configuration (Host Instructor)

1. Meeting Generation:
   - Create the Google Meet link using the official VLSI Insights Google Workspace account.
   - Verify the URL format matches: `https://meet.google.com/xxx-yyyy-zzz`.
   - Never use personal or unverified Google accounts for company webinars.

2. Host Controls and Settings:
   - Meeting Access: Set to "Trusted" or "Open to anyone with link" to avoid entrance approval interruptions.
   - Quick Access: Keep enabled during the joining window.
   - Screen Sharing: Grant screen share permissions only to designated instructors and panelists. Disable attendee screen sharing to prevent interruptions.
   - Chat: Enable attendee chat for questions and technical queries. Disable attendee microphone permissions by default for groups exceeding 20 students.

3. Recording Guidelines:
   - Recording Storage: Ensure sufficient Google Drive storage is allocated prior to session commencement.
   - Cloud Recording: Start recording immediately after opening remarks and instructor introduction.
   - Compliance Notice: Inform all participants that the session is being recorded for archival within the LMS video portal.

## Session Lifecycle and Timing

1. Access Window:
   - LMS gated join access opens exactly 10 minutes prior to scheduled start time (`starts_at - 10 minutes`).
   - The link remains joinable up to 3 hours after start (`starts_at + 3 hours`).
   - Student session concurrency is verified at join time.

2. Presentation and Screen Sharing:
   - Instructors should utilize dual displays where possible (one for presentation slides or EDA tool terminals, one for Google Meet attendee chat).
   - Display scaling should be set to 100% or 125% to ensure high-definition clarity for schematic diagrams and code testbenches.

3. Post-Session Archive:
   - Export Google Meet cloud recording (.mp4).
   - Package via the local HLS pipeline (`tools/package_video.sh` or `tools/package_video.ps1`).
   - Upload the encrypted HLS package to the LMS media directory for student on-demand playback.
