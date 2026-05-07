<?php
// ============================================
// admin/index.php
// Dashboard para crear noticias
// ============================================

session_start();
require_once 'config.php';

$error   = '';
$success = '';

// --- Autenticación simple por sesión ---
if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['antal_admin'] = true;
    } else {
        $error = 'Contraseña incorrecta.';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$logueado = isset($_SESSION['antal_admin']) && $_SESSION['antal_admin'] === true;

// --- Listar noticias existentes ---
$noticias = [];
$editando = null; // noticia cargada para editar
if ($logueado) {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT id, titulo_es, categoria_es, fecha_es, publicado FROM noticias ORDER BY creado_en DESC");
    $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Eliminar noticia
    if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
        $stmtDel = $pdo->prepare("SELECT imagen FROM noticias WHERE id = ?");
        $stmtDel->execute([$_GET['eliminar']]);
        $imgRow = $stmtDel->fetch(PDO::FETCH_ASSOC);
        if ($imgRow && $imgRow['imagen']) {
            $imgPath = dirname(__DIR__) . '/' . $imgRow['imagen'];
            if (file_exists($imgPath)) @unlink($imgPath);
        }
        $pdo->prepare("DELETE FROM noticias WHERE id = ?")->execute([$_GET['eliminar']]);
        header('Location: index.php?ok=eliminado');
        exit;
    }

    // Toggle publicado
    if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
        $stmtT = $pdo->prepare("UPDATE noticias SET publicado = 1 - publicado WHERE id = ?");
        $stmtT->execute([$_GET['toggle']]);
        header('Location: index.php?ok=actualizado');
        exit;
    }

    // Cargar noticia para editar
    if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
        $stmtE = $pdo->prepare("SELECT * FROM noticias WHERE id = ?");
        $stmtE->execute([$_GET['editar']]);
        $editando = $stmtE->fetch(PDO::FETCH_ASSOC);
        if ($editando) {
            // Convertir ||| de vuelta a párrafos con doble salto
            $editando['contenido_es'] = str_replace('|||', "\n\n", $editando['contenido_es']);
            $editando['contenido_en'] = str_replace('|||', "\n\n", $editando['contenido_en']);
        }
    }
}

