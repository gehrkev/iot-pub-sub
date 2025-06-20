package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"math"
	"math/rand"
	"net"
	"os"
	"time"
)

type Message struct {
	Type      string    `json:"type"`
	Topic     string    `json:"topic"`
	Data      float64   `json:"data"`
	Timestamp time.Time `json:"timestamp"`
	ClientID  string    `json:"client_id"`
}

type Sensor struct {
	Name    string
	Topic   string
	Min     float64
	Max     float64
	Current float64
	Trend   float64 // tendência de mudança
	Unit    string
}

func (s *Sensor) GenerateReading() float64 {
	// Simula variação natural com tendência e ruído
	noise := (rand.Float64() - 0.5) * 2.0 // -1 a 1
	change := s.Trend + noise

	s.Current += change

	// Mantém dentro dos limites
	if s.Current < s.Min {
		s.Current = s.Min
		s.Trend = math.Abs(s.Trend) // inverte tendência
	}
	if s.Current > s.Max {
		s.Current = s.Max
		s.Trend = -math.Abs(s.Trend) // inverte tendência
	}

	// Ocasionalmente muda a tendência
	if rand.Float64() < 0.1 {
		s.Trend *= -1
	}

	return math.Round(s.Current*100) / 100 // 2 casas decimais
}

func connectToBroker() (net.Conn, error) {
	for {
		conn, err := net.Dial("tcp", "localhost:8080")
		if err != nil {
			log.Printf("Tentando conectar ao broker... %v", err)
			time.Sleep(2 * time.Second)
			continue
		}
		return conn, nil
	}
}

func runSensor(sensor *Sensor) {
	conn, err := connectToBroker()
	if err != nil {
		log.Fatalf("Não foi possível conectar ao broker: %v", err)
	}
	defer conn.Close()

	log.Printf("Sensor %s conectado - enviando dados a cada 3 segundos", sensor.Name)

	ticker := time.NewTicker(3 * time.Second)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C: // Esperar pelo tick do timer
			reading := sensor.GenerateReading()

			msg := Message{
				Type:      "sensor_data",
				Topic:     sensor.Topic,
				Data:      reading,
				Timestamp: time.Now(),
				ClientID:  fmt.Sprintf("sensor_%s", sensor.Topic),
			}

			data, err := json.Marshal(msg)
			if err != nil {
				log.Printf("Erro ao serializar dados do sensor %s: %v", sensor.Name, err)
				continue
			}

			_, err = conn.Write(append(data, '\n'))
			if err != nil {
				log.Printf("Erro ao enviar dados do sensor %s: %v", sensor.Name, err)
				// Reconecta
				conn.Close()
				conn, err = connectToBroker()
				if err != nil {
					log.Printf("Falha na reconexão: %v", err)
					return
				}
				continue
			}

			log.Printf("%s: %.2f %s", sensor.Name, reading, sensor.Unit)
		}
	}
}

func main() {
	rand.Seed(time.Now().UnixNano())

	sensorType := flag.String("t", "", "Tipo de sensor a ser executado (humidity, temperature, ou wind)")
	flag.Parse()

	if *sensorType == "" {
		log.Println("Erro: O tipo de sensor é obrigatório. Use a flag -t.")
		log.Println("Exemplo: go run sensors.go -t humidity")
		flag.PrintDefaults()
		os.Exit(1)
	}

	var selectedSensor *Sensor

	switch *sensorType {
	case "humidity":
		selectedSensor = &Sensor{
			Name:    "Sensor de Umidade do Solo",
			Topic:   "humidity",
			Min:     0.0,
			Max:     100.0,
			Current: 30.0 + rand.Float64()*70, // 30-100%
			Trend:   (rand.Float64() - 0.5) * 2,
			Unit:    "%",
		}
	case "temperature":
		selectedSensor = &Sensor{
			Name:    "Sensor de Temperatura",
			Topic:   "temperature",
			Min:     10.0,
			Max:     45.0,
			Current: 15.0 + rand.Float64()*25, // 15-40°C
			Trend:   (rand.Float64() - 0.5) * 1,
			Unit:    "°C",
		}
	case "wind":
		selectedSensor = &Sensor{
			Name:    "Sensor de Velocidade do Vento",
			Topic:   "wind",
			Min:     0.0,
			Max:     80.0,
			Current: 3.0 + rand.Float64()*37, // 3-40 km/h
			Trend:   (rand.Float64() - 0.5) * 3,
			Unit:    "km/h",
		}
	default:
		log.Printf("Erro: Tipo de sensor inválido '%s'.", *sensorType)
		log.Println("Tipos disponíveis: humidity, temperature, wind")
		os.Exit(1)
	}

	log.Println("=== SIMULADOR DE SENSOR IOT AGRÍCOLA ===")
	log.Printf("Iniciando sensor: %s (tópico: %s)", selectedSensor.Name, selectedSensor.Topic)
	log.Println("\nAguardando conexão com o broker...")

	go runSensor(selectedSensor)

	select {} // mantém executando
}
