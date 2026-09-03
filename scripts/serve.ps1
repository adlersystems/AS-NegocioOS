#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Manage the Laravel development server used for manual smoke testing.
.DESCRIPTION
    Starts (or restart/stop) `php artisan serve` on 127.0.0.1:8124 in a way that
    survives the invoking shell exiting, so it does not get torn down between
    opencode tool calls. Logs output to the OS temp dir for inspection.
    The process is re-parented to avoid the child dying with the parent shell.

    Usage:
        .\scripts\serve.ps1 start     # start the server (no-op if already up)
        .\scripts\serve.ps1 stop      # stop the server and any PHP on :8124
        .\scripts\serve.ps1 restart   # stop then start
        .\scripts\serve.ps1 status    # print whether the server is reachable
.PARAMETER Action
    One of: start, stop, restart, status. Defaults to start.
.EXAMPLE
    .\scripts\serve.ps1 start
#>
param(
    [Parameter(Position = 0)]
    [ValidateSet('start', 'stop', 'restart', 'status')]
    [string]$Action = 'start'
)

$ErrorActionPreference = 'Stop'
$port = 8124
$hostAddr = '127.0.0.1'
$root = Split-Path -Parent $PSScriptRoot
$logOut = Join-Path $env:TEMP 'opencode\serve-out.log'
$logErr = Join-Path $env:TEMP 'opencode\serve-err.log'

function Test-ServerUp {
    try {
        $res = Invoke-WebRequest "http://${hostAddr}:${port}/login" -UseBasicParsing -TimeoutSec 3
        return $res.StatusCode -eq 200
    } catch {
        return $false
    }
}

function Get-ServePids {
    # `artisan serve` parent process and the actual php -S built-in server worker
    # (CommandLine contains the php built-in server binding to our host:port).
    Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
        Where-Object {
            $cmd = $_.CommandLine
            ($cmd -match 'artisan serve') -or ($cmd -match "(-S ${hostAddr}:${port}( |$)|\b${hostAddr}:${port}\b)")
        } |
        Select-Object -ExpandProperty ProcessId
}

function Stop-Server {
    Get-ServePids | ForEach-Object {
        Write-Host "Stopping php pid $_"
        Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue
    }
    Start-Sleep -Milliseconds 500
}

function Start-Server {
    if (Test-ServerUp) {
        Write-Host "Server already up on http://${hostAddr}:${port}"
        return
    }
    if (-not (Test-Path (Join-Path $env:TEMP 'opencode'))) {
        New-Item -ItemType Directory -Path (Join-Path $env:TEMP 'opencode') -Force | Out-Null
    }

    # Re-parent via cmd /c start so the php process is detached from this shell
    # and survives when the opencode tool call returns.
    $logOutArg = "`"$logOut`""
    $logErrArg = "`"$logErr`""
    $cmd = "php artisan serve --host=${hostAddr} --port=${port}"
    Start-Process -FilePath 'cmd.exe' -ArgumentList '/c', 'start', '/b', 'cmd', '/c', "cd /d `"$root`" && $cmd > $logOutArg 2> $logErrArg" -WindowStyle Hidden

    # Wait for it to come up.
    for ($i = 0; $i -lt 20; $i++) {
        Start-Sleep -Milliseconds 500
        if (Test-ServerUp) {
            Write-Host "Server started on http://${hostAddr}:${port}"
            return
        }
    }
    Write-Error "Server failed to start. See $logErr`n$(if (Test-Path $logErr) { Get-Content $logErr -Raw })"
}

switch ($Action) {
    'status' {
        if (Test-ServerUp) { Write-Host 'up' } else { Write-Host 'down' }
        break
    }
    'stop'   { Stop-Server; Write-Host 'Stopped.'; break }
    'start'  { Start-Server; break }
    'restart' { Stop-Server; Start-Server; break }
    default { Start-Server }
}