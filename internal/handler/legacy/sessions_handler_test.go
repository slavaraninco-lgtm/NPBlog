package legacy_test

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"strings"
	"testing"
)

func TestSessionsHandler_Legacy(t *testing.T) {
	h, root := setupTestHandler(t)
	t.Cleanup(func() { os.RemoveAll(root) })

	// First login to get a session
	// Set password
	wSave := httptest.NewRecorder()
	h.HandleSaveEditorSettings(wSave, httptest.NewRequest("POST", "/save_editor_settings.php", strings.NewReader(`{"password_enabled":true,"new_password":"my-secret"}`)))

	// Login with correct password
	loginReq := httptest.NewRequest("POST", "/login.php", strings.NewReader(`{"password":"my-secret"}`))
	loginReq.Header.Set("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0.0.0")
	loginReq.RemoteAddr = "192.168.1.10:12345"
	wLogin := httptest.NewRecorder()
	h.HandleLogin(wLogin, loginReq)

	var loginResp map[string]interface{}
	_ = json.Unmarshal(wLogin.Body.Bytes(), &loginResp)
	if loginResp["success"] != true {
		t.Fatalf("expected successful login, got: %s", wLogin.Body.String())
	}

	cookie := wLogin.Result().Cookies()[0]
	if cookie.Name != "npblog_session" {
		t.Fatalf("expected npblog_session cookie, got %s", cookie.Name)
	}

	// 1. Get sessions
	getReq := httptest.NewRequest("GET", "/get_sessions.php", nil)
	getReq.AddCookie(cookie)
	wGet := httptest.NewRecorder()
	h.HandleGetSessions(wGet, getReq)

	var getResp struct {
		Success  bool `json:"success"`
		Sessions []struct {
			ID        string `json:"id"`
			Device    string `json:"device"`
			Browser   string `json:"browser"`
			IsCurrent bool   `json:"is_current"`
		} `json:"sessions"`
	}
	_ = json.Unmarshal(wGet.Body.Bytes(), &getResp)
	if !getResp.Success {
		t.Fatalf("expected success true in GetSessions")
	}
	if len(getResp.Sessions) < 1 {
		t.Fatalf("expected at least 1 session, got %d", len(getResp.Sessions))
	}
	// The current session should be first
	if !getResp.Sessions[0].IsCurrent || getResp.Sessions[0].ID != cookie.Value {
		t.Errorf("expected current session to be first")
	}
	if getResp.Sessions[0].Device != "Windows" || getResp.Sessions[0].Browser != "Chrome 124" {
		t.Errorf("expected Windows / Chrome 124, got %s / %s", getResp.Sessions[0].Device, getResp.Sessions[0].Browser)
	}

	// Terminate all others
	termOthersReq := httptest.NewRequest("POST", "/terminate_session.php", strings.NewReader(`{"all_others":true}`))
	termOthersReq.AddCookie(cookie)
	wTermOthers := httptest.NewRecorder()
	h.HandleTerminateSession(wTermOthers, termOthersReq)

	var termOthersResp map[string]interface{}
	_ = json.Unmarshal(wTermOthers.Body.Bytes(), &termOthersResp)
	if termOthersResp["success"] != true {
		t.Errorf("expected success for terminate others, got: %s", wTermOthers.Body.String())
	}

	// Now check sessions count is exactly 1
	wGetAfter := httptest.NewRecorder()
	h.HandleGetSessions(wGetAfter, getReq)
	var getRespAfter struct {
		Success  bool `json:"success"`
		Sessions []struct {
			ID        string `json:"id"`
			IsCurrent bool   `json:"is_current"`
		} `json:"sessions"`
	}
	_ = json.Unmarshal(wGetAfter.Body.Bytes(), &getRespAfter)
	if len(getRespAfter.Sessions) != 1 {
		t.Fatalf("expected exactly 1 session remaining after terminate all others, got %d", len(getRespAfter.Sessions))
	}

	// 2. Terminate session
	termReq := httptest.NewRequest("POST", "/terminate_session.php", strings.NewReader(`{"id":"`+cookie.Value+`"}`))
	termReq.AddCookie(cookie)
	wTerm := httptest.NewRecorder()
	h.HandleTerminateSession(wTerm, termReq)

	var termResp map[string]interface{}
	_ = json.Unmarshal(wTerm.Body.Bytes(), &termResp)
	if termResp["success"] != true || termResp["is_current"] != true {
		t.Errorf("expected terminate success=true, is_current=true, got: %s", wTerm.Body.String())
	}

	// Verify destroyed cookie was set
	cookies := wTerm.Result().Cookies()
	var destroyedCookie *http.Cookie
	for _, c := range cookies {
		if c.Name == "npblog_session" {
			destroyedCookie = c
			break
		}
	}
	if destroyedCookie == nil || destroyedCookie.MaxAge != -1 {
		t.Errorf("expected destroyed cookie with MaxAge=-1")
	}
}
