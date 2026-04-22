<?php
session_start();
if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$erro = '';

// CSRF: gera token na sessão e valida no POST
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $erro = 'Requisição inválida. Tente novamente.';
    } else {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = trim($_POST['senha'] ?? '');

    if ($usuario && $senha) {
        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare('SELECT id, senha_hash FROM admin WHERE usuario = :u LIMIT 1');
            $stmt->execute([':u' => $usuario]);
            $row  = $stmt->fetch();

            if ($row && password_verify($senha, $row['senha_hash'])) {
                $_SESSION['admin_id']      = $row['id'];
                $_SESSION['admin_usuario'] = $usuario;

                $pdo->prepare('UPDATE admin SET ultimo_acesso = NOW() WHERE id = :id')
                    ->execute([':id' => $row['id']]);

                header('Location: index.php');
                exit;
            } else {
                $erro = 'Usuário ou senha incorretos.';
            }
        } catch (PDOException $e) {
            $erro = 'Erro ao conectar ao banco de dados.';
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
    } // fim else CSRF
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Magtec</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Condensed:wght@700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Barlow', sans-serif;
    background: #F4F6FA;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .login-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 24px rgba(26,58,107,0.12);
    padding: 40px 36px;
    width: 100%;
    max-width: 380px;
  }
  .logo { text-align: center; margin-bottom: 28px; }
  .logo .n1 { font-family: 'Barlow Condensed', sans-serif; font-size: 28px; font-weight: 800; color: #C0281E; letter-spacing: 0.05em; }
  .logo .n2 { font-size: 11px; color: #888; letter-spacing: 0.15em; text-transform: uppercase; }
  .logo .badge { display: inline-block; background: #1A3A6B; color: #fff; font-size: 10px; font-weight: 600; padding: 2px 10px; border-radius: 20px; margin-top: 6px; letter-spacing: 0.1em; }
  h2 { font-size: 16px; font-weight: 600; color: #1A3A6B; margin-bottom: 20px; }
  .form-group { margin-bottom: 16px; }
  label { display: block; font-size: 12px; font-weight: 600; color: #1A3A6B; margin-bottom: 5px; letter-spacing: 0.04em; }
  input {
    width: 100%; padding: 10px 12px;
    border: 1.5px solid #dde3ef;
    border-radius: 6px; font-size: 14px;
    font-family: 'Barlow', sans-serif;
    transition: border-color 0.2s;
    outline: none;
  }
  input:focus { border-color: #1A3A6B; }
  .btn-login {
    width: 100%; padding: 11px;
    background: #1A3A6B; color: #fff;
    border: none; border-radius: 6px;
    font-size: 14px; font-weight: 600;
    cursor: pointer; transition: background 0.2s;
    font-family: 'Barlow', sans-serif;
    letter-spacing: 0.04em;
  }
  .btn-login:hover { background: #1E5FA8; }
  .erro { background: #fdf0f0; border: 1px solid #f5c2c2; color: #c0281e; padding: 10px 12px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
  .back { text-align: center; margin-top: 16px; font-size: 13px; }
  .back a { color: #1E5FA8; text-decoration: none; }
  .back a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="login-card">
  <div class="logo">
    <div class="n1">MAGTEC</div>
    <div class="n2">Serviços Eletrônicos</div>
    <div class="badge">PAINEL ADMIN</div>
  </div>
  <h2>Acesso restrito</h2>
  <?php if ($erro): ?>
    <div class="erro"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <div class="form-group">
      <label for="usuario">USUÁRIO</label>
      <input type="text" id="usuario" name="usuario" autocomplete="username" required>
    </div>
    <div class="form-group">
      <label for="senha">SENHA</label>
      <input type="password" id="senha" name="senha" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn-login">Entrar</button>
  </form>
  <div class="back"><a href="../index.html">← Voltar ao site</a></div>
</div>
</body>
</html>
