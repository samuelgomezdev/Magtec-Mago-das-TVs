<?php
header('Content-Type: application/json; charset=utf-8');

// Restringir CORS ao próprio domínio (atualizar na hospedagem)
$allowed_origins = ['http://localhost', 'http://127.0.0.1'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDB();

    // POST — criar novo contato (formulário público)
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $_POST;

        $nome       = trim($data['nome'] ?? '');
        $telefone   = trim($data['telefone'] ?? '');
        $equipamento = trim($data['equipamento'] ?? '');
        $mensagem   = trim($data['mensagem'] ?? '');

        if (empty($nome) || strlen($nome) < 2) {
            http_response_code(422);
            echo json_encode(['sucesso' => false, 'erro' => 'Nome inválido.']);
            exit;
        }
        if (empty($mensagem) || strlen($mensagem) < 5) {
            http_response_code(422);
            echo json_encode(['sucesso' => false, 'erro' => 'Mensagem muito curta.']);
            exit;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $pdo->prepare(
            'INSERT INTO contatos (nome, telefone, equipamento, mensagem, ip_origem)
             VALUES (:nome, :telefone, :equipamento, :mensagem, :ip)'
        );
        $stmt->execute([
            ':nome'        => $nome,
            ':telefone'    => $telefone,
            ':equipamento' => $equipamento,
            ':mensagem'    => $mensagem,
            ':ip'          => $ip,
        ]);

        echo json_encode(['sucesso' => true, 'mensagem' => 'Mensagem enviada com sucesso!']);
        exit;
    }

    // As rotas abaixo exigem sessão de admin
    session_start();
    if (empty($_SESSION['admin_id'])) {
        http_response_code(401);
        echo json_encode(['erro' => 'Não autorizado.']);
        exit;
    }

    // GET — listar contatos (admin)
    if ($method === 'GET') {
        $status = $_GET['status'] ?? null;
        $sql = 'SELECT * FROM contatos';
        $params = [];
        if ($status && in_array($status, ['novo', 'lido', 'respondido'])) {
            $sql .= ' WHERE status = :status';
            $params[':status'] = $status;
        }
        $sql .= ' ORDER BY criado_em DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
        exit;
    }

    // PUT — atualizar status de um contato (admin)
    if ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id     = (int)($data['id'] ?? 0);
        $status = $data['status'] ?? '';

        if (!$id || !in_array($status, ['novo', 'lido', 'respondido'])) {
            http_response_code(422);
            echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos.']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE contatos SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);
        echo json_encode(['sucesso' => true]);
        exit;
    }

    // DELETE — remover contato (admin)
    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(422);
            echo json_encode(['sucesso' => false, 'erro' => 'ID inválido.']);
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM contatos WHERE id = :id');
        $stmt->execute([':id' => $id]);
        echo json_encode(['sucesso' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno no servidor.']);
}
