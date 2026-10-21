package service

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"os/exec"
	"path/filepath"
	"time"

	"npblog/internal/config"
)

type KatexInput struct {
	Tex          string `json:"tex"`
	DisplayMode  bool   `json:"displayMode"`
	ThrowOnError bool   `json:"throwOnError"`
}

type KatexOutput struct {
	Success bool   `json:"success"`
	HTML    string `json:"html,omitempty"`
	Error   string `json:"error,omitempty"`
}

type FormulaService struct {
	cfg        *config.AppConfig
	scriptPath string
}

func NewFormulaService(cfg *config.AppConfig) *FormulaService {
	script := filepath.Join(cfg.BaseDir, "api", "src", "render_katex.cjs")
	return &FormulaService{
		cfg:        cfg,
		scriptPath: script,
	}
}

// RenderKaTeX вызывает локальный Node.js скрипт render_katex.cjs
func (f *FormulaService) RenderKaTeX(ctx context.Context, tex string, displayMode bool) (*KatexOutput, error) {
	ctxTimeout, cancel := context.WithTimeout(ctx, 4*time.Second)
	defer cancel()

	reqBytes, err := json.Marshal(KatexInput{
		Tex:          tex,
		DisplayMode:  displayMode,
		ThrowOnError: false,
	})
	if err != nil {
		return nil, err
	}

	cmd := exec.CommandContext(ctxTimeout, "node", f.scriptPath)
	cmd.Stdin = bytes.NewReader(reqBytes)

	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	if err := cmd.Run(); err != nil {
		errMsg := stderr.String()
		if errMsg == "" {
			errMsg = err.Error()
		}
		return &KatexOutput{
			Success: false,
			Error:   "Ошибка запуска Node.js KaTeX: " + errMsg,
		}, nil
	}

	var output KatexOutput
	if err := json.Unmarshal(stdout.Bytes(), &output); err != nil {
		return nil, errors.New("неверный формат ответа KaTeX Node: " + stdout.String())
	}

	return &output, nil
}
