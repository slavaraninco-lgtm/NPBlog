package service

import (
	"errors"
	"fmt"
	"html"
	"os"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"

	"npblog/internal/domain"
)

var postContentContainerPatterns = []*regexp.Regexp{
	regexp.MustCompile(`(?i)<(article|div|main|section)\b[^>]*\bid=["']npblog-post-content["'][^>]*>`),
	regexp.MustCompile(`(?i)<(article|div|main|section)\b[^>]*\bclass=["'][^"']*(?:post-content|content)[^"']*["'][^>]*>`),
}

// RegeneratePostWithTemplate применяет шаблон к существующей статье, сохраняя её содержимое и дату.
func (p *PostService) RegeneratePostWithTemplate(activeBlogPath string, id int, templateHTML []byte) error {
	blogDir := p.storage.GetDataPath(activeBlogPath, "blog")
	postFile := filepath.Join(blogDir, fmt.Sprintf("post-%d.html", id))
	currentHTML, err := os.ReadFile(postFile)
	if err != nil {
		return errors.New("статья не найдена")
	}

	var posts []domain.PostMeta
	if err := p.storage.SafeReadJSON(filepath.Join(blogDir, "posts-meta.json"), &posts); err != nil {
		return errors.New("не удалось прочитать метаданные статьи")
	}
	var post *domain.PostMeta
	for i := range posts {
		if posts[i].ID == id {
			post = &posts[i]
			break
		}
	}
	if post == nil {
		return errors.New("метаданные статьи не найдены")
	}

	rawContent, err := extractCurrentPostContent(string(currentHTML))
	if err != nil {
		return err
	}
	dataDirName := filepath.Base(filepath.Dir(blogDir))
	cleanContent := p.FormatArticleContent(rawContent, dataDirName)

	articleHTML := string(templateHTML)
	articleHTML = strings.ReplaceAll(articleHTML, "{{POST_ID}}", strconv.Itoa(id))
	articleHTML = strings.ReplaceAll(articleHTML, "{{TITLE}}", html.EscapeString(post.Title))
	articleHTML = strings.ReplaceAll(articleHTML, "{{DATE}}", html.EscapeString(post.Date))
	articleHTML = strings.ReplaceAll(articleHTML, "{{META_TAGS}}", p.GenerateSEOTags(id, post.Title, cleanContent))
	articleHTML = strings.ReplaceAll(articleHTML, "{{CUSTOM_FONTS}}", p.getCustomFontsCSS(activeBlogPath))
	settings := p.cfg.GetSettings()
	articleHTML = strings.ReplaceAll(articleHTML, "{{BODY_STYLE}}", fmt.Sprintf(`style="max-width: %dpx;"`, settings.ContentWidth))
	articleHTML = strings.ReplaceAll(articleHTML, "{{CONTENT_WRAPPER_START}}", "")
	articleHTML = strings.ReplaceAll(articleHTML, "{{CONTENT_WRAPPER_END}}", "")

	wrappedContent := cleanContent
	if !strings.Contains(articleHTML, `id="npblog-post-content"`) {
		wrappedContent = `<article id="npblog-post-content" class="content">` + cleanContent + `</article>`
	}
	articleHTML = strings.ReplaceAll(articleHTML, "{{CONTENT}}", wrappedContent)
	if err := p.storage.SafeWriteFile(postFile, []byte(articleHTML)); err != nil {
		return errors.New("не удалось сохранить HTML статьи")
	}
	_ = p.ApplyBackgroundForPost(activeBlogPath, id)
	p.addTemplateRevisionBackup(activeBlogPath, id, post.Title, post.Date, articleHTML)
	return nil
}

func extractCurrentPostContent(document string) (string, error) {
	for _, pattern := range postContentContainerPatterns {
		location := pattern.FindStringSubmatchIndex(document)
		if location == nil {
			continue
		}
		tag := document[location[2]:location[3]]
		contentStart := location[1]
		tags := regexp.MustCompile(`(?i)</?` + regexp.QuoteMeta(tag) + `\b[^>]*>`)
		depth := 0
		for _, token := range tags.FindAllStringIndex(document[location[0]:], -1) {
			start := location[0] + token[0]
			end := location[0] + token[1]
			if strings.HasPrefix(strings.ToLower(document[start:end]), "</") {
				depth--
				if depth == 0 {
					return strings.TrimSpace(document[contentStart:start]), nil
				}
			} else {
				depth++
			}
		}
	}
	return "", errors.New("не удалось извлечь содержимое статьи; HTML файл не изменён")
}

func (p *PostService) addTemplateRevisionBackup(activeBlogPath string, id int, title, date, articleHTML string) {
	backupDir := p.storage.GetBackupPath(strconv.Itoa(id))
	_ = os.MkdirAll(backupDir, 0755)
	backupMetaFile := p.storage.GetBackupPath("backup-meta.json")
	var backupMeta domain.BackupMeta
	_ = p.storage.SafeReadJSON(backupMetaFile, &backupMeta)
	if backupMeta == nil {
		backupMeta = make(domain.BackupMeta)
	}
	idKey := strconv.Itoa(id)
	postBackup := backupMeta[idKey]
	nextBackupNum := len(postBackup.Backups) + 1
	postBackup.PostID = id
	postBackup.PostTitle = title
	postBackup.Backups = append(postBackup.Backups, domain.BackupEntry{
		BackupNumber: nextBackupNum,
		Filename:     fmt.Sprintf("%d-%d.html", id, nextBackupNum),
		Date:         date,
		Title:        title,
	})
	backupMeta[idKey] = postBackup
	_ = p.storage.SafeWriteJSON(backupMetaFile, backupMeta)
	_ = p.storage.SafeWriteFile(filepath.Join(backupDir, fmt.Sprintf("%d-%d.html", id, nextBackupNum)), []byte(articleHTML))
}
