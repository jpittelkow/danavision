#!/usr/bin/env pwsh
# Quick release script - commits all changes, bumps version, tags, and pushes
# Usage: ./scripts/push.ps1 [patch|minor|major|<version>] [commit-message]
# Example: ./scripts/push.ps1 patch "feat: add new feature"

param(
    [Parameter(Position=0)]
    [string]$VersionBump = "patch",
    
    [Parameter(Position=1)]
    [string]$CommitMessage = ""
)

$ErrorActionPreference = "Stop"

# Get script directory
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RootDir = Split-Path -Parent $ScriptDir
$VersionFile = Join-Path $RootDir "VERSION"
$ChangelogFile = Join-Path $RootDir "CHANGELOG.md"
$PackageJson = Join-Path (Join-Path $RootDir "frontend") "package.json"
$SwJs = Join-Path (Join-Path (Join-Path $RootDir "frontend") "public") "sw.js"

# Check if we're in a git repository
if (-not (Test-Path (Join-Path $RootDir ".git"))) {
    Write-Error "Not in a git repository. Run this script from the project root."
    exit 1
}

# Check current branch
$CurrentBranch = git rev-parse --abbrev-ref HEAD
if ($CurrentBranch -eq "HEAD") {
    Write-Error "You are in detached HEAD state. Check out a branch before releasing."
    exit 1
}
if ($CurrentBranch -ne "master") {
    Write-Warning "You are on branch '$CurrentBranch', not 'master'. Continue anyway? (y/N)"
    $Response = Read-Host
    if ($Response -ne "y" -and $Response -ne "Y") {
        Write-Host "Aborted." -ForegroundColor Yellow
        exit 0
    }
}

# Pull remote changes to avoid diverging from GitHub Actions sync commits
Write-Host "Pulling latest from origin/$CurrentBranch..." -ForegroundColor Cyan
$ErrorActionPreference = "Continue"
git fetch origin $CurrentBranch 2>&1 | Out-Null
$ErrorActionPreference = "Stop"
$LocalHead = git rev-parse HEAD
$RemoteHead = git rev-parse "origin/$CurrentBranch" 2>$null
if ($RemoteHead -and $LocalHead -ne $RemoteHead) {
    $MergeBase = git merge-base $LocalHead $RemoteHead 2>$null
    if ($MergeBase -eq $LocalHead) {
        # Remote is ahead — fast-forward local
        Write-Host "Fast-forwarding to include remote commits..." -ForegroundColor Cyan
        git merge --ff-only "origin/$CurrentBranch"
    } elseif ($MergeBase -eq $RemoteHead) {
        # Local is ahead — nothing to pull
        Write-Host "Local is ahead of remote, nothing to pull." -ForegroundColor Cyan
    } else {
        Write-Error "Local and remote have diverged. Resolve manually before releasing."
        exit 1
    }
}

# Check for uncommitted changes
$status = git status --porcelain
if ([string]::IsNullOrWhiteSpace($status)) {
    Write-Host "No changes to commit. Working tree is clean." -ForegroundColor Yellow
    exit 0
}

# Show what will be committed
Write-Host "`nChanges to be committed:" -ForegroundColor Cyan
git status --short

# Get commit message if not provided
if ([string]::IsNullOrWhiteSpace($CommitMessage)) {
    if ([Environment]::UserInteractive -and (-not [Console]::IsInputRedirected)) {
        Write-Host "`nEnter commit message:" -ForegroundColor Yellow
        $CommitMessage = Read-Host
    }
    if ([string]::IsNullOrWhiteSpace($CommitMessage)) {
        Write-Error "Commit message is required. Usage: ./scripts/push.ps1 patch ""feat: description of changes"""
        exit 1
    }
}

