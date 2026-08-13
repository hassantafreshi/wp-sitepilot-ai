[CmdletBinding()]
param(
	[string] $SourceDirectory = (Split-Path -Parent $PSScriptRoot),
	[string] $OutputDirectory = (Join-Path (Split-Path -Parent $PSScriptRoot) 'dist')
)

$ErrorActionPreference = 'Stop'
$source = (Resolve-Path -LiteralPath $SourceDirectory).Path
$manifest = Join-Path $source 'sitepilot-ai.php'
if (-not (Test-Path -LiteralPath $manifest)) {
	throw "SitePilot plugin manifest was not found: $manifest"
}

$header = Get-Content -LiteralPath $manifest -Raw
if ($header -notmatch '(?m)^ \* Version: ([0-9.]+)$') {
	throw 'Unable to read the plugin version from the manifest.'
}
$version = $Matches[1]
New-Item -ItemType Directory -Force -Path $OutputDirectory | Out-Null

$poFile = Join-Path $source 'languages\sitepilot-ai-fa_IR.po'
$moFile = Join-Path $source 'languages\sitepilot-ai-fa_IR.mo'
$msgfmtCandidates = @()
if ($env:ProgramFiles) {
	$msgfmtCandidates += Join-Path $env:ProgramFiles 'Git\usr\bin\msgfmt.exe'
}
if (${env:ProgramFiles(x86)}) {
	$msgfmtCandidates += Join-Path ${env:ProgramFiles(x86)} 'Poedit\GettextTools\bin\msgfmt.exe'
}
$msgfmt = $msgfmtCandidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
if ( (Test-Path -LiteralPath $poFile) -and $msgfmt ) {
	& $msgfmt --check --output-file $moFile $poFile
	if ($LASTEXITCODE -ne 0) {
		throw 'Unable to compile the Persian translation file.'
	}
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$stage = Join-Path ([System.IO.Path]::GetTempPath()) "sitepilot-ai-build-$stamp"
$packageRoot = Join-Path $stage 'sitepilot-ai'
New-Item -ItemType Directory -Force -Path $packageRoot | Out-Null

$excludedDirectories = @('.git', '.github', 'vendor', 'node_modules', 'tests', 'docs', 'scripts', 'dist', '.phpstan.cache', '.phpunit.cache')
$excludedFiles = @('composer.json', 'composer.lock', 'phpcs.xml.dist', 'phpstan.neon', 'phpunit.xml', 'phpunit.xml.dist', 'MANUAL-TESTING.md', 'PHASE_1_RELEASE_READINESS_FA.md', 'RELEASE_NOTES.md', 'test_100_scenarios.php')

& robocopy $source $packageRoot /E /NFL /NDL /NJH /NJS /XD $excludedDirectories /XF $excludedFiles | Out-Null
if ($LASTEXITCODE -gt 7) {
	throw "robocopy failed with exit code $LASTEXITCODE."
}

$zip = Join-Path $OutputDirectory "sitepilot-ai-$version.zip"
Compress-Archive -Path $packageRoot -DestinationPath $zip -Force

Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::OpenRead($zip)
try {
	$entries = @($archive.Entries.FullName | ForEach-Object { $_ -replace '\\', '/' })
	foreach ($required in @('sitepilot-ai/sitepilot-ai.php', 'sitepilot-ai/readme.txt', 'sitepilot-ai/includes/Core/Plugin.php')) {
		if ($entries -notcontains $required) {
			throw "Release archive is missing required file: $required"
		}
	}
	$forbidden = $entries | Where-Object { $_ -match '(^|/)(vendor|node_modules|tests|docs|scripts|\.git)(/|$)' }
	if ($forbidden) {
		throw "Release archive contains development files: $($forbidden -join ', ')"
	}
}
finally {
	$archive.Dispose()
}

Get-Item -LiteralPath $zip | Select-Object FullName, Length, LastWriteTime
$global:LASTEXITCODE = 0
