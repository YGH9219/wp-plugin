$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$pluginFile = Join-Path $root 'personal-cta-blocks.php'
$pluginHeader = Get-Content -Raw $pluginFile
$versionMatch = [regex]::Match($pluginHeader, '(?m)^\s*\*\s*Version:\s*([^\r\n]+)')

if (-not $versionMatch.Success) {
	throw 'Could not find the plugin version.'
}

$version = $versionMatch.Groups[1].Value.Trim()
$slug = 'personal-cta-blocks'
$dist = Join-Path $root 'dist'
$archivePath = Join-Path $dist "$slug-$version.zip"

if (Test-Path $archivePath) {
	throw "Archive already exists: $archivePath. Increase the plugin version before building a release."
}

New-Item -ItemType Directory -Force $dist | Out-Null
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$files = @(
	Get-Item -LiteralPath $pluginFile
) + @(
	Get-ChildItem -LiteralPath (Join-Path $root 'blocks') -File -Recurse
)

$archive = [System.IO.Compression.ZipFile]::Open(
	$archivePath,
	[System.IO.Compression.ZipArchiveMode]::Create
)

try {
	foreach ($file in $files) {
		$relativePath = $file.FullName.Substring($root.Length).TrimStart('\', '/').Replace('\', '/')
		$entryPath = "$slug/$relativePath"
		[System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
			$archive,
			$file.FullName,
			$entryPath,
			[System.IO.Compression.CompressionLevel]::Optimal
		) | Out-Null
	}
}
finally {
	$archive.Dispose()
}

$check = [System.IO.Compression.ZipFile]::OpenRead($archivePath)
try {
	$entryNames = @($check.Entries | ForEach-Object FullName)
	if ($entryNames -contains "$slug/$slug.php" -and $entryNames -notmatch '\\') {
		Write-Output "Built $archivePath"
		return
	}

	throw 'The ZIP structure is not valid for a WordPress plugin.'
}
finally {
	$check.Dispose()
}
