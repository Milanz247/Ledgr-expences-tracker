package main

import (
	"log"
	"notifier/internal/config"
	"notifier/internal/db"
	"notifier/internal/service"
	"notifier/internal/telegram"
)

func main() {
	log.Println("Starting Notifier Service...")

	// 1. Load Config
	cfg := config.LoadConfig()

	// 2. Connect DB
	database := db.Connect(cfg)
	defer database.Close()

	// 3. Init Telegram Client
	tgClient := telegram.NewClient()

	// 4. Start Service
	srv := service.New(database, tgClient)
	srv.Run()
}
