package main

import (
	"bytes"
	"crypto/rand"
	"encoding/json"
	"fmt"
	"io"
	"mime/multipart"
	"net/http"
	"net/http/cookiejar"
	"net/url"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"sync"
	"time"
)

// TestRecord captures the outcome of a single test assertion block inside a suite.
type TestRecord struct {
	Suite      string  `json:"suite"`
	Name       string  `json:"name"`
	Status     string  `json:"status"` // PASS, FAIL, SKIP
	DurationMs float64 `json:"duration_ms"`
	Error      string  `json:"error,omitempty"`
}

// TestRunner tracks test execution, formatted console output, and summary reporting.
type TestRunner struct {
	mu      sync.Mutex
	Verbose bool
	Filter  string
	Records []TestRecord
	Passed  int
	Failed  int
	Skipped int
	StartAt time.Time
}

// NewTestRunner creates an initialized runner with start timestamp.
func NewTestRunner(verbose bool, filter string) *TestRunner {
	return &TestRunner{
		Verbose: verbose,
		Filter:  strings.ToLower(strings.TrimSpace(filter)),
		StartAt: time.Now(),
	}
}

// Suite executes a named suite unless filtered out, collecting errors cleanly.
func (tr *TestRunner) Suite(name string, fn func(s *SuiteContext)) {
	if tr.Filter != "" && !strings.Contains(strings.ToLower(name), tr.Filter) {
		return
	}
	fmt.Printf("\n=== [%s] ===\n", name)
	ctx := &SuiteContext{runner: tr, suiteName: name}
	fn(ctx)
}

// SuiteContext provides Fail/Skip/Check helpers scoped to a single suite.
type SuiteContext struct {
	runner    *TestRunner
	suiteName string
}

// Case runs a sub-case with panic recovery so unexpected bugs fail safely without aborting other suites.
func (s *SuiteContext) Case(name string, fn func() error) {
	start := time.Now()
	var err error
	func() {
		defer func() {
			if rec := recover(); rec != nil {
				err = fmt.Errorf("panic: %v", rec)
			}
		}()
		err = fn()
	}()

	dur := float64(time.Since(start).Microseconds()) / 1000.0
	status := "PASS"
	errMsg := ""
	if err != nil {
		if strings.HasPrefix(err.Error(), "SKIP:") {
			status = "SKIP"
			errMsg = strings.TrimSpace(strings.TrimPrefix(err.Error(), "SKIP:"))
		} else {
			status = "FAIL"
			errMsg = err.Error()
		}
	}

	s.runner.mu.Lock()
	defer s.runner.mu.Unlock()
	s.runner.Records = append(s.runner.Records, TestRecord{
		Suite:      s.suiteName,
		Name:       name,
		Status:     status,
		DurationMs: dur,
		Error:      errMsg,
	})
	switch status {
	case "PASS":
		s.runner.Passed++
		if s.runner.Verbose {
			fmt.Printf("  [PASS] %-54s (%.1f ms)\n", name, dur)
		} else {
			fmt.Printf("  [PASS] %s\n", name)
		}
	case "SKIP":
		s.runner.Skipped++
		fmt.Printf("  [SKIP] %s — %s\n", name, errMsg)
	case "FAIL":
		s.runner.Failed++
		fmt.Printf("  [FAIL] %s\n         Причина: %s\n", name, errMsg)
	}
}

