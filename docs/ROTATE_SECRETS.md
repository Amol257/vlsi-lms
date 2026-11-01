# Secret Rotation Guide for VLSI LMS

This document specifies the exact steps required to rotate application secrets, database credentials, and webhook tokens across the VLSI LMS infrastructure.

## 1. Application Key Rotation (`APP_KEY`)

The `APP_KEY` is a 32-byte (64-character hexadecimal) cryptographic key used to encrypt video keys on disk and sign HMAC tokens.

### Generation
Generate a fresh cryptographic 32-byte hexadecimal key on your terminal:
```bash
openssl rand -hex 32
```

### Impact Assessment
Rotating `APP_KEY` produces the following immediate effects:
1. Invalidation of active video playback tokens: Students watching videos will receive a token mismatch upon subsequent key requests.
2. Invalidation of encrypted video keys: All existing `key_enc` records in the `videos` table were encrypted with the prior key and can no longer be decrypted by `lms_decrypt_video_key()`.
3. Ingestion Requirement: After rotating `APP_KEY`, all video packages must be re-imported through the server ingestion flow so that their keys are encrypted with the new `APP_KEY`.

---

## 2. Webhook Secrets Rotation

### Enrollment Webhook Secret (`ENROLLMENT_WEBHOOK_SECRET`)
1. Generate a new 32-byte random secret:
   ```bash
   openssl rand -hex 32
   ```
2. Update `config.local.php` under `enrollment.webhook_secret`.
3. Update the matching webhook secret configured in the main website repository (`vlsiinsights.com`).

### Razorpay Webhook Secret (`webhook_secret`)
1. Generate or copy a new webhook secret from the **Razorpay Dashboard** -> **Settings** -> **Webhooks**.
2. Update `config.local.php` under `razorpay.webhook_secret`.
3. Update the webhook endpoint secret in Razorpay Dashboard for `https://learn.vlsiinsights.com/api/razorpay_webhook.php`.

---

## 3. Database Credentials and Seeded Passwords

1. Hostinger MySQL Password:
   - Navigate to Hostinger hPanel -> **Databases** -> **MySQL Databases**.
   - Change the database user password.
   - Update `config.local.php` under `db.pass`.
2. Seeded Accounts:
   - Previously seeded demo accounts (`admin@lms.local`, `teacher@vlsiinsights.com`, `student@example.com`) must be deleted or permanently suspended in the live database:
     ```sql
     UPDATE lms_users SET status = 'blocked' WHERE email IN ('admin@lms.local', 'teacher@vlsiinsights.com', 'student@example.com');
     ```

---

## 4. Git History Sanitization Commands

Any secret committed in earlier Git commits must be treated as permanently compromised. To purge the legacy key from historical commits before making the repository public:

### Option A: Using git-filter-repo (Recommended)
```bash
# 1. Create a clean mirror clone
git clone --mirror https://github.com/Amol257/vlsi-lms.git lms-cleanup
cd lms-cleanup

# 2. Replace the exposed key string across all commit history
git filter-repo --replace-text <(echo "30e1799c8cb94460b6273182f6a3c351c8434102b5af47cba6aa79c069a7c357==><64_HEX_CHARS_GENERATED_BY_ADMIN>")

# 3. Force push the rewritten history back to origin
git push --force --all origin
git push --force --tags origin
```

### Option B: Using BFG Repo-Cleaner
```bash
# 1. Place the leaked key in a file named passwords.txt
echo "30e1799c8cb94460b6273182f6a3c351c8434102b5af47cba6aa79c069a7c357" > passwords.txt

# 2. Run BFG on a bare or mirror clone
java -jar bfg.jar --replace-text passwords.txt lms-cleanup.git

# 3. Reflog expiration and force push
cd lms-cleanup.git
git reflog expire --expire=now --all && git gc --prune=now --aggressive
git push --force --all origin
```
