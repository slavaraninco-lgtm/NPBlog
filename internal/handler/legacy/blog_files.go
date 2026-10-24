package legacy

import (
	"net/http"
	"os"
	"path/filepath"
	"strings"
)

// initializeBlog copies only presentation files into the newly created, empty directory.
func (l *LegacyHandler) initializeBlog(target, title string) error {
	source := filepath.Join(l.cfg.BaseDir, "data")
	for _, rel := range []string{"blog.html", "blog/template_post.html", "blog/assets", "blog/templates"} {
		origin := filepath.Join(source, filepath.FromSlash(rel))
		if err := filepath.Walk(origin, func(path string, info os.FileInfo, err error) error {
			if err != nil {
				return err
			}
			if info.Mode()&os.ModeSymlink != 0 {
				return nil
			}
			relative, err := filepath.Rel(source, path)
			if err != nil {
				return err
			}
			dest := filepath.Join(target, relative)
			if info.IsDir() {
				return os.MkdirAll(dest, 0755)
			}
			content, err := os.ReadFile(path)
			if err != nil {
				return err
			}
			return l.storage.SafeWriteFile(dest, content)
		}); err != nil {
			return err
		}
	}
	return l.storage.SafeWriteJSON(filepath.Join(target, "blog-view-settings.json"), map[string]string{"title": title})
}

// HandleBlogFile resolves public URLs for registered blogs, including blogs added after startup.
func (l *LegacyHandler) HandleBlogFile(w http.ResponseWriter, r *http.Request) {
	parts := strings.SplitN(strings.TrimPrefix(r.URL.Path, "/"), "/", 2)
	if len(parts) != 2 {
		http.NotFound(w, r)
		return
	}
	settings := l.cfg.GetSettings()
	paths := append([]string{"data"}, settings.BlogPaths...)
	for _, root := range paths {
		if !filepath.IsAbs(root) {
			root = filepath.Join(l.cfg.BaseDir, root)
		}
		if filepath.Base(root) != parts[0] {
			continue
		}
		target, err := l.storage.ValidateSafePath(root, parts[1])
		if err != nil {
			break
		}
		root, err = filepath.EvalSymlinks(root)
		if err != nil {
			break
		}
		target, err = filepath.EvalSymlinks(target)
		if err != nil {
			break
		}
		relative, err := filepath.Rel(root, target)
		if err != nil || relative == ".." || strings.HasPrefix(relative, ".."+string(filepath.Separator)) {
			break
		}
		info, err := os.Stat(target)
		if err != nil || !info.Mode().IsRegular() {
			break
		}
		http.ServeFile(w, r, target)
		return
	}
	http.NotFound(w, r)
}
