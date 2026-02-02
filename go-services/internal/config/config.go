package config

import (
	"log"
	"os"
	"path/filepath"

	"github.com/joho/godotenv"
)

type Config struct {
	DBUser     string
	DBPassword string
	DBHost     string
	DBPort     string
	DBName     string
}

func LoadConfig() *Config {
	// Priority 1: .env in current directory (Production: running from backend root)
	if err := godotenv.Load(".env"); err == nil {
		log.Println("Loaded .env from current directory")
	} else {
		// Priority 2: ../.env (Development: running from go-services/cmd/notifier)
		if err := godotenv.Load("../.env"); err == nil {
			log.Println("Loaded .env from parent directory")
		} else {
			// Priority 3: ../../.env (Development: running from go-services/internal/...)
			envPath := filepath.Join("..", "..", ".env")
			if err := godotenv.Load(envPath); err == nil {
				log.Println("Loaded .env from grandparent directory")
			} else {
				log.Printf("Warning: Could not load .env file. Relying on system env vars.")
			}
		}
	}

	return &Config{
		DBUser:     os.Getenv("DB_USERNAME"),
		DBPassword: os.Getenv("DB_PASSWORD"),
		DBHost:     os.Getenv("DB_HOST"),
		DBPort:     os.Getenv("DB_PORT"),
		DBName:     os.Getenv("DB_DATABASE"),
	}
}
