package main

import (
	"bufio"
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"net"
	"sync"
	"time"
)

type Message struct {
	Type      string    `json:"type"`  // "sensor_data", "subscribe", "unsubscribe"
	Topic     string    `json:"topic"` // "humidity", "temperature", "wind"
	Data      float64   `json:"data"`  // valor do sensor
	Timestamp time.Time `json:"timestamp"`
	ClientID  string    `json:"client_id"`
}

type Client struct {
	ID            string
	Conn          net.Conn
	Subscriptions map[string]bool
	IsPublisher   bool
}

type Broker struct {
	clients     map[string]*Client
	topics      map[string][]*Client // topic -> lista de subscribers
	mutex       sync.RWMutex
	sensorData  map[string]float64   // últimos valores dos sensores
	dataHistory map[string][]Message // histórico de dados
}

func NewBroker() *Broker {
	return &Broker{
		clients:     make(map[string]*Client),
		topics:      make(map[string][]*Client),
		sensorData:  make(map[string]float64),
		dataHistory: make(map[string][]Message),
	}
}

func (b *Broker) AddClient(conn net.Conn, clientID string) *Client {
	b.mutex.Lock()
	defer b.mutex.Unlock() //  em Go, 'defer' executa o Unlock ao terminar a execução da função [https://aprendagolang.com.br/o-que-e-e-como-funciona-o-defer/}

	client := &Client{
		ID:            clientID,
		Conn:          conn,
		Subscriptions: make(map[string]bool),
		IsPublisher:   false,
	}

	b.clients[clientID] = client
	log.Printf("Cliente conectado: %s", clientID)
	return client
}

func (b *Broker) RemoveClient(clientID string) {
	b.mutex.Lock()
	defer b.mutex.Unlock()

	client, exists := b.clients[clientID]
	if !exists {
		return
	}

	for topic := range client.Subscriptions {
		b.unsubscribeClient(client, topic)
	}

	client.Conn.Close()
	delete(b.clients, clientID)
	log.Printf("Cliente desconectado: %s", clientID)
}

func (b *Broker) Subscribe(clientID, topic string) {
	b.mutex.Lock()
	defer b.mutex.Unlock()

	client, exists := b.clients[clientID]
	if !exists {
		return
	}

	client.Subscriptions[topic] = true
	b.topics[topic] = append(b.topics[topic], client)

	log.Printf("Cliente %s inscrito no tópico: %s", clientID, topic)

	if lastValue, exists := b.sensorData[topic]; exists {
		msg := Message{
			Type:      "sensor_data",
			Topic:     topic,
			Data:      lastValue,
			Timestamp: time.Now(),
			ClientID:  "broker",
		}
		b.notify(client, msg)
	}
}

func (b *Broker) unsubscribeClient(client *Client, topic string) {
	subscribers := b.topics[topic]
	for i, sub := range subscribers {
		if sub.ID == client.ID {
			b.topics[topic] = append(subscribers[:i], subscribers[i+1:]...)
			break
		}
	}
	delete(client.Subscriptions, topic)
}

func (b *Broker) Publish(msg Message) {
	b.mutex.Lock()
	defer b.mutex.Unlock()

	b.sensorData[msg.Topic] = msg.Data

	history := b.dataHistory[msg.Topic]
	history = append(history, msg)
	if len(history) > 100 {
		history = history[1:]
	}
	b.dataHistory[msg.Topic] = history

	log.Printf("Publicando no tópico %s: %.2f", msg.Topic, msg.Data)

	subscribers := b.topics[msg.Topic]
	for _, client := range subscribers {
		b.notify(client, msg)
	}
}

func (b *Broker) notify(client *Client, msg Message) {
	data, err := json.Marshal(msg)
	if err != nil {
		log.Printf("Erro ao serializar mensagem: %v", err)
		return
	}

	_, err = client.Conn.Write(append(data, '\n'))
	if err != nil {
		log.Printf("Erro ao enviar para cliente %s: %v", client.ID, err)
		go b.RemoveClient(client.ID) // em Go, 'go' executa a função em concorrência, como 'threads leves', ou goroutines [https://aprendagolang.com.br/o-que-sao-e-como-funcionam-as-goroutines/]
	}
}

func (b *Broker) GetSensorData() map[string]float64 {
	b.mutex.RLock()
	defer b.mutex.RUnlock()

	data := make(map[string]float64)
	for k, v := range b.sensorData {
		data[k] = v
	}
	return data
}

func (b *Broker) GetHistory(topic string) []Message {
	b.mutex.RLock()
	defer b.mutex.RUnlock()

	return b.dataHistory[topic]
}

func (b *Broker) handleClient(client *Client) {
	defer b.RemoveClient(client.ID)

	scanner := bufio.NewScanner(client.Conn)
	for scanner.Scan() {
		var msg Message
		if err := json.Unmarshal(scanner.Bytes(), &msg); err != nil {
			log.Printf("Erro ao decodificar mensagem de %s: %v", client.ID, err)
			continue
		}

		msg.Timestamp = time.Now()
		msg.ClientID = client.ID

		switch msg.Type {
		case "subscribe":
			b.Subscribe(client.ID, msg.Topic)
		case "unsubscribe":
			b.mutex.Lock()
			b.unsubscribeClient(client, msg.Topic)
			b.mutex.Unlock()
		case "sensor_data":
			client.IsPublisher = true
			b.Publish(msg)
		case "get_current":
			data := b.GetSensorData()
			for topic, value := range data {
				currentMsg := Message{
					Type:      "sensor_data",
					Topic:     topic,
					Data:      value,
					Timestamp: time.Now(),
					ClientID:  "broker",
				}
				b.notify(client, currentMsg)
			}
		}
	}
}

func main() {
	listenAddr := flag.String("l", "0.0.0.0:8080", "Endereço e porta para o broker escutar (ex: 0.0.0.0:8080)")
	flag.Parse()

	broker := NewBroker()

	listener, err := net.Listen("tcp", *listenAddr)
	if err != nil {
		log.Fatalf("Erro ao iniciar servidor em %s: %v", *listenAddr, err)
	}
	defer listener.Close()

	log.Printf("Broker IoT iniciado em %s", *listenAddr)
	log.Println("Aguardando conexões de sensores e sistemas...")

	for {
		conn, err := listener.Accept()
		if err != nil {
			log.Printf("Erro ao aceitar conexão: %v", err)
			continue
		}

		clientID := fmt.Sprintf("client_%d", time.Now().UnixNano())
		client := broker.AddClient(conn, clientID)

		go broker.handleClient(client) // em Go, 'go' executa a função em concorrência, como 'threads leves', ou goroutines [https://aprendagolang.com.br/o-que-sao-e-como-funcionam-as-goroutines/]
	}
}
