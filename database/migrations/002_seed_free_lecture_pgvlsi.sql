/* Seed Free Lecture: 13)PGVLSI_JUNE_VER 30- Oct -26.mp4 */
/* Encrypted AES-128 HLS package with Libsodium-encrypted key at rest */

INSERT INTO `videos` (
  `program_id`,
  `module_id`,
  `title`,
  `description`,
  `is_trial`,
  `status`,
  `uploaded_by`,
  `hls_dir`,
  `key_enc`,
  `duration_sec`,
  `size_bytes`,
  `created_at`
)
SELECT
  COALESCE((SELECT `id` FROM `programs` WHERE `id` = 2 LIMIT 1), (SELECT `id` FROM `programs` ORDER BY `id` ASC LIMIT 1)),
  NULL,
  'PGVLSI June Verification: Lecture 13 (Free Preview)',
  'Free preview lecture from the PGVLSI June Verification batch (30 Oct session). Streamed via AES-128 encrypted HLS with timed HMAC key gating, single-session protection, and dynamic watermarking.',
  1,
  'approved',
  COALESCE((SELECT `id` FROM `lms_users` WHERE `role` = 'admin' ORDER BY `id` ASC LIMIT 1), 1),
  'd31b7a2dd5922df913a90b0bf2874e34',
  '0yPajjPGjvhY4LH4vQDRmkhwZy0480h3xzc3M/+59qdLY1Rw+xhRGT3Q3OZclAJuGUL6HerMBag=',
  1851,
  241334407,
  NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `videos` WHERE `hls_dir` = 'd31b7a2dd5922df913a90b0bf2874e34'
);

