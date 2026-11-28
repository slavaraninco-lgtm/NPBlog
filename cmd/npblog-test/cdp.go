package main

import (
	"bufio"
	"crypto/rand"
	"crypto/sha1"
	"encoding/base64"
	"encoding/binary"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
	"sync"
	"sync/atomic"
	"time"
)

// findChromiumBinary locates Edge, Chrome, or Chromium on the host system.
func findChromiumBinary(explicit string) string {
	if explicit != "" {
		if _, err := os.Stat(explicit); err == nil {
			return explicit
		}
	}
	var candidates []string
	switch runtime.GOOS {
	case "windows":
		candidates = []string{
			`C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe`,
			`C:\Program Files\Microsoft\Edge\Application\msedge.exe`,
			`C:\Program Files\Google\Chrome\Application\chrome.exe`,
			`C:\Program Files (x86)\Google\Chrome\Application\chrome.exe`,
		}
	default:
		candidates = []string{"chromium", "chromium-browser", "google-chrome", "microsoft-edge"}
	}
	for _, c := range candidates {
		if filepath.IsAbs(c) {
			if _, err := os.Stat(c); err == nil {
				return c
			}
		} else if p, err := exec.LookPath(c); err == nil {
			return p
		}
	}
	for _, name := range []string{"msedge", "chrome", "chromium"} {
		if p, err := exec.LookPath(name); err == nil {
			return p
		}
	}
	return ""
}

// cdpClient speaks Chrome DevTools Protocol over a minimal stdlib RFC-6455 WebSocket.
type cdpClient struct {
	conn      net.Conn
	br        *bufio.Reader
	writeMu   sync.Mutex
	pendMu    sync.Mutex
	pending   map[int64]chan cdpResponse
	nextID    int64
	cmd       *exec.Cmd
	profile   string
	eventsMu  sync.Mutex
	jsErrors  []string
	netErrors []string
	closed    int32
}

type cdpResponse struct {
	ID     int64                  `json:"id"`
	Result json.RawMessage        `json:"result"`
	Error  map[string]interface{} `json:"error"`
}

// launchHeadlessCDP starts a headless browser on an ephemeral debugging port and attaches to its first page target.
func launchHeadlessCDP(binaryPath string) (*cdpClient, error) {
	ln, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		return nil, err
	}
	debugPort := ln.Addr().(*net.TCPAddr).Port
	_ = ln.Close()

	profileDir, err := os.MkdirTemp("", "npblog-cdp-profile-*")
	if err != nil {
		return nil, err
	}

	args := []string{
		"--headless=new",
		fmt.Sprintf("--remote-debugging-port=%d", debugPort),
		"--user-data-dir=" + profileDir,
		"--no-first-run",
		"--no-default-browser-check",
		"--disable-gpu",
		"--disable-extensions",
		"--window-size=1440,900",
		"about:blank",
	}
	cmd := exec.Command(binaryPath, args...)
	if err := cmd.Start(); err != nil {
		_ = os.RemoveAll(profileDir)
		return nil, err
	}

	wsURL, err := waitForCDPTarget(debugPort, 12*time.Second)
	if err != nil {
		_ = cmd.Process.Kill()
		_, _ = cmd.Process.Wait()
		_ = os.RemoveAll(profileDir)
		return nil, err
	}

	conn, br, err := dialRFC6455(wsURL)
	if err != nil {
		_ = cmd.Process.Kill()
		_, _ = cmd.Process.Wait()
		_ = os.RemoveAll(profileDir)
		return nil, err
	}

	cli := &cdpClient{
		conn:    conn,
		br:      br,
		pending: make(map[int64]chan cdpResponse),
		cmd:     cmd,
		profile: profileDir,
	}
	go cli.readLoop()

	for _, domain := range []string{"Page.enable", "Runtime.enable", "Log.enable", "Network.enable"} {
		if _, err := cli.Call(domain, nil); err != nil {
			cli.Close()
			return nil, fmt.Errorf("CDP %s: %w", domain, err)
		}
	}
	return cli, nil
}

