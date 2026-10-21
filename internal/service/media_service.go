package service

import (
	"crypto/rand"
	"encoding/base64"
	"encoding/hex"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strings"
	"time"

	"npblog/internal/config"
	"npblog/internal/domain"
)

type FileUploadItem struct {
	Filename string
	Data     []byte
}

type DocumentFileInfo struct {
	Name  string `json:"name"`
	Path  string `json:"path"`
	URL   string `json:"url"`
	Size  int64  `json:"size"`
	Mtime int64  `json:"mtime"`
}

type MediaFileInfo struct {
	Name string `json:"name"`
	Path string `json:"path"`
	Size int64  `json:"size"`
}

type FontFileInfo struct {
	Name   string `json:"name"`
	File   string `json:"file"`
	Path   string `json:"path"`
	Format string `json:"format"`
}

type MediaService struct {
	cfg     *config.AppConfig
	storage *StorageService
}

func NewMediaService(cfg *config.AppConfig, storage *StorageService) *MediaService {
	return &MediaService{
		cfg:     cfg,
		storage: storage,
	}
}

func (m *MediaService) generateID() string {
	b := make([]byte, 8)
	_, _ = rand.Read(b)
	return fmt.Sprintf("%x%x", time.Now().Unix(), hex.EncodeToString(b)[:6])
}

// UploadImage сохраняет изображение в data/uploads/
func (m *MediaService) UploadImage(activeBlogPath, originalName string, data []byte) (string, error) {
	ext := strings.ToLower(filepath.Ext(originalName))
	allowed := map[string]bool{".jpg": true, ".jpeg": true, ".png": true, ".gif": true, ".webp": true, ".svg": true, ".avif": true}
	if !allowed[ext] {
		return "", errors.New("недопустимый тип файла. Разрешены: jpg, jpeg, png, gif, webp, svg, avif")
	}

	uploadsDir := m.storage.GetDataPath(activeBlogPath, "uploads")
	_ = os.MkdirAll(uploadsDir, 0755)

	newFilename := m.generateID() + ext
	targetFile := filepath.Join(uploadsDir, newFilename)

	if err := m.storage.SafeWriteFile(targetFile, data); err != nil {
		return "", err
	}

	dataDirName := filepath.Base(m.storage.GetDataPath(activeBlogPath, ""))
	return fmt.Sprintf("/%s/uploads/%s", dataDirName, newFilename), nil
}

// UploadImagesGrid сохраняет пачку изображений и формирует HTML-сетку
func (m *MediaService) UploadImagesGrid(activeBlogPath string, files []FileUploadItem) (string, error) {
	if len(files) == 0 {
		return "", errors.New("файлы не переданы")
	}

	var uploadedPaths []string
	for _, f := range files {
		path, err := m.UploadImage(activeBlogPath, f.Filename, f.Data)
		if err == nil {
			uploadedPaths = append(uploadedPaths, path)
		}
	}

	if len(uploadedPaths) == 0 {
		return "", errors.New("не удалось загрузить изображения для сетки")
	}

	count := len(uploadedPaths)
	var sb strings.Builder
	sb.WriteString(fmt.Sprintf(`<div class="image-grid grid-%d">`, count))
	for _, p := range uploadedPaths {
		sb.WriteString(fmt.Sprintf(`<div class="grid-item"><img src="%s" alt="Grid Image"></div>`, p))
	}
	sb.WriteString(`</div>`)

	return sb.String(), nil
}

// SaveEditedImage декодирует base64 изображение из холста редактора
func (m *MediaService) SaveEditedImage(activeBlogPath, dataURI string) (string, error) {
	re := regexp.MustCompile(`^data:image/(\w+);base64,(.+)`)
	matches := re.FindStringSubmatch(dataURI)
	if len(matches) < 3 {
		return "", errors.New("неверный формат base64 изображения")
	}

	format := strings.ToLower(matches[1])
	if format == "jpeg" {
		format = "jpg"
	}

	rawBytes, err := base64.StdEncoding.DecodeString(matches[2])
	if err != nil {
		return "", errors.New("ошибка декодирования base64")
	}

	uploadsDir := m.storage.GetDataPath(activeBlogPath, "uploads")
	_ = os.MkdirAll(uploadsDir, 0755)

	newFilename := fmt.Sprintf("edit_%s.%s", m.generateID(), format)
	targetFile := filepath.Join(uploadsDir, newFilename)

	if err := m.storage.SafeWriteFile(targetFile, rawBytes); err != nil {
		return "", err
	}

	dataDirName := filepath.Base(m.storage.GetDataPath(activeBlogPath, ""))
	return fmt.Sprintf("/%s/uploads/%s", dataDirName, newFilename), nil
}

