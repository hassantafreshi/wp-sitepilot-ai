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
# The MO that ships must be the one QA ran against, and scripts/compile-mo.php is
# its only writer (WordPress' own POMO classes). So the PO is validated into a
# throwaway file here instead of overwriting languages\*.mo: compiling with
# msgfmt produced a different, hash-tabled MO and silently changed the worktree.
if ( (Test-Path -LiteralPath $poFile) -and $msgfmt ) {
	$moCheck = Join-Path ([System.IO.Path]::GetTempPath()) ('aisaautopilot-mo-check-' + [System.Guid]::NewGuid().ToString('N') + '.mo')
	try {
		& $msgfmt --check --output-file $moCheck $poFile
		if ($LASTEXITCODE -ne 0) {
			throw 'The Persian translation file does not compile.'
		}
	}
	finally {
		if (Test-Path -LiteralPath $moCheck) { Remove-Item -LiteralPath $moCheck -Force }
	}
	if (-not (Test-Path -LiteralPath $moFile)) {
		throw "The compiled translation is missing: $moFile. Run scripts/compile-mo.php."
	}
	if ((Get-Item -LiteralPath $moFile).LastWriteTimeUtc -lt (Get-Item -LiteralPath $poFile).LastWriteTimeUtc) {
		throw "The compiled translation is older than the PO file. Run scripts/compile-mo.php before building."
	}
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$stage = Join-Path ([System.IO.Path]::GetTempPath()) "ai-site-assistant-autopilot-build-$stamp"
$packageRoot = Join-Path $stage 'ai-site-assistant-autopilot'
New-Item -ItemType Directory -Force -Path $packageRoot | Out-Null

# 'test-artifacts' holds QA screenshots and gate logs written next to the source
# by the agent tooling; '.claude' holds agent configuration. Neither belongs in a
# release, and both are excluded here rather than deleted from the worktree.
$excludedDirectories = @('.git', '.github', '.claude', 'vendor', 'node_modules', 'tests', 'docs', 'scripts', 'dist', 'test-artifacts', '.phpstan.cache', '.phpunit.cache')
# Plugin Check rejects hidden files and any markdown in the plugin root other
# than the ones WordPress.org expects, so those are excluded here too.
# 'handoff.md' is the session state file kept in the plugin root; without it here
# the root allow-list below stops the build.
$excludedFiles = @('composer.json', 'composer.lock', 'phpcs.xml.dist', 'phpstan.neon', 'phpunit.xml', 'phpunit.xml.dist', 'handoff.md', 'MANUAL-TESTING.md', 'PHASE_1_RELEASE_READINESS_FA.md', 'RELEASE_NOTES.md', 'README.md', 'SECURITY_HARDENING_1.0.3_FA.md', 'test_100_scenarios.php', '.gitignore', '.gitattributes', '.editorconfig')

& robocopy $source $packageRoot /E /NFL /NDL /NJH /NJS /XD $excludedDirectories /XF $excludedFiles | Out-Null
if ($LASTEXITCODE -gt 7) {
	throw "robocopy failed with exit code $LASTEXITCODE."
}

$zip = Join-Path $OutputDirectory "ai-site-assistant-autopilot-$version.zip"
# The ZIP spec requires '/' as the path separator. Both Compress-Archive and
# ZipFile::CreateFromDirectory on Windows PowerShell 5.1 write '\' instead;
# Windows unzip tools tolerate it, but on WordPress.org (Linux) each entry then
# extracts as a single flat file with literal backslashes in its name and the
# plugin will not install. So each entry name is set explicitly below.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
if (Test-Path -LiteralPath $zip) { Remove-Item -LiteralPath $zip -Force }

$archiveOut = [System.IO.Compression.ZipFile]::Open($zip, [System.IO.Compression.ZipArchiveMode]::Create)
try {
	$stageFull = (Resolve-Path -LiteralPath $stage).Path.TrimEnd([char]92) + [char]92
	foreach ($file in Get-ChildItem -LiteralPath $stage -Recurse -File | Sort-Object FullName) {
		$entryName = $file.FullName.Substring($stageFull.Length).Replace([char]92, '/')
		[System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
			$archiveOut,
			$file.FullName,
			$entryName,
			[System.IO.Compression.CompressionLevel]::Optimal
		) | Out-Null
	}
}
finally {
	$archiveOut.Dispose()
}
$archive = [System.IO.Compression.ZipFile]::OpenRead($zip)
try {
	$rawEntries = @($archive.Entries.FullName)
	$entries = @($rawEntries | ForEach-Object { $_ -replace '\\', '/' })
	foreach ($required in @('ai-site-assistant-autopilot/ai-site-assistant-autopilot.php', 'ai-site-assistant-autopilot/readme.txt', 'ai-site-assistant-autopilot/includes/Core/Plugin.php')) {
		if ($entries -notcontains $required) {
			throw "Release archive is missing required file: $required"
		}
	}
	# The ZIP spec requires '/'. Backslash entries install as one flat file with
	# literal backslashes in the name on any non-Windows host.
	$backslashed = $rawEntries | Where-Object { $_.Contains([char]92) }
	if ($backslashed) {
		throw "Release archive uses backslash path separators: $($backslashed[0])"
	}
	# The exclusion lists above are deny-lists, so a stray file dropped in the
	# plugin root would ship silently. WordPress.org rejects unexpected developer
	# scripts, so the root is an allow-list instead.
	$allowedRoot = @('ai-site-assistant-autopilot.php', 'uninstall.php', 'readme.txt')
	$unexpected = $entries | Where-Object {
		$parts = $_.Split('/')
		($parts.Count -eq 2) -and ($allowedRoot -notcontains $parts[1])
	}
	if ($unexpected) {
		throw "Release archive has unexpected files in the plugin root: $($unexpected -join ', ')"
	}

	$forbidden = $entries | Where-Object { $_ -match '(^|/)(vendor|node_modules|tests|docs|scripts|dist|test-artifacts|\.git|\.github|\.claude|\.phpstan\.cache|\.phpunit\.cache)(/|$)' }
	if ($forbidden) {
		throw "Release archive contains development files: $($forbidden -join ', ')"
	}
}
finally {
	$archive.Dispose()
}

Get-Item -LiteralPath $zip | Select-Object FullName, Length, LastWriteTime
$global:LASTEXITCODE = 0
