<?php
/**
 * Valírica — Blog (listado público)
 * SEO-optimizado: JSON-LD WebSite + Blog, OG, Twitter Cards, canonical, hreflang
 */
require_once 'config.php';

// ─── Parámetros ────────────────────────────────────────────────────────────
$page       = max(1, (int)($_GET['p'] ?? 1));
$category   = trim($_GET['cat'] ?? '');
$per_page   = 9;
$offset     = ($page - 1) * $per_page;
$base_url   = 'https://www.valirica.com';
$canonical  = $base_url . '/blog' . ($page > 1 ? '?p=' . $page : '') . ($category ? ($page > 1 ? '&' : '?') . 'cat=' . urlencode($category) : '');

// ─── Consulta posts ────────────────────────────────────────────────────────
$where  = "status = 'published'";
$params = [];
$types  = '';
if ($category) {
    $where .= " AND category = ?";
    $params[] = $category;
    $types   .= 's';
}

// Total para paginación
$count_sql = "SELECT COUNT(*) FROM blog_posts WHERE $where";
$stmt_c = $conn->prepare($count_sql);
if ($types) $stmt_c->bind_param($types, ...$params);
$stmt_c->execute();
$stmt_c->bind_result($total);
$stmt_c->fetch();
$stmt_c->close();
$total_pages = (int)ceil($total / $per_page);

// Posts de la página actual
$sql = "SELECT id, slug, title, excerpt, cover_gradient, cover_image, author_name,
               category, tags, reading_time, published_at, featured
        FROM blog_posts
        WHERE $where
        ORDER BY featured DESC, published_at DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$p_page = $per_page;
$p_off  = $offset;
if ($types) {
    $stmt->bind_param($types . 'ii', ...[...$params, $p_page, $p_off]);
} else {
    $stmt->bind_param('ii', $p_page, $p_off);
}
$stmt->execute();
$result = $stmt->get_result();
$posts  = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Categorías disponibles
$cats_res = $conn->query("SELECT DISTINCT category, COUNT(*) as n FROM blog_posts WHERE status='published' GROUP BY category ORDER BY n DESC");
$categories = $cats_res->fetch_all(MYSQLI_ASSOC);

// Featured post (primero de la lista si aplica)
$featured_post = null;
if ($page === 1 && !$category && !empty($posts) && $posts[0]['featured']) {
    $featured_post = array_shift($posts);
}