func waitForCDPTarget(port int, timeout time.Duration) (string, error) {
	deadline := time.Now().Add(timeout)
	listURL := fmt.Sprintf("http://127.0.0.1:%d/json/list", port)
	hc := &http.Client{Timeout: 800 * time.Millisecond}

	for time.Now().Before(deadline) {
		resp, err := hc.Get(listURL)
		if err == nil {
			body, _ := io.ReadAll(resp.Body)
			_ = resp.Body.Close()
			var targets []struct {
				Type                 string `json:"type"`
				WebSocketDebuggerURL string `json:"webSocketDebuggerUrl"`
			}
			if json.Unmarshal(body, &targets) == nil {
				for _, t := range targets {
					if t.Type == "page" && t.WebSocketDebuggerURL != "" {
						return t.WebSocketDebuggerURL, nil
					}
				}
			}
		}
		time.Sleep(150 * time.Millisecond)
	}
	return "", fmt.Errorf("timeout waiting for browser DevTools on port %d", port)
}

func dialRFC6455(wsURL string) (net.Conn, *bufio.Reader, error) {
	u, err := url.Parse(wsURL)
	if err != nil {
		return nil, nil, err
	}
	conn, err := net.DialTimeout("tcp", u.Host, 5*time.Second)
	if err != nil {
		return nil, nil, err
	}
	keyBytes := make([]byte, 16)
	_, _ = rand.Read(keyBytes)
	wsKey := base64.StdEncoding.EncodeToString(keyBytes)

	reqPath := u.RequestURI()
	if reqPath == "" {
		reqPath = "/"
	}
	reqStr := fmt.Sprintf(
		"GET %s HTTP/1.1\r\nHost: %s\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: %s\r\nSec-WebSocket-Version: 13\r\n\r\n",
		reqPath, u.Host, wsKey,
	)
	if _, err := conn.Write([]byte(reqStr)); err != nil {
		_ = conn.Close()
		return nil, nil, err
	}

	br := bufio.NewReaderSize(conn, 64*1024)
	resp, err := http.ReadResponse(br, &http.Request{Method: http.MethodGet})
	if err != nil {
		_ = conn.Close()
		return nil, nil, err
	}
	if resp.StatusCode != http.StatusSwitchingProtocols {
		_ = conn.Close()
		return nil, nil, fmt.Errorf("websocket handshake HTTP %d", resp.StatusCode)
	}
	h := sha1.Sum([]byte(wsKey + "258EAFA5-E914-47DA-95CA-C5AB0DC85B11"))
	expectedAccept := base64.StdEncoding.EncodeToString(h[:])
	if resp.Header.Get("Sec-WebSocket-Accept") != expectedAccept {
		_ = conn.Close()
		return nil, nil, errors.New("invalid Sec-WebSocket-Accept header")
	}
	return conn, br, nil
}

func (c *cdpClient) writeFrame(opcode byte, payload []byte) error {
	c.writeMu.Lock()
	defer c.writeMu.Unlock()

	var hdr []byte
	hdr = append(hdr, 0x80|opcode)
	n := len(payload)
	switch {
	case n < 126:
		hdr = append(hdr, 0x80|byte(n))
	case n <= 65535:
		hdr = append(hdr, 0x80|126, byte(n>>8), byte(n))
	default:
		hdr = append(hdr, 0x80|127)
		var b8 [8]byte
		binary.BigEndian.PutUint64(b8[:], uint64(n))
		hdr = append(hdr, b8[:]...)
	}

	var mask [4]byte
	_, _ = rand.Read(mask[:])
	hdr = append(hdr, mask[:]...)

	masked := make([]byte, n)
	for i := 0; i < n; i++ {
		masked[i] = payload[i] ^ mask[i%4]
	}

	if _, err := c.conn.Write(hdr); err != nil {
		return err
	}
	if n > 0 {
		if _, err := c.conn.Write(masked); err != nil {
			return err
		}
	}
	return nil
}