# Check for sensitive files before staging
Write-Host "`nChecking for sensitive files..." -ForegroundColor Cyan
$SensitivePatterns = @("*.env", "*.env.local", "*.key", "*.pem", "*.p8", "*.pfx", "credentials*.json", "secrets*.json")
$ChangedFiles = @(git status --porcelain | ForEach-Object { $_.Substring(3).Trim() })
$SuspiciousFiles = @()
foreach ($pattern in $SensitivePatterns) {
    $SuspiciousFiles += @($ChangedFiles | Where-Object { $_ -like $pattern -and $_ -notlike "*.example" -and $_ -notlike "*.md" })
}
if ($SuspiciousFiles.Count -gt 0) {
    Write-Warning "Potentially sensitive files detected:"
    $SuspiciousFiles | ForEach-Object { Write-Warning "  - $_" }
    $Response = Read-Host "Continue anyway? (y/N)"
    if ($Response -ne "y" -and $Response -ne "Y") {
        Write-Host "Aborted." -ForegroundColor Yellow
        exit 1
    }
}

# Stage all changes
Write-Host "`nStaging all changes..." -ForegroundColor Cyan
git add -A

# Commit
Write-Host "Committing changes..." -ForegroundColor Cyan
git commit -m "$CommitMessage"

# Run tests before pushing
Write-Host "`n========================================" -ForegroundColor Magenta
Write-Host "Running tests before release..." -ForegroundColor Magenta
Write-Host "========================================" -ForegroundColor Magenta

# Run backend tests
Write-Host "`nRunning backend tests in Docker..." -ForegroundColor Yellow
docker compose exec -T app bash -c "cd /var/www/html/backend && php artisan test" 2>&1
$BackendTestExit = $LASTEXITCODE

if ($BackendTestExit -ne 0) {
    Write-Host "`nBackend tests failed!" -ForegroundColor Red
    Write-Host "Fix the test failures and try again." -ForegroundColor Red
    # Reset the commits we made
    Write-Host "Resetting commits..." -ForegroundColor Yellow
    git reset --soft HEAD~1
    exit 1
}

Write-Host "Backend tests passed!" -ForegroundColor Green

# Run frontend tests
Write-Host "`nRunning frontend tests in Docker..." -ForegroundColor Yellow
docker compose exec -T app bash -c "cd /var/www/html/frontend && npm test" 2>&1
$FrontendTestExit = $LASTEXITCODE

if ($FrontendTestExit -ne 0) {
    Write-Host "`nFrontend tests failed!" -ForegroundColor Red
    Write-Host "Fix the test failures and try again." -ForegroundColor Red
    # Reset the commits we made
    Write-Host "Resetting commits..." -ForegroundColor Yellow
    git reset --soft HEAD~1
    exit 1
}

Write-Host "Frontend tests passed!" -ForegroundColor Green

# Run frontend lint (matches CI)
Write-Host "`nRunning frontend lint in Docker..." -ForegroundColor Yellow
$ErrorActionPreference = "Continue"
docker compose exec -T app bash -c "cd /var/www/html/frontend && npm run lint" 2>&1
$LintExit = $LASTEXITCODE
$ErrorActionPreference = "Stop"

if ($LintExit -ne 0) {
    Write-Host "`nFrontend lint failed!" -ForegroundColor Red
    Write-Host "Fix the lint errors and try again." -ForegroundColor Red
    git reset --soft HEAD~1
    exit 1
}

Write-Host "Frontend lint passed!" -ForegroundColor Green

# Run frontend build / TypeScript check (matches CI)
Write-Host "`nRunning frontend build in Docker..." -ForegroundColor Yellow
$ErrorActionPreference = "Continue"
docker compose exec -T app bash -c "cd /var/www/html/frontend && npm run build" 2>&1
$BuildExit = $LASTEXITCODE
$ErrorActionPreference = "Stop"

if ($BuildExit -ne 0) {
    Write-Host "`nFrontend build failed!" -ForegroundColor Red
    Write-Host "Fix the build errors and try again." -ForegroundColor Red
    git reset --soft HEAD~1
    exit 1
}

Write-Host "Frontend build passed!" -ForegroundColor Green

# Run composer audit (matches CI)
Write-Host "`nRunning composer audit in Docker..." -ForegroundColor Yellow
$ErrorActionPreference = "Continue"
docker compose exec -T app bash -c "cd /var/www/html/backend && composer audit --abandoned=report" 2>&1
$AuditExit = $LASTEXITCODE
$ErrorActionPreference = "Stop"

