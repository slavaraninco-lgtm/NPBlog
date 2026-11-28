package main

import (
	"context"
	"fmt"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"npblog/internal/app"
	"npblog/internal/config"
)

func main() {
	cfg, err := config.LoadConfig()
	if err != nil {
		log.Fatalf("Ошибка загрузки конфигурации: %v", err)
	}

	srvComp := app.NewServer(cfg, true)
	fmt.Print(srvComp.SelfTest.FormatConsoleReport(srvComp.SelfTestReport))
	if !srvComp.SelfTestReport.Passed {
		log.Printf("⚠️ ВНИМАНИЕ: %s", srvComp.SelfTestReport.Summary)
	}
	serverAddr := app.ListenAddress(cfg)

	srv := &http.Server{
		Addr:         serverAddr,
		Handler:      srvComp.Router,
		ReadTimeout:  60 * time.Second,
		WriteTimeout: 0, // Не ограничиваем время записи для поддержки непрерывных SSE потоков (FTP синхронизация и др.)
		IdleTimeout:  120 * time.Second,
	}

	go func() {
		log.Printf("🚀 NPBlog Go Сервер запущен на http://%s", serverAddr)
		log.Printf("📁 Рабочая директория: %s", cfg.BaseDir)
		log.Printf("🎨 Каталог ресурсов (Hybrid): %s", cfg.AssetsDir)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Fatalf("Критическая ошибка сервера: %v", err)
		}
	}()

	quit := make(chan os.Signal, 1)
	signal.Notify(quit, os.Interrupt, syscall.SIGTERM)
	<-quit

	log.Println("🛑 Остановка сервера NPBlog...")
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	if err := srv.Shutdown(ctx); err != nil {
		log.Fatalf("Ошибка при остановке сервера: %v", err)
	}
	log.Println("✅ Сервер успешно остановлен")
}
