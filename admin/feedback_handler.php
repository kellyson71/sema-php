<?php
header('Content-Type: application/json');

// Configurações
$feedbackFile = 'feedback_data.json';
$maxFeedbackEntries = 1000; // Limite máximo de entradas

// Função para carregar feedback existente
function loadFeedback() {
    global $feedbackFile;
    
    if (file_exists($feedbackFile)) {
        $content = file_get_contents($feedbackFile);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }
    
    return [];
}

// Função para salvar feedback
function saveFeedback($data) {
    global $feedbackFile;
    
    try {
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            throw new Exception('Erro ao codificar JSON');
        }
        
        $result = file_put_contents($feedbackFile, $jsonData);
        if ($result === false) {
            throw new Exception('Erro ao salvar arquivo');
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Erro ao salvar feedback: " . $e->getMessage());
        return false;
    }
}

// Função para limpar feedback antigo se exceder o limite
function cleanOldFeedback($data) {
    global $maxFeedbackEntries;
    
    if (count($data) > $maxFeedbackEntries) {
        // Manter apenas as entradas mais recentes
        $data = array_slice($data, -$maxFeedbackEntries);
    }
    
    return $data;
}

// Processar requisição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Ação não especificada']);
        exit;
    }
    
    switch ($input['action']) {
        case 'submit_feedback':
            if (!isset($input['type']) || !isset($input['rating'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Dados incompletos']);
                exit;
            }
            
            $feedback = [
                'id' => uniqid(),
                'timestamp' => date('Y-m-d H:i:s'),
                'type' => $input['type'],
                'rating' => $input['rating'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                'page' => $input['page'] ?? 'visualizar_requerimento.php'
            ];
            
            // Adicionar comentário se fornecido
            if (isset($input['comment']) && !empty($input['comment'])) {
                $feedback['comment'] = trim($input['comment']);
            }
            
            $data = loadFeedback();
            $data[] = $feedback;
            $data = cleanOldFeedback($data);
            
            if (saveFeedback($data)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Feedback registrado com sucesso',
                    'feedback_id' => $feedback['id']
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erro ao salvar feedback']);
            }
            break;
            
        case 'get_stats':
            $data = loadFeedback();
            
            $stats = [
                'total' => count($data),
                'by_type' => [],
                'by_rating' => [],
                'recent' => array_slice(array_reverse($data), 0, 10)
            ];
            
            // Estatísticas por tipo
            foreach ($data as $item) {
                $type = $item['type'] ?? 'unknown';
                $rating = $item['rating'] ?? 'unknown';
                
                if (!isset($stats['by_type'][$type])) {
                    $stats['by_type'][$type] = 0;
                }
                $stats['by_type'][$type]++;
                
                if (!isset($stats['by_rating'][$rating])) {
                    $stats['by_rating'][$rating] = 0;
                }
                $stats['by_rating'][$rating]++;
            }
            
            echo json_encode($stats);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Ação não reconhecida']);
            break;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
}
?>
