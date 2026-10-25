package legacy_test

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"net/url"
	"strings"
	"testing"
)

func TestHandleFtp_GetAndSaveSettings(t *testing.T) {
	handler, _ := setupTestHandler(t)

	// 1. Сохранение параметров
	form := url.Values{
		"ftpServer":       {"ftp.mysite.com"},
		"ftpProtocol":     {"sftp"},
		"ftpPort":         {"22"},
		"ftpUsername":     {"john"},
		"ftpPassword":     {"secret"},
		"ftpDirectory":    {"/public_html"},
		"ftpSsl":          {"0"},
		"ftpSkipExisting": {"1"},
		"remember":        {"1"},
	}

	reqSave := httptest.NewRequest(http.MethodPost, "/ftp.php", strings.NewReader(form.Encode()))
	reqSave.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	wSave := httptest.NewRecorder()
	handler.HandleFtp(wSave, reqSave)

	if wSave.Code != http.StatusOK {
		t.Fatalf("HandleFtp save failed with status %d: %s", wSave.Code, wSave.Body.String())
	}

	var saveResp map[string]interface{}
	if err := json.Unmarshal(wSave.Body.Bytes(), &saveResp); err != nil || saveResp["success"] != true {
		t.Fatalf("unexpected save response: %s", wSave.Body.String())
	}

	// 2. Получение сохраненных параметров
	reqGet := httptest.NewRequest(http.MethodGet, "/ftp.php?action=get_settings", nil)
	wGet := httptest.NewRecorder()
	handler.HandleFtp(wGet, reqGet)

	if wGet.Code != http.StatusOK {
		t.Fatalf("HandleFtp get_settings failed with status %d: %s", wGet.Code, wGet.Body.String())
	}

	var getResp struct {
		Success     bool                   `json:"success"`
		Credentials map[string]interface{} `json:"credentials"`
	}
	if err := json.Unmarshal(wGet.Body.Bytes(), &getResp); err != nil || !getResp.Success {
		t.Fatalf("unexpected get response: %s", wGet.Body.String())
	}

	creds := getResp.Credentials
	if creds["ftpServer"] != "ftp.mysite.com" {
		t.Errorf("expected ftpServer ftp.mysite.com, got %v", creds["ftpServer"])
	}
	if creds["ftpProtocol"] != "sftp" {
		t.Errorf("expected ftpProtocol sftp, got %v", creds["ftpProtocol"])
	}
	if creds["ftpDirectory"] != "/public_html" {
		t.Errorf("expected ftpDirectory /public_html, got %v", creds["ftpDirectory"])
	}
	// Пароль не должен возвращаться клиенту
	if p, ok := creds["ftpPassword"]; ok && p != "" && p != nil {
		t.Errorf("ftpPassword should be omitted or empty, got %v", p)
	}

	// 3. Сброс настроек
	resetForm := url.Values{
		"action": {"reset"},
	}
	reqReset := httptest.NewRequest(http.MethodPost, "/ftp.php", strings.NewReader(resetForm.Encode()))
	reqReset.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	wReset := httptest.NewRecorder()
	handler.HandleFtp(wReset, reqReset)

	if wReset.Code != http.StatusOK {
		t.Fatalf("HandleFtp reset failed with status %d", wReset.Code)
	}

	// Проверяем, что настройки сброшены
	wGet2 := httptest.NewRecorder()
	handler.HandleFtp(wGet2, reqGet)
	var getResp2 struct {
		Success     bool                   `json:"success"`
		Credentials map[string]interface{} `json:"credentials"`
	}
	_ = json.Unmarshal(wGet2.Body.Bytes(), &getResp2)
	if getResp2.Credentials != nil && len(getResp2.Credentials) > 0 {
		t.Errorf("credentials should be nil after reset, got %+v", getResp2.Credentials)
	}
}

func TestHandleFtpUploadStream_Validation(t *testing.T) {
	handler, _ := setupTestHandler(t)

	// Запрос без обязательных полей
	req := httptest.NewRequest(http.MethodPost, "/ftp_upload_stream.php", strings.NewReader(""))
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	w := httptest.NewRecorder()

	handler.HandleFtpUploadStream(w, req)

	respBody := w.Body.String()
	if !strings.Contains(respBody, `"type":"error"`) {
		t.Errorf("expected error event in response, got: %s", respBody)
	}

	// Запрос со всеми заполненными полями
	form := url.Values{
		"ftpServer":       {"w10.host"},
		"ftpProtocol":     {"ftp"},
		"ftpPort":         {"21"},
		"ftpUsername":     {"g"},
		"ftpPassword":     {"secret"},
		"ftpDirectory":    {"/"},
		"ftpSsl":          {"0"},
		"ftpSkipExisting": {"0"},
		"blogToUpload":    {"data"},
	}
	req2 := httptest.NewRequest(http.MethodPost, "/ftp_upload_stream.php", strings.NewReader(form.Encode()))
	// Обратите внимание: fetch с URLSearchParams отправляет Content-Type: application/x-www-form-urlencoded;charset=UTF-8
	req2.Header.Set("Content-Type", "application/x-www-form-urlencoded;charset=UTF-8")
	w2 := httptest.NewRecorder()

	handler.HandleFtpUploadStream(w2, req2)
	respBody2 := w2.Body.String()
	t.Logf("Response with filled form: %s", respBody2)
	if strings.Contains(respBody2, "Пожалуйста, заполните все обязательные поля") {
		t.Fatalf("Validation unexpectedly failed on filled form: %s", respBody2)
	}
}
