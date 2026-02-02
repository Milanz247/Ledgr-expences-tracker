package service

import (
	"database/sql"
	"fmt"
	"log"
	"notifier/internal/models"
	"notifier/internal/telegram"
	"time"
)

type NotificationService struct {
	DB       *sql.DB
	Telegram *telegram.Client
}

func New(db *sql.DB, tg *telegram.Client) *NotificationService {
	return &NotificationService{
		DB:       db,
		Telegram: tg,
	}
}

func (s *NotificationService) Run() {
	ticker := time.NewTicker(1 * time.Minute)
	defer ticker.Stop()

	log.Println("Notification service started. Checking every minute...")

	for range ticker.C {
		s.checkAndSendReports()
	}
}

func (s *NotificationService) checkAndSendReports() {
	now := time.Now()

	// Keep it simple: check for HH:MM:00 match.
	// In production, matching a range (last run vs now) is safer, but exact match ok for minute ticker.
	currentTimeShort := now.Format("15:04")

	// Query users who have reports enabled and scheduled for this minute
	rows, err := s.DB.Query(`
		SELECT id, user_id, daily_report_time, telegram_topic_id, telegram_chat_id, last_sent_at 
		FROM report_settings 
		WHERE is_enabled = true 
		AND DATE_FORMAT(daily_report_time, '%H:%i') = ?
	`, currentTimeShort)

	if err != nil {
		log.Println("Error querying report settings:", err)
		return
	}
	defer rows.Close()

	for rows.Next() {
		var setting models.ReportSetting
		var telegramChatID sql.NullString

		err := rows.Scan(&setting.ID, &setting.UserID, &setting.DailyReportTime, &setting.TelegramTopicID, &telegramChatID, &setting.LastSentAt)
		if err != nil {
			log.Println("Error scanning report setting:", err)
			continue
		}

		// Check if already sent today
		if setting.LastSentAt.Valid {
			lastSent := setting.LastSentAt.Time
			if lastSent.Year() == now.Year() && lastSent.YearDay() == now.YearDay() {
				// Already sent today
				continue
			}
		}

		log.Printf("Processing report for UserID: %d", setting.UserID)
		s.generateAndSendReport(setting, telegramChatID.String)
	}
}

func (s *NotificationService) generateAndSendReport(setting models.ReportSetting, overriddenChatID string) {
	// 1. Get Bot info
	var bot models.TelegramBot
	err := s.DB.QueryRow("SELECT token, chat_id FROM telegram_bots LIMIT 1").Scan(&bot.Token, &bot.ChatID)
	if err != nil {
		log.Println("Error fetching bot info:", err)
		return
	}

	// Use overridden chat ID if present in settings, else bot default
	targetChatID := bot.ChatID
	if overriddenChatID != "" {
		targetChatID = overriddenChatID
	}

	// 2. Calculate expenses for today
	var totalAmount float64
	var expenseCount int
	today := time.Now().Format("2006-01-02")

	err = s.DB.QueryRow(`
		SELECT COALESCE(SUM(amount), 0), COUNT(*)
		FROM expenses 
		WHERE user_id = ? AND date = ?
	`, setting.UserID, today).Scan(&totalAmount, &expenseCount)

	if err != nil {
		log.Println("Error calculating expenses:", err)
		return
	}

	// 3. Format Message
	message := fmt.Sprintf("📊 *Daily Expense Report*\n\n📅 Date: %s\n💸 Total Spent: %.2f\n📝 Count: %d\n\n_Generated automatically_",
		today, totalAmount, expenseCount)

	// 4. Send
	topicID := ""
	if setting.TelegramTopicID.Valid {
		topicID = setting.TelegramTopicID.String
	}

	err = s.Telegram.SendMessage(bot.Token, targetChatID, message, topicID)
	if err != nil {
		log.Println("Error sending telegram message:", err)
		return
	}

	// 5. Update last_sent_at
	_, err = s.DB.Exec("UPDATE report_settings SET last_sent_at = NOW() WHERE id = ?", setting.ID)
	if err != nil {
		log.Println("Error updating last_sent_at:", err)
	}

	log.Printf("Report sent successfully to UserID: %d", setting.UserID)
}