// WriteReport writes a JSON + readable report if reportPath is set.
func (tr *TestRunner) WriteReport(reportPath string) error {
	if reportPath == "" {
		return nil
	}
	if dir := filepath.Dir(reportPath); dir != "" && dir != "." {
		_ = os.MkdirAll(dir, 0755)
	}
	payload := map[string]interface{}{
		"generated_at": time.Now().Format(time.RFC3339),
		"duration_ms":  float64(time.Since(tr.StartAt).Microseconds()) / 1000.0,
		"passed":       tr.Passed,
		"failed":       tr.Failed,
		"skipped":      tr.Skipped,
		"total":        len(tr.Records),
		"records":      tr.Records,
	}
	raw, err := json.MarshalIndent(payload, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(reportPath, raw, 0644)
}

// NPClient is a cookie- and CSRF-aware HTTP test client for NPBlog.
type NPClient struct {
	BaseURL     string
	HTTP        *http.Client
	CSRFToken   string
	BearerToken string
	BlogHeader  string
}

// NewNPClient creates a client with an isolated in-memory cookie jar.
func NewNPClient(baseURL string) *NPClient {
	jar, _ := cookiejar.New(nil)
	return &NPClient{
		BaseURL: strings.TrimRight(baseURL, "/"),
		HTTP: &http.Client{
			Jar:     jar,
			Timeout: 25 * time.Second,
			CheckRedirect: func(req *http.Request, via []*http.Request) error {
				return http.ErrUseLastResponse
			},
		},
	}
}

// CloneSessionFree returns a clean unauthenticated client sharing only BaseURL.
func (c *NPClient) CloneFresh() *NPClient {
	return NewNPClient(c.BaseURL)
}

// HTTPResp wraps raw status, headers, body, and lazy JSON map decoding.
type HTTPResp struct {
	Status int
	Header http.Header
	Body   []byte
}

// JSON decodes the body into map[string]interface{}.
func (r *HTTPResp) JSON() (map[string]interface{}, error) {
	var out map[string]interface{}
	if err := json.Unmarshal(r.Body, &out); err != nil {
		return nil, fmt.Errorf("invalid JSON (%v): %q", err, truncateStr(string(r.Body), 240))
	}
	return out, nil
}

// MustOKJSON verifies status 200 and parses JSON, optionally asserting success == true.
func (r *HTTPResp) MustSuccessJSON() (map[string]interface{}, error) {
	if r.Status != http.StatusOK {
		return nil, fmt.Errorf("HTTP %d: %s", r.Status, truncateStr(string(r.Body), 240))
	}
	m, err := r.JSON()
	if err != nil {
		return nil, err
	}
	if okVal, has := m["success"]; has {
		if b, ok := okVal.(bool); !ok || !b {
			return m, fmt.Errorf("expected success=true, got: %s", truncateStr(string(r.Body), 260))
		}
	}
	return m, nil
}

var csrfTokenRe = regexp.MustCompile(`(?i)(?:csrf[_-]?token['"]?\s*[:=]\s*['"]|<meta\s+name=["']csrf-token["']\s+content=["'])([a-zA-Z0-9_-]{8,})`)

// RefreshCSRF fetches GET / and extracts the session cookie + embedded CSRF token.
func (c *NPClient) RefreshCSRF() (string, error) {
	resp, err := c.RawReq(http.MethodGet, "/", nil, nil)
	if err != nil {
		return "", err
	}
	if resp.Status != http.StatusOK {
		return "", fmt.Errorf("GET / returned %d", resp.Status)
	}
	html := string(resp.Body)
	if m := csrfTokenRe.FindStringSubmatch(html); len(m) >= 2 {
		c.CSRFToken = m[1]
		return c.CSRFToken, nil
	}
	return "", fmt.Errorf("CSRF token not found in GET / response (%d bytes)", len(resp.Body))
}

// SetCookie sets a cookie explicitly on BaseURL in the client's jar.
func (c *NPClient) SetCookie(name, value string) {
	u, err := url.Parse(c.BaseURL)
	if err != nil || c.HTTP.Jar == nil {
		return
	}
	c.HTTP.Jar.SetCookies(u, []*http.Cookie{{Name: name, Value: value, Path: "/"}})
}

// GetCookie reads a named cookie from BaseURL jar.
func (c *NPClient) GetCookie(name string) string {
	u, err := url.Parse(c.BaseURL)
	if err != nil || c.HTTP.Jar == nil {
		return ""
	}
	for _, ck := range c.HTTP.Jar.Cookies(u) {
		if ck.Name == name {
			return ck.Value
		}
	}
	return ""
}

// RawReq performs an HTTP request attaching client auth/CSRF/blog headers unless overridden.
func (c *NPClient) RawReq(method, path string, body []byte, headers map[string]string) (*HTTPResp, error) {
	var bodyReader io.Reader
	if body != nil {
		bodyReader = bytes.NewReader(body)
	}
	targetURL := c.BaseURL + path
	req, err := http.NewRequest(method, targetURL, bodyReader)
	if err != nil {
		return nil, err
	}
	if c.BearerToken != "" {
		req.Header.Set("Authorization", "Bearer "+c.BearerToken)
	}
	if c.BlogHeader != "" {
		req.Header.Set("X-Blog-Path", c.BlogHeader)
	}
	if method != http.MethodGet && method != http.MethodHead && method != http.MethodOptions && c.CSRFToken != "" {
		req.Header.Set("X-CSRF-Token", c.CSRFToken)
	}
	for k, v := range headers {
		if v == "__DELETE__" {
			req.Header.Del(k)
		} else {
			req.Header.Set(k, v)
		}
	}

	res, err := c.HTTP.Do(req)
	if err != nil {
		return nil, err
	}
	defer res.Body.Close()
	data, err := io.ReadAll(res.Body)
	if err != nil {
		return nil, err
	}
	return &HTTPResp{
		Status: res.StatusCode,
		Header: res.Header,
		Body:   data,
	}, nil
}

// JSONReq encodes payload as JSON and sends an HTTP request.
func (c *NPClient) JSONReq(method, path string, payload interface{}, headers map[string]string) (*HTTPResp, error) {
	var raw []byte
	var err error
	if payload != nil {
		switch v := payload.(type) {
		case string:
			raw = []byte(v)
		case []byte:
			raw = v
		default:
			raw, err = json.Marshal(v)
			if err != nil {
				return nil, err
			}
		}
	}
	h := map[string]string{"Content-Type": "application/json", "Accept": "application/json"}
	for k, v := range headers {
		h[k] = v
	}
	return c.RawReq(method, path, raw, h)
}

// UploadFileItem describes one file part for multipart uploads.
type UploadFileItem struct {
	Field    string
	Filename string
	Data     []byte
}

// MultipartReq builds and posts a multipart/form-data request.
func (c *NPClient) MultipartReq(path string, fields map[string]string, files []UploadFileItem, extraHeaders map[string]string) (*HTTPResp, error) {
	var buf bytes.Buffer
	mw := multipart.NewWriter(&buf)
	for k, v := range fields {
		if err := mw.WriteField(k, v); err != nil {
			return nil, err
		}
	}
	for _, f := range files {
		fw, err := mw.CreateFormFile(f.Field, f.Filename)
		if err != nil {
			return nil, err
		}
		if _, err := fw.Write(f.Data); err != nil {
			return nil, err
		}
	}
	if err := mw.Close(); err != nil {
		return nil, err
	}
	h := map[string]string{"Content-Type": mw.FormDataContentType()}
	for k, v := range extraHeaders {
		h[k] = v
	}
	return c.RawReq(http.MethodPost, path, buf.Bytes(), h)
}

func truncateStr(s string, maxLen int) string {
	s = strings.TrimSpace(strings.ReplaceAll(strings.ReplaceAll(s, "\r", " "), "\n", " "))
	if len(s) <= maxLen {
		return s
	}
	return s[:maxLen] + "..."
}

func randomHex(nBytes int) string {
	b := make([]byte, nBytes)
	_, _ = rand.Read(b)
	return fmt.Sprintf("%x", b)
}
