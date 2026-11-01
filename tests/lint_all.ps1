$php = 'C:\Users\acer\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
$files = Get-ChildItem -Path 'c:\Users\acer\Desktop\Antigravity\WORK\VLSI LMS' -Filter *.php -Recurse
$failed = 0
$passed = 0
foreach ($f in $files) {
    $out = & $php -l $f.FullName 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host "FAIL: $($f.FullName)"
        Write-Host $out
        $failed++
    } else {
        $passed++
    }
}
Write-Host "LINT_SUMMARY: Passed: $passed, Failed: $failed"

