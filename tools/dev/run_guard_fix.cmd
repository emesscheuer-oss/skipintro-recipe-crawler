@echo off
setlocal
REM === Absolutpfade anpassen falls noetig ===
set "PLUG=C:\Users\Micha\Local Sites\rezepte\app\public\wp-content\plugins\skipintro-recipe-crawler"
set "PHP=C:\Users\Micha\AppData\Roaming\Local\lightning-services\php-8.2.27+1\bin\win64\php.exe"

echo [fix] Wechsle nach:
echo   "%PLUG%"
cd /d "%PLUG%" || (echo [ERROR] Ordner nicht gefunden.& goto :PAUSE)

echo.
echo [fix] PHP Binary:
echo   "%PHP%"
if not exist "%PHP%" (
  echo [warn] PHP unter obigem Pfad nicht gefunden – versuche "php" aus PATH...
  set "PHP=php"
)

echo.
echo [diag] PHP-Version:
"%PHP%" -v
echo.

echo [diag] Lint strict_guard.php:
if not exist "tools\dev\strict_guard.php" (
  echo [ERROR] Datei fehlt: tools\dev\strict_guard.php
  goto :PAUSE
)
"%PHP%" -l "tools\dev\strict_guard.php"
if errorlevel 1 (
  echo.
  echo [ERROR] PHP-Parsefehler. Bitte strict_guard.php ersetzen/reparieren.
  goto :PAUSE
)

echo.
echo [exec] Guard FIX:
"%PHP%" "tools\dev\strict_guard.php" --fix
set "RC=%ERRORLEVEL%"

echo.
echo [fix] ExitCode: %RC%
goto :PAUSE

:PAUSE
echo.
echo -- Ende (FIX). Fenster bleibt offen. --
pause
exit /b %RC%
