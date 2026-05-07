<?php
$pageTitle = 'Dashboard';
$current   = 'dashboard';
require_once __DIR__ . '/../includes/auth_check.php';
$current_user = requireAuth();
include __DIR__ . '/../includes/header.php';

// En tu app real: cargar desde MySQL/PostgreSQL
$stats = [
  ['label' => 'Atletas activos',  'value' => '342'],
  ['label' => 'Competiciones',    'value' => '12'],
  ['label' => 'Inscripciones',    'value' => '184'],
  ['label' => 'Categorías',       'value' => '8'],
];

$proximas = [
  ['name' => 'Campeonato Nacional Open', 'date' => '12 May 2026', 'city' => 'Madrid',   'inscritos' => 184],
  ['name' => 'Copa Mediterránea Classic','date' => '29 May 2026', 'city' => 'Valencia', 'inscritos' => 96],
  ['name' => 'Trofeo Atlántico Pro-Am',  'date' => '14 Jun 2026', 'city' => 'A Coruña', 'inscritos' => 142],
];
?>

<div class="page-header">
  <div>
    <h1>Dashboard</h1>
    <p>Resumen general de la temporada</p>
  </div>
  <button class="btn btn-primary">+ Nueva competición</button>
</div>

<div class="grid grid-4" style="margin-bottom:2rem">
  <?php foreach ($stats as $s): ?>
    <div class="stat-card">
      <div class="stat-label"><?= htmlspecialchars($s['label']) ?></div>
      <div class="stat-value"><?= htmlspecialchars($s['value']) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-body">
    <h2 style="font-size:1.5rem;margin-bottom:1rem">Próximas competiciones</h2>
    <table class="table">
      <thead>
        <tr><th>Evento</th><th>Fecha</th><th>Ciudad</th><th>Inscritos</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($proximas as $c): ?>
          <tr>
            <td style="font-weight:500"><?= htmlspecialchars($c['name']) ?></td>
            <td><?= htmlspecialchars($c['date']) ?></td>
            <td><?= htmlspecialchars($c['city']) ?></td>
            <td><?= $c['inscritos'] ?></td>
            <td><a href="/competiciones.php" class="btn btn-outline">Ver</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