if ($AuditExit -ne 0) {
    Write-Host "`nComposer audit found security vulnerabilities!" -ForegroundColor Red
    Write-Host "Fix the vulnerabilities and try again." -ForegroundColor Red
    git reset --soft HEAD~1
    exit 1
}

Write-Host "Composer audit passed!" -ForegroundColor Green

Write-Host "`n========================================" -ForegroundColor Green
Write-Host "All tests passed! Proceeding with release..." -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green

# Read current version
$CurrentVersion = (Get-Content $VersionFile).Trim()
Write-Host "`nCurrent version: $CurrentVersion" -ForegroundColor Cyan

# Calculate new version
$VersionParts = $CurrentVersion -split '\.'
$Major = [int]$VersionParts[0]
$Minor = [int]$VersionParts[1]
$Patch = [int]$VersionParts[2]

$NewVersion = switch ($VersionBump.ToLower()) {
    "patch" { "$Major.$Minor.$($Patch + 1)" }
    "minor" { "$Major.$($Minor + 1).0" }
    "major" { "$($Major + 1).0.0" }
    default {
        # Check if it's a valid semver
        if ($VersionBump -match '^\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?(\+[a-zA-Z0-9.]+)?$') {
            $VersionBump
        } else {
            Write-Error "Invalid version bump: $VersionBump. Use patch, minor, major, or x.y.z"
            exit 1
        }
    }
}

Write-Host "New version: $NewVersion" -ForegroundColor Green

# Require a manually-written changelog entry for the new version
Write-Host "Checking changelog..." -ForegroundColor Cyan
$HasManualChangelog = $false
if (Test-Path $ChangelogFile) {
    $ExistingChangelog = Get-Content $ChangelogFile -Raw
    if ($ExistingChangelog -match "## \[$([regex]::Escape($NewVersion))\]") {
        Write-Host "Changelog entry found for v$NewVersion" -ForegroundColor Green
        $HasManualChangelog = $true
    }
}
if (-not $HasManualChangelog) {
    Write-Error "No changelog entry found for v$NewVersion. Write a detailed entry in CHANGELOG.md before releasing."
    Write-Host "Resetting commits..." -ForegroundColor Yellow
    git reset --soft HEAD~1
    exit 1
}

# Update VERSION file
Set-Content -Path $VersionFile -Value $NewVersion -NoNewline

# Update package.json
$PackageContent = Get-Content $PackageJson -Raw
$OldPattern = '"version":\s*"[^"]*"'
$NewPattern = '"version": "' + $NewVersion + '"'
$PackageContent = $PackageContent -replace $OldPattern, $NewPattern
Set-Content -Path $PackageJson -Value $PackageContent -NoNewline

# Update CACHE_VERSION in service worker so caches bust on release
if (Test-Path $SwJs) {
    $SwContent = Get-Content $SwJs -Raw
    $SwOldPattern = "const CACHE_VERSION = 'sourdough-v[^']*'"
    $SwNewPattern = "const CACHE_VERSION = 'sourdough-v$NewVersion'"
    $SwContent = $SwContent -replace $SwOldPattern, $SwNewPattern
    Set-Content -Path $SwJs -Value $SwContent -NoNewline
}

Write-Host "Updated version files" -ForegroundColor Cyan

# Stage version files and changelog
$FilesToStage = @($VersionFile, $PackageJson, $ChangelogFile)
if (Test-Path $SwJs) { $FilesToStage += $SwJs }
git add @FilesToStage

# Commit version bump
Write-Host "Committing version bump..." -ForegroundColor Cyan
git commit -m "Release v$NewVersion"

# Create tag
Write-Host "Creating tag v$NewVersion..." -ForegroundColor Cyan
git tag "v$NewVersion"

# Push everything (commit + tag together to avoid race conditions)
Write-Host "`nPushing to origin..." -ForegroundColor Cyan
git push origin $CurrentBranch "v$NewVersion"

Write-Host ""
Write-Host "Release complete!" -ForegroundColor Green
Write-Host "Version: $NewVersion" -ForegroundColor Green
Write-Host "Tag: v$NewVersion" -ForegroundColor Green
Write-Host ""
Write-Host "GitHub Actions release workflow should now be running." -ForegroundColor Cyan