func (c *cdpClient) readLoop() {
	var fragBuf []byte
	for {
		b0, err := c.br.ReadByte()
		if err != nil {
			return
		}
		b1, err := c.br.ReadByte()
		if err != nil {
			return
		}
		fin := (b0 & 0x80) != 0
		opcode := b0 & 0x0f
		masked := (b1 & 0x80) != 0
		length := uint64(b1 & 0x7f)

		if length == 126 {
			var ext [2]byte
			if _, err := io.ReadFull(c.br, ext[:]); err != nil {
				return
			}
			length = uint64(binary.BigEndian.Uint16(ext[:]))
		} else if length == 127 {
			var ext [8]byte
			if _, err := io.ReadFull(c.br, ext[:]); err != nil {
				return
			}
			length = binary.BigEndian.Uint64(ext[:])
		}

		var mask [4]byte
		if masked {
			if _, err := io.ReadFull(c.br, mask[:]); err != nil {
				return
			}
		}

		payload := make([]byte, length)
		if length > 0 {
			if _, err := io.ReadFull(c.br, payload); err != nil {
				return
			}
		}
		if masked {
			for i := range payload {
				payload[i] ^= mask[i%4]
			}
		}

		switch opcode {
		case 0x8: // close
			return
		case 0x9: // ping
			_ = c.writeFrame(0xA, payload)
			continue
		case 0xA: // pong
			continue
		case 0x1, 0x0:
			fragBuf = append(fragBuf, payload...)
			if fin {
				c.handleMessage(fragBuf)
				fragBuf = nil
			}
		}
	}
}

func (c *cdpClient) handleMessage(raw []byte) {
	var msg struct {
		ID     int64           `json:"id"`
		Method string          `json:"method"`
		Params json.RawMessage `json:"params"`
		Result json.RawMessage `json:"result"`
		Error  map[string]interface{} `json:"error"`
	}
	if json.Unmarshal(raw, &msg) != nil {
		return
	}
	if msg.ID > 0 {
		c.pendMu.Lock()
		ch := c.pending[msg.ID]
		delete(c.pending, msg.ID)
		c.pendMu.Unlock()
		if ch != nil {
			ch <- cdpResponse{ID: msg.ID, Result: msg.Result, Error: msg.Error}
		}
		return
	}

	switch msg.Method {
	case "Runtime.exceptionThrown":
		c.eventsMu.Lock()
		c.jsErrors = append(c.jsErrors, truncateStr(string(msg.Params), 300))
		c.eventsMu.Unlock()
	case "Log.entryAdded":
		var p struct {
			Entry struct {
				Level string `json:"level"`
				Text  string `json:"text"`
				URL   string `json:"url"`
			} `json:"entry"`
		}
		if json.Unmarshal(msg.Params, &p) == nil && p.Entry.Level == "error" {
			if !isIgnoredOptionalURL(p.Entry.URL) {
				c.eventsMu.Lock()
				c.netErrors = append(c.netErrors, fmt.Sprintf("%s (%s)", p.Entry.Text, p.Entry.URL))
				c.eventsMu.Unlock()
			}
		}
	case "Network.responseReceived":
		var p struct {
			Response struct {
				URL    string `json:"url"`
				Status int    `json:"status"`
			} `json:"response"`
		}
		if json.Unmarshal(msg.Params, &p) == nil && p.Response.Status >= 400 {
			if !isIgnoredOptionalURL(p.Response.URL) {
				c.eventsMu.Lock()
				c.netErrors = append(c.netErrors, fmt.Sprintf("HTTP %d %s", p.Response.Status, p.Response.URL))
				c.eventsMu.Unlock()
			}
		}
	}
}

