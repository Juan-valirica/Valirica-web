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
$faq_items = [];
preg_match_all('/<h3[^>]*>(.*?)<\/h3>\s*<p[^>]*>(.*?)<\/p>/si', $post['content'], $faq_matches, PREG_SET_ORDER);
foreach ($faq_matches as $m) {
    $q = trim(strip_tags($m[1]));
    $a = trim(strip_tags($m[2]));
    if ($q && $a && str_ends_with($q, '?')) {
        $faq_items[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
    }
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
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-ZZH7VP37W6"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-ZZH7VP37W6');
  </script>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

  <!-- ── SEO primario ── -->
  <title><?= h($seo_title) ?></title>
  <meta name="description" content="<?= h($seo_desc) ?>">
  <?php if ($post['seo_keywords']): ?><meta name="keywords" content="<?= h($post['seo_keywords']) ?>"><?php endif; ?>
  <meta name="author"  content="<?= h($post['author_name']) ?>">
  <meta name="robots"  content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
  <link rel="canonical" href="<?= h($post_url) ?>">

  <!-- ── Open Graph ── -->
  <meta property="og:type"              content="article">
  <meta property="og:site_name"         content="Valírica">
  <meta property="og:title"             content="<?= h($post['title']) ?>">
  <meta property="og:description"       content="<?= h($seo_desc) ?>">
  <meta property="og:url"               content="<?= h($post_url) ?>">
  <meta property="og:image"             content="<?= h($cover_image) ?>">
  <meta property="og:image:alt"         content="<?= h($post['title']) ?>">
  <meta property="og:locale"            content="es_ES">
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

  <!-- ── Recursos ── -->
  <link rel="icon" type="image/svg+xml" href="/assets/icons/favicon-light.svg">
  <meta name="theme-color" content="#012133">
  <link rel="preconnect" href="https://use.typekit.net" crossorigin>
  <link rel="preload" href="https://use.typekit.net/qrv8fyz.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="https://use.typekit.net/qrv8fyz.css"></noscript>
  <link rel="preconnect" href="https://unpkg.com">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">

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

    /* ── NAV ── */
    .vl-nav {
      position: sticky; top: 0; z-index: 9900;
      background: rgba(255,255,255,0.65);
      backdrop-filter: blur(22px) saturate(180%);
      -webkit-backdrop-filter: blur(22px) saturate(180%);
      border-bottom: 1px solid rgba(255,255,255,0.52);
      box-shadow: 0 4px 24px rgba(1,33,51,0.07);
      padding: 8px 0;
    }
    .vl-nav-inner {
      width: 90%; max-width: 1240px; margin: 0 auto;
      display: flex; align-items: center; gap: 8px;
      height: 56px;
      border-radius: 16px;
      background: rgba(255,255,255,0.94);
      border: 1px solid rgba(255,255,255,0.72);
      box-shadow: 0 8px 32px rgba(1,33,51,0.07), 0 2px 8px rgba(1,33,51,0.04), inset 0 1px 0 rgba(255,255,255,0.85);
      padding: 0 20px;
    }
    .vl-nav-logo {
      display: flex; align-items: center;
      text-decoration: none; flex-shrink: 0; margin-right: 8px;
    }
    .vl-nav-logo img { height: 34px; width: auto; }
    .vl-nav-title {
      font-size: 13px; color: rgba(1,33,51,0.45); font-weight: 500;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
      flex: 1; min-width: 0;
    }
    .vl-nav-links { display: flex; gap: 2px; align-items: center; flex-shrink: 0; }
    .vl-nav-link {
      padding: 7px 12px; border-radius: 9px; font-size: 13.5px; font-weight: 600;
      text-decoration: none; color: rgba(1,33,51,0.65);
      transition: color 180ms ease, background 180ms ease; white-space: nowrap;
    }
    .vl-nav-link:hover { color: #012133; background: rgba(1,33,51,0.055); }
    .vl-nav-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
    .vl-nav-login {
      padding: 8px 14px; border-radius: 9px; font-size: 13.5px; font-weight: 600;
      color: rgba(1,33,51,0.65); text-decoration: none;
      transition: color 180ms ease, background 180ms ease; white-space: nowrap;
    }
    .vl-nav-login:hover { color: #012133; background: rgba(1,33,51,0.055); }
    .vl-nav-cta {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 9px 16px; border-radius: 10px; font-size: 13.5px; font-weight: 700;
      background: var(--c-accent); color: #fff; text-decoration: none;
      box-shadow: 0 6px 20px rgba(255,151,0,0.30);
      transition: transform 220ms ease, box-shadow 220ms ease; white-space: nowrap;
    }
    .vl-nav-cta:hover { transform: scale(0.97); box-shadow: 0 4px 14px rgba(255,151,0,0.25); }
    .vl-nav-cta svg { width: 14px; height: 14px; flex-shrink: 0; }

    /* ── COVER ── */
    .post-cover {
      width: 100%; min-height: 420px;
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
      transition: transform var(--transition), box-shadow var(--transition);
      display: flex; flex-direction: column;
    }
    .related-card:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(0,0,0,0.20); border-color: rgba(255,255,255,0.16); }
    .related-card-cover { height: 140px; position: relative; }
    .related-card-body { padding: 16px 18px 18px; flex: 1; display: flex; flex-direction: column; }
    .related-card-cat { font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #4dd6f0; margin-bottom: 8px; }
    .related-card-title { font-size: 15px; font-weight: 800; color: #fff; line-height: 1.3; margin-bottom: 10px; flex: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .related-card-meta { font-size: 12px; color: rgba(255,255,255,0.38); display: flex; align-items: center; gap: 8px; }
    .related-card-meta i { font-size: 13px; }

    /* ── FOOTER ── */
    .blog-footer {
      border-top: 1px solid rgba(255,255,255,0.07);
      padding: 36px 24px;
    }
    .blog-footer-inner {
      max-width: 1160px; margin: 0 auto;
      display: flex; flex-direction: column; gap: 20px; align-items: center; text-align: center;
    }
    .blog-footer-brand p {
      font-size: 12px; color: rgba(255,255,255,0.30); margin-top: 8px; line-height: 1.5;
    }
    .blog-footer-links {
      display: flex; gap: 6px; flex-wrap: wrap; justify-content: center;
    }
    .blog-footer-links a {
      padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 600;
      color: rgba(255,255,255,0.45); text-decoration: none;
      transition: color var(--transition), background var(--transition);
    }
    .blog-footer-links a:hover { color: rgba(255,255,255,0.75); background: rgba(255,255,255,0.06); }
    .blog-footer-legal {
      display: flex; gap: 16px; flex-wrap: wrap; justify-content: center;
      font-size: 12px; color: rgba(255,255,255,0.25);
    }
    .blog-footer-legal a { color: rgba(255,255,255,0.35); text-decoration: none; }
    .blog-footer-legal a:hover { color: rgba(255,255,255,0.55); }

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
      .vl-nav-title { display: none; }
    }
    @media (max-width: 480px) {
      .vl-nav-links { display: none; }
      .vl-nav-login { display: none; }
    }
    @media (max-width: 480px) {
      .post-layout { padding: 32px 16px 60px; }
    }
  </style>
</head>
<body>
<div id="reading-progress" role="progressbar" aria-label="Progreso de lectura" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>

<!-- ══ NAV ══════════════════════════════════════════════════════════════════ -->
<nav class="vl-nav" role="navigation" aria-label="Navegación principal">
  <div class="vl-nav-inner">
    <a href="https://www.valirica.com" class="vl-nav-logo" aria-label="Valírica — inicio">
      <img src="/assets/icons/logo-light.svg" alt="Valírica HR Software" height="34" width="127" loading="eager">
    </a>
    <div class="vl-nav-links">
      <a href="https://www.valirica.com/#modulos" class="vl-nav-link">Plataforma</a>
      <a href="https://www.valirica.com/#diagnostico-cultural" class="vl-nav-link">Diagnóstico</a>
      <a href="https://www.valirica.com/#beneficios" class="vl-nav-link">Impacto</a>
      <a href="https://www.valirica.com/#diferenciador" class="vl-nav-link">Por qué Valírica</a>
      <a href="https://www.valirica.com/#seguridad" class="vl-nav-link">Seguridad</a>
      <a href="/blog" class="vl-nav-link active" aria-current="page">Blog</a>
    </div>
    <div class="vl-nav-actions">
      <a href="https://app.valirica.com" class="vl-nav-login" target="_blank" rel="noopener noreferrer">Acceder</a>
      <a href="https://app.valirica.com/registro.php" class="vl-nav-cta" target="_blank" rel="noopener noreferrer">
        Prueba gratuita
        <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </div>
  </div>
</nav>

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
      <?php foreach ($related as $rel): ?>
      <article>
        <a href="/blog/<?= h($rel['slug']) ?>" class="related-card" aria-label="<?= h($rel['title']) ?>">
          <div class="related-card-cover" style="background: <?= h($rel['cover_gradient']) ?>"></div>
          <div class="related-card-body">
            <p class="related-card-cat"><?= h($rel['category']) ?></p>
            <h3 class="related-card-title"><?= h($rel['title']) ?></h3>
            <div class="related-card-meta">
              <span><i class="ph ph-clock"></i> <?= (int)$rel['reading_time'] ?> min</span>
              <span><i class="ph ph-calendar"></i> <?= format_date_es($rel['published_at']) ?></span>
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
<footer class="blog-footer" role="contentinfo">
  <div class="blog-footer-inner">
    <div class="blog-footer-brand">
      <img src="/assets/icons/logo-dark.svg" alt="Valírica HR Software" height="28" width="105" loading="lazy" style="opacity:0.75;">
      <p>Inteligencia cultural organizacional para PYMES · España y Colombia</p>
    </div>
    <div class="blog-footer-links">
      <a href="https://www.valirica.com">Web</a>
      <a href="/blog">Blog</a>
      <a href="https://app.valirica.com/registro.php" target="_blank" rel="noopener noreferrer">Prueba gratuita</a>
      <a href="https://www.linkedin.com/company/valirica" target="_blank" rel="noopener noreferrer">LinkedIn</a>
      <a href="https://www.instagram.com/valirica.rrhh" target="_blank" rel="noopener noreferrer">Instagram</a>
    </div>
    <div class="blog-footer-legal">
      <span>© <?= date('Y') ?> Valírica</span>
      <a href="mailto:vale@valirica.com">vale@valirica.com</a>
      <a href="/legal/privacidad">Privacidad</a>
      <a href="/legal/terminos">Términos</a>
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
</body>
</html>
