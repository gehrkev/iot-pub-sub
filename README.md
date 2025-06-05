# Sistema IoT Pub-Sub Agrícola

Sistema de demonstração do padrão pub-sub usando sockets TCP para sensores IoT aplicados à agronomia.

Desenvolvido para a disciplina de Desenvolvimento de Sistemas Paralelos e Distribuídos.

## Créditos

* **Professor:** Fernando dos Santos
* **Integrantes:** André Henrique Ludwig, Vitor André Gehrke

## Arquitetura

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Sensores IoT  │───▶│  Broker Pub-Sub │◀───│  Dashboard Web  │
│   (Go - TCP)    │    │   (Go - TCP)    │    │  (Laravel/PHP)  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                  │
                                  ▼
                       ┌─────────────────┐
                       │ Sistema Irrigação│
                       │   (Go - TCP)    │
                       └─────────────────┘
```

## Como Executar

### 1. Pré-requisitos
- **Go 1.24.3+** instalado
- **PHP 8.4.7+** instalado
- **Composer** instalado
- **Laravel 12.16.0+** (opcional: Laravel Installer)

### 2. Setup do Broker

```bash
cd broker-app
go run main.go
```

O broker estará rodando na porta **8080**.

### 3. Setup dos Sensores

```bash
cd sensors-app
go run sensors.go
```

Os sensores começarão a enviar dados automaticamente.

### 4. Setup do Sistema de Irrigação

```bash
cd irrigation-app
go run irrigation.go
```

O sistema monitorará e reagirá aos sensores automaticamente.

### 5. Setup do Dashboard

```bash
cd iot-dashboard
php artisan serve
```

Acesse: **http://localhost:8000**

## Funcionalidades

### Broker Pub-Sub
- Gerenciamento de conexões TCP
- Sistema de tópicos (humidity, temperature, wind)
- Distribuição de mensagens em tempo real
- Histórico de dados (últimas 100 leituras)
- Thread-safe com mutexes

### Sensores Simulados [TBA]
- **Sensor de Umidade**
- **Sensor de Temperatura**
- **Sensor de Vento**
- Reconexão automática
- Dados a cada 3 segundos

### Sistema de Irrigação [TBA]
- Ativação e desativação automática de acordo com umidade

### Dashboard Laravel [TBA]
- Sensores em tempo real
- Gráficos históricos
- Sistema de alertas
- Status de conexão
- Atualização automática

## Configurações

### Broker
- **Porta**: 8080
- **Protocolo**: TCP
- **Timeout**: 5 segundos

### Sensores
- **Intervalo**: 3 segundos
- **Reconexão**: Automática

### Irrigação
- **Threshold mín Umidade**: 30% (ajustável por temperatura)
- **Threshold máx Umidade**: 70%
- **Intervalo mín**: 5 minutos

### Dashboard
- **Atualização**: 5 segundos
- **Histórico**: 24 horas
- **Gráficos**: Tempo real

---

## Demonstrações Possíveis

1. **Pub-Sub Pattern**: Múltiples subscribers recebendo os mesmos dados
2. **Tolerância a Falhas**: Desconectar/reconectar componentes
3. **Escalabilidade**: Adicionar novos sensores facilmente
4. **Automação**: Sistema reagindo automaticamente aos dados
5. **Monitoramento**: Dashboard visual em tempo real

---

## Problemas de Socket (PHP)
```bash
php -m | grep socket  # Verificar extensão socket
```