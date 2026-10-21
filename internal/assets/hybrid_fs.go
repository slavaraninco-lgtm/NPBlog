package assets

import (
	"io"
	"io/fs"
	"mime"
	"net/http"
	"os"
	"path/filepath"
	"strings"
)

type HybridFS struct {
	diskRoot string
	fallback fs.FS
}

func NewHybridFS(diskRoot string, fallback fs.FS) *HybridFS {
	return &HybridFS{
		diskRoot: diskRoot,
		fallback: fallback,
	}
}

// ServeHTTP отдает файл с приоритетом диска и fallback на embedded FS
func (h *HybridFS) ServeHTTP(w http.ResponseWriter, r *http.Request, relPath string) bool {
	cleanRel := filepath.Clean(strings.TrimPrefix(relPath, "/"))

	// 1. Проверяем локальный диск
	diskPath := filepath.Join(h.diskRoot, cleanRel)
	if info, err := os.Stat(diskPath); err == nil && !info.IsDir() {
		http.ServeFile(w, r, diskPath)
		return true
	}

	// 2. Fallback на встроенные ресурсы
	if h.fallback != nil {
		fPath := strings.ReplaceAll(cleanRel, "\\", "/")
		if file, err := h.fallback.Open(fPath); err == nil {
			defer file.Close()
			if stat, err := file.Stat(); err == nil && !stat.IsDir() {
				ext := strings.ToLower(filepath.Ext(cleanRel))
				contentType := mime.TypeByExtension(ext)
				if contentType == "" {
					switch ext {
					case ".js":
						contentType = "application/javascript; charset=utf-8"
					case ".css":
						contentType = "text/css; charset=utf-8"
					case ".json":
						contentType = "application/json; charset=utf-8"
					case ".svg":
						contentType = "image/svg+xml"
					case ".ttf":
						contentType = "font/ttf"
					case ".woff":
						contentType = "font/woff"
					case ".woff2":
						contentType = "font/woff2"
					}
				}
				if contentType != "" {
					w.Header().Set("Content-Type", contentType)
				}
				if seeker, ok := file.(io.ReadSeeker); ok {
					http.ServeContent(w, r, stat.Name(), stat.ModTime(), seeker)
				} else {
					bytes, _ := io.ReadAll(file)
					_, _ = w.Write(bytes)
				}
				return true
			}
		}
	}

	return false
}

// ReadFile возвращает содержимое файла (с диска или из embed)
func (h *HybridFS) ReadFile(relPath string) ([]byte, error) {
	cleanRel := filepath.Clean(strings.TrimPrefix(relPath, "/"))
	diskPath := filepath.Join(h.diskRoot, cleanRel)
	if bytes, err := os.ReadFile(diskPath); err == nil {
		return bytes, nil
	}
	if h.fallback != nil {
		fPath := strings.ReplaceAll(cleanRel, "\\", "/")
		if file, err := h.fallback.Open(fPath); err == nil {
			defer file.Close()
			return io.ReadAll(file)
		}
	}
	return nil, os.ErrNotExist
}
