Set-Location $PSScriptRoot
Write-Host "Starting BeetleOps docs at http://localhost:3456"
Write-Host "Press Ctrl+C to stop."
npx --yes serve -l 3456 .
