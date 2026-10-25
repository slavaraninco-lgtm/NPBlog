package service

import (
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"npblog/internal/config"
	"npblog/internal/domain"
)

type BackupService struct {
	cfg     *config.AppConfig
	storage *StorageService
}

func NewBackupService(cfg *config.AppConfig, storage *StorageService) *BackupService {
	return &BackupService{
		cfg:     cfg,
		storage: storage,
	}
}

// GetAllBackups возвращает сводные метаданные всех резервных копий
func (b *BackupService) GetAllBackups() (domain.BackupMeta, error) {
	metaFile := b.storage.GetBackupPath("backup-meta.json")
	var meta domain.BackupMeta
	if err := b.storage.SafeReadJSON(metaFile, &meta); err != nil {
		return make(domain.BackupMeta), nil
	}
	return meta, nil
}

// GetPostBackups возвращает историю бэкапов для конкретной статьи
func (b *BackupService) GetPostBackups(postID int) (*domain.PostBackupInfo, error) {
	all, err := b.GetAllBackups()
	if err != nil {
		return nil, err
	}
	info, exists := all[strconv.Itoa(postID)]
	if !exists {
		return nil, errors.New("резервные копии не найдены")
	}
	return &info, nil
}

// GetBackupFileContent читает содержимое файла бэкапа по идентификатору/ключу статьи и имени файла или номеру
func (b *BackupService) GetBackupFileContent(postIDKey string, filename string, backupNumber int) (string, error) {
	postIDKey = strings.TrimSpace(postIDKey)
	filename = strings.TrimSpace(filename)

	if postIDKey == "" && filename != "" {
		clean := filepath.Base(filename)
		parts := strings.Split(strings.TrimSuffix(clean, ".html"), "-")
		if len(parts) >= 2 {
			postIDKey = parts[0]
		}
	}
	if postIDKey == "" {
		return "", errors.New("не указан ID статьи")
	}

	postIDClean := filepath.Base(postIDKey)
	backupDir := b.storage.GetBackupPath(postIDClean)

	var targetFile string
	if filename != "" {
		safeFilename := filepath.Base(filename)
		candidate := filepath.Join(backupDir, safeFilename)
		if fi, err := os.Stat(candidate); err == nil && !fi.IsDir() {
			targetFile = candidate
		}
	}

	if targetFile == "" && backupNumber > 0 {
		candidate := filepath.Join(backupDir, fmt.Sprintf("%s-%d.html", postIDClean, backupNumber))
		if fi, err := os.Stat(candidate); err == nil && !fi.IsDir() {
			targetFile = candidate
		}
	}

	// Поиск в backup-meta.json
	if targetFile == "" {
		all, _ := b.GetAllBackups()
		if info, ok := all[postIDClean]; ok {
			for _, entry := range info.Backups {
				if (backupNumber > 0 && entry.BackupNumber == backupNumber) || (filename != "" && entry.Filename == filename) {
					candidate := filepath.Join(backupDir, filepath.Base(entry.Filename))
					if fi, err := os.Stat(candidate); err == nil && !fi.IsDir() {
						targetFile = candidate
						break
					}
				}
			}
		}
	}

	if targetFile == "" {
		return "", errors.New("файл резервной копии не найден")
	}

	bytes, err := os.ReadFile(targetFile)
	if err != nil {
		return "", errors.New("файл резервной копии не найден")
	}
	return string(bytes), nil
}

// GetBackupContent читает содержимое файла конкретной ревизии статьи (совместимость)
func (b *BackupService) GetBackupContent(postID, backupNumber int) (string, error) {
	return b.GetBackupFileContent(strconv.Itoa(postID), "", backupNumber)
}

