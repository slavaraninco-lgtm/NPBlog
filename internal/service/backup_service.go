package service

import (
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strconv"
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

// GetBackupContent читает содержимое файла конкретной ревизии статьи
func (b *BackupService) GetBackupContent(postID, backupNumber int) (string, error) {
	backupDir := b.storage.GetBackupPath(strconv.Itoa(postID))
	file := filepath.Join(backupDir, fmt.Sprintf("%d-%d.html", postID, backupNumber))

	bytes, err := os.ReadFile(file)
	if err != nil {
		return "", errors.New("файл резервной копии не найден")
	}
	return string(bytes), nil
}

// RestoreBackup восстанавливает статью из бэкапа с созданием точки отката
func (b *BackupService) RestoreBackup(activeBlogPath string, postID, backupNumber int) error {
	backupContent, err := b.GetBackupContent(postID, backupNumber)
	if err != nil {
		return err
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

// DeleteBackup удаляет отдельную ревизию бэкапа
func (b *BackupService) DeleteBackup(postID, backupNumber int) error {
	backupDir := b.storage.GetBackupPath(strconv.Itoa(postID))
	file := filepath.Join(backupDir, fmt.Sprintf("%d-%d.html", postID, backupNumber))
	_ = os.Remove(file)

	metaFile := b.storage.GetBackupPath("backup-meta.json")
	var meta domain.BackupMeta
	_ = b.storage.SafeReadJSON(metaFile, &meta)
	if meta != nil {
		idKey := strconv.Itoa(postID)
		if pInfo, exists := meta[idKey]; exists {
			var newBackups []domain.BackupEntry
			for _, item := range pInfo.Backups {
				if item.BackupNumber != backupNumber {
					newBackups = append(newBackups, item)
				}
			}
			pInfo.Backups = newBackups
			meta[idKey] = pInfo
			_ = b.storage.SafeWriteJSON(metaFile, meta)
		}
	}
	return nil
}
