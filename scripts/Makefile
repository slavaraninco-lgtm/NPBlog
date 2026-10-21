.PHONY: all build windows linux clean run test

DIST_DIR := dist
BINARY_NAME := npblog

all: clean build

build: windows linux

windows:
	@echo "Компиляция для Windows (x86_64)..."
	@mkdir -p $(DIST_DIR)
	GOOS=windows GOARCH=amd64 go build -ldflags="-s -w" -o $(DIST_DIR)/$(BINARY_NAME).exe ./cmd/npblog

linux:
	@echo "Компиляция для Linux (amd64 / arm64)..."
	@mkdir -p $(DIST_DIR)
	GOOS=linux GOARCH=amd64 go build -ldflags="-s -w" -o $(DIST_DIR)/$(BINARY_NAME)-linux-amd64 ./cmd/npblog
	GOOS=linux GOARCH=arm64 go build -ldflags="-s -w" -o $(DIST_DIR)/$(BINARY_NAME)-linux-arm64 ./cmd/npblog

clean:
	@echo "Очистка $(DIST_DIR)..."
	@rm -rf $(DIST_DIR)

run:
	go run ./cmd/npblog --port=8080 --dev

test:
	go test -v ./...
