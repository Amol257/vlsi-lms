<?php
/**
 * One-Time HLS Path Normalizer for Hostinger Linux
 * Fixes Windows backslash filenames into proper Linux directory hierarchy
 */

$hls_base = __DIR__ . '/lms_storage/hls';
$target_dir = $hls_base . '/d31b7a2dd5922df913a90b0bf2874e34';

if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

$count = 0;

// Scan inside hls_free_lecture_d31b folder
$source_dir = $hls_base . '/hls_free_lecture_d31b';
if (is_dir($source_dir)) {
    $files = scandir($source_dir);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $clean_name = basename(str_replace('\\', '/', $f));
        $src_file = $source_dir . '/' . $f;
        $dst_file = $target_dir . '/' . $clean_name;
        if (rename($src_file, $dst_file)) {
            $count++;
        }
    }
    @rmdir($source_dir);
}

// Also check if any backslash files are directly in hls/
if (is_dir($hls_base)) {
    $root_files = scandir($hls_base);
    foreach ($root_files as $f) {
        if (strpos($f, 'd31b7a2dd5922df913a90b0bf2874e34\\') !== false || strpos($f, 'd31b7a2dd5922df913a90b0bf2874e34/') !== false) {
            $clean_name = basename(str_replace('\\', '/', $f));
            $src_file = $hls_base . '/' . $f;
            $dst_file = $target_dir . '/' . $clean_name;
            if (rename($src_file, $dst_file)) {
                $count++;
            }
        }
    }
}

header('Content-Type: text/plain');
echo "SUCCESS: Moved and organized {$count} video segments into:\n";
echo "lms_storage/hls/d31b7a2dd5922df913a90b0bf2874e34/\n\n";
echo "Now open https://learn.vlsiinsights.com/student/watch.php?id=1 to play your lecture!";