// ─── JSON-LD ───────────────────────────────────────────────────────────────
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
    'contactPoint' => ['@type' => 'ContactPoint', 'telephone' => '+34-600-876-538', 'email' => 'vale@valirica.com', 'contactType' => 'customer service', 'availableLanguage' => ['Spanish']],
    'areaServed'  => [['@type' => 'Country', 'name' => 'España'], ['@type' => 'Country', 'name' => 'Colombia']],
];
$jsonld_website = [
    '@context'        => 'https://schema.org',
    '@type'           => 'WebSite',
    '@id'             => $base_url . '/#website',
    'name'            => 'Valírica',
    'url'             => $base_url,
    'description'     => 'Plataforma SaaS de RRHH e inteligencia cultural para PYMES en España y Colombia: clima laboral en tiempo real, prevención de burnout, registro de jornada laboral y retención de talento.',
    'publisher'       => ['@id' => $base_url . '/#organization'],
    'inLanguage'      => 'es',
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => $base_url . '/blog?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
];
$jsonld_blog = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Blog',
    'name'        => 'Blog de Cultura Organizacional, Clima Laboral y Burnout — Valírica',
    'description' => 'Artículos, guías y recursos sobre cultura organizacional, clima laboral, prevención del burnout, software de RRHH para PYMES, registro de jornada laboral, retención de talento y Employee Wellbeing en España y Colombia. Liderazgo de equipos, DISC, Hofstede y people analytics.',
    'url'         => $base_url . '/blog',
    'inLanguage'  => 'es',
    'publisher'   => ['@id' => $base_url . '/#organization'],
];
$jsonld_breadcrumb = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => $base_url],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog',   'item' => $base_url . '/blog'],
    ],
];
if ($category) {
    $jsonld_breadcrumb['itemListElement'][] = [
        '@type'    => 'ListItem',
        'position' => 3,
        'name'     => htmlspecialchars($category),
        'item'     => $base_url . '/blog?cat=' . urlencode($category),
    ];
}

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function format_date_es($dt){
    $months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $ts = strtotime($dt);
    return $ts ? date('j', $ts) . ' de ' . $months[(int)date('n', $ts) - 1] . ' de ' . date('Y', $ts) : '';
}
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
  <title><?= $category ? h($category) . ' — Blog Valírica HR Software' : 'Blog de Cultura Organizacional, Clima Laboral y Prevención de Burnout | Valírica' ?></title>
  <meta name="description" content="<?= $category ? 'Artículos sobre ' . h($category) . ' para equipos de RRHH en Valírica. Software de clima laboral y prevención de burnout para PYMES en España y Colombia.' : 'Descubre guías, estrategias y recursos sobre cultura organizacional, clima laboral, prevención del burnout, software RRHH para PYMES, registro de jornada y retención de talento en España y Colombia.' ?>">
  <meta name="keywords" content="cultura organizacional, clima laboral, burnout laboral, prevención burnout, software RRHH España, HR software PYMES, registro de jornada laboral, Employee Wellbeing Platform, retención de talento, liderazgo equipos, gestión talento, DISC, Hofstede, people analytics, employee engagement">
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
  <meta name="author" content="Equipo Valírica">
  <link rel="canonical" href="<?= h($canonical) ?>">

  <!-- ── Open Graph ── -->
  <meta property="og:type"        content="website">
  <meta property="og:site_name"   content="Valírica">
  <meta property="og:title"       content="Blog de Cultura Organizacional | Valírica">
  <meta property="og:description" content="Guías, estrategias y recursos sobre cultura organizacional, liderazgo de equipos y gestión del talento.">
  <meta property="og:url"         content="<?= h($canonical) ?>">
  <meta property="og:image"       content="https://app.valirica.com/uploads/logo-192.png">
  <meta property="og:locale"      content="es_ES">

  <!-- ── Twitter Card ── -->
  <meta name="twitter:card"        content="summary_large_image">
  <meta name="twitter:title"       content="Blog de Cultura Organizacional | Valírica">
  <meta name="twitter:description" content="Guías, estrategias y recursos sobre cultura organizacional, liderazgo de equipos y gestión del talento.">
  <meta name="twitter:image"       content="https://app.valirica.com/uploads/logo-192.png">

  <!-- ── Paginación SEO ── -->
  <?php if ($page > 1): ?><link rel="prev" href="<?= h($base_url . '/blog?p=' . ($page - 1) . ($category ? '&cat=' . urlencode($category) : '')) ?>"><?php endif; ?>
  <?php if ($page < $total_pages): ?><link rel="next" href="<?= h($base_url . '/blog?p=' . ($page + 1) . ($category ? '&cat=' . urlencode($category) : '')) ?>"><?php endif; ?>

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
  <script type="application/ld+json"><?= json_encode($jsonld_org,        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script type="application/ld+json"><?= json_encode($jsonld_website,    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script type="application/ld+json"><?= json_encode($jsonld_blog,       JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <script type="application/ld+json"><?= json_encode($jsonld_breadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --c-primary:   #012133;
      --c-secondary: #184656;
      --c-teal:      #007a96;
      --c-accent:    #ff9700;
      --c-soft:      #FFF5F0;
      --font: "gelica", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      --radius-card: 20px;
      --transition: 0.22s ease;
    }
    html { scroll-behavior: smooth; }
    body {
      font-family: var(--font);
      background: linear-gradient(160deg, #010f1a 0%, #011929 35%, var(--c-primary) 70%, #0d3a4f 100%);
      min-height: 100vh;
      color: #fff;
      -webkit-font-smoothing: antialiased;
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

    /* ── HERO ── */
    .blog-hero {
      position: relative; overflow: hidden;
      padding: 148px 24px 72px;
      text-align: center;
    }
    .blog-hero::before {
      content: '';
      position: absolute; inset: 0;
      background:
        radial-gradient(ellipse at 70% 0%,  rgba(0,122,150,0.35) 0%, transparent 55%),
        radial-gradient(ellipse at 15% 90%, rgba(255,151,0,0.18) 0%, transparent 48%);
      pointer-events: none;
    }
    /* Decorative rings */
    .blog-hero .vl-ring {
      position: absolute; border-radius: 50%; border: 1px solid rgba(0,122,150,0.13); pointer-events: none;
    }
    .blog-hero .r1 { width: 500px; height: 500px; top: -220px; right: -150px; }
    .blog-hero .r2 { width: 360px; height: 360px; bottom: -130px; left: -100px; border-color: rgba(255,151,0,0.09); }

    .blog-hero-inner { position: relative; z-index: 1; max-width: 680px; margin: 0 auto; }
    .blog-hero-tag {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(255,151,0,0.12); border: 1px solid rgba(255,151,0,0.25);
      color: #f5a23d; font-size: 11px; font-weight: 700; letter-spacing: 2px;
      text-transform: uppercase; padding: 6px 14px; border-radius: 100px;
      margin-bottom: 22px;
    }
    .blog-hero-tag i { font-size: 14px; }
    .blog-hero h1 {
      font-size: clamp(32px, 5vw, 52px); font-weight: 900;
      color: #fff; line-height: 1.1; letter-spacing: -1px;
      margin-bottom: 16px;
    }
    .blog-hero h1 span { color: var(--c-accent); }
    .blog-hero p {
      font-size: clamp(15px, 2vw, 17px); color: rgba(255,255,255,0.55);
      line-height: 1.7; max-width: 520px; margin: 0 auto 36px;
    }

    /* ── BREADCRUMB ── */
    .vl-breadcrumb {
      display: flex; align-items: center; gap: 6px;
      justify-content: center;
      font-size: 12px; color: rgba(255,255,255,0.38);
      margin-bottom: 0;
    }
    .vl-breadcrumb a { color: rgba(255,255,255,0.45); text-decoration: none; }
    .vl-breadcrumb a:hover { color: rgba(255,255,255,0.7); }
    .vl-breadcrumb i { font-size: 11px; }

    /* ── CATEGORY FILTERS ── */
    .vl-filters {
      padding: 0 24px 40px;
    }
    .vl-filters-inner {
      max-width: 1160px; margin: 0 auto;
      display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;
    }
    .vl-filter-btn {
      padding: 8px 18px; border-radius: 100px;
      border: 1px solid rgba(255,255,255,0.14);
      background: rgba(255,255,255,0.05);
      color: rgba(255,255,255,0.58); font-size: 13px; font-weight: 600;
      font-family: var(--font); cursor: pointer;
      text-decoration: none;
      transition: all var(--transition);
    }
    .vl-filter-btn:hover { background: rgba(255,255,255,0.10); color: #fff; border-color: rgba(255,255,255,0.25); }
    .vl-filter-btn.active {
      background: rgba(255,151,0,0.15); border-color: rgba(255,151,0,0.40);
      color: #f5a23d;
    }
    .vl-filter-count {
      display: inline-flex; align-items: center; justify-content: center;
      background: rgba(255,255,255,0.12); border-radius: 100px;
      width: 20px; height: 20px; font-size: 11px; margin-left: 4px;
    }

    /* ── MAIN GRID ── */
    .blog-main { padding: 0 24px 80px; }
    .blog-main-inner { max-width: 1160px; margin: 0 auto; }

    /* ── FEATURED CARD ── */
    .blog-featured {
      display: grid; grid-template-columns: 1fr 1fr; gap: 32px;
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.10);
      border-radius: 26px; overflow: hidden;
      margin-bottom: 48px;
      transition: transform var(--transition), box-shadow var(--transition);
      text-decoration: none; color: inherit;
      position: relative;
    }
    .blog-featured:hover {
      transform: translateY(-4px);
      box-shadow: 0 24px 64px rgba(0,122,150,0.18);
      border-color: rgba(0,122,150,0.30);
    }
    .blog-featured-cover {
      min-height: 340px;
      display: flex; align-items: flex-end;
      padding: 28px;
      position: relative;
    }
    .blog-featured-cover::after {
      content: '';
      position: absolute; inset: 0;
      background: linear-gradient(to top, rgba(1,33,51,0.7) 0%, transparent 60%);
    }
    .blog-featured-badge {
      position: relative; z-index: 1;
      display: inline-flex; align-items: center; gap: 5px;
      background: rgba(255,151,0,0.20); border: 1px solid rgba(255,151,0,0.35);
      color: #f5a23d; font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
      text-transform: uppercase; padding: 5px 12px; border-radius: 100px;
    }
    .blog-featured-badge i { font-size: 13px; }
    .blog-featured-body { padding: 36px 40px 36px 0; display: flex; flex-direction: column; justify-content: center; }
    .blog-cat-pill {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
      color: #4dd6f0; margin-bottom: 14px;
    }
    .blog-cat-pill i { font-size: 13px; }
    .blog-featured-title {
      font-size: clamp(22px, 2.5vw, 30px); font-weight: 900;
      color: #fff; line-height: 1.2; letter-spacing: -0.5px; margin-bottom: 14px;
    }
    .blog-featured-excerpt {
      font-size: 14px; color: rgba(255,255,255,0.55); line-height: 1.7;
      margin-bottom: 24px; flex: 1;
      display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
    }
    .blog-meta {
      display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
      font-size: 12px; color: rgba(255,255,255,0.40);
    }
    .blog-meta span { display: flex; align-items: center; gap: 4px; }
    .blog-meta i { font-size: 14px; }
    .blog-read-more {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 11px 22px; border-radius: 12px; font-size: 13px; font-weight: 700;
      background: linear-gradient(135deg, var(--c-teal), #005f74);
      color: #fff; text-decoration: none; align-self: flex-start;
      box-shadow: 0 4px 14px rgba(0,122,150,0.30);
      transition: opacity var(--transition), transform var(--transition);
    }
    .blog-read-more:hover { opacity: 0.88; transform: scale(0.98); }
    .blog-read-more i { font-size: 16px; }

    /* ── POST GRID ── */
    .blog-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
      margin-bottom: 56px;
    }
    .blog-card {
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.09);
      border-radius: var(--radius-card);
      overflow: hidden;
      text-decoration: none; color: inherit;
      transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition);
      display: flex; flex-direction: column;
    }
    .blog-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 50px rgba(0,0,0,0.25);
      border-color: rgba(255,255,255,0.18);
    }
    .blog-card-cover {
      height: 180px; position: relative;
      display: flex; align-items: flex-end; padding: 16px;
    }
    .blog-card-cover::after {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(to top, rgba(1,33,51,0.55) 0%, transparent 65%);
    }
    .blog-card-cat {
      position: relative; z-index: 1;
      font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
      color: rgba(255,255,255,0.75);
      background: rgba(0,0,0,0.30); backdrop-filter: blur(8px);
      padding: 4px 10px; border-radius: 100px;
    }
    .blog-card-body { padding: 20px 22px 22px; flex: 1; display: flex; flex-direction: column; }
    .blog-card-title {
      font-size: 20px; font-weight: 800; color: #fff;
      line-height: 1.35; letter-spacing: -0.3px; margin-bottom: 10px;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .blog-card-excerpt {
      font-size: 13px; color: rgba(255,255,255,0.48); line-height: 1.6;
      flex: 1; margin-bottom: 18px;
      display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
    }
    .blog-card-footer {
      display: flex; align-items: center; justify-content: space-between;
      font-size: 12px; color: rgba(255,255,255,0.38);
      border-top: 1px solid rgba(255,255,255,0.07); padding-top: 14px;
    }
    .blog-card-footer-left { display: flex; align-items: center; gap: 10px; }
    .blog-card-footer span { display: flex; align-items: center; gap: 4px; }
    .blog-card-footer i { font-size: 13px; }
    .blog-card-arrow {
      width: 30px; height: 30px; border-radius: 50%;
      background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
      display: flex; align-items: center; justify-content: center;
      color: rgba(255,255,255,0.55); font-size: 15px;
      transition: background var(--transition), color var(--transition);
    }
    .blog-card:hover .blog-card-arrow { background: var(--c-accent); color: #fff; border-color: var(--c-accent); }

    /* ── EMPTY STATE ── */
    .blog-empty {
      text-align: center; padding: 80px 24px;
      color: rgba(255,255,255,0.45);
    }
    .blog-empty i { font-size: 56px; color: rgba(255,255,255,0.15); margin-bottom: 16px; display: block; }
    .blog-empty h3 { font-size: 20px; color: rgba(255,255,255,0.6); margin-bottom: 8px; }

    /* ── PAGINATION ── */
    .vl-pagination {
      display: flex; justify-content: center; align-items: center; gap: 8px;
      margin-top: 8px;
    }
    .vl-pag-btn {
      min-width: 42px; height: 42px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.05);
      color: rgba(255,255,255,0.55); font-size: 13px; font-weight: 700; font-family: var(--font);
      text-decoration: none; cursor: pointer;
      transition: all var(--transition);
    }
    .vl-pag-btn:hover { background: rgba(255,255,255,0.10); color: #fff; border-color: rgba(255,255,255,0.25); }
    .vl-pag-btn.active { background: var(--c-accent); border-color: var(--c-accent); color: #fff; box-shadow: 0 4px 14px rgba(255,151,0,0.30); }
    .vl-pag-btn:disabled, .vl-pag-btn.disabled { opacity: 0.3; pointer-events: none; }
    .vl-pag-btn i { font-size: 16px; }

    /* ── CTA BANNER ── */
    .blog-cta {
      background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.09);
      border-radius: 24px; padding: 48px 40px;
      text-align: center; margin-top: 56px;
      position: relative; overflow: hidden;
    }
    .blog-cta::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse at 50% 0%, rgba(255,151,0,0.12) 0%, transparent 65%);
      pointer-events: none;
    }
    .blog-cta-inner { position: relative; z-index: 1; max-width: 540px; margin: 0 auto; }
    .blog-cta h2 { font-size: 26px; font-weight: 900; color: #fff; margin-bottom: 12px; letter-spacing: -0.4px; }
    .blog-cta h2 span { color: var(--c-accent); }
    .blog-cta p { font-size: 15px; color: rgba(255,255,255,0.52); line-height: 1.7; margin-bottom: 28px; }
    .blog-cta-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .btn-primary {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 13px 26px; border-radius: 12px; font-size: 14px; font-weight: 700;
      background: linear-gradient(135deg, var(--c-accent), #e07800); color: #fff;
      text-decoration: none; box-shadow: 0 4px 18px rgba(255,151,0,0.35);
      transition: opacity var(--transition), transform var(--transition);
    }
    .btn-primary:hover { opacity: 0.88; transform: scale(0.98); }
    .btn-secondary {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 13px 26px; border-radius: 12px; font-size: 14px; font-weight: 700;
      background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.15); color: #fff;
      text-decoration: none;
      transition: background var(--transition), border-color var(--transition);
    }
    .btn-secondary:hover { background: rgba(255,255,255,0.11); border-color: rgba(255,255,255,0.25); }

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
      .blog-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
      .blog-featured { grid-template-columns: 1fr; }
      .blog-featured-cover { min-height: 220px; }
      .blog-featured-body { padding: 24px; }
      .blog-grid { grid-template-columns: 1fr; }
      .blog-hero { padding: 56px 20px 48px; }
    }
    @media (max-width: 480px) {
      .blog-cta { padding: 36px 24px; }
    }
  </style>
</head>
<body>

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
        <a href="https://www.valirica.com/#modulos" class="nav-link">Plataforma</a>
        <a href="https://www.valirica.com/#diagnostico-cultural" class="nav-link">Diagnóstico</a>
        <a href="https://www.valirica.com/#beneficios" class="nav-link">Impacto</a>
        <a href="https://www.valirica.com/#diferenciador" class="nav-link">Por qué Valírica</a>
        <a href="https://www.valirica.com/#seguridad" class="nav-link">Seguridad</a>
        <a href="/blog" class="nav-link is-active" aria-current="page">Blog</a>
      </nav>

      <div class="nav-actions">
        <a href="https://app.valirica.com"
           class="nav-login"
           target="_blank"
           rel="noopener noreferrer">
          Acceder
        </a>
        <a href="https://app.valirica.com/registro.php"
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
        <a href="https://www.valirica.com/#diagnostico-cultural" class="nav-mobile-link">Diagnóstico cultural</a>
        <a href="https://www.valirica.com/#modulos" class="nav-mobile-link">Plataforma</a>
        <a href="https://www.valirica.com/#beneficios" class="nav-mobile-link">Impacto real</a>
        <a href="https://www.valirica.com/#diferenciador" class="nav-mobile-link">Por qué Valírica</a>
        <a href="https://www.valirica.com/#seguridad" class="nav-mobile-link">Seguridad y datos</a>
        <a href="/blog" class="nav-mobile-link is-active">Blog</a>
      </nav>
      <div class="nav-mobile-actions">
        <a href="https://app.valirica.com"
           class="nav-mobile-login"
           target="_blank"
           rel="noopener noreferrer">
          Iniciar sesión
        </a>
        <a href="https://app.valirica.com/registro.php"
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

<!-- ══ HERO ══════════════════════════════════════════════════════════════════ -->
<header class="blog-hero" role="banner">
  <span class="vl-ring r1"></span>
  <span class="vl-ring r2"></span>
  <div class="blog-hero-inner">
    <nav aria-label="Breadcrumb" class="vl-breadcrumb" style="margin-bottom:24px">
      <a href="/">Inicio</a>
      <i class="ph ph-caret-right"></i>
      <?php if ($category): ?>
        <a href="/blog">Blog</a>
        <i class="ph ph-caret-right"></i>
        <span><?= h($category) ?></span>
      <?php else: ?>
        <span aria-current="page">Blog</span>
      <?php endif; ?>
    </nav>
    <div class="blog-hero-tag">
      <i class="ph ph-pencil-line"></i>
      <?= $category ? h($category) : 'Cultura, Equipos & Talento' ?>
    </div>
    <h1>
      <?php if ($category): ?>
        <?= h($category) ?>
      <?php else: ?>
        Ideas que <span>transforman</span><br>equipos y culturas
      <?php endif; ?>
    </h1>
    <p>
      <?= $category
        ? 'Artículos, guías y estrategias sobre ' . h($category) . ' para líderes y equipos de recursos humanos.'
        : 'Artículos, guías y estrategias basadas en datos para líderes que quieren construir culturas organizacionales de alto rendimiento.'
      ?>
    </p>
  </div>
</header>

<!-- ══ CATEGORY FILTERS ══════════════════════════════════════════════════════ -->
<?php if (!empty($categories)): ?>
<section class="vl-filters" aria-label="Filtrar por categoría">
  <div class="vl-filters-inner">
    <a href="/blog" class="vl-filter-btn <?= !$category ? 'active' : '' ?>">
      <i class="ph ph-squares-four"></i> Todos
      <span class="vl-filter-count"><?= $total ?></span>
    </a>
    <?php foreach ($categories as $cat): ?>
      <a href="/blog?cat=<?= urlencode($cat['category']) ?>"
         class="vl-filter-btn <?= $category === $cat['category'] ? 'active' : '' ?>">
        <?= h($cat['category']) ?>
        <span class="vl-filter-count"><?= (int)$cat['n'] ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- ══ POSTS ════════════════════════════════════════════════════════════════ -->
<main class="blog-main" id="main-content">
  <div class="blog-main-inner">

    <?php if (empty($posts) && !$featured_post): ?>
      <div class="blog-empty" role="status">
        <i class="ph ph-article"></i>
        <h3>No hay artículos todavía</h3>
        <p>Pronto publicaremos contenido sobre cultura organizacional y gestión de equipos.</p>
      </div>

    <?php else: ?>

      <!-- ── Featured post ── -->
      <?php if ($featured_post): ?>
      <article>
        <a href="/blog/<?= h($featured_post['slug']) ?>" class="blog-featured" aria-label="Artículo destacado: <?= h($featured_post['title']) ?>">
          <div class="blog-featured-cover" style="background: <?= h($featured_post['cover_gradient']) ?>">
            <?php
              $fi_ci  = $featured_post['cover_image'] ?? '';
              $fi_cat = strtolower($featured_post['category'] ?? '');
              $cat_icon_map = ['burnout'=>'ph-fire','liderazgo'=>'ph-crown','cultura'=>'ph-building-office','equipo'=>'ph-users-three','desempe'=>'ph-trend-up','talento'=>'ph-star','clima'=>'ph-chart-bar','innovaci'=>'ph-lightbulb','rrhh'=>'ph-briefcase','recurso'=>'ph-briefcase'];
              if (str_starts_with($fi_ci, 'icon:')) {
                $fi_icon = h(substr($fi_ci, 5));
              } else {
                $fi_icon = 'ph-article';
                foreach ($cat_icon_map as $k => $v) { if (strpos($fi_cat, $k) !== false) { $fi_icon = $v; break; } }
              }
            ?>
              <i class="ph <?= $fi_icon ?>" style="position:absolute;top:50%;right:8%;transform:translateY(-50%);font-size:110px;opacity:0.15;color:#fff;pointer-events:none;" aria-hidden="true"></i>
            <span class="blog-featured-badge"><i class="ph-fill ph-star"></i> Artículo destacado</span>
          </div>
          <div class="blog-featured-body">
            <div class="blog-cat-pill"><i class="ph ph-tag"></i> <?= h($featured_post['category']) ?></div>
            <h2 class="blog-featured-title"><?= h($featured_post['title']) ?></h2>
            <p class="blog-featured-excerpt"><?= h($featured_post['excerpt']) ?></p>
            <div class="blog-meta" style="margin-bottom:24px">
              <span><i class="ph ph-user"></i> <?= h($featured_post['author_name']) ?></span>
              <span><i class="ph ph-calendar"></i> <?= format_date_es($featured_post['published_at']) ?></span>
              <span><i class="ph ph-clock"></i> <?= (int)$featured_post['reading_time'] ?> min de lectura</span>
            </div>
            <span class="blog-read-more">Leer artículo <i class="ph ph-arrow-right"></i></span>
          </div>
        </a>
      </article>
      <?php endif; ?>

      <!-- ── Post grid ── -->
      <?php if (!empty($posts)): ?>
      <section aria-label="Artículos del blog">
        <div class="blog-grid">
          <?php foreach ($posts as $post): ?>
          <article>
            <a href="/blog/<?= h($post['slug']) ?>" class="blog-card" aria-label="<?= h($post['title']) ?>">
              <div class="blog-card-cover" style="background: <?= h($post['cover_gradient']) ?>">
                <?php
                  $card_ci  = $post['cover_image'] ?? '';
                  $card_cat = strtolower($post['category'] ?? '');
                  if (str_starts_with($card_ci, 'icon:')) {
                    $card_icon = h(substr($card_ci, 5));
                  } else {
                    $card_icon = 'ph-article';
                    foreach ($cat_icon_map as $k => $v) { if (strpos($card_cat, $k) !== false) { $card_icon = $v; break; } }
                  }
                ?>
                <i class="ph <?= $card_icon ?>" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:64px;opacity:0.18;color:#fff;pointer-events:none;" aria-hidden="true"></i>
                <span class="blog-card-cat"><?= h($post['category']) ?></span>
              </div>
              <div class="blog-card-body">
                <h2 class="blog-card-title"><?= h($post['title']) ?></h2>
                <p class="blog-card-excerpt"><?= h($post['excerpt']) ?></p>
                <div class="blog-card-footer">
                  <div class="blog-card-footer-left">
                    <span><i class="ph ph-calendar"></i> <?= format_date_es($post['published_at']) ?></span>
                    <span><i class="ph ph-clock"></i> <?= (int)$post['reading_time'] ?> min</span>
                  </div>
                  <div class="blog-card-arrow" aria-hidden="true"><i class="ph ph-arrow-right"></i></div>
                </div>
              </div>
            </a>
          </article>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- ── Pagination ── -->
      <?php if ($total_pages > 1): ?>
      <nav class="vl-pagination" aria-label="Paginación del blog">
        <?php
        $prev_url = $page > 1 ? '/blog?p=' . ($page - 1) . ($category ? '&cat=' . urlencode($category) : '') : null;
        $next_url = $page < $total_pages ? '/blog?p=' . ($page + 1) . ($category ? '&cat=' . urlencode($category) : '') : null;
        ?>
        <a href="<?= $prev_url ?? '#' ?>" class="vl-pag-btn <?= !$prev_url ? 'disabled' : '' ?>" aria-label="Página anterior" <?= !$prev_url ? 'aria-disabled="true"' : '' ?>>
          <i class="ph ph-caret-left"></i>
        </a>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <?php if ($i === $page): ?>
            <span class="vl-pag-btn active" aria-current="page"><?= $i ?></span>
          <?php elseif ($i <= 3 || $i >= $total_pages - 1 || abs($i - $page) <= 1): ?>
            <a href="/blog?p=<?= $i ?><?= $category ? '&cat=' . urlencode($category) : '' ?>" class="vl-pag-btn"><?= $i ?></a>
          <?php elseif (abs($i - $page) === 2): ?>
            <span class="vl-pag-btn" style="pointer-events:none">…</span>
          <?php endif; ?>
        <?php endfor; ?>
        <a href="<?= $next_url ?? '#' ?>" class="vl-pag-btn <?= !$next_url ? 'disabled' : '' ?>" aria-label="Página siguiente" <?= !$next_url ? 'aria-disabled="true"' : '' ?>>
          <i class="ph ph-caret-right"></i>
        </a>
      </nav>
      <?php endif; ?>

    <?php endif; ?>

    <!-- ── CTA Banner ── -->
    <aside class="blog-cta" aria-label="Llamada a la acción">
      <div class="blog-cta-inner">
        <h2>¿Listo para <span>activar tu cultura</span> con datos reales?</h2>
        <p>Valírica es la plataforma de inteligencia cultural para PYMES. Fichaje digital, canal de denuncias, diagnóstico DISC y Hofstede, y detección de burnout en tiempo real — todo en uno.</p>
        <div class="blog-cta-btns">
          <a href="https://app.valirica.com/registro.php" class="btn-primary" target="_blank" rel="noopener noreferrer">
            <i class="ph ph-rocket-launch"></i> Prueba gratuita
          </a>
          <a href="https://app.valirica.com" class="btn-secondary" target="_blank" rel="noopener noreferrer">
            <i class="ph ph-sign-in"></i> Ya tengo cuenta
          </a>
        </div>
      </div>
    </aside>

  </div>
</main>

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
            <a href="&#109;&#97;&#105;&#108;&#116;&#111;&#58;&#118;&#97;&#108;&#101;&#64;&#118;&#97;&#108;&#105;&#114;&#105;&#99;&#97;&#46;&#99;&#111;&#109;" class="footer-contact-item">
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


<script src="/main.min.js" defer></script>
</body>
</html>
