<?php
/**
 * Valírica — Seed: Canal de Denuncias
 * ─────────────────────────────────────────────────────────────────────────────
 * Ejecuta UNA SOLA VEZ desde el navegador o CLI, luego ELIMINA este archivo.
 * URL: https://www.valirica.com/seed-canal-denuncias.php
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
?><!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Seed: Canal de Denuncias — Valírica</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 720px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
  h1 { color: #012133; }
  .ok   { background: #e8f5e9; border: 1px solid #4caf50; padding: 12px 16px; margin: 8px 0; border-radius: 8px; }
  .skip { background: #fff8e1; border: 1px solid #ffc107; padding: 12px 16px; margin: 8px 0; border-radius: 8px; }
  .err  { background: #fce4ec; border: 1px solid #f44336; padding: 12px 16px; margin: 8px 0; border-radius: 8px; }
  .warn { background: #fff3e0; border: 2px solid #ff9800; padding: 14px 18px; margin: 16px 0; border-radius: 8px; font-weight: 600; }
  .btn  { display: inline-block; background: #012133; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 8px; margin-top: 20px; }
</style>
</head>
<body>
<h1>Seed: Canal de Denuncias — Valírica</h1>
<?php

$post = [
  'slug'            => 'canal-de-denuncias-ley-2-2023-espana-ley-1010-colombia-2025',
  'title'           => 'Canal de Denuncias en PYMES: Requisitos Ley 2/2023 (España) y Ley 1010 (Colombia) 2025',
  'excerpt'         => '¿Tu empresa necesita un canal de denuncias? Conoce los requisitos legales de la Ley 2/2023 en España y la Ley 1010 en Colombia, los plazos de respuesta, sanciones y cómo implementarlo en tu PYME en 2025.',
  'cover_gradient'  => 'linear-gradient(135deg, #1c3d6e 0%, #0d2145 100%)',
  'cover_image'     => 'icon:ph-shield-check',
  'author_name'     => 'Equipo Valírica',
  'author_title'    => 'Expertos en RRHH y Cultura Organizacional',
  'author_avatar'   => '',
  'category'        => 'Compliance',
  'tags'            => 'canal de denuncias, ley 2/2023, ley 1010, compliance, PYMES, sistema interno de información, acoso laboral, canal de denuncias España, canal de denuncias Colombia, Comité de Convivencia Laboral',
  'seo_title'       => 'Canal de Denuncias PYMES: Ley 2/2023 y Ley 1010 | Valírica',
  'seo_description' => '¿Tu empresa necesita un canal de denuncias? Conoce los requisitos legales de la Ley 2/2023 en España y la Ley 1010 en Colombia. Guía completa para PYMES 2025.',
  'seo_keywords'    => 'canal de denuncias ley 2/2023, canal de denuncias pymes españa, ley 1010 acoso laboral colombia, sistema interno de información, requisitos canal de denuncias, canal de denuncias Colombia 2025',
  'reading_time'    => 9,
  'featured'        => 0,
  'status'          => 'published',
  'published_at'    => '2025-03-17 09:00:00',

  'content'         => <<<'HTML'
<p>Un canal de denuncias es un sistema interno que permite a empleados reportar conductas ilegales o contrarias a la ética de forma confidencial y sin miedo a represalias. En España es obligatorio para empresas con ≥50 trabajadores (Ley 2/2023); en Colombia lo regula la Ley 1010 a través del Comité de Convivencia Laboral.</p>

<h2>¿Qué es un canal de denuncias y para qué sirve?</h2>

<p>Un canal de denuncias —también llamado <strong>sistema interno de información</strong> o <strong>línea ética</strong>— es el mecanismo que permite a los empleados, colaboradores o terceros reportar de forma segura, confidencial y (opcionalmente) anónima cualquier conducta irregular dentro de una organización.</p>

<p>Su objetivo no es castigar, sino <strong>prevenir</strong>: detectar problemas antes de que escalen, proteger la integridad de la empresa y crear una cultura organizacional basada en la transparencia.</p>

<p>Los tipos de conductas que suelen reportarse incluyen:</p>
<ul>
  <li>Acoso laboral o sexual</li>
  <li>Fraude o corrupción interna</li>
  <li>Incumplimientos de normativas legales</li>
  <li>Discriminación o abuso de poder</li>
  <li>Conflictos de interés</li>
</ul>

<h2>¿Qué empresas están obligadas a tener un canal de denuncias en España?</h2>

<p>Desde la entrada en vigor de la <strong>Ley 2/2023, de 20 de febrero</strong>, toda empresa española con <strong>50 o más empleados</strong> debe contar con un canal de denuncias. El plazo de implementación para empresas de 50 a 249 empleados expiró el 1 de diciembre de 2023.</p>

<p>También están obligadas, independientemente de su tamaño, las empresas que operen en sectores regulados como:</p>
<ul>
  <li>Servicios financieros</li>
  <li>Prevención de blanqueo de capitales</li>
  <li>Seguridad en el transporte</li>
</ul>

<blockquote>
  ¿Cuánto puede costar no cumplir? Las sanciones por infringir la Ley 2/2023 pueden llegar hasta <strong>1.000.000 €</strong> para personas jurídicas en casos de infracciones muy graves.
  <em>Fuente: Ley 2/2023, de 20 de febrero, reguladora de la protección de las personas que informen sobre infracciones normativas.</em>
</blockquote>

<h2>¿Cuáles son los requisitos técnicos del canal de denuncias según la Ley 2/2023?</h2>

<h3>¿Qué debe garantizar el canal?</h3>

<p>La ley es clara: el canal debe cumplir con los siguientes requisitos técnicos y de gestión:</p>

<div style="overflow-x:auto;margin:28px 0;border-radius:14px;border:1px solid rgba(255,255,255,0.09);overflow:hidden;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead>
      <tr style="background:rgba(0,122,150,0.22);border-bottom:1px solid rgba(255,255,255,0.12);">
        <th style="padding:13px 18px;text-align:left;font-weight:700;color:#fff;white-space:nowrap;">Requisito</th>
        <th style="padding:13px 18px;text-align:left;font-weight:700;color:#fff;">Obligación legal</th>
      </tr>
    </thead>
    <tbody>
      <tr style="border-bottom:1px solid rgba(255,255,255,0.06);">
        <td style="padding:11px 18px;color:#4dd6f0;font-weight:600;white-space:nowrap;">Confidencialidad</td>
        <td style="padding:11px 18px;color:rgba(255,255,255,0.78);">Identidad del denunciante protegida en todo momento</td>
      </tr>
      <tr style="border-bottom:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.025);">
        <td style="padding:11px 18px;color:#4dd6f0;font-weight:600;white-space:nowrap;">Anonimato</td>
        <td style="padding:11px 18px;color:rgba(255,255,255,0.78);">Permite (aunque no exige) denuncias anónimas</td>
      </tr>
      <tr style="border-bottom:1px solid rgba(255,255,255,0.06);">
        <td style="padding:11px 18px;color:#4dd6f0;font-weight:600;white-space:nowrap;">Acuse de recibo</td>
        <td style="padding:11px 18px;color:rgba(255,255,255,0.78);">En <strong style="color:#fff;">7 días hábiles</strong> desde la recepción</td>
      </tr>
      <tr style="border-bottom:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.025);">
        <td style="padding:11px 18px;color:#4dd6f0;font-weight:600;white-space:nowrap;">Respuesta</td>
        <td style="padding:11px 18px;color:rgba(255,255,255,0.78);">Máximo <strong style="color:#fff;">3 meses</strong> desde la denuncia</td>
      </tr>
      <tr style="border-bottom:1px solid rgba(255,255,255,0.06);">
        <td style="padding:11px 18px;color:#4dd6f0;font-weight:600;white-space:nowrap;">Responsable designado</td>
        <td style="padding:11px 18px;color:rgba(255,255,255,0.78);">Persona física o comité independiente</td>
      </tr>
      <tr style="border-bottom:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.025);">
        <td style="padding:11px 18px;color:#4dd6f0;font-weight:600;white-space:nowrap;">Libro registro</td>
        <td style="padding:11px 18px;color:rgba(255,255,255,0.78);">Registro reservado de todas las comunicaciones</td>
      </tr>
      <tr style="border-bottom:1px solid rgba(255,255,255,0.06);">
        <td style="padding:11px 18px;color:#4dd6f0;font-weight:600;white-space:nowrap;">Política publicada</td>
        <td style="padding:11px 18px;color:rgba(255,255,255,0.78);">Documento público con los principios del sistema</td>
      </tr>
      <tr style="background:rgba(255,255,255,0.025);">
        <td style="padding:11px 18px;color:#4dd6f0;font-weight:600;white-space:nowrap;">Sin represalias</td>
        <td style="padding:11px 18px;color:rgba(255,255,255,0.78);">Prohibición de despido, degradación o sanción al denunciante</td>
      </tr>
    </tbody>
  </table>
</div>

<h3>¿Qué formatos acepta la ley?</h3>

<p>El canal puede ser <strong>escrito, verbal o mixto</strong>. Las plataformas digitales son la opción más recomendada por su trazabilidad, seguridad y automatización del proceso.</p>

<h2>¿Qué dice Colombia sobre el canal de denuncias?</h2>

<p>Colombia tiene su propio marco legal, diferente pero igualmente riguroso, especialmente actualizado en 2024 y 2025:</p>

<h3>Marco legal colombiano (actualizado 2025)</h3>

<ul>
  <li><strong>Ley 1010 de 2006:</strong> Previene, corrige y sanciona el acoso laboral. Exige que toda empresa tenga mecanismos internos para tramitar quejas.</li>
  <li><strong>Resolución 3461 de 2025:</strong> Actualiza los lineamientos del Comité de Convivencia Laboral (CCL). El proceso de tramitación no debe superar 65 días calendario.</li>
  <li><strong>Ley 2365 de 2024:</strong> Nueva ley específica para prevenir y atender el acoso sexual en el ámbito laboral.</li>
  <li><strong>Circular 0076 de 2025:</strong> Plazo hasta el 30 de julio de 2025 para implementar protocolo contra acoso sexual.</li>
</ul>

<h3>¿Qué es el Comité de Convivencia Laboral?</h3>

<p>El Comité de Convivencia Laboral (CCL) es el órgano interno obligatorio en Colombia equivalente al responsable del sistema en España. Sus funciones son:</p>

<ol>
  <li>Recibir y tramitar quejas de acoso laboral</li>
  <li>Escuchar a las partes y buscar acuerdos de convivencia</li>
  <li>Formular planes de mejora</li>
  <li>Hacer seguimiento a los casos</li>
</ol>

<blockquote>
  <strong>Importante:</strong> El CCL no es competente para casos de acoso sexual (rige la Ley 2365 de 2024) ni puede determinar si una conducta constituye acoso laboral (esto lo hace el Ministerio del Trabajo).
</blockquote>

<h2>¿Cómo implementar un canal de denuncias efectivo en tu PYME?</h2>

<p>Tanto en España como en Colombia, los pasos clave son similares:</p>

<ol>
  <li><strong>Elige la plataforma:</strong> Digital es la opción más segura y trazable.</li>
  <li><strong>Designa un responsable:</strong> Independiente, con acceso restringido.</li>
  <li><strong>Redacta la política:</strong> Visible para todos los empleados.</li>
  <li><strong>Configura los flujos:</strong> Tiempos de respuesta, notificaciones, registro.</li>
  <li><strong>Forma a tu equipo:</strong> Los empleados deben saber que existe y cómo usarlo.</li>
  <li><strong>Audita regularmente:</strong> Comprueba que cumple con los plazos legales.</li>
</ol>

<h2>¿Puede una herramienta de RRHH gestionar el canal de denuncias?</h2>

<p>Sí, y es la opción más eficiente para las PYMES. Un software como <strong>Valírica</strong> integra el canal de denuncias directamente en la plataforma de RRHH, lo que permite:</p>

<ul>
  <li>Recibir denuncias de forma anónima desde cualquier dispositivo</li>
  <li>Gestionar plazos automáticamente (acuse de recibo en 7 días, resolución en 3 meses)</li>
  <li>Registro trazable y seguro de cada comunicación</li>
  <li>Separación de accesos: solo el responsable designado puede ver las denuncias</li>
  <li>Cumplimiento simultáneo con Ley 2/2023 (España) y Ley 1010 (Colombia)</li>
</ul>

<div class="blog-faq">
  <h2>Preguntas frecuentes sobre el canal de denuncias</h2>

  <div class="faq-item">
    <h3>¿Es obligatorio el canal de denuncias para empresas de menos de 50 trabajadores en España?</h3>
    <p>No. La obligación de la Ley 2/2023 aplica a empresas con 50 o más empleados. Sin embargo, se recomienda para cualquier empresa que quiera protegerse legalmente y fomentar una cultura de transparencia.</p>
  </div>

  <div class="faq-item">
    <h3>¿Puede el canal de denuncias ser anónimo?</h3>
    <p>Sí. La Ley 2/2023 permite —aunque no obliga— a que las empresas acepten denuncias anónimas. En Colombia, el CCL también puede tramitar quejas sin identificar al denunciante si así se establece en el procedimiento interno.</p>
  </div>

  <div class="faq-item">
    <h3>¿Qué pasa si un empleado sufre represalias por denunciar?</h3>
    <p>La Ley 2/2023 prohíbe explícitamente el despido, la degradación o cualquier medida que perjudique al denunciante. En Colombia, la Ley 1010 también protege a los informantes. Incumplir estas protecciones puede acarrear sanciones graves.</p>
  </div>

  <div class="faq-item">
    <h3>¿Cuánto tiempo tiene la empresa para responder una denuncia?</h3>
    <p>En España: acuse de recibo en 7 días y respuesta completa en máximo 3 meses. En Colombia: el proceso del CCL no debe superar 65 días calendario (Resolución 3461/2025).</p>
  </div>
</div>

<p style="font-size:13px;color:rgba(255,255,255,0.38);border-top:1px solid rgba(255,255,255,0.06);margin-top:40px;padding-top:16px;"><em>Última actualización: Marzo 2025 | Categoría: Compliance y Cultura Organizacional</em></p>
HTML,
];

// ── INSERT con ON DUPLICATE KEY UPDATE ───────────────────────────────────────
$stmt = $conn->prepare("
  INSERT INTO blog_posts
    (slug, title, excerpt, content, cover_gradient, cover_image,
     author_name, author_title, author_avatar, category, tags,
     status, featured, seo_title, seo_description, seo_keywords,
     reading_time, published_at)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  ON DUPLICATE KEY UPDATE
    title           = VALUES(title),
    excerpt         = VALUES(excerpt),
    content         = VALUES(content),
    cover_gradient  = VALUES(cover_gradient),
    cover_image     = VALUES(cover_image),
    author_name     = VALUES(author_name),
    author_title    = VALUES(author_title),
    category        = VALUES(category),
    tags            = VALUES(tags),
    status          = VALUES(status),
    seo_title       = VALUES(seo_title),
    seo_description = VALUES(seo_description),
    seo_keywords    = VALUES(seo_keywords),
    reading_time    = VALUES(reading_time)
");

if (!$stmt) {
    echo '<div class="err">❌ Error preparando query: ' . htmlspecialchars($conn->error) . '</div>';
    exit;
}

$p = $post;
$stmt->bind_param(
    'ssssssssssssisssis',
    $p['slug'], $p['title'], $p['excerpt'], $p['content'],
    $p['cover_gradient'], $p['cover_image'],
    $p['author_name'], $p['author_title'], $p['author_avatar'],
    $p['category'], $p['tags'],
    $p['status'], $p['featured'],
    $p['seo_title'], $p['seo_description'], $p['seo_keywords'],
    $p['reading_time'], $p['published_at']
);

if ($stmt->execute()) {
    if ($stmt->affected_rows === 1) {
        echo '<div class="ok">✅ <strong>Insertado correctamente:</strong> ' . htmlspecialchars($p['title']) . '</div>';
    } elseif ($stmt->affected_rows === 2) {
        echo '<div class="ok">🔄 <strong>Actualizado:</strong> ' . htmlspecialchars($p['title']) . '</div>';
    } else {
        echo '<div class="skip">⏭️ Sin cambios (contenido idéntico ya existente): ' . htmlspecialchars($p['title']) . '</div>';
    }
    echo '<div class="ok">🔗 URL: <a href="/blog/' . htmlspecialchars($p['slug']) . '">/blog/' . htmlspecialchars($p['slug']) . '</a></div>';
} else {
    echo '<div class="err">❌ Error: ' . htmlspecialchars($stmt->error) . '</div>';
}

$stmt->close();
$conn->close();
?>

<br>
<div class="warn">⚠️ ELIMINA ESTE ARCHIVO DEL SERVIDOR INMEDIATAMENTE tras confirmar que funciona.</div>
<a class="btn" href="/blog">→ Ver el blog</a>
</body>
</html>