func isIgnoredOptionalURL(rawURL string) bool {
	if strings.Contains(rawURL, "favicon.ico") {
		return true
	}
	return strings.Contains(rawURL, "serve_data.php?file=global-settings.json") ||
		strings.Contains(rawURL, "serve_data.php?file=blog-view-settings.json")
}

// Call invokes a CDP method synchronously with timeout.
func (c *cdpClient) Call(method string, params map[string]interface{}) (json.RawMessage, error) {
	id := atomic.AddInt64(&c.nextID, 1)
	ch := make(chan cdpResponse, 1)

	c.pendMu.Lock()
	c.pending[id] = ch
	c.pendMu.Unlock()

	req := map[string]interface{}{"id": id, "method": method}
	if params != nil {
		req["params"] = params
	}
	raw, _ := json.Marshal(req)
	if err := c.writeFrame(0x1, raw); err != nil {
		c.pendMu.Lock()
		delete(c.pending, id)
		c.pendMu.Unlock()
		return nil, err
	}

	select {
	case resp := <-ch:
		if resp.Error != nil {
			return nil, fmt.Errorf("CDP error %v", resp.Error)
		}
		return resp.Result, nil
	case <-time.After(15 * time.Second):
		c.pendMu.Lock()
		delete(c.pending, id)
		c.pendMu.Unlock()
		return nil, fmt.Errorf("CDP call %s timed out", method)
	}
}

// Navigate opens url in the active tab and waits briefly for load completion.
func (c *cdpClient) Navigate(targetURL string) error {
	if _, err := c.Call("Page.navigate", map[string]interface{}{"url": targetURL}); err != nil {
		return err
	}
	return c.WaitFor(`document.readyState === 'complete'`, 10*time.Second)
}

// Eval evaluates an arbitrary JavaScript expression (supporting async Promises) and decodes its JSON value.
func (c *cdpClient) Eval(expr string, out interface{}) error {
	raw, err := c.Call("Runtime.evaluate", map[string]interface{}{
		"expression":    expr,
		"awaitPromise":  true,
		"returnByValue": true,
	})
	if err != nil {
		return err
	}
	var res struct {
		Result struct {
			Type        string          `json:"type"`
			Value       json.RawMessage `json:"value"`
			Description string          `json:"description"`
		} `json:"result"`
		ExceptionDetails interface{} `json:"exceptionDetails"`
	}
	if err := json.Unmarshal(raw, &res); err != nil {
		return err
	}
	if res.ExceptionDetails != nil {
		return fmt.Errorf("JS exception: %v", res.ExceptionDetails)
	}
	if out != nil && len(res.Result.Value) > 0 {
		return json.Unmarshal(res.Result.Value, out)
	}
	return nil
}

// WaitFor polls a boolean JS expression until it evaluates true.
func (c *cdpClient) WaitFor(expr string, timeout time.Duration) error {
	deadline := time.Now().Add(timeout)
	for time.Now().Before(deadline) {
		var ok bool
		if err := c.Eval("Boolean("+expr+")", &ok); err == nil && ok {
			return nil
		}
		time.Sleep(60 * time.Millisecond)
	}
	return fmt.Errorf("WaitFor timeout: %s", expr)
}

func (c *cdpClient) TakeErrors() ([]string, []string) {
	c.eventsMu.Lock()
	defer c.eventsMu.Unlock()
	js := append([]string(nil), c.jsErrors...)
	netErr := append([]string(nil), c.netErrors...)
	c.jsErrors = nil
	c.netErrors = nil
	return js, netErr
}

// Close shuts down the websocket and terminates the headless browser + temporary profile.
func (c *cdpClient) Close() {
	if !atomic.CompareAndSwapInt32(&c.closed, 0, 1) {
		return
	}
	if c.conn != nil {
		_ = c.conn.Close()
	}
	if c.cmd != nil && c.cmd.Process != nil {
		_ = c.cmd.Process.Kill()
		_, _ = c.cmd.Process.Wait()
	}
	if c.profile != "" {
		_ = os.RemoveAll(c.profile)
	}
}
