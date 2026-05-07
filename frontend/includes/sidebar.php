<?php
$nav = [
  ['key' => 'dashboard',     'href' => '/pages/dashboard.php',         'label' => 'Dashboard'],
  ['key' => 'atletas',       'href' => '/pages/atletas.php',       'label' => 'Atletas'],
  ['key' => 'competiciones', 'href' => '/pages/competiciones.php', 'label' => 'Competiciones'],
  ['key' => 'inscripciones', 'href' => '/pages/inscripciones.php', 'label' => 'Inscripciones'],
  ['key' => 'puntuaciones',  'href' => '/pages/puntuaciones.php',  'label' => 'Puntuaciones'],
  ['key' => 'resultados',    'href' => '/pages/resultados.php',    'label' => 'Resultados'],
];
$icons = [
  'dashboard'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>',
  'atletas'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  'competiciones' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>',
  'inscripciones' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 14l2 2 4-4"/></svg>',
  'puntuaciones'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>',
  'resultados'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 16V9"/><path d="M12 16V5"/><path d="M17 16v-7"/></svg>',
];
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-mark">CP</div>
    <div>
      <div class="brand-title">CLASSIC</div>
      <div class="brand-sub">PHYSIQUE · MGMT</div>
    </div>
  </div>
  <nav class="nav">
    <?php foreach ($nav as $item): ?>
      <a href="<?= $item['href'] ?>" class="<?= $current === $item['key'] ? 'active' : '' ?>">
        <?= $icons[$item['key']] ?>
        <span><?= $item['label'] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="/pages/logout.php" style="display:flex;align-items:center;gap:.75rem;padding:.6rem .85rem;border-radius:.375rem;color:var(--muted-foreground);font-size:.875rem">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Cerrar sesión
    </a>
  </div>
</aside>
