[CmdletBinding()]
param(
	[string] $SourceDirectory = '',
	[string] $OutputDirectory = ''
)

$ErrorActionPreference = 'Stop'

# Resolved here rather than as a parameter default: in Windows PowerShell 5.1 a
# param default is evaluated in the caller's scope, where $PSScriptRoot can be
# empty, and the script then fails before it does anything.
$pluginRoot = Split-Path -Parent $PSCommandPath
$pluginRoot = Split-Path -Parent $pluginRoot
if ([string]::IsNullOrWhiteSpace($SourceDirectory)) { $SourceDirectory = $pluginRoot }
if ([string]::IsNullOrWhiteSpace($OutputDirectory)) { $OutputDirectory = Join-Path $pluginRoot 'dist' }

$source = (Resolve-Path -LiteralPath $SourceDirectory).Path
$manifest = Join-Path $source 'ai-site-assistant-autopilot.php'
if (-not (Test-Path -LiteralPath $manifest)) {
	throw "AISAAutoPilot plugin manifest was not found: $manifest"
}

$header = Get-Content -LiteralPath $manifest -Raw
if ($header -notmatch '(?m)^ \* Version: ([0-9.]+)$') {
	throw 'Unable to read the plugin version from the manifest.'
}
$version = $Matches[1]
New-Item -ItemType Directory -Force -Path $OutputDirectory | Out-Null

$poFile = Join-Path $source 'languages\ai-site-assistant-autopilot-fa_IR.po'
$moFile = Join-Path $source 'languages\ai-site-assistant-autopilot-fa_IR.mo'
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
$stage = Join-Path ([System.IO.Path]::GetTempPath()) "ai-site-assistant-autopilot-build-$stamp"
$packageRoot = Join-Path $stage 'ai-site-assistant-autopilot'
New-Item -ItemType Directory -Force -Path $packageRoot | Out-Null

$excludedDirectories = @('.git', '.github', 'vendor', 'node_modules', 'tests', 'docs', 'scripts', 'dist', '.phpstan.cache', '.phpunit.cache')
# Plugin Check rejects hidden files and any markdown in the plugin root other
# than the ones WordPress.org expects, so those are excluded here too.
$excludedFiles = @('composer.json', 'composer.lock', 'phpcs.xml.dist', 'phpstan.neon', 'phpunit.xml', 'phpunit.xml.dist', 'MANUAL-TESTING.md', 'PHASE_1_RELEASE_READINESS_FA.md', 'RELEASE_NOTES.md', 'README.md', 'SECURITY_HARDENING_1.0.3_FA.md', 'test_100_scenarios.php', '.gitignore', '.gitattributes', '.editorconfig')

& robocopy $source $packageRoot /E /NFL /NDL /NJH /NJS /XD $excludedDirectories /XF $excludedFiles | Out-Null
if ($LASTEXITCODE -gt 7) {
	throw "robocopy failed with exit code $LASTEXITCODE."
}

$zip = Join-Path $OutputDirectory "ai-site-assistant-autopilot-$version.zip"
Compress-Archive -Path $packageRoot -DestinationPath $zip -Force

Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::OpenRead($zip)
try {
	$entries = @($archive.Entries.FullName | ForEach-Object { $_ -replace '\\', '/' })
	foreach ($required in @('ai-site-assistant-autopilot/ai-site-assistant-autopilot.php', 'ai-site-assistant-autopilot/readme.txt', 'ai-site-assistant-autopilot/includes/Core/Plugin.php')) {
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
