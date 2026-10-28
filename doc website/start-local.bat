@echo off
cd /d "%~dp0"
echo Starting BeetleOps docs at http://localhost:3456
echo Press Ctrl+C to stop.
npx --yes serve -l 3456 .