// UploadDocument сохраняет документ в data/files/documents/
func (m *MediaService) UploadDocument(activeBlogPath, originalName string, data []byte) (string, error) {
	docDir := m.storage.GetDataPath(activeBlogPath, "files/documents")
	_ = os.MkdirAll(docDir, 0755)

	ext := strings.ToLower(filepath.Ext(originalName))
	cleanBase := strings.TrimSuffix(filepath.Base(originalName), ext)
	cleanBase = regexp.MustCompile(`[^a-zA-Z0-9_\-\sА-Яа-яЁё]`).ReplaceAllString(cleanBase, "")
	if cleanBase == "" {
		cleanBase = "document"
	}

	newFilename := fmt.Sprintf("%s_%s%s", cleanBase, m.generateID(), ext)
	targetFile := filepath.Join(docDir, newFilename)

	if err := m.storage.SafeWriteFile(targetFile, data); err != nil {
		return "", err
	}
	dataDirName := filepath.Base(m.storage.GetDataPath(activeBlogPath, ""))
	return fmt.Sprintf("/%s/files/documents/%s", dataDirName, newFilename), nil
}

// GetDocuments возвращает список документов
func (m *MediaService) GetDocuments(activeBlogPath string) ([]DocumentFileInfo, error) {
	docDir := m.storage.GetDataPath(activeBlogPath, "files/documents")
	entries, err := os.ReadDir(docDir)
	if err != nil {
		return []DocumentFileInfo{}, nil
	}

	dataDirName := filepath.Base(m.storage.GetDataPath(activeBlogPath, ""))
	var list []DocumentFileInfo
	for _, e := range entries {
		if e.IsDir() {
			continue
		}
		info, err := e.Info()
		if err != nil {
			continue
		}
		list = append(list, DocumentFileInfo{
			Name:  e.Name(),
			Path:  filepath.Join(docDir, e.Name()),
			URL:   fmt.Sprintf("/%s/files/documents/%s", dataDirName, e.Name()),
			Size:  info.Size(),
			Mtime: info.ModTime().Unix(),
		})
	}

	sort.Slice(list, func(i, j int) bool {
		return list[i].Mtime > list[j].Mtime
	})

	return list, nil
}

// UploadAudio сохраняет аудио в data/files/audio/
func (m *MediaService) UploadAudio(activeBlogPath, originalName string, data []byte) (string, error) {
	audioDir := m.storage.GetDataPath(activeBlogPath, "files/audio")
	_ = os.MkdirAll(audioDir, 0755)

	ext := strings.ToLower(filepath.Ext(originalName))
	newFilename := m.generateID() + ext
	targetFile := filepath.Join(audioDir, newFilename)

	if err := m.storage.SafeWriteFile(targetFile, data); err != nil {
		return "", err
	}
	dataDirName := filepath.Base(m.storage.GetDataPath(activeBlogPath, ""))
	return fmt.Sprintf("/%s/files/audio/%s", dataDirName, newFilename), nil
}

// GetAudioFiles возвращает список аудиофайлов
func (m *MediaService) GetAudioFiles(activeBlogPath string) ([]MediaFileInfo, error) {
	audioDir := m.storage.GetDataPath(activeBlogPath, "files/audio")
	entries, err := os.ReadDir(audioDir)
	if err != nil {
		return []MediaFileInfo{}, nil
	}

	dataDirName := filepath.Base(m.storage.GetDataPath(activeBlogPath, ""))
	var list []MediaFileInfo
	for _, e := range entries {
		if e.IsDir() {
			continue
		}
		info, _ := e.Info()
		var sz int64
		if info != nil {
			sz = info.Size()
		}
		list = append(list, MediaFileInfo{
			Name: e.Name(),
			Path: fmt.Sprintf("/%s/files/audio/%s", dataDirName, e.Name()),
			Size: sz,
		})
	}
	return list, nil
}

