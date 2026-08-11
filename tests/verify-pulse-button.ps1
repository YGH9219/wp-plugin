$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$pluginHeader = Get-Content -Raw (Join-Path $root 'personal-cta-blocks.php')
$versionMatch = [regex]::Match($pluginHeader, '(?m)^\s*\*\s*Version:\s*([^\r\n]+)')

if (-not $versionMatch.Success) { throw 'Could not find the plugin version.' }

$version = $versionMatch.Groups[1].Value.Trim()
$block = Get-Content -Raw (Join-Path $root 'blocks\pulse-button\block.json') | ConvertFrom-Json

if ($block.name -ne 'personal-cta-blocks/pulse-button') { throw 'Unexpected block name.' }
if ($block.version -ne $version) { throw 'Block and plugin versions do not match.' }
if ($block.keywords -notcontains 'ㅂㅌ') { throw 'The /ㅂㅌ inserter keyword is missing.' }
if (-not $block.attributes.text -or -not $block.attributes.url) { throw 'Text or URL attributes are missing.' }

@(
	'personal-cta-blocks.php',
	'blocks\pulse-button\editor.js',
	'blocks\pulse-button\render.php',
	'blocks\pulse-button\style.css',
	'includes\threads-core.php',
	'includes\threads-openai.php',
	'includes\threads-meta.php',
	'includes\threads-admin.php',
	'assets\threads-admin.js',
	'assets\threads-admin.css'
) |
	ForEach-Object {
		if (-not (Test-Path (Join-Path $root $_))) { throw "Missing required file: $_" }
	}

$archivePath = Join-Path $root "dist\personal-cta-blocks-$version.zip"
if (Test-Path $archivePath) {
	Add-Type -AssemblyName System.IO.Compression
	Add-Type -AssemblyName System.IO.Compression.FileSystem
	$archive = [System.IO.Compression.ZipFile]::OpenRead($archivePath)
	try {
		$entries = @($archive.Entries | ForEach-Object FullName)
		$requiredEntries = @(
			'personal-cta-blocks/personal-cta-blocks.php',
			'personal-cta-blocks/includes/threads-core.php',
			'personal-cta-blocks/includes/threads-openai.php',
			'personal-cta-blocks/includes/threads-meta.php',
			'personal-cta-blocks/includes/threads-admin.php',
			'personal-cta-blocks/assets/threads-admin.js',
			'personal-cta-blocks/assets/threads-admin.css'
		)
		foreach ($requiredEntry in $requiredEntries) {
			if ($entries -notcontains $requiredEntry) { throw "Plugin ZIP is missing $requiredEntry." }
		}
		if ($entries -match '\\') { throw 'Plugin ZIP contains Windows-style paths.' }
		if ($entries -match '^personal-cta-blocks/(tests|scripts|dist)/') { throw 'Plugin ZIP contains development files.' }
	}
	finally {
		$archive.Dispose()
	}
}

Write-Output 'Pulse CTA block metadata is valid.'
