package main

import (
	"bufio"
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"net"
	"time"
)

type Message struct {
	Type      string    `json:"type"`
	Topic     string    `json:"topic"`
	Data      float64   `json:"data"`
	Timestamp time.Time `json:"timestamp"`
	ClientID  string    `json:"client_id"`
}

type IrrigationSystem struct {
	IsActive           bool
	MaxHumidity        float64
	CurrentHumidity    float64
	CurrentTemperature float64
	LastActivation     time.Time
	MinIntervalMinutes int
	TotalActivations   int
}

var brokerAddr string

func NewIrrigationSystem() *IrrigationSystem {
	return &IrrigationSystem{
		IsActive:           false,
		MaxHumidity:        80.0, // Desliga quando umidade > 80%
		MinIntervalMinutes: 5,    // Mínimo 5 min entre ativações
		TotalActivations:   0,
		CurrentTemperature: 25.0, // Um valor inicial padrão para a temperatura
	}
}

func (sys *IrrigationSystem) ProcessSensorData(msg Message) {
	switch msg.Topic {
	case "humidity":
		sys.CurrentHumidity = msg.Data
		sys.checkIrrigationNeeded()
	case "temperature":
		sys.CurrentTemperature = msg.Data
	}
}

func (sys *IrrigationSystem) calculateMinHumidity() float64 {
	if sys.CurrentTemperature > 30.0 {
		return 60.0 // Mais exigente em dias quentes
	} else if sys.CurrentTemperature < 15.0 {
		return 30.0 // Menos exigente em dias frios
	}
	return 40.0 // Padrão
}

func (sys *IrrigationSystem) checkIrrigationNeeded() {
	now := time.Now()
	minHumidityThreshold := sys.calculateMinHumidity()

	if !sys.IsActive && sys.CurrentHumidity < minHumidityThreshold {
		if now.Sub(sys.LastActivation).Minutes() >= float64(sys.MinIntervalMinutes) {
			sys.activateIrrigation(minHumidityThreshold)
		} else {
			log.Printf("⏳ Irrigação necessária (%.1f%%), mas aguardando intervalo mínimo", sys.CurrentHumidity)
		}
	}

	if sys.IsActive && sys.CurrentHumidity >= sys.MaxHumidity {
		sys.deactivateIrrigation()
	}
}

func (sys *IrrigationSystem) activateIrrigation(threshold float64) {
	sys.IsActive = true
	sys.LastActivation = time.Now()
	sys.TotalActivations++

	log.Printf("💧 IRRIGAÇÃO ATIVADA! Umidade: %.1f%% (< %.1f%%)",
		sys.CurrentHumidity, threshold)
	log.Printf("   Ativação #%d - Horário: %s",
		sys.TotalActivations, sys.LastActivation.Format("15:04:05"))
}

func (sys *IrrigationSystem) deactivateIrrigation() {
	sys.IsActive = false
	duration := time.Since(sys.LastActivation)

	log.Printf("🛑 IRRIGAÇÃO DESATIVADA! Umidade: %.1f%% (>= %.1f%%)",
		sys.CurrentHumidity, sys.MaxHumidity)
	log.Printf("   Duração da irrigação: %s", duration.Round(time.Second))
}

func (sys *IrrigationSystem) printStatus() {
	status := "DESLIGADA"
	if sys.IsActive {
		status = "ATIVA"
	}

	minHumidity := sys.calculateMinHumidity()

	fmt.Printf("\n=== STATUS DO SISTEMA DE IRRIGAÇÃO ===\n")
	fmt.Printf("Estado: %s\n", status)
	fmt.Printf("Umidade atual: %.1f%%\n", sys.CurrentHumidity)
	fmt.Printf("Temperatura atual: %.1f°C\n", sys.CurrentTemperature)
	fmt.Printf("Threshold mínima (calculada): %.1f%%\n", minHumidity)
	fmt.Printf("Threshold máxima: %.1f%%\n", sys.MaxHumidity)
	fmt.Printf("Total de ativações: %d\n", sys.TotalActivations)
	if !sys.LastActivation.IsZero() {
		fmt.Printf("Última ativação: %s\n", sys.LastActivation.Format("15:04:05"))
	}
	fmt.Printf("=====================================\n\n")
}

func connectToBroker() (net.Conn, error) {
	for {
		conn, err := net.Dial("tcp", brokerAddr)
		if err != nil {
			log.Printf("Tentando conectar ao broker em %s... %v", brokerAddr, err)
			time.Sleep(2 * time.Second)
			continue
		}
		return conn, nil
	}
}

func subscribeToTopics(conn net.Conn, topics []string) error {
	for _, topic := range topics {
		msg := Message{
			Type:  "subscribe",
			Topic: topic,
		}

		data, err := json.Marshal(msg)
		if err != nil {
			return err
		}

		_, err = conn.Write(append(data, '\n'))
		if err != nil {
			return err
		}

		log.Printf("Inscrito no tópico: %s", topic)
	}
	return nil
}

func main() {
	flag.StringVar(&brokerAddr, "b", "localhost:8080", "Endereço e porta do broker IoT (ex: 192.168.1.100:8080)")
	flag.Parse()

	system := NewIrrigationSystem()

	log.Println("=== SISTEMA DE IRRIGAÇÃO AUTOMATIZADA ===")
	log.Println("Configurações:")
	fmt.Printf("- Umidade máxima: %.1f%%\n", system.MaxHumidity)
	fmt.Printf("- Intervalo mínimo: %d minutos\n", system.MinIntervalMinutes)
	log.Println("\nConectando ao broker...")

	conn, err := connectToBroker()
	if err != nil {
		log.Fatalf("Erro ao conectar: %v", err)
	}
	defer conn.Close()

	topics := []string{"humidity", "temperature"}
	err = subscribeToTopics(conn, topics)
	if err != nil {
		log.Fatalf("Erro ao se inscrever nos tópicos: %v", err)
	}

	log.Println("Sistema de irrigação ativo! Monitorando sensores...")

	statusTicker := time.NewTicker(30 * time.Second)
	defer statusTicker.Stop()

	go func() { // Goroutine de função anônima
		// O canal .C é criado vazio. O loop tenta ler dele e bloqueia a goroutine até que o Ticker envie um sinal (a cada 30s)
		for range statusTicker.C {
			// Ao receber o sinal, a goroutine é desbloqueada, executa printStatus() e o loop volta a bloquear, esperando o próximo sinal
			system.printStatus()
		}
	}()

	scanner := bufio.NewScanner(conn)
	for scanner.Scan() {
		var msg Message
		err := json.Unmarshal(scanner.Bytes(), &msg)
		if err != nil {
			log.Printf("Erro ao decodificar mensagem: %v", err)
			continue
		}

		if msg.Type == "sensor_data" {
			log.Printf("📊 %s: %.1f", msg.Topic, msg.Data)
			system.ProcessSensorData(msg)
		}
	}

	if err := scanner.Err(); err != nil {
		log.Printf("Erro na leitura: %v", err)
	}
}
