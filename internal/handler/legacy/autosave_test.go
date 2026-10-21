package legacy_test

import (
	"bytes"
	"encoding/json"
	"mime/multipart"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"
)

func TestAutosavesFlow(t *testing.T) {
	handler, tmpDir := setupTestHandler(t)
	defer os.RemoveAll(tmpDir)

	// 1. Save Autosave via Multipart Form (how browser sends it)
	body := &bytes.Buffer{}
	writer := multipart.NewWriter(body)
	_ = writer.WriteField("title", "Черновая статья про космос")
	_ = writer.WriteField("content", "<p>Текст про черные дыры</p>")
	_ = writer.Close()

	req := httptest.NewRequest(http.MethodPost, "/save_autosave.php", body)
	req.Header.Set("Content-Type", writer.FormDataContentType())
	w := httptest.NewRecorder()

	handler.HandleSaveAutosave(w, req)
	if w.Code != http.StatusOK {
		t.Fatalf("Expected 200, got %d", w.Code)
	}

	var saveResp map[string]interface{}
	_ = json.Unmarshal(w.Body.Bytes(), &saveResp)
	if saveResp["success"] != true {
		t.Fatalf("Expected success: true, got %v", saveResp)
	}
	savedID, ok := saveResp["id"].(string)
	if !ok || savedID == "" {
		t.Fatalf("Expected non-empty id, got %v", saveResp["id"])
	}

	// 2. Get All Autosaves
	reqAll := httptest.NewRequest(http.MethodGet, "/get_all_autosaves.php", nil)
	wAll := httptest.NewRecorder()
	handler.HandleGetAllAutosaves(wAll, reqAll)

	var allResp struct {
		Success   bool                     `json:"success"`
		Autosaves []map[string]interface{} `json:"autosaves"`
	}
	_ = json.Unmarshal(wAll.Body.Bytes(), &allResp)
	if !allResp.Success || len(allResp.Autosaves) != 1 {
		t.Fatalf("Expected 1 autosave in list, got: %+v", allResp)
	}
	if allResp.Autosaves[0]["title"] != "Черновая статья про космос" {
		t.Errorf("Expected title 'Черновая статья про космос', got %v", allResp.Autosaves[0]["title"])
	}
	if allResp.Autosaves[0]["id"] != savedID {
		t.Errorf("Expected id '%s', got %v", savedID, allResp.Autosaves[0]["id"])
	}

	// 3. Load Autosave by ID via POST JSON (exact request from editor.html loadAutosaveById)
	postBody, _ := json.Marshal(map[string]string{"id": savedID})
	reqGet := httptest.NewRequest(http.MethodPost, "/get_autosave.php", bytes.NewReader(postBody))
	reqGet.Header.Set("Content-Type", "application/json")
	wGet := httptest.NewRecorder()

	handler.HandleGetAutosave(wGet, reqGet)
	if wGet.Code != http.StatusOK {
		t.Fatalf("POST /get_autosave.php failed with code %d", wGet.Code)
	}

	var getResp struct {
		Success  bool                   `json:"success"`
		Autosave map[string]interface{} `json:"autosave"`
	}
	_ = json.Unmarshal(wGet.Body.Bytes(), &getResp)
	if !getResp.Success || getResp.Autosave == nil {
		t.Fatalf("Expected success with autosave data, got %v", wGet.Body.String())
	}
	if getResp.Autosave["title"] != "Черновая статья про космос" {
		t.Errorf("Expected title 'Черновая статья про космос', got %v", getResp.Autosave["title"])
	}
	if getResp.Autosave["content"] != "<p>Текст про черные дыры</p>" {
		t.Errorf("Expected content '<p>Текст про черные дыры</p>', got %v", getResp.Autosave["content"])
	}

	// 4. Delete Autosave by ID via POST JSON
	delBody, _ := json.Marshal(map[string]string{"id": savedID})
	reqDel := httptest.NewRequest(http.MethodPost, "/delete_autosave.php", bytes.NewReader(delBody))
	reqDel.Header.Set("Content-Type", "application/json")
	wDel := httptest.NewRecorder()

	handler.HandleDeleteAutosave(wDel, reqDel)
	if wDel.Code != http.StatusOK {
		t.Fatalf("Expected 200, got %d", wDel.Code)
	}

	// Verify file is gone from autosave folder
	autosavePath := filepath.Join(tmpDir, "autosave", "autosave_"+savedID+".json")
	if _, err := os.Stat(autosavePath); !os.IsNotExist(err) {
		t.Errorf("Expected autosave file to be deleted, but it exists: %s", autosavePath)
	}
}
