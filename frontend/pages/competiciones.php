<?php
$pageTitle = 'Competiciones';
$current   = 'competiciones';
require_once __DIR__ . '/../includes/auth_check.php';
$current_user = requireAuth(['admin', 'organizador']);
include __DIR__ . '/../includes/header.php';

$comps = [
  ['name'=>'Campeonato Nacional Open','date'=>'12 May 2026','city'=>'Madrid','venue'=>'Palacio Vistalegre','inscritos'=>184,'estado'=>'Inscripciones abiertas'],
  ['name'=>'Copa Mediterránea Classic','date'=>'29 May 2026','city'=>'Valencia','venue'=>'Roig Arena','inscritos'=>96,'estado'=>'Inscripciones abiertas'],
  ['name'=>'Trofeo Atlántico Pro-Am','date'=>'14 Jun 2026','city'=>'A Coruña','venue'=>'Coliseum','inscritos'=>142,'estado'=>'Inscripciones abiertas'],
  ['name'=>'Iberian Classic Showdown','date'=>'03 Mar 2026','city'=>'Lisboa','venue'=>'Altice Arena','inscritos'=>210,'estado'=>'Finalizada'],
  ['name'=>'Andalucía Pro Qualifier','date'=>'21 Feb 2026','city'=>'Sevilla','venue'=>'Cartuja','inscritos'=>168,'estado'=>'Finalizada'],
  ['name'=>'Winter Classic Cup','date'=>'12 Ene 2026','city'=>'Barcelona','venue'=>'Sant Jordi','inscritos'=>124,'estado'=>'Finalizada'],
];
?>

<div class="page-header">
  <div>
    <h1>Competiciones</h1>
    <p>Calendario federativo y eventos</p>
  </div>
  <button class="btn btn-primary">+ Nueva competición</button>
</div>

<div class="grid grid-3">
  <?php foreach ($comps as $c): $fin = $c['estado'] === 'Finalizada'; ?>
    <div class="card">
      <div class="card-accent-bar <?= $fin ? 'muted' : '' ?>"></div>
      <div class="card-body">
        <span class="badge <?= $fin ? 'badge-muted' : 'badge-primary' ?>"><?= htmlspecialchars($c['estado']) ?></span>
        <h3 style="font-size:1.5rem;margin-top:.75rem;line-height:1.1"><?= htmlspecialchars($c['name']) ?></h3>
        <div style="margin-top:1rem;font-size:.875rem;color:var(--muted-foreground);display:flex;flex-direction:column;gap:.4rem">
          <div>📅 <?= htmlspecialchars($c['date']) ?></div>
          <div>📍 <?= htmlspecialchars($c['venue']) ?>, <?= htmlspecialchars($c['city']) ?></div>
          <div>👥 <?= $c['inscritos'] ?> inscritos</div>
        </div>
        <button class="btn btn-outline" style="width:100%;margin-top:1.25rem;justify-content:center">Ver detalles</button>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
