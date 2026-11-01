$files = git -C 'c:\Users\acer\Desktop\Antigravity\WORK\VLSI LMS' ls-files
$found = 0
foreach ($f in $files) {
    $path = Join-Path 'c:\Users\acer\Desktop\Antigravity\WORK\VLSI LMS' $f
    if (Test-Path $path -PathType Leaf) {
        $content = Get-Content $path -Raw -Encoding UTF8
        if ($content -match '[\u2013\u2014]') {
            Write-Host "EM_DASH_FOUND: $f"
            $found++
        }
    }
}
Write-Host "TOTAL_EM_DASHES_FOUND: $found"

