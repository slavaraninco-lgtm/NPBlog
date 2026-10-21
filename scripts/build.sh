#!/bin/bash
set -e

echo "=============================================="
echo " NPBlog - Сборка бинарников Windows и Linux"
echo "=============================================="

mkdir -p dist

echo "[1/3] Сборка Linux amd64..."
GOOS=linux GOARCH=amd64 go build -ldflags="-s -w" -o dist/npblog-linux-amd64 ./cmd/npblog

echo "[2/3] Сборка Linux arm64..."
GOOS=linux GOARCH=arm64 go build -ldflags="-s -w" -o dist/npblog-linux-arm64 ./cmd/npblog

echo "[3/3] Сборка Windows x86_64 (npblog.exe)..."
GOOS=windows GOARCH=amd64 go build -ldflags="-s -w" -o dist/npblog.exe ./cmd/npblog

chmod +x dist/npblog-linux-* || true
cp dist/npblog-linux-amd64 npblog || true
cp dist/npblog.exe npblog.exe || true
chmod +x npblog || true

echo "=============================================="
echo " Готово! Все бинарники собраны в папку dist/"
echo " и скопированы в корень (npblog, npblog.exe)"
echo "=============================================="
ls -lh dist/
