package models

import (
	"database/sql"
	"time"
)

type ReportSetting struct {
	ID              int
	UserID          int
	Frequency       string
	IsEnabled       bool
	LastSentAt      sql.NullTime
	DailyReportTime string // Using string for TIME column
	TelegramTopicID sql.NullString
	// TelegramChatID  sql.NullString
	ReportEmail string
}

type Expense struct {
	ID          int
	Amount      float64
	Description string
	Category    string
	Date        time.Time
}

type TelegramBot struct {
	Token  string
	ChatID string
}
