package service

import (
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"
	"time"

	"npblog/internal/config"
)

func TestAuthService_Sessions(t *testing.T) {
	tmpDir := t.TempDir()
	dataDir := filepath.Join(tmpDir, "data")
	_ = os.MkdirAll(dataDir, 0755)

	cfg := &config.AppConfig{
		BaseDir: tmpDir,
	}
	storage := NewStorageService(cfg)
	auth := NewAuthService(cfg, storage)

	// Create request 1 (Windows / Chrome)
	req1 := httptest.NewRequest("GET", "/", nil)
	req1.RemoteAddr = "192.168.1.50:12345"
	req1.Header.Set("User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36")
	w1 := httptest.NewRecorder()
	s1 := auth.CreateSession(w1, req1)
	if s1 == nil || s1.ID == "" {
		t.Fatalf("expected s1 to be created")
	}

	// Create request 2 (iPhone / Safari)
	req2 := httptest.NewRequest("GET", "/", nil)
	req2.RemoteAddr = "10.0.0.2:54321"
	req2.Header.Set("User-Agent", "Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1")
	w2 := httptest.NewRecorder()
	s2 := auth.CreateSession(w2, req2)
	if s2 == nil || s2.ID == "" {
		t.Fatalf("expected s2 to be created")
	}

	// Get active sessions for s1
	items := auth.GetActiveSessions(s1.ID)
	if len(items) != 2 {
		t.Fatalf("expected 2 active sessions, got %d", len(items))
	}
	if !items[0].IsCurrent || items[0].ID != s1.ID {
		t.Errorf("expected first session to be s1 (isCurrent=true), got %+v", items[0])
	}
	if items[0].Device != "Windows" || items[0].Browser != "Chrome 124" {
		t.Errorf("expected Windows / Chrome 124, got %s / %s", items[0].Device, items[0].Browser)
	}

	// Terminate s2
	if !auth.TerminateSession(s2.ID) {
		t.Errorf("expected s2 termination to succeed")
	}
	itemsAfter := auth.GetActiveSessions(s1.ID)
	if len(itemsAfter) != 1 || itemsAfter[0].ID != s1.ID {
		t.Errorf("expected only s1 to remain, got %+v", itemsAfter)
	}

	// Create s3 and test TerminateOtherSessions
	req3 := httptest.NewRequest("GET", "/", nil)
	w3 := httptest.NewRecorder()
	s3 := auth.CreateSession(w3, req3)
	if s3 == nil {
		t.Fatalf("expected s3 to be created")
	}

	count := auth.TerminateOtherSessions(s1.ID)
	if count != 1 {
		t.Errorf("expected 1 session terminated, got %d", count)
	}
	itemsFinal := auth.GetActiveSessions(s1.ID)
	if len(itemsFinal) != 1 || itemsFinal[0].ID != s1.ID {
		t.Errorf("expected only s1 remaining after TerminateOtherSessions")
	}

	// Test persistence reload
	time.Sleep(100 * time.Millisecond) // wait for async disk write
	auth2 := NewAuthService(cfg, storage)
	reloaded := auth2.GetActiveSessions(s1.ID)
	if len(reloaded) != 1 || reloaded[0].ID != s1.ID {
		t.Errorf("expected reloaded session to match s1, got %+v", reloaded)
	}
}
