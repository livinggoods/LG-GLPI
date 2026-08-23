$ErrorActionPreference = "Stop"

$TaskName = "AfyaDesk GLPI Local Server"
$ScriptPath = Resolve-Path (Join-Path $PSScriptRoot "start-afyadesk-local.ps1")
$PowerShellPath = "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe"
$CurrentUser = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$RunCommand = "`"$PowerShellPath`" -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$ScriptPath`""

try {
    $Action = New-ScheduledTaskAction `
        -Execute $PowerShellPath `
        -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$ScriptPath`""

    $Trigger = New-ScheduledTaskTrigger -AtLogOn -User $CurrentUser
    $Settings = New-ScheduledTaskSettingsSet `
        -AllowStartIfOnBatteries `
        -DontStopIfGoingOnBatteries `
        -MultipleInstances IgnoreNew `
        -RestartCount 3 `
        -RestartInterval (New-TimeSpan -Minutes 1)

    Register-ScheduledTask `
        -TaskName $TaskName `
        -Action $Action `
        -Trigger $Trigger `
        -Settings $Settings `
        -Description "Keeps the local AfyaDesk GLPI PHP server running on http://127.0.0.1:8081/." `
        -Force | Out-Null

    Start-ScheduledTask -TaskName $TaskName

    Write-Output "Installed and started scheduled task: $TaskName"
} catch {
    try {
        $RunKey = "HKCU:\Software\Microsoft\Windows\CurrentVersion\Run"
        New-Item -Path $RunKey -Force | Out-Null
        New-ItemProperty -Path $RunKey -Name $TaskName -Value $RunCommand -PropertyType String -Force | Out-Null
        Write-Output "Scheduled Task was unavailable, so installed user startup registry entry: $TaskName"
    } catch {
        $StartupDir = [Environment]::GetFolderPath("Startup")
        $LauncherPath = Join-Path $StartupDir "$TaskName.cmd"
        $LauncherContent = @"
@echo off
start "$TaskName" /min powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "$ScriptPath"
"@
        Set-Content -LiteralPath $LauncherPath -Value $LauncherContent -Encoding ASCII
        Write-Output "Scheduled Task and registry startup were unavailable, so installed Startup-folder launcher: $LauncherPath"
    }

    Start-Process `
        -FilePath $PowerShellPath `
        -ArgumentList "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$ScriptPath`"" `
        -WindowStyle Hidden
}