if (isset($_GET['ok'])) {
    $msgs = [
        'guardado'    => '✅ Noticia publicada correctamente.',
        'eliminado'   => '🗑️ Noticia eliminada.',
        'actualizado' => '✏️ Noticia actualizada correctamente.',
    ];
    $success = $msgs[$_GET['ok']] ?? '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ANTAL24 — Panel de Noticias</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --azul:    #062d76;
      --verde:   #39a08b;
      --gris:    #f4f6fa;
      --borde:   rgba(6,45,118,0.12);
      --texto:   #1a2540;
      --muted:   #6b7280;
      --danger:  #e53e3e;
      --shadow:  0 4px 24px rgba(6,45,118,0.08);
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--gris);
      color: var(--texto);
      min-height: 100vh;
    }

    /* ---- TOPBAR ---- */
    .topbar {
      background: var(--azul);
      padding: 0 2rem;
      height: 60px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 12px rgba(0,0,0,0.15);
    }
    .topbar-logo {
      font-family: 'Playfair Display', serif;
      color: white;
      font-size: 20px;
      letter-spacing: 0.5px;
    }
    .topbar-logo span { color: var(--verde); }
    .btn-logout {
      font-size: 12px;
      color: rgba(255,255,255,0.7);
      text-decoration: none;
      border: 1px solid rgba(255,255,255,0.2);
      padding: 6px 14px;
      border-radius: 6px;
      transition: all 0.2s;
    }
    .btn-logout:hover { background: rgba(255,255,255,0.1); color: white; }

    /* ---- WRAPPER ---- */
    .wrapper {
      max-width: 1100px;
      margin: 0 auto;
      padding: 2.5rem 1.5rem 4rem;
      display: grid;
      grid-template-columns: 1fr 380px;
      gap: 2rem;
      align-items: start;
    }
    @media (max-width: 860px) {
      .wrapper { grid-template-columns: 1fr; }
    }

    /* ---- CARD ---- */
    .card {
      background: white;
      border-radius: 16px;
      padding: 2rem;
      border: 1px solid var(--borde);
      box-shadow: var(--shadow);
    }
    .card-title {
      font-family: 'Playfair Display', serif;
      font-size: 22px;
      color: var(--azul);
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid var(--verde);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* ---- FORM ---- */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }

    .field { display: flex; flex-direction: column; gap: 6px; }
    .field.full { grid-column: 1 / -1; }

    label {
      font-size: 11px;
      font-weight: 600;
      color: var(--azul);
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    label .req { color: var(--verde); }

    input[type="text"],
    input[type="file"],
    textarea,
    select {
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      padding: 10px 14px;
      border: 1.5px solid var(--borde);
      border-radius: 8px;
      color: var(--texto);
      background: white;
      transition: border-color 0.2s;
      width: 100%;
    }
    input[type="text"]:focus,
    textarea:focus,
    select:focus {
      outline: none;
      border-color: var(--verde);
      box-shadow: 0 0 0 3px rgba(57,160,139,0.12);
    }
    textarea { resize: vertical; min-height: 110px; line-height: 1.7; }

    .hint {
      font-size: 11px;
      color: var(--muted);
      margin-top: 3px;
    }

    .section-label {
      font-size: 13px;
      font-weight: 600;
      color: var(--azul);
      background: rgba(6,45,118,0.05);
      padding: 6px 12px;
      border-radius: 6px;
      border-left: 3px solid var(--verde);
      margin: 1.2rem 0 0.8rem;
      grid-column: 1 / -1;
    }

    /* ---- BOTÓN ---- */
    .btn-submit {
      margin-top: 1.5rem;
      grid-column: 1 / -1;
      background: var(--azul);
      color: white;
      border: none;
      padding: 14px 28px;
      border-radius: 10px;
      font-family: 'DM Sans', sans-serif;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      letter-spacing: 0.3px;
    }
    .btn-submit:hover {
      background: #0a3d8f;
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(6,45,118,0.25);
    }

    /* ---- ALERTS ---- */
    .alert {
      padding: 12px 16px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 1.5rem;
    }
    .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
    .alert-error   { background: #fef2f2; color: #7f1d1d; border: 1px solid #fca5a5; }

    /* ---- TABLA DE NOTICIAS ---- */
    .news-list { display: flex; flex-direction: column; gap: 10px; }
    .news-item {
      background: white;
      border: 1px solid var(--borde);
      border-radius: 10px;
      padding: 14px 16px;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .news-item-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .dot-on  { background: var(--verde); }
    .dot-off { background: var(--muted); }
    .news-item-body { flex: 1; min-width: 0; }
    .news-item-title {
      font-size: 13px;
      font-weight: 600;
      color: var(--azul);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .news-item-meta { font-size: 11px; color: var(--muted); margin-top: 2px; }
    .news-item-cat {
      font-size: 10px;
      font-weight: 600;
      color: var(--verde);
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .news-item-actions { display: flex; gap: 6px; flex-shrink: 0; align-items: center; }
    .btn-del {
      font-size: 11px;
      color: var(--danger);
      text-decoration: none;
      border: 1px solid rgba(229,62,62,0.25);
      padding: 4px 10px;
      border-radius: 6px;
      flex-shrink: 0;
      transition: all 0.2s;
    }
    .btn-del:hover { background: #fef2f2; }
    .btn-edit {
      font-size: 11px;
      color: var(--azul);
      text-decoration: none;
      border: 1px solid rgba(6,45,118,0.25);
      padding: 4px 10px;
      border-radius: 6px;
      flex-shrink: 0;
      transition: all 0.2s;
    }
    .btn-edit:hover { background: rgba(6,45,118,0.06); }
    .btn-toggle {
      font-size: 11px;
      color: var(--verde);
      text-decoration: none;
      border: 1px solid rgba(57,160,139,0.3);
      padding: 4px 10px;
      border-radius: 6px;
      flex-shrink: 0;
      transition: all 0.2s;
    }
    .btn-toggle:hover { background: rgba(57,160,139,0.08); }
    .form-editing-banner {
      background: #fffbeb;
      border: 1.5px solid #f59e0b;
      color: #92400e;
      border-radius: 10px;
      padding: 10px 16px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 1.2rem;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* ---- LOGIN ---- */
    .login-wrap {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--gris);
    }
    .login-card {
      background: white;
      border-radius: 20px;
      padding: 3rem 2.5rem;
      width: 100%;
      max-width: 380px;
      border: 1px solid var(--borde);
      box-shadow: 0 8px 48px rgba(6,45,118,0.12);
      text-align: center;
    }
    .login-logo {
      font-family: 'Playfair Display', serif;
      font-size: 28px;
      color: var(--azul);
      margin-bottom: 0.3rem;
    }
    .login-logo span { color: var(--verde); }
    .login-sub { font-size: 13px; color: var(--muted); margin-bottom: 2rem; }
    .login-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
      text-align: left;
      margin-bottom: 1.2rem;
    }
    .login-btn {
      width: 100%;
      padding: 13px;
      background: var(--azul);
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all 0.2s;
    }
    .login-btn:hover { background: #0a3d8f; }
    .login-footer { margin-top: 1.5rem; font-size: 11px; color: var(--muted); }

    /* ---- IMAGE PREVIEW ---- */
    #img-preview {
      margin-top: 10px;
      max-width: 100%;
      max-height: 160px;
      border-radius: 8px;
      border: 1.5px solid var(--borde);
      display: none;
      object-fit: cover;
    }
  </style>
</head>
<body>

<?php if (!$logueado): ?>
<!-- ============================
     PANTALLA DE LOGIN
============================= -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">ANTAL<span>24</span></div>
    <p class="login-sub">Panel de Noticias — Acceso restringido</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="login-field">
        <label>Contraseña de acceso</label>
        <input type="password" name="password" placeholder="••••••••" required autofocus />
      </div>
      <button type="submit" class="login-btn">Ingresar al panel →</button>
    </form>
    <p class="login-footer">antal24.com © <?= date('Y') ?></p>
  </div>
</div>

<?php else: ?>
<!-- ============================
     PANEL PRINCIPAL
============================= -->
<div class="topbar">
  <span class="topbar-logo">ANTAL<span>24</span> — Noticias</span>
  <a href="?logout=1" class="btn-logout">Cerrar sesión</a>
</div>

<div class="wrapper">

  <!-- COLUMNA IZQUIERDA: FORMULARIO -->
  <div>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-title">
        <?php if ($editando): ?>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Editar Noticia
        <?php else: ?>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          Nueva Noticia
        <?php endif; ?>
      </div>

      <?php if ($editando): ?>
        <div class="form-editing-banner">
          ✏️ Editando: <em><?= htmlspecialchars($editando['titulo_es']) ?></em>
          &nbsp;·&nbsp;
          <a href="index.php" style="color:inherit;font-weight:700;">✕ Cancelar</a>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= $editando ? 'actualizar.php' : 'guardar.php' ?>" enctype="multipart/form-data">
        <?php if ($editando): ?>
          <input type="hidden" name="id" value="<?= (int)$editando['id'] ?>" />
        <?php endif; ?>
        <div class="form-grid">

          <!-- SECCIÓN ESPAÑOL -->
          <div class="section-label">🇲🇽 Versión en Español</div>

          <div class="field">
            <label>Categoría ES <span class="req">*</span></label>
            <input type="text" name="categoria_es" placeholder="Ej: Logística" required
              value="<?= htmlspecialchars($editando['categoria_es'] ?? '') ?>" />
          </div>
          <div class="field">
            <label>Fecha ES <span class="req">*</span></label>
            <input type="text" name="fecha_es" placeholder="12 de abril de 2026" required
              value="<?= htmlspecialchars($editando['fecha_es'] ?? '') ?>" />
          </div>
          <div class="field full">
            <label>Título ES <span class="req">*</span></label>
            <input type="text" name="titulo_es" placeholder="Título de la noticia en español" required
              value="<?= htmlspecialchars($editando['titulo_es'] ?? '') ?>" />
          </div>
          <div class="field full">
            <label>Contenido ES <span class="req">*</span></label>
            <textarea name="contenido_es" placeholder="Escribe cada párrafo separado por una línea en blanco.&#10;&#10;Párrafo 1...&#10;&#10;Párrafo 2..." required><?= htmlspecialchars($editando['contenido_es'] ?? '') ?></textarea>
            <span class="hint">Separa los párrafos con una línea en blanco entre cada uno.</span>
          </div>
          <div class="field full">
            <label>Tags ES</label>
            <input type="text" name="tags_es" placeholder="Transporte, Norte, Distribución"
              value="<?= htmlspecialchars($editando['tags_es'] ?? '') ?>" />
            <span class="hint">Separados por coma.</span>
          </div>

          <!-- SECCIÓN INGLÉS -->
          <div class="section-label">🇺🇸 Versión en Inglés</div>

          <div class="field">
            <label>Categoría EN <span class="req">*</span></label>
            <input type="text" name="categoria_en" placeholder="Ej: Logistics" required
              value="<?= htmlspecialchars($editando['categoria_en'] ?? '') ?>" />
          </div>
          <div class="field">
            <label>Fecha EN <span class="req">*</span></label>
            <input type="text" name="fecha_en" placeholder="April 12, 2026" required
              value="<?= htmlspecialchars($editando['fecha_en'] ?? '') ?>" />
          </div>
          <div class="field full">
            <label>Título EN <span class="req">*</span></label>
            <input type="text" name="titulo_en" placeholder="News title in English" required
              value="<?= htmlspecialchars($editando['titulo_en'] ?? '') ?>" />
          </div>
          <div class="field full">
            <label>Contenido EN <span class="req">*</span></label>
            <textarea name="contenido_en" placeholder="Write each paragraph separated by a blank line.&#10;&#10;Paragraph 1...&#10;&#10;Paragraph 2..." required><?= htmlspecialchars($editando['contenido_en'] ?? '') ?></textarea>
            <span class="hint">Separate paragraphs with a blank line.</span>
          </div>
          <div class="field full">
            <label>Tags EN</label>
            <input type="text" name="tags_en" placeholder="Transport, North, Distribution"
              value="<?= htmlspecialchars($editando['tags_en'] ?? '') ?>" />
          </div>

          <!-- IMAGEN -->
          <div class="section-label">🖼️ Imagen de portada</div>

          <div class="field full">
            <label>Imagen (JPG, PNG, WebP — máx. 5MB)<?= $editando && $editando['imagen'] ? ' — <em style="font-weight:400;color:var(--muted);">dejar vacío para mantener la actual</em>' : '' ?></label>
            <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" id="img-input" />
            <?php if ($editando && $editando['imagen']): ?>
              <img id="img-preview" src="../<?= htmlspecialchars($editando['imagen']) ?>" alt="Imagen actual" style="display:block;" />
            <?php else: ?>
              <img id="img-preview" src="" alt="Vista previa" />
            <?php endif; ?>
          </div>

          <!-- PUBLICADO -->
          <div class="field">
            <label>Estado</label>
            <select name="publicado">
              <option value="1" <?= ($editando['publicado'] ?? 1) == 1 ? 'selected' : '' ?>>✅ Publicado</option>
              <option value="0" <?= ($editando['publicado'] ?? 1) == 0 ? 'selected' : '' ?>>📝 Borrador</option>
            </select>
          </div>

          <button type="submit" class="btn-submit">
            <?php if ($editando): ?>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
              Guardar cambios
            <?php else: ?>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
              Publicar noticia
            <?php endif; ?>
          </button>

        </div>
      </form>
    </div>
  </div>

  <!-- COLUMNA DERECHA: LISTA DE NOTICIAS -->
  <div>
    <div class="card">
      <div class="card-title" style="font-size:18px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        Noticias (<?= count($noticias) ?>)
      </div>

      <?php if (empty($noticias)): ?>
        <p style="font-size:13px;color:var(--muted);text-align:center;padding:2rem 0;">
          Aún no hay noticias publicadas.
        </p>
      <?php else: ?>
        <div class="news-list">
          <?php foreach ($noticias as $n): ?>
            <div class="news-item <?= (isset($_GET['editar']) && $_GET['editar'] == $n['id']) ? 'news-item--active' : '' ?>">
              <div class="news-item-dot <?= $n['publicado'] ? 'dot-on' : 'dot-off' ?>"
                   title="<?= $n['publicado'] ? 'Publicado' : 'Borrador' ?>"></div>
              <div class="news-item-body">
                <div class="news-item-cat"><?= htmlspecialchars($n['categoria_es']) ?></div>
                <div class="news-item-title"><?= htmlspecialchars($n['titulo_es']) ?></div>
                <div class="news-item-meta"><?= htmlspecialchars($n['fecha_es']) ?> · <?= $n['publicado'] ? '<span style="color:var(--verde)">Publicado</span>' : '<span style="color:var(--muted)">Borrador</span>' ?></div>
              </div>
              <div class="news-item-actions">
                <a href="?editar=<?= $n['id'] ?>" class="btn-edit" title="Editar">✏️</a>
                <a href="?toggle=<?= $n['id'] ?>" class="btn-toggle" title="<?= $n['publicado'] ? 'Pasar a borrador' : 'Publicar' ?>">
                  <?= $n['publicado'] ? '📝' : '✅' ?>
                </a>
                <a href="?eliminar=<?= $n['id'] ?>"
                   class="btn-del" title="Eliminar"
                   onclick="return confirm('¿Eliminar esta noticia?')">✕</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

</div><!-- /wrapper -->
<?php endif; ?>

<script>
  // Preview de imagen antes de subir
  document.getElementById('img-input')?.addEventListener('change', function () {
    const preview = document.getElementById('img-preview');
    const file = this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = e => {
        preview.src = e.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(file);
    } else {
      // Si hay imagen guardada, no la ocultar
      if (!preview.dataset.existing) {
        preview.style.display = 'none';
      }
    }
  });

  // Scroll al formulario si estamos editando
  if (window.location.search.includes('editar=')) {
    document.querySelector('.card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
</script>
</body>
</html>