// UploadVideo сохраняет видео в data/files/videos/
func (m *MediaService) UploadVideo(activeBlogPath, originalName string, data []byte) (string, error) {
	videoDir := m.storage.GetDataPath(activeBlogPath, "files/videos")
	_ = os.MkdirAll(videoDir, 0755)

	ext := strings.ToLower(filepath.Ext(originalName))
	newFilename := m.generateID() + ext
	targetFile := filepath.Join(videoDir, newFilename)

	if err := m.storage.SafeWriteFile(targetFile, data); err != nil {
		return "", err
	}
	dataDirName := filepath.Base(m.storage.GetDataPath(activeBlogPath, ""))
	return fmt.Sprintf("/%s/files/videos/%s", dataDirName, newFilename), nil
}

// GetVideoFiles возвращает список видеофайлов
func (m *MediaService) GetVideoFiles(activeBlogPath string) ([]MediaFileInfo, error) {
	videoDir := m.storage.GetDataPath(activeBlogPath, "files/videos")
	entries, err := os.ReadDir(videoDir)
	if err != nil {
		return []MediaFileInfo{}, nil
	}

	dataDirName := filepath.Base(m.storage.GetDataPath(activeBlogPath, ""))
	var list []MediaFileInfo
	for _, e := range entries {
		if e.IsDir() {
			continue
		}
		info, _ := e.Info()
		var sz int64
		if info != nil {
			sz = info.Size()
		}
		list = append(list, MediaFileInfo{
			Name: e.Name(),
			Path: fmt.Sprintf("/%s/files/videos/%s", dataDirName, e.Name()),
			Size: sz,
		})
	}
	return list, nil
}

// UploadFont сохраняет шрифт в data/fonts/
func (m *MediaService) UploadFont(activeBlogPath, originalName string, data []byte) error {
	fontsDir := m.storage.GetDataPath(activeBlogPath, "fonts")
	_ = os.MkdirAll(fontsDir, 0755)

	cleanName := filepath.Base(originalName)
	targetFile := filepath.Join(fontsDir, cleanName)
	return m.storage.SafeWriteFile(targetFile, data)
}

// GetCustomFonts возвращает список шрифтов
func (m *MediaService) GetCustomFonts(activeBlogPath string) ([]FontFileInfo, error) {
	fontsDir := m.storage.GetDataPath(activeBlogPath, "fonts")
	entries, err := os.ReadDir(fontsDir)
	if err != nil {
		return []FontFileInfo{}, nil
	}

	var list []FontFileInfo
	for _, e := range entries {
		if e.IsDir() {
			continue
		}
		ext := strings.ToLower(strings.TrimPrefix(filepath.Ext(e.Name()), "."))
		if ext == "ttf" || ext == "otf" || ext == "woff" || ext == "woff2" {
			fontName := strings.TrimSuffix(e.Name(), filepath.Ext(e.Name()))
			list = append(list, FontFileInfo{
				Name:   fontName,
				File:   e.Name(),
				Path:   filepath.Join(fontsDir, e.Name()),
				Format: ext,
			})
		}
	}
	return list, nil
}

// UploadSmiles распаковывает/сохраняет набор смайлов в data/smiles/{setName}/
func (m *MediaService) UploadSmiles(activeBlogPath, setName string, files []FileUploadItem) error {
	cleanSet := regexp.MustCompile(`[^a-zA-Z0-9_\-\sА-Яа-яЁё]`).ReplaceAllString(setName, "")
	cleanSet = strings.TrimSpace(cleanSet)
	if cleanSet == "" || cleanSet == "." || cleanSet == ".." {
		return errors.New("неверное имя набора смайлов")
	}

	setDir := m.storage.GetDataPath(activeBlogPath, filepath.Join("smiles", cleanSet))
	_ = os.MkdirAll(setDir, 0755)

	for _, f := range files {
		cleanFile := filepath.Base(f.Filename)
		target := filepath.Join(setDir, cleanFile)
		_ = m.storage.SafeWriteFile(target, f.Data)
	}
	return nil
}

