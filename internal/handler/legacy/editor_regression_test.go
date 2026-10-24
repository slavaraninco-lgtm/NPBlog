package legacy_test

import (
	"encoding/json"
	"golang.org/x/crypto/bcrypt"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestPasswordSettingsHashAndVerify(t *testing.T) {
	h, root := setupTestHandler(t)
	t.Cleanup(func() { os.RemoveAll(root) })
	save := func(body string) bool {
		w := httptest.NewRecorder()
		h.HandleSaveEditorSettings(w, httptest.NewRequest("POST", "/save_editor_settings.php", strings.NewReader(body)))
		var result map[string]interface{}
		if err := json.Unmarshal(w.Body.Bytes(), &result); err != nil {
			t.Fatal(err)
		}
		return result["success"] == true
	}
	if !save(`{"password_enabled":true,"new_password":"test-password"}`) {
		t.Fatal("enable failed")
	}
	content, _ := os.ReadFile(filepath.Join(root, "editor_settings.json"))
	var settings map[string]interface{}
	json.Unmarshal(content, &settings)
	if strings.Contains(string(content), "test-password") || bcrypt.CompareHashAndPassword([]byte(settings["password_hash"].(string)), []byte("test-password")) != nil {
		t.Fatal("password not hashed")
	}
	if save(`{"password_enabled":false,"old_password":"wrong"}`) {
		t.Fatal("wrong password accepted")
	}
	if !save(`{"password_enabled":false,"old_password":"test-password"}`) {
		t.Fatal("disable failed")
	}
}

func TestCreateBlogAndServeVideo(t *testing.T) {
	h, root := setupTestHandler(t)
	t.Cleanup(func() { os.RemoveAll(root) })
	for _, rel := range []string{"blog/assets/style.css", "blog/templates/NPBlog/main.html", "blog/template_post.html"} {
		dest := filepath.Join(root, "data", filepath.FromSlash(rel))
		if err := os.MkdirAll(filepath.Dir(dest), 0755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(dest, []byte("template"), 0600); err != nil {
			t.Fatal(err)
		}
	}
	w := httptest.NewRecorder()
	h.HandleCreateBlog(w, httptest.NewRequest("POST", "/create_blog.php", strings.NewReader(`{"title":"New","folder":"data_new","make_active":true}`)))
	var result map[string]interface{}
	json.Unmarshal(w.Body.Bytes(), &result)
	if result["success"] != true || result["path"] != "data_new" || result["title"] != "New" || result["make_active"] != true {
		t.Fatalf("bad response: %s", w.Body.String())
	}
	video := filepath.Join(root, "data_new", "files", "videos", "sample.mp4")
	os.MkdirAll(filepath.Dir(video), 0755)
	os.WriteFile(video, []byte("0123456789"), 0600)
	req := httptest.NewRequest("GET", "/data_new/files/videos/sample.mp4", nil)
	req.Header.Set("Range", "bytes=0-3")
	w = httptest.NewRecorder()
	h.HandleBlogFile(w, req)
	if w.Code != 206 || w.Body.String() != "0123" || !strings.HasPrefix(w.Header().Get("Content-Type"), "video/mp4") {
		t.Fatalf("video response: %d %v", w.Code, w.Header())
	}
	w = httptest.NewRecorder()
	h.HandleCreateBlog(w, httptest.NewRequest("POST", "/create_blog.php", strings.NewReader(`{"folder":"data_new"}`)))
	json.Unmarshal(w.Body.Bytes(), &result)
	if result["success"] == true {
		t.Fatal("existing directory accepted")
	}
	if _, err := os.Stat(video); err != nil {
		t.Fatal("existing data lost")
	}
}
