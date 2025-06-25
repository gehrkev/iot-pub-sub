<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class DashboardController extends Controller
{
    private $socket = null;

    public function index()
    {
        return view('dashboard.index');
    }

    public function getSensorData(): JsonResponse
    {
        try {
            $data = $this->connectAndGetData();
            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toISOString()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao conectar com o broker: ' . $e->getMessage()
            ], 500);
        }
    }

    private function connectAndGetData(): array
    {
        $brokerHost = env('BROKER_HOST', 'localhost');
        $brokerPort = env('BROKER_PORT', 8080);

        Log::info('Dashboard: Attempting to connect to broker...');

        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (!$this->socket) {
            Log::error('Dashboard: Socket creation failed: ' . socket_strerror(socket_last_error()));
            throw new Exception('Não foi possível criar socket');
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 10, 'usec' => 0)); // Timeout de recebimento de 10s
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 10, 'usec' => 0)); // Timeout de envio de 10s

        Log::info('Dashboard: Connecting to ' . $brokerHost . ':' . $brokerPort);
        $result = socket_connect($this->socket, $brokerHost, $brokerPort);

        if (!$result) {
            Log::error('Dashboard: Socket connect failed: ' . socket_strerror(socket_last_error($this->socket)));
            socket_close($this->socket); // Fecha o socket se a conexão falhar
            throw new Exception('Não foi possível conectar ao broker: ' . socket_strerror(socket_last_error($this->socket)));
        }
        Log::info('Dashboard: Connected successfully.');

        $message = json_encode([
            'type' => 'get_current',
            'topic' => '',
            'data' => 0,
            'timestamp' => now()->toISOString(),
            'client_id' => 'laravel_dashboard'
        ]);

        Log::info('Dashboard: Writing message to broker: ' . $message);
        socket_write($this->socket, $message . "\n");
        Log::info('Dashboard: Message written. Attempting to read response...');

        $sensorData = [];
        $timeout = time() + 5;
        $buffer = '';

        while (time() < $timeout) {
            Log::info('Dashboard: Loop de leitura - time left: ' . ($timeout - time()) . 's');
            $responseChunk = socket_read($this->socket, 1024);

            if ($responseChunk === false) {
                // Erro ou conexão fechada pelo peer
                $lastError = socket_last_error($this->socket);
                if ($lastError != 0 && $lastError != 104) { // 104 = Connection reset by peer (pode ser normal)
                    Log::warning('Dashboard: Socket read error: ' . socket_strerror($lastError));
                }
                break;
            }
            if ($responseChunk === '') {
                // Nenhum dado lido, pode significar que a conexão foi fechada ou não há mais dados
                Log::info('Dashboard: Empty response chunk received. Assuming end of data or connection closed by broker.');
                break;
            }

            Log::info('Dashboard: Chunk received: ' . trim($responseChunk));
            $buffer .= $responseChunk;

            // Tenta processar mensagens completas no buffer
            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $response = substr($buffer, 0, $newlinePos);
                $buffer = substr($buffer, $newlinePos + 1); // Remove a mensagem processada do buffer

                $data = json_decode(trim($response), true);
                if ($data && isset($data['type']) && $data['type'] === 'sensor_data') {
                    $sensorData[$data['topic']] = [
                        'value' => $data['data'],
                        'timestamp' => $data['timestamp'],
                        'unit' => $this->getUnit($data['topic'])
                    ];
                    Log::info('Dashboard: Processed sensor data for topic: ' . $data['topic']); // Log
                }
            }
             // Se não houver mais dados por um curto período, saia (para evitar esperar pelo timeout de 5s se o broker já enviou tudo)
            socket_set_nonblock($this->socket);
            $canRead = @socket_read($this->socket, 1); // Tenta ler 1 byte sem bloquear
            socket_set_block($this->socket);
            if ($canRead === false || $canRead === '') {
                 Log::info('Dashboard: No more immediate data from broker in non-blocking check.');
                 break;
            } else {
                $buffer .= $canRead; // Adiciona o byte lido de volta ao buffer
            }
        }
        Log::info('Dashboard: Finished reading loop. Sensor data count: ' . count($sensorData)); // Log

        socket_close($this->socket);

        return $sensorData;
    }

    private function getUnit(string $topic): string
    {
        $units = [
            'humidity' => '%',
            'temperature' => '°C',
            'wind' => 'km/h'
        ];

        return $units[$topic] ?? '';
    }

    public function getHistoricalData(Request $request): JsonResponse
    {
        $topic = $request->get('topic', 'humidity');

        try {
            // Em uma implementação real, salvaríamos os dados em banco
            // Aqui vamos simular dados históricos
            $data = $this->generateMockHistoricalData($topic);

            return response()->json([
                'success' => true,
                'data' => $data,
                'topic' => $topic
            ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0')
              ->header('Pragma', 'no-cache') // Para compatibilidade
              ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT'); // Data no passado
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function generateMockHistoricalData(string $topic): array
    {
        $data = [];
        $now = now();

        // Gera dados das últimas 24 horas
        for ($i = 23; $i >= 0; $i--) {
            $timestamp = $now->copy()->subHours($i);

            switch ($topic) {
                case 'humidity':
                    $value = 45 + sin($i * 0.5) * 15 + rand(-5, 5);
                    break;
                case 'temperature':
                    $value = 25 + sin($i * 0.3) * 8 + rand(-2, 3);
                    break;
                case 'wind':
                    $value = 15 + sin($i * 0.7) * 10 + rand(-3, 8);
                    break;
                default:
                    $value = rand(0, 100);
            }

            $data[] = [
                'timestamp' => $timestamp->format('H:i'),
                'value' => round($value, 1)
            ];
        }

        return $data;
    }

    public function getSystemStatus(): JsonResponse
    {
        try {
            $currentData = $this->connectAndGetData();

            // Calcula status do sistema baseado nos dados
            $status = $this->calculateSystemStatus($currentData);

            return response()->json([
                'success' => true,
                'status' => $status
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function calculateSystemStatus(array $sensorData): array
    {
        $humidity = $sensorData['humidity']['value'] ?? 50;
        $temperature = $sensorData['temperature']['value'] ?? 25;
        $wind = $sensorData['wind']['value'] ?? 15;

        $alerts = [];

        // Verifica condições críticas
        if ($humidity < 20) {
            $alerts[] = [
                'type' => 'danger',
                'message' => 'Condições de solo seco. Necessário irrigar.',
                'icon' => 'exclamation-triangle'
            ];
        } elseif ($humidity < 30) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'Umidade de solo baixa. Considere irrigar.',
                'icon' => 'exclamation-circle'
            ];
        }

        if ($temperature > 35) {
            $alerts[] = [
                'type' => 'danger',
                'message' => 'Perigo, temperatura extrema! Danos podem estar ocorrendo, verifique.',
                'icon' => 'thermometer-full'
            ];
        } elseif ($temperature > 30) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'Atenção, temperatura elevada.',
                'icon' => 'thermometer-half'
            ];
        }

        if ($wind > 60) {
            $alerts[] = [
                'type' => 'danger',
                'message' => 'Perigo, ventos extremos! Risco de acamamento.',
                'icon' => 'wind'
            ];
        } elseif ($wind > 40) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'Atenção, ventos fortes na região.',
                'icon' => 'wind'
            ];
        }

        // Status geral
        $overallStatus = 'good';
        if (count($alerts) > 0) {
            $overallStatus = in_array('danger', array_column($alerts, 'type')) ? 'critical' : 'warning';
        }

        return [
            'overall' => $overallStatus,
            'alerts' => $alerts,
            'irrigation_needed' => $humidity < 30,
            'last_update' => now()->format('H:i:s')
        ];
    }
}
