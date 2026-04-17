@echo off
setlocal

set SCRIPT=%~dp0scripts\start.bat
if not exist "%SCRIPT%" (
  echo Missing scripts\start.bat
  exit /b 1
)

call "%SCRIPT%" %*
