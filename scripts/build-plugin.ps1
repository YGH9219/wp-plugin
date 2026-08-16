param(
	[switch]$Force
)

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
	if (-not $Force) {
		throw "Archive already exists: $archivePath. Increase the plugin version before building a release."
	}
	$resolvedArchive = (Resolve-Path -LiteralPath $archivePath).Path
	$resolvedDist = [System.IO.Path]::GetFullPath($dist)
	if ([System.IO.Path]::GetDirectoryName($resolvedArchive) -ne $resolvedDist) {
		throw "Refusing to replace an archive outside the dist directory: $resolvedArchive"
	}
	Remove-Item -LiteralPath $resolvedArchive -Force
}

New-Item -ItemType Directory -Force $dist | Out-Null
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$files = @(Get-Item -LiteralPath $pluginFile)
foreach ($runtimeDirectory in @('blocks', 'includes', 'assets')) {
	$path = Join-Path $root $runtimeDirectory
	if (-not (Test-Path -LiteralPath $path)) {
		throw "Missing runtime directory: $runtimeDirectory"
	}
	$files += @(Get-ChildItem -LiteralPath $path -File -Recurse)
}

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
	$requiredEntries = @(
		"$slug/$slug.php",
		"$slug/includes/threads-core.php",
		"$slug/includes/threads-openai.php",
		"$slug/includes/threads-meta.php",
		"$slug/includes/threads-daily.php",
		"$slug/includes/threads-admin.php",
		"$slug/includes/social-thumbnail.php",
		"$slug/assets/fonts/Pretendard-ExtraBold.otf",
		"$slug/assets/fonts/LICENSE-Pretendard.txt",
		"$slug/assets/threads-editor-panel.js",
		"$slug/assets/threads-editor-panel.css"
	)
	$missingEntries = @($requiredEntries | Where-Object { $entryNames -notcontains $_ })
	if ($missingEntries.Count -eq 0 -and $entryNames -notmatch '\\') {
		Write-Output "Built $archivePath"
		return
	}

	throw "The ZIP structure is not valid for a WordPress plugin. Missing: $($missingEntries -join ', ')"
}
finally {
	$check.Dispose()
}
