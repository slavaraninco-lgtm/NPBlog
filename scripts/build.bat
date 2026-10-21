@echo off
echo ==============================================
echo  NPBlog - Сборка бинарников Windows и Linux
echo ==============================================

if not exist dist mkdir dist

echo [1/3] Сборка Windows x86_64 (npblog.exe)...
set GOOS=windows
set GOARCH=amd64
go build -ldflags="-s -w" -o dist\npblog.exe .\cmd\npblog
if %ERRORLEVEL% NEQ 0 (
    echo Ошибка сборки Windows!
    exit /b %ERRORLEVEL%
)

echo [2/3] Кросс-компиляция Linux amd64 (npblog)...
set GOOS=linux
set GOARCH=amd64
go build -ldflags="-s -w" -o dist\npblog-linux-amd64 .\cmd\npblog
if %ERRORLEVEL% NEQ 0 (
    echo Ошибка сборки Linux amd64!
    exit /b %ERRORLEVEL%
)

echo [3/3] Кросс-компиляция Linux arm64 (npblog-arm64)...
set GOOS=linux
set GOARCH=arm64
go build -ldflags="-s -w" -o dist\npblog-linux-arm64 .\cmd\npblog
if %ERRORLEVEL% NEQ 0 (
    echo Ошибка сборки Linux arm64!
    exit /b %ERRORLEVEL%
)

set GOOS=
set GOARCH=

echo ==============================================
echo  Готово! Все бинарники собраны в папку dist\
echo ==============================================
dir dist
