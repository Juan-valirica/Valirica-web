<?php
/**
 * Valírica — Blog Post (artículo individual)
 * SEO máximo: JSON-LD Article + BreadcrumbList + FAQPage, OG, Twitter, canonical
 * AEO (Answer Engine Optimization): FAQ schema, estructura clara, respuestas directas
 */
require_once 'config.php';

// ─── Resuelve slug ─────────────────────────────────────────────────────────
$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: /blog', true, 301); exit; }

// ─── Consulta post ─────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT id, slug, title, excerpt, content, cover_gradient, cover_image,
           author_name, author_title, author_avatar, category, tags,
           seo_title, seo_description, seo_keywords, reading_time,
           view_count, published_at, updated_at
    FROM blog_posts
    WHERE slug = ? AND status = 'published'
    LIMIT 1
");
$stmt->bind_param('s', $slug);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$post) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// ─── Incrementar vistas (fire-and-forget) ──────────────────────────────────
$conn->query("UPDATE blog_posts SET view_count = view_count + 1 WHERE id = " . (int)$post['id']);

// ─── Posts relacionados ────────────────────────────────────────────────────
$rel_stmt = $conn->prepare("
    SELECT slug, title, cover_gradient, reading_time, published_at, category
    FROM blog_posts
    WHERE status = 'published' AND id != ? AND category = ?
    ORDER BY published_at DESC LIMIT 3
");
$rel_stmt->bind_param('is', $post['id'], $post['category']);
$rel_stmt->execute();
$related = $rel_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rel_stmt->close();

// Si hay pocos relacionados de la misma cat, completa con otros
if (count($related) < 3) {
    $needed = 3 - count($related);
    $excl_ids = array_column($related, 'id');
    $excl_ids[] = $post['id'];
    $placeholders = implode(',', array_fill(0, count($excl_ids), '?'));
    $types_str = str_repeat('i', count($excl_ids));
    $more_stmt = $conn->prepare("
        SELECT slug, title, cover_gradient, reading_time, published_at, category
        FROM blog_posts
        WHERE status = 'published' AND id NOT IN ($placeholders)
        ORDER BY published_at DESC LIMIT ?
    ");
    $more_stmt->bind_param($types_str . 'i', ...[...$excl_ids, $needed]);
    $more_stmt->execute();
    $more = $more_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $related = array_merge($related, $more);
    $more_stmt->close();
}

// ─── Helpers ───────────────────────────────────────────────────────────────
function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function format_date_es($dt){
    $months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $ts = strtotime($dt);
    return $ts ? date('j', $ts) . ' de ' . $months[(int)date('n', $ts) - 1] . ' de ' . date('Y', $ts) : '';
}
function iso_date($dt){ return date('c', strtotime($dt)); }

// ─── Meta & SEO vars ───────────────────────────────────────────────────────
$base_url    = 'https://www.valirica.com';
$post_url    = $base_url . '/blog/' . $post['slug'];
$seo_title   = $post['seo_title']   ?: ($post['title'] . ' | Blog Valírica');
$seo_desc    = $post['seo_description'] ?: substr(strip_tags($post['excerpt']), 0, 160);
$cover_image = $post['cover_image']  ?: 'https://app.valirica.com/uploads/logo-192.png';
$tags_arr    = array_filter(array_map('trim', explode(',', $post['tags'] ?? '')));

// ─── Extraer FAQs del contenido para JSON-LD FAQPage ──────────────────────
// Captura H3 y H4 que terminen en '?' como preguntas AEO
$faq_items = [];
preg_match_all('/<(h3|h4)[^>]*>(.*?)<\/\1>\s*<p[^>]*>(.*?)<\/p>/si', $post['content'], $faq_matches, PREG_SET_ORDER);
foreach ($faq_matches as $m) {
    $q = trim(strip_tags($m[2]));
    $a = trim(strip_tags($m[3]));
    if ($q && $a && str_ends_with($q, '?')) {
        $faq_items[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
    }
}
// Si no se extrajeron FAQs desde el contenido, usar el excerpt como respuesta fallback
if (empty($faq_items) && !empty($post['excerpt'])) {
    $implied_q = '\u00bfQu\u00e9 es ' . strip_tags($post['category']) . ' y c\u00f3mo puede ayudar a tu empresa?';
    $faq_items[] = [
        '@type'          => 'Question',
        'name'           => $implied_q,
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($post['excerpt']))]
    ];
}

// ─── Extraer TOC (H2) del contenido ───────────────────────────────────────
preg_match_all('/<h2[^>]*>(.*?)<\/h2>/si', $post['content'], $toc_matches);
$toc_items = [];
foreach ($toc_matches[1] as $idx => $heading) {
    $clean = trim(strip_tags($heading));
    $anchor = 'sec-' . ($idx + 1);
    $toc_items[] = ['text' => $clean, 'anchor' => $anchor];
}
// Inyectar anchors en el contenido para el TOC
$content_html = $post['content'];
$toc_idx = 0;
$content_html = preg_replace_callback('/<h2([^>]*)>(.*?)<\/h2>/si', function($m) use (&$toc_idx) {
    $anchor = 'sec-' . (++$toc_idx);
    return '<h2' . $m[1] . ' id="' . $anchor . '">' . $m[2] . '</h2>';
}, $content_html);

// ─── JSON-LD: Article ──────────────────────────────────────────────────────
$jsonld_article = [
    '@context'         => 'https://schema.org',
    '@type'            => 'Article',
    'headline'         => $post['title'],
    'description'      => $seo_desc,
    'image'            => $cover_image,
    'url'              => $post_url,
    'datePublished'    => iso_date($post['published_at']),
    'dateModified'     => iso_date($post['updated_at']),
    'inLanguage'       => 'es',
    'timeRequired'     => 'PT' . (int)$post['reading_time'] . 'M',
    'keywords'         => $post['seo_keywords'] ?: implode(', ', $tags_arr),
    'articleSection'   => $post['category'],
    'author'           => [
        '@type' => 'Organization',
        'name'  => $post['author_name'],
        'url'   => $base_url,
    ],
    'publisher'        => ['@id' => $base_url . '/#organization'],
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $post_url],
    'wordCount'        => str_word_count(strip_tags($post['content'])),
    'about'            => [['@type' => 'Thing', 'name' => $post['category']]],
];
$jsonld_breadcrumb = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => $base_url],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog',   'item' => $base_url . '/blog'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $post['category'], 'item' => $base_url . '/blog?cat=' . urlencode($post['category'])],
        ['@type' => 'ListItem', 'position' => 4, 'name' => $post['title'],    'item' => $post_url],
    ],
];
$jsonld_org = [
    '@context'    => 'https://schema.org',
    '@type'       => ['Organization', 'LocalBusiness'],
    '@id'         => $base_url . '/#organization',
    'name'        => 'Valírica',
    'url'         => $base_url,
    'logo'        => ['@type' => 'ImageObject', 'url' => $base_url . '/assets/icons/logo-light.svg', 'width' => 134, 'height' => 34],
    'description' => 'Plataforma SaaS de inteligencia cultural organizacional para PYMES en España y Colombia. Fichaje digital (RDL 8/2019), canal de denuncias (Ley 2/2023), gestión del desempeño y motor de inteligencia cultural en tiempo real.',
    'sameAs'      => [
        'https://www.linkedin.com/company/valirica',
        'https://www.instagram.com/valirica.rrhh',
        'https://www.youtube.com/channel/UC6DmXMbAuyX-5QxYlv7WnLA',
    ],
    'contactPoint'=> ['@type' => 'ContactPoint', 'telephone' => '+34-600-876-538', 'email' => 'vale@valirica.com', 'contactType' => 'customer service', 'availableLanguage' => ['Spanish']],
    'areaServed'  => [['@type' => 'Country', 'name' => 'España'], ['@type' => 'Country', 'name' => 'Colombia']],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <!-- ── Google Analytics ── -->
  <!-- Google Tag Manager -->
  <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
  new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
  j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
  'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
  })(window,document,'script','dataLayer','GTM-TF4JJCXB');</script>
  <!-- End Google Tag Manager -->

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

  <!-- ── SEO primario ── -->
  <title><?= h($seo_title) ?></title>
  <meta name="description" content="<?= h($seo_desc) ?>">
  <?php if ($post['seo_keywords']): ?><meta name="keywords" content="<?= h($post['seo_keywords']) ?>"><?php endif; ?>
  <meta name="author"  content="<?= h($post['author_name']) ?>">
  <meta name="robots"  content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
  <link rel="canonical" href="<?= h($post_url) ?>">
  <link rel="alternate" hreflang="es-ES" href="<?= h($post_url) ?>">
  <link rel="alternate" hreflang="es-CO" href="<?= h($post_url) ?>">
  <link rel="alternate" hreflang="es" href="<?= h($post_url) ?>">

  <!-- ── Open Graph ── -->
  <meta property="og:type"              content="article">
  <meta property="og:site_name"         content="Valírica">
  <meta property="og:title"             content="<?= h($post['title']) ?>">
  <meta property="og:description"       content="<?= h($seo_desc) ?>">
  <meta property="og:url"               content="<?= h($post_url) ?>">
  <meta property="og:image"             content="<?= h($cover_image) ?>">
  <meta property="og:image:alt"         content="<?= h($post['title']) ?>">
  <meta property="og:locale"            content="es_ES">
  <meta property="og:locale:alternate" content="es_CO">
  <meta property="article:published_time" content="<?= iso_date($post['published_at']) ?>">
  <meta property="article:modified_time"  content="<?= iso_date($post['updated_at']) ?>">
  <meta property="article:author"         content="<?= h($post['author_name']) ?>">
  <meta property="article:section"        content="<?= h($post['category']) ?>">
  <?php foreach ($tags_arr as $tag): ?>
  <meta property="article:tag" content="<?= h($tag) ?>">
  <?php endforeach; ?>

  <!-- ── Twitter Card ── -->
  <meta name="twitter:card"        content="summary_large_image">
  <meta name="twitter:title"       content="<?= h($post['title']) ?>">
  <meta name="twitter:description" content="<?= h($seo_desc) ?>">
  <meta name="twitter:image"       content="<?= h($cover_image) ?>">

  <!-- ── Sitemap discovery ── -->
  <link rel="sitemap" type="application/xml" title="Sitemap" href="/sitemap.xml">

  <!-- ── Recursos ── -->
  <link rel="icon" type="image/svg+xml" href="/assets/icons/favicon-light.svg">
  <meta name="theme-color" content="#012133">
  <link rel="preconnect" href="https://use.typekit.net" crossorigin>
  <link rel="preload" href="https://use.typekit.net/qrv8fyz.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="https://use.typekit.net/qrv8fyz.css"></noscript>
  <link rel="preconnect" href="https://unpkg.com">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
  <script src="https://unpkg.com/lucide@latest" defer></script>

  <!-- ── JSON-LD Structured Data ── -->
  <script type="application/ld+json"><?= json_encode($jsonld_article,    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script type="application/ld+json"><?= json_encode($jsonld_breadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script type="application/ld+json"><?= json_encode($jsonld_org,        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <?php if (!empty($faq_items)): ?>
  <script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faq_items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <?php endif; ?>

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --c-primary:   #012133;
      --c-secondary: #184656;
      --c-teal:      #007a96;
      --c-accent:    #ff9700;
      --c-soft:      #FFF5F0;
      --font: "gelica", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      --transition: 0.22s ease;
    }
    html { scroll-behavior: smooth; }
    body {
      font-family: var(--font);
      background: linear-gradient(160deg, #010f1a 0%, #011929 35%, var(--c-primary) 70%, #0d3a4f 100%);
      min-height: 100vh; color: #fff;
      -webkit-font-smoothing: antialiased;
    }

    /* ── READING PROGRESS ── */
    #reading-progress {
      position: fixed; top: 0; left: 0; z-index: 200;
      height: 3px; width: 0%;
      background: linear-gradient(90deg, var(--c-teal), var(--c-accent));
      transition: width 0.1s linear;
    }

    /* ── CONTAINER ── */
    .container{width:90%;max-width:1240px;margin:0 auto}

    /* ── NAV (idéntico a index.html / styles.css) ── */
    .navbar{position:fixed;top:0;left:0;right:0;z-index:9900;padding:16px 0;pointer-events:none}
    .nav-inner{pointer-events:all;display:flex;align-items:center;gap:8px;padding:15px 12px 15px 14px;border-radius:18px;background:rgba(255,255,255,0.82);backdrop-filter:blur(24px) saturate(200%);-webkit-backdrop-filter:blur(24px) saturate(200%);border:1px solid rgba(255,255,255,0.72);box-shadow:0 8px 32px rgba(1,33,51,0.07),0 2px 8px rgba(1,33,51,0.04),inset 0 1px 0 rgba(255,255,255,0.85);transition:background 350ms ease,box-shadow 350ms ease,border-color 350ms ease}
    .navbar.is-scrolled .nav-inner{background:rgba(255,255,255,0.94);border-color:rgba(255,255,255,0.90);box-shadow:0 12px 40px rgba(1,33,51,0.11),0 2px 8px rgba(1,33,51,0.06),inset 0 1px 0 rgba(255,255,255,0.98)}
    .nav-logo{display:inline-flex;align-items:center;text-decoration:none;flex-shrink:0;margin-right:4px}
    .nav-logo-img{height:34px;width:auto;display:block}
    .nav-links{display:flex;align-items:center;gap:4px;flex:1;justify-content:center}
    .nav-link{display:inline-block;padding:7px 12px;border-radius:9px;font-size:13.5px;font-weight:600;color:rgba(1,33,51,0.68);text-decoration:none;transition:color 180ms ease,background 180ms ease;white-space:nowrap;letter-spacing:-0.01em}
    .nav-link:hover{color:#012133;background:rgba(1,33,51,0.055)}
    .nav-link.is-active{color:#007a96;background:rgba(0,122,150,0.07)}
    .nav-actions{display:flex;align-items:center;gap:8px;flex-shrink:0;margin-left:4px}
    .nav-login{padding:8px 14px;border-radius:9px;font-size:13.5px;font-weight:600;color:rgba(1,33,51,0.65);text-decoration:none;transition:color 180ms ease,background 180ms ease;white-space:nowrap}
    .nav-login:hover{color:#012133;background:rgba(1,33,51,0.055)}
    .nav-cta{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:10px;background:#ff9700;color:#ffffff;font-size:13.5px;font-weight:700;text-decoration:none;box-shadow:0 6px 20px rgba(255,151,0,0.30);transition:transform 220ms ease,box-shadow 220ms ease;white-space:nowrap;letter-spacing:-0.01em}
    .nav-cta:hover{transform:translateY(-1px);box-shadow:0 10px 28px rgba(255,151,0,0.42)}
    .nav-cta:active{transform:scale(0.97);transition-duration:60ms}
    .nav-cta-arrow{width:14px;height:14px;flex-shrink:0;transition:transform 220ms ease}
    .nav-cta:hover .nav-cta-arrow{transform:translateX(2px)}
    .nav-burger{display:none;flex-direction:column;justify-content:center;align-items:center;gap:5px;width:38px;height:38px;border:none;background:rgba(1,33,51,0.05);border-radius:9px;cursor:pointer;padding:0;flex-shrink:0;transition:background 200ms ease;margin-left:4px}
    .nav-burger:hover{background:rgba(1,33,51,0.09)}
    .burger-line{display:block;height:1.8px;background:#012133;border-radius:2px;transition:transform 300ms cubic-bezier(.4,0,.2,1),opacity 300ms ease,width 300ms ease}
    .burger-line:nth-child(1){width:18px}
    .burger-line:nth-child(2){width:13px}
    .burger-line:nth-child(3){width:18px}
    .nav-burger[aria-expanded="true"] .burger-line:nth-child(1){width:18px;transform:translateY(6.8px) rotate(45deg)}
    .nav-burger[aria-expanded="true"] .burger-line:nth-child(2){opacity:0;transform:scaleX(0)}
    .nav-burger[aria-expanded="true"] .burger-line:nth-child(3){width:18px;transform:translateY(-6.8px) rotate(-45deg)}
    .nav-mobile-menu{max-height:0;overflow:hidden;transition:max-height 380ms cubic-bezier(.4,0,.2,1);pointer-events:none}
    .nav-mobile-menu.is-open{max-height:500px;pointer-events:all}
    .nav-mobile-menu .container{background:rgba(255,255,255,0.96);backdrop-filter:blur(24px) saturate(180%);-webkit-backdrop-filter:blur(24px) saturate(180%);border:1px solid rgba(255,255,255,0.78);border-top:none;border-radius:0 0 16px 16px;padding:10px 20px 20px;box-shadow:0 16px 40px rgba(1,33,51,0.10);margin-top:2px}
    .nav-mobile-links{display:flex;flex-direction:column;gap:2px;padding-bottom:14px;border-bottom:1px solid rgba(1,33,51,0.07);margin-bottom:14px}
    .nav-mobile-link{display:block;padding:10px 12px;border-radius:9px;font-size:15px;font-weight:600;color:rgba(1,33,51,0.72);text-decoration:none;transition:color 160ms ease,background 160ms ease}
    .nav-mobile-link:hover,.nav-mobile-link:active{color:#012133;background:rgba(1,33,51,0.05)}
    .nav-mobile-actions{display:flex;gap:10px;align-items:center}
    .nav-mobile-login{padding:10px 16px;border-radius:9px;font-size:14px;font-weight:600;color:rgba(1,33,51,0.65);text-decoration:none;background:rgba(1,33,51,0.05);flex:1;text-align:center;transition:background 160ms ease,color 160ms ease}
    .nav-mobile-login:hover{background:rgba(1,33,51,0.09);color:#012133}
    .nav-mobile-cta{flex:1;justify-content:center;padding:10px 16px;font-size:14px}
    @media (max-width:1024px){.nav-link{font-size:13px;padding:7px 9px}.nav-login{font-size:13px;padding:8px 11px}.nav-cta{font-size:13px;padding:9px 13px}}
    @media (max-width:768px){.navbar{pointer-events:all;padding:0;background:rgba(255,255,255,0.65);backdrop-filter:blur(22px) saturate(180%);-webkit-backdrop-filter:blur(22px) saturate(180%);border-bottom:1px solid rgba(255,255,255,0.52);box-shadow:0 4px 24px rgba(1,33,51,0.07),inset 0 -1px 0 rgba(255,255,255,0.45);overflow:hidden}.navbar.is-scrolled{background:rgba(255,255,255,0.82);border-bottom-color:rgba(255,255,255,0.70);box-shadow:0 6px 32px rgba(1,33,51,0.10),inset 0 -1px 0 rgba(255,255,255,0.60)}.nav-inner{width:100%;background:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;border-radius:0;border:none;box-shadow:none;padding:11px 16px;border-bottom:1px solid rgba(1,33,51,0.06)}.navbar .container{width:100%;max-width:100%;padding:0}.nav-links{display:none}.nav-burger{display:none}.nav-mobile-menu{display:none!important}.nav-actions{display:flex;gap:7px;margin-left:auto;flex-shrink:0}.nav-login{padding:7px 13px;font-size:13px;border-radius:9px;background:rgba(1,33,51,0.06);color:rgba(1,33,51,0.72)}.nav-cta{padding:7px 13px;font-size:13px;gap:5px}.nav-cta-arrow{width:12px;height:12px}.nav-logo-img{height:34px}.nav-strip{display:flex;overflow-x:auto;overflow-y:hidden;scrollbar-width:none;-ms-overflow-style:none;gap:6px;padding:8px 16px 10px;max-height:52px;opacity:1;transition:max-height 300ms cubic-bezier(.4,0,.2,1),opacity 220ms ease,padding-top 300ms ease,padding-bottom 300ms ease}.nav-strip::-webkit-scrollbar{display:none}.nav-strip.is-hidden{max-height:0;opacity:0;padding-top:0;padding-bottom:0;pointer-events:none}.nav-strip-link{flex-shrink:0;display:inline-block;padding:7px 14px;border-radius:20px;font-size:13px;font-weight:600;color:rgba(1,33,51,0.65);text-decoration:none;white-space:nowrap;background:rgba(1,33,51,0.055);transition:color 160ms ease,background 160ms ease;letter-spacing:-0.01em}.nav-strip-link:active{color:#012133;background:rgba(1,33,51,0.10)}.nav-strip-link.is-active{color:#007a96;background:rgba(0,122,150,0.10)}}
    @media (min-width:769px){.nav-strip{display:none}}
    @media (max-width:480px){.nav-logo-img{height:34px}.nav-cta{padding:7px 11px;font-size:12.5px}.nav-cta-arrow{display:none}}

    /* ── COVER ── */
    .post-cover {
      width: 100%; min-height: 520px;
      display: flex; align-items: flex-end;
      padding: 48px 24px;
      position: relative; overflow: hidden;
    }
    .post-cover::after {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(to top, rgba(1,15,26,0.92) 0%, rgba(1,33,51,0.4) 50%, transparent 85%);
    }
    .post-cover img.cover-img {
      position: absolute; inset: 0; width: 100%; height: 100%;
      object-fit: cover; opacity: 0.35;
    }
    .post-cover-inner {
      position: relative; z-index: 1;
      max-width: 820px; margin: 0 auto; width: 100%;
    }

    /* ── BREADCRUMB ── */
    .vl-breadcrumb {
      display: flex; align-items: center; gap: 6px;
      font-size: 12px; color: rgba(255,255,255,0.38);
      margin-bottom: 20px; flex-wrap: wrap;
    }
    .vl-breadcrumb a { color: rgba(255,255,255,0.45); text-decoration: none; }
    .vl-breadcrumb a:hover { color: rgba(255,255,255,0.7); }
    .vl-breadcrumb i { font-size: 11px; }

    /* ── POST HEADER ── */
    .post-cat-pill {
      display: inline-flex; align-items: center; gap: 5px;
      background: rgba(255,151,0,0.15); border: 1px solid rgba(255,151,0,0.30);
      color: #f5a23d; font-size: 11px; font-weight: 700; letter-spacing: 1.5px;
      text-transform: uppercase; padding: 5px 13px; border-radius: 100px;
      text-decoration: none; margin-bottom: 16px; display: inline-flex;
    }
    .post-cat-pill:hover { background: rgba(255,151,0,0.22); }
    h1.post-title {
      font-size: clamp(28px, 4.5vw, 46px); font-weight: 900; color: #fff;
      line-height: 1.1; letter-spacing: -1px; margin-bottom: 18px;
    }
    .post-meta-bar {
      display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
      font-size: 13px; color: rgba(255,255,255,0.50);
    }
    .post-meta-bar span { display: flex; align-items: center; gap: 5px; }
    .post-meta-bar i { font-size: 15px; }
    .post-meta-bar .sep { color: rgba(255,255,255,0.18); }

    /* ── LAYOUT ── */
    .post-layout {
      max-width: 1160px; margin: 0 auto;
      display: grid; grid-template-columns: 1fr 280px;
      gap: 56px; padding: 48px 24px 80px; align-items: start;
    }

    /* ── ARTICLE CONTENT ── */
    .post-article { min-width: 0; }
    .post-excerpt {
      font-size: 18px; color: rgba(255,255,255,0.65); line-height: 1.7;
      border-left: 3px solid var(--c-accent); padding-left: 20px;
      margin-bottom: 40px; font-style: italic;
    }
    .post-content { font-size: 16px; line-height: 1.8; color: rgba(255,255,255,0.82); }
    .post-content h2 {
      font-size: 24px; font-weight: 800; color: #fff;
      margin: 48px 0 18px; letter-spacing: -0.3px; line-height: 1.2;
      scroll-margin-top: 80px;
    }
    .post-content h3 {
      font-size: 19px; font-weight: 700; color: rgba(255,255,255,0.92);
      margin: 32px 0 12px; letter-spacing: -0.2px;
    }
    .post-content p { margin-bottom: 20px; }
    .post-content ul, .post-content ol {
      margin: 0 0 22px 24px; display: flex; flex-direction: column; gap: 8px;
    }
    .post-content li { line-height: 1.7; color: rgba(255,255,255,0.78); }
    .post-content ul li::marker { color: var(--c-accent); }
    .post-content ol li::marker { color: var(--c-teal); font-weight: 700; }
    .post-content blockquote {
      background: rgba(255,151,0,0.07); border-left: 3px solid var(--c-accent);
      border-radius: 0 12px 12px 0; padding: 18px 22px;
      margin: 28px 0; color: rgba(255,255,255,0.80); font-style: italic; font-size: 17px;
    }
    .post-content blockquote strong { font-style: normal; color: #fff; }
    .post-content blockquote em { display: block; font-size: 13px; color: rgba(255,255,255,0.45); margin-top: 8px; font-style: normal; }
    .post-content strong { color: #fff; font-weight: 700; }
    .post-content a { color: #4dd6f0; text-decoration: underline; text-decoration-color: rgba(77,214,240,0.3); }
    .post-content a:hover { color: #fff; }

    /* FAQ styles inside content */
    .post-content .blog-faq {
      background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.09);
      border-radius: 20px; padding: 32px; margin: 40px 0;
    }
    .post-content .blog-faq h2 {
      font-size: 20px; margin-top: 0; margin-bottom: 24px;
      color: #fff; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 14px;
    }
    .post-content .faq-item {
      padding: 18px 0; border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .post-content .faq-item:last-child { border-bottom: none; padding-bottom: 0; }
    .post-content .faq-item h3 {
      font-size: 16px; font-weight: 700; color: rgba(255,255,255,0.92);
      margin-bottom: 8px; margin-top: 0;
    }
    .post-content .faq-item p { margin-bottom: 0; font-size: 14px; color: rgba(255,255,255,0.60); }

    /* Divider between sections */
    .post-content h2::before {
      content: ''; display: block; height: 1px;
      background: rgba(255,255,255,0.06); margin-bottom: 36px;
    }
    .post-content h2:first-of-type::before { display: none; }

    /* ── SHARE BAR ── */
    .post-share {
      display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
      background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.09);
      border-radius: 16px; padding: 20px 24px; margin-top: 48px;
    }
    .post-share-label { font-size: 13px; font-weight: 700; color: rgba(255,255,255,0.5); flex-shrink: 0; }
    .share-btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 16px; border-radius: 10px;
      font-size: 12px; font-weight: 700; font-family: var(--font);
      text-decoration: none; cursor: pointer; border: none;
      transition: opacity var(--transition), transform var(--transition);
    }
    .share-btn:hover { opacity: 0.85; transform: scale(0.97); }
    .share-btn i { font-size: 15px; }
    .share-btn-linkedin { background: #0077b5; color: #fff; }
    .share-btn-twitter  { background: #1da1f2; color: #fff; }
    .share-btn-copy     { background: rgba(255,255,255,0.10); color: rgba(255,255,255,0.75); border: 1px solid rgba(255,255,255,0.15); }
    .share-btn-copy:hover { background: rgba(255,255,255,0.16); }

    /* ── TAGS ── */
    .post-tags {
      display: flex; gap: 8px; flex-wrap: wrap; margin-top: 32px;
    }
    .post-tag {
      padding: 6px 14px; border-radius: 100px;
      background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.10);
      font-size: 12px; color: rgba(255,255,255,0.50); font-weight: 600;
      text-decoration: none;
    }
    .post-tag:hover { background: rgba(255,255,255,0.10); color: rgba(255,255,255,0.75); }
    .post-tag i { font-size: 12px; margin-right: 3px; }

    /* ── AUTHOR BOX ── */
    .post-author-box {
      display: flex; align-items: flex-start; gap: 18px;
      background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.09);
      border-radius: 20px; padding: 24px; margin-top: 40px;
    }
    .post-author-avatar {
      width: 56px; height: 56px; border-radius: 50%; flex-shrink: 0;
      background: linear-gradient(135deg, var(--c-teal), var(--c-accent));
      display: flex; align-items: center; justify-content: center;
      font-size: 24px; color: #fff;
    }
    .post-author-info h3 { font-size: 15px; font-weight: 800; color: #fff; margin-bottom: 2px; }
    .post-author-info p  { font-size: 13px; color: rgba(255,255,255,0.45); margin: 0; }

    /* ── SIDEBAR ── */
    .post-sidebar { position: sticky; top: 80px; }
    .sidebar-card {
      background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.09);
      border-radius: 18px; padding: 22px; margin-bottom: 20px;
    }
    .sidebar-card h3 {
      font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
      color: rgba(255,255,255,0.35); margin-bottom: 16px;
      display: flex; align-items: center; gap: 6px;
    }
    .sidebar-card h3 i { font-size: 14px; }

    /* TOC */
    .toc-list { list-style: none; display: flex; flex-direction: column; gap: 2px; }
    .toc-list li a {
      display: flex; align-items: flex-start; gap: 8px; padding: 7px 10px;
      border-radius: 10px; font-size: 13px; color: rgba(255,255,255,0.50);
      text-decoration: none; line-height: 1.4;
      transition: background var(--transition), color var(--transition);
    }
    .toc-list li a:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.82); }
    .toc-list li a.active { background: rgba(255,151,0,0.10); color: #f5a23d; }
    .toc-list li a::before {
      content: ''; flex-shrink: 0; width: 5px; height: 5px;
      border-radius: 50%; background: rgba(255,255,255,0.25);
      margin-top: 6px;
    }
    .toc-list li a.active::before { background: var(--c-accent); }

    /* Progress circle */
    .sidebar-progress { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
    .progress-ring-wrap { position: relative; width: 44px; height: 44px; flex-shrink: 0; }
    .progress-ring { transform: rotate(-90deg); }
    .progress-ring-bg { fill: none; stroke: rgba(255,255,255,0.08); stroke-width: 3; }
    .progress-ring-fill { fill: none; stroke: var(--c-accent); stroke-width: 3; stroke-linecap: round; transition: stroke-dashoffset 0.1s linear; }
    .progress-pct {
      position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
      font-size: 11px; font-weight: 800; color: rgba(255,255,255,0.7);
    }
    .sidebar-progress-text { font-size: 13px; color: rgba(255,255,255,0.45); }
    .sidebar-progress-text strong { color: #fff; font-size: 14px; display: block; }

    /* Sidebar CTA */
    .sidebar-cta-btn {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      padding: 12px 18px; border-radius: 12px; font-size: 13px; font-weight: 700;
      background: linear-gradient(135deg, var(--c-accent), #e07800); color: #fff;
      text-decoration: none; box-shadow: 0 4px 14px rgba(255,151,0,0.30); width: 100%;
      transition: opacity var(--transition), transform var(--transition);
    }
    .sidebar-cta-btn:hover { opacity: 0.88; transform: scale(0.98); }

    /* ── RELATED POSTS ── */
    .related-section { padding: 0 24px 80px; }
    .related-inner { max-width: 1160px; margin: 0 auto; }
    .related-section h2 {
      font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 28px;
      display: flex; align-items: center; gap: 10px;
    }
    .related-section h2 i { color: var(--c-accent); font-size: 24px; }
    .related-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    .related-card {
      background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.09);
      border-radius: 18px; overflow: hidden; text-decoration: none; color: inherit;
      transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition);
      display: flex; flex-direction: column;
    }
    .related-card:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(0,0,0,0.20); border-color: rgba(255,255,255,0.16); }
    .related-card-accent { height: 4px; width: 100%; flex-shrink: 0; display: block; }
    .related-card-body { padding: 18px 20px 20px; flex: 1; display: flex; flex-direction: column; }
    .related-card-top {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 12px;
    }
    .related-card-cat {
      font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
      color: #4dd6f0; display: flex; align-items: center; gap: 5px; margin: 0;
    }
    .related-card-cat i { font-size: 13px; }
    .related-card-icon {
      width: 28px; height: 28px; border-radius: 8px;
      background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.10);
      display: flex; align-items: center; justify-content: center;
      color: rgba(255,255,255,0.40); font-size: 14px; flex-shrink: 0;
    }
    .related-card-title {
      font-size: 15px; font-weight: 800; color: #fff; line-height: 1.35;
      display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
      flex: 1; padding-bottom: 14px;
    }
    .related-card-footer {
      display: flex; align-items: center; justify-content: space-between;
      font-size: 12px; color: rgba(255,255,255,0.38);
      border-top: 1px solid rgba(255,255,255,0.07); padding-top: 12px;
    }
    .related-card-meta { display: flex; align-items: center; gap: 8px; }
    .related-card-meta span { display: flex; align-items: center; gap: 4px; }
    .related-card-meta i { font-size: 13px; }
    .related-card-arrow {
      width: 26px; height: 26px; border-radius: 50%;
      background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
      display: flex; align-items: center; justify-content: center;
      color: rgba(255,255,255,0.55); font-size: 13px;
      transition: background var(--transition), color var(--transition), border-color var(--transition);
      flex-shrink: 0;
    }
    .related-card:hover .related-card-arrow { background: var(--c-accent); color: #fff; border-color: var(--c-accent); }

    /* ── FOOTER (idéntico a index.html / styles.css) ── */
    .footer{background:#012133;color:rgba(255,255,255,0.72)}
    .footer-top{padding:80px 0 60px;border-bottom:1px solid rgba(255,255,255,0.07)}
    .footer-grid{display:grid;grid-template-columns:1.6fr 1fr 1fr 1.3fr;gap:40px 60px;align-items:start}
    .footer-brand{display:flex;flex-direction:column;gap:18px}
    .footer-logo{display:inline-block;text-decoration:none}
    .footer-logo-img{height:100px;width:auto;display:block}
    .footer-tagline{font-size:14px;line-height:1.7;color:rgba(255,255,255,0.50);max-width:290px}
    .footer-social{display:flex;gap:10px}
    .footer-social-link{width:36px;height:36px;border-radius:10px;border:1px solid rgba(255,255,255,0.12);background:rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,0.55);text-decoration:none;transition:background 200ms ease,color 200ms ease,border-color 200ms ease}
    .footer-social-link:hover{background:rgba(255,255,255,0.10);color:#ffffff;border-color:rgba(255,255,255,0.22)}
    .footer-social-link svg{width:16px;height:16px;stroke-width:1.8}
    .footer-col{display:flex;flex-direction:column;gap:16px}
    .footer-col-title{font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:rgba(255,255,255,0.55);margin-bottom:4px}
    .footer-col-list{list-style:none;display:flex;flex-direction:column;gap:10px}
    .footer-col-list a{font-size:14px;color:rgba(255,255,255,0.60);text-decoration:none;transition:color 200ms ease;display:inline-block}
    .footer-col-list a:hover{color:#ffffff}
    .footer-contact-list{gap:12px}
    .footer-contact-item{display:inline-flex;align-items:center;gap:9px;font-size:14px;color:rgba(255,255,255,0.60);text-decoration:none;transition:color 200ms ease}
    .footer-contact-item:hover{color:#ffffff}
    .footer-locations{display:flex;align-items:center;gap:9px;font-size:14px;color:rgba(255,255,255,0.40)}
    .footer-contact-icon{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.09);flex-shrink:0}
    .footer-contact-icon svg{width:14px;height:14px;stroke-width:1.8}
    .footer-cta-btn{margin-top:8px;display:inline-flex;align-items:center;gap:8px;padding:11px 20px;border-radius:9px;background:#ff9700;color:#ffffff;font-size:13.5px;font-weight:700;text-decoration:none;box-shadow:0 8px 28px rgba(255,151,0,0.30);transition:transform 240ms ease,box-shadow 240ms ease;align-self:flex-start}
    .footer-cta-btn:hover{transform:translateY(-2px);box-shadow:0 14px 38px rgba(255,151,0,0.42)}
    .footer-cta-btn:active{transform:scale(0.97)}
    .footer-cta-icon{display:inline-flex}
    .footer-cta-icon svg{width:14px;height:14px;stroke-width:2.2}
    .footer-bottom{padding:20px 0}
    .footer-bottom-inner{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
    .footer-copyright{font-size:12.5px;color:rgba(255,255,255,0.50)}
    .footer-bottom-links{display:flex;gap:20px}
    .footer-bottom-links a{font-size:12.5px;color:rgba(255,255,255,0.52);text-decoration:none;transition:color 200ms ease}
    .footer-bottom-links a:hover{color:rgba(255,255,255,0.85)}
    .footer-made{font-size:12.5px;color:rgba(255,255,255,0.42)}
    @media (max-width:1024px){.footer-grid{grid-template-columns:1fr 1fr;gap:40px 48px}.footer-brand{grid-column:1 / -1}.footer-tagline{max-width:100%}}
    @media (max-width:768px){.footer-top{padding:60px 0 48px}.footer-grid{grid-template-columns:1fr 1fr;gap:32px 24px}.footer-col--contact{grid-column:1 / -1}.footer-cta-btn{align-self:stretch;justify-content:center}.footer-bottom-inner{flex-direction:column;align-items:flex-start;gap:10px}.footer-made{display:none}}
    @media (max-width:480px){.footer-grid{grid-template-columns:1fr;gap:28px}.footer-col--contact{grid-column:unset}.footer-bottom-links{gap:14px}}

    /* ── RESPONSIVE ── */
    @media (max-width: 1024px) {
      .post-layout { grid-template-columns: 1fr; }
      .post-sidebar { position: static; }
      .related-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
      .post-cover { min-height: 280px; padding: 32px 20px; }
      h1.post-title { font-size: 28px; }
      .related-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
      .post-layout { padding: 32px 16px 60px; }
    }
  </style>
</head>
<body>
  <!-- Google Tag Manager (noscript) -->
  <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TF4JJCXB"
  height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
  <!-- End Google Tag Manager (noscript) -->
<div id="reading-progress" role="progressbar" aria-label="Progreso de lectura" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>

<!-- ══ NAV ══════════════════════════════════════════════════════════════════ -->
<header class="navbar" id="mainNav" role="banner">
  <div class="container">
    <div class="nav-inner">

      <a href="/" class="nav-logo" aria-label="Valírica — inicio">
        <img src="/assets/icons/logo-light.svg"
             alt="Valírica HR Software"
             class="nav-logo-img"
             width="134"
             height="34">
      </a>

      <nav class="nav-links" aria-label="Navegación principal">
        <a href="https://www.valirica.com/#modulos" id="nav-link-plataforma" class="nav-link">Plataforma</a>
        <a href="https://www.valirica.com/#diagnostico-cultural" id="nav-link-diagnostico" class="nav-link">Diagnóstico</a>
        <a href="https://www.valirica.com/#beneficios" id="nav-link-beneficios" class="nav-link">Impacto</a>
        <a href="https://www.valirica.com/#diferenciador" id="nav-link-diferenciador" class="nav-link">Por qué Valírica</a>
        <a href="https://www.valirica.com/#seguridad" id="nav-link-seguridad" class="nav-link">Seguridad</a>
        <a href="/blog" id="nav-link-blog" class="nav-link is-active" aria-current="page">Blog</a>
      </nav>

      <div class="nav-actions">
        <a href="https://app.valirica.com"
           id="nav-login-btn"
           class="nav-login"
           target="_blank"
           rel="noopener noreferrer">
          Acceder
        </a>
        <a href="https://app.valirica.com/registro.php"
           id="nav-cta-free-trial"
           class="nav-cta"
           target="_blank"
           rel="noopener noreferrer">
          Prueba gratuita
          <svg class="nav-cta-arrow" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
      </div>

      <button class="nav-burger"
              id="navBurger"
              aria-label="Abrir menú"
              aria-expanded="false"
              aria-controls="navMobileMenu"
              type="button">
        <span class="burger-line"></span>
        <span class="burger-line"></span>
        <span class="burger-line"></span>
      </button>

    </div>
  </div>

  <nav class="nav-strip" id="navStrip" aria-label="Secciones">
    <a href="https://www.valirica.com/#modulos"               class="nav-strip-link">Plataforma</a>
    <a href="https://www.valirica.com/#diagnostico-cultural"  class="nav-strip-link">Diagnóstico</a>
    <a href="https://www.valirica.com/#beneficios"            class="nav-strip-link">Impacto</a>
    <a href="https://www.valirica.com/#diferenciador"         class="nav-strip-link">Por qué Valírica</a>
    <a href="https://www.valirica.com/#seguridad"             class="nav-strip-link">Seguridad</a>
  </nav>

  <div class="nav-mobile-menu" id="navMobileMenu" aria-hidden="true">
    <div class="container">
      <nav class="nav-mobile-links" aria-label="Menú móvil">
        <a href="https://www.valirica.com/#diagnostico-cultural" id="nav-mobile-link-diagnostico" class="nav-mobile-link">Diagnóstico cultural</a>
        <a href="https://www.valirica.com/#modulos" id="nav-mobile-link-plataforma" class="nav-mobile-link">Plataforma</a>
        <a href="https://www.valirica.com/#beneficios" id="nav-mobile-link-impacto" class="nav-mobile-link">Impacto real</a>
        <a href="https://www.valirica.com/#diferenciador" id="nav-mobile-link-diferenciador" class="nav-mobile-link">Por qué Valírica</a>
        <a href="https://www.valirica.com/#seguridad" id="nav-mobile-link-seguridad" class="nav-mobile-link">Seguridad y datos</a>
        <a href="/blog" id="nav-mobile-link-blog" class="nav-mobile-link is-active">Blog</a>
      </nav>
      <div class="nav-mobile-actions">
        <a href="https://app.valirica.com"
           id="nav-mobile-login-btn"
           class="nav-mobile-login"
           target="_blank"
           rel="noopener noreferrer">
          Iniciar sesión
        </a>
        <a href="https://app.valirica.com/registro.php"
           id="nav-mobile-cta-free-trial"
           class="nav-cta nav-mobile-cta"
           target="_blank"
           rel="noopener noreferrer">
          Prueba gratuita
          <svg class="nav-cta-arrow" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
      </div>
    </div>
  </div>

</header>

<!-- ══ COVER ════════════════════════════════════════════════════════════════ -->
<header class="post-cover" style="background: <?= h($post['cover_gradient']) ?>">
  <?php
    $post_ci  = $post['cover_image'] ?? '';
    $post_cat = strtolower($post['category'] ?? '');
    $post_cat_icon_map = ['burnout'=>'ph-fire','liderazgo'=>'ph-crown','cultura'=>'ph-building-office','equipo'=>'ph-users-three','desempe'=>'ph-trend-up','talento'=>'ph-star','clima'=>'ph-chart-bar','innovaci'=>'ph-lightbulb','rrhh'=>'ph-briefcase','recurso'=>'ph-briefcase'];
    if (str_starts_with($post_ci, 'icon:')) {
      $post_icon = h(substr($post_ci, 5));
    } else {
      $post_icon = 'ph-article';
      foreach ($post_cat_icon_map as $k => $v) { if (strpos($post_cat, $k) !== false) { $post_icon = $v; break; } }
    }
  ?>
  <i class="ph <?= $post_icon ?>" style="position:absolute;top:50%;right:5%;transform:translateY(-50%);font-size:180px;opacity:0.10;color:#fff;pointer-events:none;" aria-hidden="true"></i>
  <div class="post-cover-inner">
    <nav aria-label="Breadcrumb" class="vl-breadcrumb">
      <a href="/">Inicio</a>
      <i class="ph ph-caret-right"></i>
      <a href="/blog">Blog</a>
      <i class="ph ph-caret-right"></i>
      <a href="/blog?cat=<?= urlencode($post['category']) ?>"><?= h($post['category']) ?></a>
      <i class="ph ph-caret-right"></i>
      <span aria-current="page"><?= h(mb_strimwidth($post['title'], 0, 60, '…')) ?></span>
    </nav>
    <a href="/blog?cat=<?= urlencode($post['category']) ?>" class="post-cat-pill">
      <i class="ph ph-tag"></i> <?= h($post['category']) ?>
    </a>
    <h1 class="post-title"><?= h($post['title']) ?></h1>
    <div class="post-meta-bar">
      <span><i class="ph ph-user-circle"></i> <?= h($post['author_name']) ?></span>
      <span class="sep">·</span>
      <span><i class="ph ph-calendar"></i>
        <time datetime="<?= iso_date($post['published_at']) ?>"><?= format_date_es($post['published_at']) ?></time>
      </span>
      <span class="sep">·</span>
      <span><i class="ph ph-clock"></i> <?= (int)$post['reading_time'] ?> min de lectura</span>
      <?php if ($post['view_count'] > 10): ?>
      <span class="sep">·</span>
      <span><i class="ph ph-eye"></i> <?= number_format($post['view_count']) ?> lecturas</span>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- ══ LAYOUT ══════════════════════════════════════════════════════════════ -->
<div class="post-layout">

  <!-- ── ARTICLE ── -->
  <main id="main-content">
    <article itemscope itemtype="https://schema.org/Article">
      <meta itemprop="headline"       content="<?= h($post['title']) ?>">
      <meta itemprop="datePublished"  content="<?= iso_date($post['published_at']) ?>">
      <meta itemprop="dateModified"   content="<?= iso_date($post['updated_at']) ?>">
      <meta itemprop="author"         content="<?= h($post['author_name']) ?>">
      <meta itemprop="publisher"      content="Valírica">

      <!-- Excerpt -->
      <p class="post-excerpt"><?= h($post['excerpt']) ?></p>

      <!-- Content -->
      <div class="post-content" itemprop="articleBody">
        <?= $content_html ?>
      </div>

      <!-- Tags -->
      <?php if (!empty($tags_arr)): ?>
      <div class="post-tags" aria-label="Etiquetas del artículo">
        <?php foreach ($tags_arr as $tag): ?>
          <a href="/blog?q=<?= urlencode($tag) ?>" class="post-tag" rel="tag">
            <i class="ph ph-hash"></i><?= h($tag) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Author box -->
      <div class="post-author-box" itemprop="author" itemscope itemtype="https://schema.org/Organization">
        <div class="post-author-avatar">
          <?php if ($post['author_avatar']): ?>
            <img src="<?= h($post['author_avatar']) ?>" alt="<?= h($post['author_name']) ?>" width="56" height="56" style="border-radius:50%;width:100%;height:100%;object-fit:cover;">
          <?php else: ?>
            <i class="ph ph-users-three"></i>
          <?php endif; ?>
        </div>
        <div class="post-author-info">
          <h3 itemprop="name"><?= h($post['author_name']) ?></h3>
          <p><?= h($post['author_title']) ?></p>
        </div>
      </div>

      <!-- Share -->
      <div class="post-share" aria-label="Compartir artículo">
        <span class="post-share-label"><i class="ph ph-share-network"></i> Compartir:</span>
        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($post_url) ?>"
           class="share-btn share-btn-linkedin" target="_blank" rel="noopener noreferrer" aria-label="Compartir en LinkedIn">
          <i class="ph ph-linkedin-logo"></i> LinkedIn
        </a>
        <a href="https://twitter.com/intent/tweet?url=<?= urlencode($post_url) ?>&text=<?= urlencode($post['title']) ?>"
           class="share-btn share-btn-twitter" target="_blank" rel="noopener noreferrer" aria-label="Compartir en Twitter/X">
          <i class="ph ph-x-logo"></i> Twitter
        </a>
        <button class="share-btn share-btn-copy" onclick="copyUrl()" aria-label="Copiar enlace">
          <i class="ph ph-link"></i> <span id="copy-label">Copiar enlace</span>
        </button>
      </div>
    </article>
  </main>

  <!-- ── SIDEBAR ── -->
  <aside class="post-sidebar" aria-label="Información del artículo">

    <!-- Reading progress -->
    <div class="sidebar-card">
      <div class="sidebar-progress">
        <div class="progress-ring-wrap" aria-hidden="true">
          <svg class="progress-ring" width="44" height="44" viewBox="0 0 44 44">
            <circle class="progress-ring-bg" cx="22" cy="22" r="18"/>
            <circle class="progress-ring-fill" cx="22" cy="22" r="18" id="ring-fill"
              stroke-dasharray="113.1" stroke-dashoffset="113.1"/>
          </svg>
          <div class="progress-pct" id="progress-pct">0%</div>
        </div>
        <div class="sidebar-progress-text">
          <strong><?= (int)$post['reading_time'] ?> min de lectura</strong>
          Progreso del artículo
        </div>
      </div>
    </div>

    <!-- TOC -->
    <?php if (!empty($toc_items)): ?>
    <div class="sidebar-card">
      <h3><i class="ph ph-list"></i> Contenido</h3>
      <ul class="toc-list" role="list">
        <?php foreach ($toc_items as $toc): ?>
          <li>
            <a href="#<?= h($toc['anchor']) ?>" class="toc-link" data-anchor="<?= h($toc['anchor']) ?>">
              <?= h($toc['text']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <!-- CTA -->
    <div class="sidebar-card">
      <h3><i class="ph ph-rocket-launch"></i> Valírica</h3>
      <p style="font-size:13px;color:rgba(255,255,255,0.50);line-height:1.6;margin-bottom:16px;">
        Inteligencia cultural para PYMES: fichaje, canal de denuncias, diagnóstico DISC y Hofstede, y detección de burnout en tiempo real.
      </p>
      <a href="https://app.valirica.com/registro.php" class="sidebar-cta-btn" target="_blank" rel="noopener noreferrer">
        <i class="ph ph-arrow-right"></i> Prueba gratuita
      </a>
    </div>

  </aside>
</div>

<!-- ══ RELATED POSTS ════════════════════════════════════════════════════════ -->
<?php if (!empty($related)): ?>
<section class="related-section" aria-labelledby="related-heading">
  <div class="related-inner">
    <h2 id="related-heading"><i class="ph ph-article"></i> Artículos relacionados</h2>
    <div class="related-grid">
      <?php
        $rel_icon_map = ['burnout'=>'ph-fire','liderazgo'=>'ph-crown','cultura'=>'ph-building-office','equipo'=>'ph-users-three','desempe'=>'ph-trend-up','talento'=>'ph-star','clima'=>'ph-chart-bar','innovaci'=>'ph-lightbulb','rrhh'=>'ph-briefcase','recurso'=>'ph-briefcase'];
        foreach ($related as $rel):
          $rel_cat  = strtolower($rel['category'] ?? '');
          $rel_icon = 'ph-article';
          foreach ($rel_icon_map as $k => $v) { if (strpos($rel_cat, $k) !== false) { $rel_icon = $v; break; } }
      ?>
      <article>
        <a href="/blog/<?= h($rel['slug']) ?>" class="related-card" aria-label="<?= h($rel['title']) ?>">
          <span class="related-card-accent" style="background: <?= h($rel['cover_gradient']) ?>"></span>
          <div class="related-card-body">
            <div class="related-card-top">
              <p class="related-card-cat"><i class="ph <?= $rel_icon ?>"></i> <?= h($rel['category']) ?></p>
              <div class="related-card-icon" aria-hidden="true"><i class="ph <?= $rel_icon ?>"></i></div>
            </div>
            <h3 class="related-card-title"><?= h($rel['title']) ?></h3>
            <div class="related-card-footer">
              <div class="related-card-meta">
                <span><i class="ph ph-clock"></i> <?= (int)$rel['reading_time'] ?> min</span>
                <span><i class="ph ph-calendar"></i> <?= format_date_es($rel['published_at']) ?></span>
              </div>
              <div class="related-card-arrow" aria-hidden="true"><i class="ph ph-arrow-right"></i></div>
            </div>
          </div>
        </a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══ FOOTER ════════════════════════════════════════════════════════════════ -->
<footer class="footer" role="contentinfo">

  <div class="footer-top">
    <div class="container footer-grid">

      <div class="footer-brand">
        <a href="/" class="footer-logo" aria-label="Valírica — inicio">
          <img src="/assets/icons/logo-dark.svg"
               alt="Valírica HR Software"
               class="footer-logo-img"
               width="134"
               height="34"
               loading="lazy">
        </a>
        <p class="footer-tagline">Inteligencia cultural organizacional para PYMES que lideran con datos, no con intuición.</p>
        <div class="footer-social" aria-label="Redes sociales">
          <a href="https://wa.me/34600876538?text=Hola%2C%20me%20interesa%20saber%20c%C3%B3mo%20Val%C3%ADrica%20puede%20ayudar%20a%20mi%20equipo."
             class="footer-social-link"
             target="_blank"
             rel="noopener noreferrer"
             aria-label="Contactar por WhatsApp">
            <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
          </a>
          <a href="https://www.linkedin.com/company/valirica"
             class="footer-social-link"
             target="_blank"
             rel="me noopener noreferrer"
             aria-label="Seguir a Valírica en LinkedIn">
            <i data-lucide="linkedin"></i>
          </a>
          <a href="https://www.instagram.com/valirica.rrhh"
             class="footer-social-link"
             target="_blank"
             rel="me noopener noreferrer"
             aria-label="Seguir a Valírica en Instagram (@valirica.rrhh)">
            <i data-lucide="instagram"></i>
          </a>
          <a href="https://www.youtube.com/channel/UC6DmXMbAuyX-5QxYlv7WnLA"
             class="footer-social-link"
             target="_blank"
             rel="me noopener noreferrer"
             aria-label="Ver el canal de Valírica en YouTube (@valirica-rh)">
            <i data-lucide="youtube"></i>
          </a>
        </div>
      </div>

      <nav class="footer-col" aria-label="Plataforma">
        <h4 class="footer-col-title">Plataforma</h4>
        <ul class="footer-col-list">
          <li><a href="https://app.valirica.com/registro.php" target="_blank" rel="noopener noreferrer">Crear cuenta gratis</a></li>
          <li><a href="https://app.valirica.com" target="_blank" rel="noopener noreferrer">Iniciar sesión</a></li>
          <li><a href="https://www.valirica.com/#diagnostico-cultural">Diagnóstico cultural</a></li>
          <li><a href="https://www.valirica.com/#modulos">Cómo funciona</a></li>
          <li><a href="https://www.valirica.com/#beneficios">Impacto real</a></li>
          <li><a href="https://www.valirica.com/#pqr">Preguntas frecuentes</a></li>
        </ul>
      </nav>

      <nav class="footer-col" aria-label="Legal">
        <h4 class="footer-col-title">Legal</h4>
        <ul class="footer-col-list">
          <li><a href="https://app.valirica.com/legal/terminos" target="_blank" rel="noopener noreferrer">Términos de servicio</a></li>
          <li><a href="https://app.valirica.com/legal/privacidad" target="_blank" rel="noopener noreferrer">Política de privacidad</a></li>
          <li><a href="https://app.valirica.com/legal/cookies" target="_blank" rel="noopener noreferrer">Política de cookies</a></li>
          <li><a href="https://www.valirica.com/#seguridad">Seguridad</a></li>
        </ul>
      </nav>

      <div class="footer-col footer-col--contact">
        <h4 class="footer-col-title">Contacto</h4>
        <ul class="footer-col-list footer-contact-list">
          <li>
            <a href="https://wa.me/34600876538?text=Hola%2C%20me%20interesa%20saber%20c%C3%B3mo%20Val%C3%ADrica%20puede%20ayudar%20a%20mi%20equipo."
               id="footer-contact-whatsapp"
               target="_blank" rel="noopener noreferrer" class="footer-contact-item">
              <span class="footer-contact-icon">
                <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15" aria-hidden="true">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
              </span>
              <span>+34 600 876 538</span>
            </a>
          </li>
          <li>
            <a href="&#109;&#97;&#105;&#108;&#116;&#111;&#58;&#118;&#97;&#108;&#101;&#64;&#118;&#97;&#108;&#105;&#114;&#105;&#99;&#97;&#46;&#99;&#111;&#109;" id="footer-contact-email" class="footer-contact-item">
              <span class="footer-contact-icon"><i data-lucide="mail"></i></span>
              <span>&#118;&#97;&#108;&#101;&#64;&#118;&#97;&#108;&#105;&#114;&#105;&#99;&#97;&#46;&#99;&#111;&#109;</span>
            </a>
          </li>

          <li class="footer-locations">
            <span class="footer-contact-icon"><i data-lucide="map-pin"></i></span>
            <span>España &amp; Colombia</span>
          </li>
        </ul>
        <a href="https://app.valirica.com/registro.php"
           id="footer-cta-free-trial"
           class="footer-cta-btn"
           target="_blank"
           rel="noopener noreferrer">
          Prueba gratuita
          <i data-lucide="arrow-right" class="footer-cta-icon"></i>
        </a>
      </div>

    </div>
  </div>

  <div class="footer-bottom">
    <div class="container footer-bottom-inner">
      <span class="footer-copyright">© <?= date('Y') ?> Valírica. Todos los derechos reservados.</span>
      <nav class="footer-bottom-links" aria-label="Links legales pie de página">
        <a href="https://app.valirica.com/legal/privacidad" target="_blank" rel="noopener noreferrer">Privacidad</a>
        <a href="https://app.valirica.com/legal/terminos" target="_blank" rel="noopener noreferrer">Términos</a>
        <a href="https://app.valirica.com/legal/cookies" target="_blank" rel="noopener noreferrer">Cookies</a>
      </nav>
      <span class="footer-made">Hecho con intención · España &amp; Colombia</span>
    </div>
  </div>

</footer>

<script>
// ── Reading progress bar & TOC highlight ──────────────────────────────────
(function(){
  const bar     = document.getElementById('reading-progress');
  const ringFill= document.getElementById('ring-fill');
  const pctEl   = document.getElementById('progress-pct');
  const article = document.querySelector('.post-content');
  const tocLinks= document.querySelectorAll('.toc-link');
  const r       = 18;
  const circ    = 2 * Math.PI * r;

  function updateProgress(){
    if (!article) return;
    const rect  = article.getBoundingClientRect();
    const total = article.offsetHeight;
    const scrolled = Math.min(Math.max(-rect.top, 0), total);
    const pct   = Math.round((scrolled / total) * 100);

    bar.style.width = pct + '%';
    bar.setAttribute('aria-valuenow', pct);
    if (ringFill) {
      ringFill.style.strokeDashoffset = circ - (circ * pct / 100);
    }
    if (pctEl) pctEl.textContent = pct + '%';

    // TOC active section
    const sections = document.querySelectorAll('.post-content h2[id]');
    let active = null;
    sections.forEach(s => {
      if (s.getBoundingClientRect().top <= 100) active = s.id;
    });
    tocLinks.forEach(l => l.classList.toggle('active', l.dataset.anchor === active));
  }

  window.addEventListener('scroll', updateProgress, { passive: true });
  updateProgress();
})();

// ── Copy URL ──────────────────────────────────────────────────────────────
function copyUrl(){
  navigator.clipboard.writeText(window.location.href).then(function(){
    const el = document.getElementById('copy-label');
    const orig = el.textContent;
    el.textContent = '¡Copiado!';
    setTimeout(() => { el.textContent = orig; }, 2000);
  });
}
</script>

<script src="/main.min.js" defer></script>
</body>
</html>
