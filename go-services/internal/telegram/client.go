package telegram

import (
	"bytes"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
)

type Client struct {
	BaseURL string
}

func NewClient() *Client {
	return &Client{
		BaseURL: "https://api.telegram.org/bot",
	}
}

func (c *Client) SendMessage(token, chatID, message, topicID string) error {
	url := fmt.Sprintf("%s%s/sendMessage", c.BaseURL, token)

	payload := map[string]interface{}{
		"chat_id":    chatID,
		"text":       message,
		"parse_mode": "Markdown",
	}

	if topicID != "" {
		payload["message_thread_id"] = topicID
	}

	jsonPayload, err := json.Marshal(payload)
	if err != nil {
		return err
	}

	resp, err := http.Post(url, "application/json", bytes.NewBuffer(jsonPayload))
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("unexpected status: %s", resp.Status)
	}

	log.Printf("Sent message to ChatID: %s, TopicID: %s", chatID, topicID)
	return nil
}
