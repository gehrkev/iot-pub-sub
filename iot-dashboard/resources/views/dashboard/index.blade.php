<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard IoT Agrícola</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.18);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .sensor-card {
            text-align: center;
            padding: 2rem 1rem;
        }

        .sensor-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .sensor-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .sensor-label {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-good { background-color: #28a745; }
        .status-warning { background-color: #ffc107; }
        .status-critical { background-color: #dc3545; }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .alert-item {
            border-left: 4px solid;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
        }

        .alert-danger { border-left-color: #dc3545; }
        .alert-warning { border-left-color: #ffc107; }

        .last-update {
            font-size: 0.9rem;
            color: #6c757d;
            text-align: center;
            margin-top: 1rem;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.02);
            }
            100% {
                transform: scale(1);
            }
        }

        .updating {
            animation-name: pulse;
            animation-duration: 1s;
            animation-iteration-count: infinite;
            animation-timing-function: ease-in-out;
            /* IMPORTANTE: Evitar interferência da transição do .dashboard-card */
            /* Desabilitar todas as transições enquanto 'updating' está ativo */
            transition: none !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="mb-1">
                                <i class="fas fa-seedling text-success me-2"></i>
                                Dashboard IoT Agrícola
                            </h1>
                            <p class="text-muted mb-0">Sistema de Monitoramento em Tempo Real</p>
                        </div>
                        <div class="text-end">
                            <div class="d-flex align-items-center mb-2">
                                <span class="status-indicator" id="connection-status"></span>
                                <span id="connection-text">Conectando...</span>
                            </div>
                            <small class="text-muted" id="last-update">Última atualização: --:--:--</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Alerts -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card p-3">
                    <h5 class="mb-3">
                        <i class="fas fa-bell text-warning me-2"></i>
                        Alertas do Sistema
                    </h5>
                    <div id="alerts-container">
                        <div class="text-muted">Carregando alertas...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sensor Cards -->
        <div class="row mb-4">
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="dashboard-card sensor-card">
                    <div class="sensor-icon text-primary">
                        <i class="fas fa-tint"></i>
                    </div>
                    <div class="sensor-value text-primary" id="humidity-value">--</div>
                    <div class="sensor-label">Umidade do Solo</div>
                    <small class="text-muted" id="humidity-time">--:--:--</small>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-3">
                <div class="dashboard-card sensor-card">
                    <div class="sensor-icon text-danger">
                        <i class="fas fa-thermometer-half"></i>
                    </div>
                    <div class="sensor-value text-danger" id="temperature-value">--</div>
                    <div class="sensor-label">Temperatura</div>
                    <small class="text-muted" id="temperature-time">--:--:--</small>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 mb-3">
                <div class="dashboard-card sensor-card">
                    <div class="sensor-icon text-info">
                        <i class="fas fa-wind"></i>
                    </div>
                    <div class="sensor-value text-info" id="wind-value">--</div>
                    <div class="sensor-label">Velocidade do Vento</div>
                    <small class="text-muted" id="wind-time">--:--:--</small>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="dashboard-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Histórico - Umidade
                        </h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshChart('humidity')">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="chart-container">
                        <canvas id="humidity-chart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="dashboard-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Histórico - Temperatura
                        </h5>
                        <button class="btn btn-sm btn-outline-danger" onclick="refreshChart('temperature')">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="chart-container">
                        <canvas id="temperature-chart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="dashboard-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Histórico - Vento
                        </h5>
                        <button class="btn btn-sm btn-outline-info" onclick="refreshChart('wind')">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="chart-container">
                        <canvas id="wind-chart"></canvas>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let charts = {};
        let updateInterval;

        // Configuração do Chart.js
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        Chart.defaults.color = '#666';

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            updateDashboard();

            // Atualiza a cada 5 segundos
            updateInterval = setInterval(updateDashboard, 5000);
        });

        function initializeCharts() {
            // Chart de Umidade
            const humidityCtx = document.getElementById('humidity-chart').getContext('2d');
            charts.humidity = new Chart(humidityCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Umidade (%)',
                        data: [],
                        border: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { display: true, color: 'rgba(0,0,0,0.1)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });

            // Chart de Temperatura
            const temperatureCtx = document.getElementById('temperature-chart').getContext('2d');
            charts.temperature = new Chart(temperatureCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Temperatura (°C)',
                        data: [],
                        border: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false,
                            grid: { display: true, color: 'rgba(0,0,0,0.1)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });

            const windCtx = document.getElementById('wind-chart').getContext('2d');
            charts.wind = new Chart(windCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Velocidade do Vento (km/h)',
                        data: [],
                        borderColor: '#0dcaf0',
                        backgroundColor: 'rgba(13, 202, 240, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { display: true, color: 'rgba(0,0,0,0.1)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }

        async function updateDashboard() {
            try {
                // Adiciona efeito visual de atualização
                document.querySelectorAll('.sensor-card').forEach(card => {
                    card.classList.add('updating');
                });

                // Busca dados dos sensores
                const response = await fetch('/dashboard/sensor-data');
                const result = await response.json();

                if (result.success) {
                    updateSensorCards(result.data);
                    updateConnectionStatus(true);
                } else {
                    throw new Error(result.error);
                }

                // Busca status do sistema
                const statusResponse = await fetch('/dashboard/system-status');
                const statusResult = await statusResponse.json();

                if (statusResult.success) {
                    updateAlerts(statusResult.status);
                }

            } catch (error) {
                console.error('Erro ao atualizar dashboard:', error);
                updateConnectionStatus(false);
            } finally {
                // Remove efeito visual
                setTimeout(() => {
                    document.querySelectorAll('.sensor-card').forEach(card => {
                        card.classList.remove('updating');
                    });
                }, 1000);
            }
        }

        function updateSensorCards(data) {
            const sensors = ['humidity', 'temperature', 'wind'];

            sensors.forEach(sensor => {
                if (data[sensor]) {
                    const valueElement = document.getElementById(`${sensor}-value`);
                    const timeElement = document.getElementById(`${sensor}-time`);

                    valueElement.textContent = `${data[sensor].value}${data[sensor].unit}`;
                    timeElement.textContent = new Date(data[sensor].timestamp).toLocaleTimeString('pt-BR');
                }
            });

            // Atualiza timestamp geral
            document.getElementById('last-update').textContent =
                `Última atualização: ${new Date().toLocaleTimeString('pt-BR')}`;
        }

        function updateConnectionStatus(connected) {
            const statusIndicator = document.getElementById('connection-status');
            const statusText = document.getElementById('connection-text');

            if (connected) {
                statusIndicator.className = 'status-indicator status-good';
                statusText.textContent = 'Conectado';
            } else {
                statusIndicator.className = 'status-indicator status-critical';
                statusText.textContent = 'Desconectado';
            }
        }

        function updateAlerts(status) {
            const container = document.getElementById('alerts-container');

            if (status.alerts.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        Todos os sistemas funcionando normalmente
                    </div>
                `;
            } else {
                container.innerHTML = status.alerts.map(alert => `
                    <div class="alert alert-${alert.type} alert-item mb-0">
                        <i class="fas fa-${alert.icon} me-2"></i>
                        ${alert.message}
                    </div>
                `).join('');
            }
        }

        async function refreshChart(sensor) {
            try {
                const response = await fetch(`/dashboard/historical-data?topic=${sensor}`, { cache: 'no-store' });
                const result = await response.json();

                if (result.success) {
                    const chart = charts[sensor];
                    chart.data.labels = result.data.map(item => item.timestamp);
                    chart.data.datasets[0].data = result.data.map(item => item.value);
                    chart.update();
                }
            } catch (error) {
                console.error('Erro ao atualizar gráfico:', error);
            }
        }

        // Inicializa os gráficos com dados históricos
        setTimeout(() => {
            refreshChart('humidity');
            refreshChart('temperature');
            refreshChart('wind');
        }, 1000);
    </script>
</body>
</html>
