Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zipPath = Join-Path $PSScriptRoot '..\dist\ai-site-assistant-autopilot-1.0.4.zip'
$zip = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
foreach ($entry in $zip.Entries) {
    if ($entry.FullName -match 'settings\.js|connections\.js|OpenAiCompatible|Anthropic|CopilotController') {
        Write-Host "$($entry.FullName)  ($($entry.Length) bytes)"
    }
}
$zip.Dispose()