// RestoreBackupFile восстанавливает статью из бэкапа с созданием точки отката
func (b *BackupService) RestoreBackupFile(activeBlogPath string, postIDKey string, filename string, backupNumber int) error {
	backupContent, err := b.GetBackupFileContent(postIDKey, filename, backupNumber)
	if err != nil {
		return err
	}

	postIDClean := filepath.Base(strings.TrimSpace(postIDKey))
	postID, _ := strconv.Atoi(postIDClean)
	if postID <= 0 && filename != "" {
		parts := strings.Split(strings.TrimSuffix(filepath.Base(filename), ".html"), "-")
		if len(parts) >= 2 {
			postID, _ = strconv.Atoi(parts[0])
		}
	}
	if postID <= 0 {
		return errors.New("некорректный ID статьи")
	}

	blogDir := b.storage.GetDataPath(activeBlogPath, "blog")
	postFile := filepath.Join(blogDir, fmt.Sprintf("post-%d.html", postID))

	// Читаем текущее состояние статьи перед откатом для создания точки восстановления
	if currentBytes, err := os.ReadFile(postFile); err == nil {
		meta, _ := b.GetAllBackups()
		idKey := strconv.Itoa(postID)
		pInfo := meta[idKey]
		newNum := len(pInfo.Backups) + 1
		nowDate := time.Now().Format("02.01.2006 15:04") + " (перед откатом)"

		pInfo.Backups = append(pInfo.Backups, domain.BackupEntry{
			BackupNumber: newNum,
			Filename:     fmt.Sprintf("%d-%d.html", postID, newNum),
			Date:         nowDate,
			Title:        pInfo.PostTitle,
		})
		meta[idKey] = pInfo
		_ = b.storage.SafeWriteJSON(b.storage.GetBackupPath("backup-meta.json"), meta)

		rollbackFile := filepath.Join(b.storage.GetBackupPath(idKey), fmt.Sprintf("%d-%d.html", postID, newNum))
		_ = b.storage.SafeWriteFile(rollbackFile, currentBytes)
	}

	// Записываем контент из ревизии в активную статью
	return b.storage.SafeWriteFile(postFile, []byte(backupContent))
}

// RestoreBackup восстанавливает статью из бэкапа с созданием точки отката (совместимость)
func (b *BackupService) RestoreBackup(activeBlogPath string, postID, backupNumber int) error {
	return b.RestoreBackupFile(activeBlogPath, strconv.Itoa(postID), "", backupNumber)
}

// DeleteBackupFile удаляет отдельную ревизию бэкапа
func (b *BackupService) DeleteBackupFile(postIDKey string, filename string, backupNumber int) error {
	postIDClean := filepath.Base(strings.TrimSpace(postIDKey))
	if postIDClean == "" && filename != "" {
		parts := strings.Split(strings.TrimSuffix(filepath.Base(filename), ".html"), "-")
		if len(parts) >= 2 {
			postIDClean = parts[0]
		}
	}
	if postIDClean == "" {
		return errors.New("не указан ID статьи")
	}

	backupDir := b.storage.GetBackupPath(postIDClean)

	var targetFile string
	if filename != "" {
		candidate := filepath.Join(backupDir, filepath.Base(filename))
		if fi, err := os.Stat(candidate); err == nil && !fi.IsDir() {
			targetFile = candidate
		}
	}
	if targetFile == "" && backupNumber > 0 {
		candidate := filepath.Join(backupDir, fmt.Sprintf("%s-%d.html", postIDClean, backupNumber))
		if fi, err := os.Stat(candidate); err == nil && !fi.IsDir() {
			targetFile = candidate
		}
	}

	if targetFile != "" {
		_ = os.Remove(targetFile)
	}

	metaFile := b.storage.GetBackupPath("backup-meta.json")
	var meta domain.BackupMeta
	_ = b.storage.SafeReadJSON(metaFile, &meta)
	if meta != nil {
		if pInfo, exists := meta[postIDClean]; exists {
			var newBackups []domain.BackupEntry
			for _, item := range pInfo.Backups {
				match := false
				if filename != "" && item.Filename == filename {
					match = true
				}
				if backupNumber > 0 && item.BackupNumber == backupNumber {
					match = true
				}
				if !match {
					newBackups = append(newBackups, item)
				}
			}
			pInfo.Backups = newBackups
			if len(newBackups) == 0 {
				delete(meta, postIDClean)
				entries, err := os.ReadDir(backupDir)
				if err == nil && len(entries) == 0 {
					_ = os.Remove(backupDir)
				}
			} else {
				meta[postIDClean] = pInfo
			}
			_ = b.storage.SafeWriteJSON(metaFile, meta)
		}
	}
	return nil
}

// DeleteBackup удаляет отдельную ревизию бэкапа (совместимость)
func (b *BackupService) DeleteBackup(postID, backupNumber int) error {
	return b.DeleteBackupFile(strconv.Itoa(postID), "", backupNumber)
}
