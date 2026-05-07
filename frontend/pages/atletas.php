<?php
$pageTitle = 'Atletas';
$current   = 'atletas';
require_once __DIR__ . '/../includes/auth_check.php';
$current_user = requireAuth(['admin', 'organizador']);
include __DIR__ . '/../includes/header.php';

$atletas = [
  ['id'=>'ATL-001','nombre'=>'Carlos Mendoza','cat'=>'Classic -180','fed'=>'IFBB','estado'=>'Activo'],
  ['id'=>'ATL-002','nombre'=>'Andrés Ruiz',  'cat'=>'Classic -175','fed'=>'IFBB','estado'=>'Activo'],
  ['id'=>'ATL-003','nombre'=>'Pablo García', 'cat'=>'Classic -170','fed'=>'NPC', 'estado'=>'Suspendido'],
  ['id'=>'ATL-004','nombre'=>'Luis Romero',  'cat'=>'Open',        'fed'=>'IFBB','estado'=>'Pendiente'],
  ['id'=>'ATL-005','nombre'=>'Marco Bianchi','cat'=>'Classic -180','fed'=>'IFBB','estado'=>'Activo'],
];
?>

<div class="page-header">
  <div>
    <h1>Atletas</h1>
    <p>Registro federativo de competidores</p>
  </div>
  <button class="btn btn-primary">+ Nuevo atleta</button>
</div>

<div class="card">
  <div class="card-body">
    <input class="input" placeholder="Buscar por nombre o ID..." style="margin-bottom:1rem;max-width:320px">
    <table class="table">
      <thead>
        <tr><th>ID</th><th>Nombre</th><th>Categoría</th><th>Federación</th><th>Estado</th></tr>
      </thead>
      <tbody>
        <?php foreach ($atletas as $a):
          $cls = ['Activo'=>'badge-success','Suspendido'=>'badge-muted','Pendiente'=>'badge-primary'][$a['estado']]; ?>
          <tr>
            <td style="font-family:monospace;color:var(--muted-foreground)"><?= $a['id'] ?></td>
            <td style="font-weight:500"><?= htmlspecialchars($a['nombre']) ?></td>
            <td><?= htmlspecialchars($a['cat']) ?></td>
            <td><?= htmlspecialchars($a['fed']) ?></td>
            <td><span class="badge <?= $cls ?>"><?= $a['estado'] ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
