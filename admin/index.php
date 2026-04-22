<?php
session_start();
if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

// Contadores para o dashboard
$total   = $pdo->query('SELECT COUNT(*) FROM contatos')->fetchColumn();
$novos   = $pdo->query("SELECT COUNT(*) FROM contatos WHERE status = 'novo'")->fetchColumn();
$lidos   = $pdo->query("SELECT COUNT(*) FROM contatos WHERE status = 'lido'")->fetchColumn();
$respondidos = $pdo->query("SELECT COUNT(*) FROM contatos WHERE status = 'respondido'")->fetchColumn();

// Mensagens (com filtro opcional)
$filtroStatus = $_GET['status'] ?? '';
$sql    = 'SELECT * FROM contatos';
$params = [];
if (in_array($filtroStatus, ['novo', 'lido', 'respondido'])) {
    $sql .= ' WHERE status = :s';
    $params[':s'] = $filtroStatus;
}
$sql .= ' ORDER BY criado_em DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contatos = $stmt->fetchAll();

$statusLabels = ['novo' => 'Novo', 'lido' => 'Lido', 'respondido' => 'Respondido'];
$statusColors = ['novo' => '#C0281E', 'lido' => '#1E5FA8', 'respondido' => '#28a745'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel Admin — Magtec</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Condensed:wght@700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Barlow', sans-serif; background: #F4F6FA; color: #222; min-height: 100vh; }

  /* HEADER */
  .header {
    background: #1A3A6B;
    padding: 0 24px;
    height: 58px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky; top: 0; z-index: 10;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
  }
  .header-logo .n1 { font-family: 'Barlow Condensed', sans-serif; color: #C0281E; font-size: 20px; font-weight: 800; }
  .header-logo .n2 { color: rgba(255,255,255,0.65); font-size: 10px; letter-spacing: 0.15em; }
  .header-badge { background: rgba(255,255,255,0.12); color: #fff; font-size: 11px; font-weight: 600; padding: 3px 12px; border-radius: 20px; letter-spacing: 0.08em; }
  .header-right { display: flex; align-items: center; gap: 16px; }
  .header-user { color: rgba(255,255,255,0.8); font-size: 13px; }
  .btn-logout { background: rgba(192,40,30,0.85); color: #fff; text-decoration: none; padding: 6px 14px; border-radius: 5px; font-size: 12px; font-weight: 600; transition: background 0.2s; }
  .btn-logout:hover { background: #C0281E; }

  /* LAYOUT */
  .container { max-width: 1100px; margin: 0 auto; padding: 28px 20px; }

  /* CARDS */
  .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
  .stat-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(26,58,107,0.07); border-top: 4px solid #1A3A6B; }
  .stat-card.vermelho { border-top-color: #C0281E; }
  .stat-card.azul { border-top-color: #1E5FA8; }
  .stat-card.verde { border-top-color: #28a745; }
  .stat-num { font-size: 36px; font-weight: 800; font-family: 'Barlow Condensed', sans-serif; color: #1A3A6B; line-height: 1; }
  .stat-card.vermelho .stat-num { color: #C0281E; }
  .stat-card.azul .stat-num { color: #1E5FA8; }
  .stat-card.verde .stat-num { color: #28a745; }
  .stat-label { font-size: 11px; font-weight: 600; color: #888; letter-spacing: 0.08em; text-transform: uppercase; margin-top: 4px; }

  /* TABELA */
  .table-section { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(26,58,107,0.07); overflow: hidden; }
  .table-header { padding: 18px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #edf0f7; }
  .table-header h2 { font-size: 16px; font-weight: 700; color: #1A3A6B; }
  .filtros { display: flex; gap: 8px; }
  .filtro-btn {
    padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
    text-decoration: none; border: 1.5px solid #dde3ef; color: #555;
    transition: all 0.2s;
  }
  .filtro-btn:hover, .filtro-btn.ativo { background: #1A3A6B; color: #fff; border-color: #1A3A6B; }
  .filtro-btn.ativo-novo { background: #C0281E; border-color: #C0281E; color: #fff; }

  table { width: 100%; border-collapse: collapse; }
  th { background: #F4F6FA; padding: 10px 16px; text-align: left; font-size: 11px; font-weight: 700; color: #888; letter-spacing: 0.06em; text-transform: uppercase; }
  td { padding: 12px 16px; border-top: 1px solid #f0f2f8; font-size: 13px; vertical-align: top; }
  tr:hover td { background: #fafbff; }

  .badge-status {
    display: inline-block; padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.06em;
  }
  .badge-novo       { background: #fdf0f0; color: #C0281E; }
  .badge-lido       { background: #e8f0fd; color: #1E5FA8; }
  .badge-respondido { background: #eaf6ec; color: #28a745; }

  .acao-btns { display: flex; gap: 6px; flex-wrap: wrap; }
  .btn-acao {
    padding: 4px 10px; border: none; border-radius: 4px; font-size: 11px;
    font-weight: 600; cursor: pointer; font-family: 'Barlow', sans-serif;
    transition: opacity 0.2s;
  }
  .btn-acao:hover { opacity: 0.8; }
  .btn-lido       { background: #e8f0fd; color: #1E5FA8; }
  .btn-respondido { background: #eaf6ec; color: #28a745; }
  .btn-del        { background: #fdf0f0; color: #C0281E; }

  .msg-text { color: #444; max-width: 280px; }
  .tel-link { color: #1E5FA8; text-decoration: none; }
  .tel-link:hover { text-decoration: underline; }

  .vazio { padding: 48px; text-align: center; color: #aaa; font-size: 14px; }
  .data-small { color: #aaa; font-size: 11px; margin-top: 3px; }

  @media (max-width: 768px) {
    .stats { grid-template-columns: repeat(2, 1fr); }
    table thead { display: none; }
    tr { display: block; border: 1px solid #edf0f7; border-radius: 8px; margin-bottom: 12px; }
    td { display: block; border: none; padding: 8px 14px; }
    td::before { content: attr(data-label); font-weight: 700; color: #888; font-size: 11px; display: block; }
  }
</style>
</head>
<body>

<header class="header">
  <div class="header-logo">
    <div class="n1">MAGTEC</div>
    <div class="n2">SERVIÇOS ELETRÔNICOS</div>
  </div>
  <div class="header-badge">PAINEL ADMIN</div>
  <div class="header-right">
    <span class="header-user">👤 <?= htmlspecialchars($_SESSION['admin_usuario']) ?></span>
    <a href="logout.php" class="btn-logout">Sair</a>
  </div>
</header>

<div class="container">

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-num"><?= $total ?></div>
      <div class="stat-label">Total de mensagens</div>
    </div>
    <div class="stat-card vermelho">
      <div class="stat-num"><?= $novos ?></div>
      <div class="stat-label">Novas</div>
    </div>
    <div class="stat-card azul">
      <div class="stat-num"><?= $lidos ?></div>
      <div class="stat-label">Lidas</div>
    </div>
    <div class="stat-card verde">
      <div class="stat-num"><?= $respondidos ?></div>
      <div class="stat-label">Respondidas</div>
    </div>
  </div>

  <!-- Tabela de mensagens -->
  <div class="table-section">
    <div class="table-header">
      <h2>Mensagens recebidas</h2>
      <div class="filtros">
        <a href="index.php" class="filtro-btn <?= !$filtroStatus ? 'ativo' : '' ?>">Todas</a>
        <a href="?status=novo" class="filtro-btn <?= $filtroStatus === 'novo' ? 'ativo-novo ativo' : '' ?>">Novas</a>
        <a href="?status=lido" class="filtro-btn <?= $filtroStatus === 'lido' ? 'ativo' : '' ?>">Lidas</a>
        <a href="?status=respondido" class="filtro-btn <?= $filtroStatus === 'respondido' ? 'ativo' : '' ?>">Respondidas</a>
      </div>
    </div>

    <?php if (empty($contatos)): ?>
      <div class="vazio">Nenhuma mensagem encontrada.</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Nome</th>
          <th>Contato</th>
          <th>Equipamento</th>
          <th>Mensagem</th>
          <th>Status</th>
          <th>Data</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="tabela-contatos">
        <?php foreach ($contatos as $c): ?>
        <tr id="row-<?= $c['id'] ?>">
          <td data-label="#"><?= $c['id'] ?></td>
          <td data-label="Nome"><strong><?= htmlspecialchars($c['nome']) ?></strong></td>
          <td data-label="Contato">
            <?php if ($c['telefone']): ?>
              <a href="https://api.whatsapp.com/send?phone=55<?= preg_replace('/\D/', '', $c['telefone']) ?>&text=Olá+<?= urlencode($c['nome']) ?>%2C+recebemos+sua+mensagem+na+Magtec!" target="_blank" rel="noopener noreferrer" class="tel-link">
                📱 <?= htmlspecialchars($c['telefone']) ?>
              </a>
            <?php else: ?>
              <span style="color:#aaa">—</span>
            <?php endif; ?>
          </td>
          <td data-label="Equipamento"><?= htmlspecialchars($c['equipamento'] ?: '—') ?></td>
          <td data-label="Mensagem">
            <div class="msg-text"><?= nl2br(htmlspecialchars($c['mensagem'])) ?></div>
          </td>
          <td data-label="Status">
            <span class="badge-status badge-<?= $c['status'] ?>" id="badge-<?= $c['id'] ?>">
              <?= $statusLabels[$c['status']] ?>
            </span>
          </td>
          <td data-label="Data">
            <?= date('d/m/Y', strtotime($c['criado_em'])) ?>
            <div class="data-small"><?= date('H:i', strtotime($c['criado_em'])) ?></div>
          </td>
          <td data-label="Ações">
            <div class="acao-btns">
              <?php if ($c['status'] !== 'lido'): ?>
              <button class="btn-acao btn-lido" onclick="atualizarStatus(<?= $c['id'] ?>, 'lido')">Marcar lido</button>
              <?php endif; ?>
              <?php if ($c['status'] !== 'respondido'): ?>
              <button class="btn-acao btn-respondido" onclick="atualizarStatus(<?= $c['id'] ?>, 'respondido')">Respondido</button>
              <?php endif; ?>
              <button class="btn-acao btn-del" onclick="deletarContato(<?= $c['id'] ?>)">Excluir</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <p style="text-align:center; color:#bbb; font-size:12px; margin-top:20px;">
    Magtec Admin &middot; <a href="../index.html" style="color:#1E5FA8;">Ver site</a>
  </p>
</div>

<script>
async function atualizarStatus(id, status) {
  const labels = { lido: 'Lido', respondido: 'Respondido' };
  const classes = { lido: 'badge-lido', respondido: 'badge-respondido' };

  const res = await fetch('../api/contatos.php', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, status })
  });
  const data = await res.json();

  if (data.sucesso) {
    const badge = document.getElementById('badge-' + id);
    badge.textContent = labels[status];
    badge.className = 'badge-status ' + classes[status];

    // Remove botões desnecessários
    const row = document.getElementById('row-' + id);
    row.querySelectorAll('.btn-lido, .btn-respondido').forEach(b => {
      if (b.textContent.includes('lido') && status === 'lido') b.remove();
      if (b.textContent.includes('Respondido') && status === 'respondido') b.remove();
    });
  }
}

async function deletarContato(id) {
  if (!confirm('Deseja realmente excluir esta mensagem?')) return;

  const res = await fetch('../api/contatos.php?id=' + id, { method: 'DELETE' });
  const data = await res.json();

  if (data.sucesso) {
    const row = document.getElementById('row-' + id);
    row.style.transition = 'opacity 0.3s';
    row.style.opacity = '0';
    setTimeout(() => row.remove(), 300);
  }
}
</script>
</body>
</html>