// GetSmiles возвращает список всех наборов смайлов и их файлов
func (m *MediaService) GetSmiles(activeBlogPath string) (map[string][]string, error) {
	smilesDir := m.storage.GetDataPath(activeBlogPath, "smiles")
	entries, err := os.ReadDir(smilesDir)
	if err != nil {
		return make(map[string][]string), nil
	}

	dataDirName := filepath.Base(m.storage.GetDataPath(activeBlogPath, ""))
	result := make(map[string][]string)
	for _, e := range entries {
		if !e.IsDir() {
			continue
		}
		setName := e.Name()
		subFiles, err := os.ReadDir(filepath.Join(smilesDir, setName))
		if err == nil {
			var list []string
			for _, sf := range subFiles {
				if !sf.IsDir() {
					url := fmt.Sprintf("/%s/smiles/%s/%s", dataDirName, setName, sf.Name())
					list = append(list, url)
				}
			}
			result[setName] = list
		}
	}
	return result, nil
}

// DeleteSmileSet удаляет набор смайлов
func (m *MediaService) DeleteSmileSet(activeBlogPath, setName string) error {
	cleanSet := regexp.MustCompile(`[^a-zA-Z0-9_\-\sА-Яа-яЁё]`).ReplaceAllString(setName, "")
	cleanSet = strings.TrimSpace(cleanSet)
	setDir := m.storage.GetDataPath(activeBlogPath, filepath.Join("smiles", cleanSet))
	return os.RemoveAll(setDir)
}

// UploadBackground сохраняет фоновое изображение
func (m *MediaService) UploadBackground(activeBlogPath, filename string, data []byte) (string, error) {
	bgDir := m.storage.GetDataPath(activeBlogPath, "backgrounds")
	_ = os.MkdirAll(bgDir, 0755)

	ext := strings.ToLower(filepath.Ext(filename))
	newFilename := m.generateID() + ext
	targetFile := filepath.Join(bgDir, newFilename)

	if err := m.storage.SafeWriteFile(targetFile, data); err != nil {
		return "", err
	}
	return newFilename, nil
}

// GetBackgrounds читает data/post_backgrounds.json
func (m *MediaService) GetBackgrounds(activeBlogPath string) (*domain.PostBackgroundConfig, error) {
	bgFile := m.storage.GetDataPath(activeBlogPath, "post_backgrounds.json")
	var cfg domain.PostBackgroundConfig
	if err := m.storage.SafeReadJSON(bgFile, &cfg); err != nil {
		return &domain.PostBackgroundConfig{Posts: make(map[string]*domain.BackgroundItem)}, nil
	}
	if cfg.Posts == nil {
		cfg.Posts = make(map[string]*domain.BackgroundItem)
	}
	return &cfg, nil
}

// SaveBackground сохраняет фон (global, blog или для статьи)
func (m *MediaService) SaveBackground(activeBlogPath, targetType, postID string, item *domain.BackgroundItem) error {
	cfg, _ := m.GetBackgrounds(activeBlogPath)
	if targetType == "global" {
		cfg.GlobalBackground = item
	} else if targetType == "blog" {
		cfg.BlogBackground = item
	} else {
		if cfg.Posts == nil {
			cfg.Posts = make(map[string]*domain.BackgroundItem)
		}
		cfg.Posts[postID] = item
	}

	bgFile := m.storage.GetDataPath(activeBlogPath, "post_backgrounds.json")
	return m.storage.SafeWriteJSON(bgFile, cfg)
}

// RemoveBackground удаляет фон
func (m *MediaService) RemoveBackground(activeBlogPath, targetType, postID string) error {
	cfg, _ := m.GetBackgrounds(activeBlogPath)
	if targetType == "global" {
		cfg.GlobalBackground = nil
	} else if targetType == "blog" {
		cfg.BlogBackground = nil
	} else {
		if cfg.Posts != nil {
			delete(cfg.Posts, postID)
		}
	}
	bgFile := m.storage.GetDataPath(activeBlogPath, "post_backgrounds.json")
	return m.storage.SafeWriteJSON(bgFile, cfg)
}

// DeleteFile удаляет файл по относительному пути внутри data
func (m *MediaService) DeleteFile(activeBlogPath, relPath string) error {
	targetPath, err := m.storage.ValidateSafePath(m.storage.GetDataPath(activeBlogPath, ""), relPath)
	if err != nil {
		return err
	}
	return os.Remove(targetPath)
}
