<?php
function biz_name(string $slug, string $fallback): string {
    $file = __DIR__ . '/bizsite/data/' . $slug . '/info.csv';
    if (!file_exists($file)) return $fallback;
    $fh = fopen($file, 'r');
    if (!$fh) return $fallback;
    while (($row = fgetcsv($fh)) !== false) {
        $k = trim(ltrim($row[0] ?? '', "\xEF\xBB\xBF"));
        if ($k === 'name' && !empty($row[1])) { fclose($fh); return $row[1]; }
    }
    fclose($fh);
    return $fallback;
}

$buildings = [
    ['n' => biz_name('tropical-squeeze',      'Tropical Squeeze'),    's' => 'tropical-squeeze',      'x' => 74,  'y' => 26,  'w' => 56,  'h' => 106, 'c' => '#27ae60'],
    ['n' => biz_name('expression-barbershop', 'Expression Barber'),   's' => 'expression-barbershop', 'x' => 136, 'y' => 26,  'w' => 54,  'h' => 106, 'c' => '#8e44ad'],
    ['n' => biz_name('queens-grill',          "Queen's Grill"),        's' => 'queens-grill',          'x' => 196, 'y' => 18,  'w' => 110, 'h' => 122, 'c' => '#c0392b'],
    ['n' => biz_name('madras-dosa',           'Madras Dosa'),          's' => 'madras-dosa',           'x' => 312, 'y' => 24,  'w' => 88,  'h' => 114, 'c' => '#c0392b'],
    ['n' => biz_name('soo-delicious',         'So Delicious'),         's' => 'soo-delicious',         'x' => 476, 'y' => 14,  'w' => 122, 'h' => 136, 'c' => '#e74c3c'],
    ['n' => biz_name('la-galerie',            'La Galerie'),           's' => 'la-galerie',            'x' => 604, 'y' => 24,  'w' => 90,  'h' => 116, 'c' => '#e67e22'],
    ['n' => biz_name('taste-jamaica',         'Taste Jamaica'),        's' => 'taste-jamaica',         'x' => 700, 'y' => 18,  'w' => 58,  'h' => 134, 'c' => '#f39c12'],
    ['n' => biz_name('bamboo-junction',       'Bamboo Junction'),      's' => 'bamboo-junction',       'x' => 768, 'y' => 18,  'w' => 112, 'h' => 126, 'c' => '#16a085'],
    ['n' => biz_name('felicias-nigerian',     "Felicia's Nigerian"),   's' => 'felicias-nigerian',     'x' => 892, 'y' => 24,  'w' => 84,  'h' => 114, 'c' => '#c0392b'],
    ['n' => biz_name('champions',             'Champions'),            's' => 'champions',             'x' => 130, 'y' => 318, 'w' => 88,  'h' => 106, 'c' => '#c0392b'],
    ['n' => 'Eco Blu',                                                  's' => null,                    'x' => 226, 'y' => 318, 'w' => 84,  'h' => 106, 'c' => '#bdc3c7'],
    ['n' => 'Facilities',                                               's' => null,                    'x' => 316, 'y' => 296, 'w' => 44,  'h' => 136, 'c' => '#7f8c8d'],
    ['n' => biz_name('perfume-shop',          'Perfume Shop'),         's' => 'perfume-shop',          'x' => 370, 'y' => 318, 'w' => 66,  'h' => 106, 'c' => '#8e44ad'],
    ['n' => biz_name('decorum',               'Decorum'),              's' => 'decorum',               'x' => 444, 'y' => 304, 'w' => 118, 'h' => 124, 'c' => '#2c3e50'],
    ['n' => biz_name('fade-king',             'Fade King'),            's' => 'fade-king',             'x' => 570, 'y' => 310, 'w' => 92,  'h' => 116, 'c' => '#8e44ad'],
    ['n' => 'Royalty Boutique',                                         's' => null,                    'x' => 670, 'y' => 310, 'w' => 96,  'h' => 116, 'c' => '#bdc3c7'],
    ['n' => biz_name('josh-roti',             'Josh Roti'),            's' => 'josh-roti',             'x' => 774, 'y' => 310, 'w' => 88,  'h' => 116, 'c' => '#c0392b'],
    ['n' => biz_name('wendys-organics',       "Wendy's Organics"),     's' => 'wendys-organics',       'x' => 870, 'y' => 310, 'w' => 90,  'h' => 116, 'c' => '#27ae60'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Site Map — Exit 473 Food &amp; Lifestyle Park, Grenada</title>
  <meta name="description" content="Interactive aerial map of Exit 473 — explore all businesses at Grenada's premier food &amp; lifestyle park on Maurice Bishop Highway, Grand Anse.">
  <link rel="canonical" href="https://exit473.com/exit473_aerial_map.php">
  <meta name="robots" content="index, follow">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Exit 473">
  <meta property="og:url" content="https://exit473.com/exit473_aerial_map.php">
  <meta property="og:title" content="Site Map — Exit 473 Food &amp; Lifestyle Park, Grenada">
  <meta property="og:description" content="Interactive aerial map of Exit 473 — explore all businesses at Grenada's premier food &amp; lifestyle park.">
  <meta property="og:image" content="https://exit473.com/images/logo.png">
  <meta property="og:locale" content="en_GD">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="Site Map — Exit 473 Food &amp; Lifestyle Park">
  <meta name="twitter:description" content="Interactive aerial map of Exit 473 — explore all businesses at Grenada's premier food &amp; lifestyle park.">
  <meta name="twitter:image" content="https://exit473.com/images/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css?v=3">
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-T6MG63FX84"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-T6MG63FX84');
  </script>
  <style>
    .map-wrap { width: 100%; overflow: hidden; background: #F0F7F2; border-radius: 12px; padding: 12px 12px 8px; }
    .map-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 8px; }
    .map-title { font-size: 16px; font-weight: 500; color: #1A2B1A; }
    .map-sub { font-size: 12px; color: #556B55; }
    .zoom-row { display: flex; gap: 5px; }
    .zoom-row button { font-size: 13px; padding: 2px 10px; cursor: pointer; background: #fff; border: 0.5px solid #DDE3EA; border-radius: 8px; color: #1A2B1A; font-family: inherit; }
    .zoom-row button:hover { background: #F0F7F2; }
    .stage { width: 100%; height: 480px; overflow: hidden; cursor: grab; position: relative; border-radius: 8px; border: 0.5px solid rgba(0,0,0,0.1); }
    .stage.drag { cursor: grabbing; }
    #world { position: absolute; transform-origin: 0 0; will-change: transform; }
    #plot { position: relative; }
    .alley { position: absolute; background: #d6c8b0; }
    .bld { position: absolute; display: flex; align-items: center; justify-content: center; cursor: pointer; border-radius: 2px; box-shadow: 3px 4px 0 rgba(0,0,0,0.22); }
    .bld:hover { z-index: 20; outline: 2.5px solid #f0c040; }
    .bld.nc { cursor: default; opacity: 0.62; }
    .bld-lbl { font-size: 9px; font-weight: 700; text-align: center; color: rgba(255,255,255,0.95); text-shadow: 0 1px 2px rgba(0,0,0,0.85); line-height: 1.3; letter-spacing: 0.04em; text-transform: uppercase; pointer-events: none; padding: 2px; overflow-wrap: break-word; hyphens: auto; max-width: 100%; }
    .tip { position: absolute; background: rgba(20,14,6,0.95); color: #fff; font-size: 12px; font-weight: 600; padding: 5px 11px; border-radius: 4px; white-space: nowrap; pointer-events: none; z-index: 50; border-left: 3px solid #f0c040; display: none; top: 0; left: 50%; transform: translate(-50%,-115%); }
    .bld:hover .tip { display: block; }
    .tree { position: absolute; border-radius: 50%; background: radial-gradient(circle at 38% 38%,#82c070,#3a6632); pointer-events: none; z-index: 5; }
    .grass { position: absolute; border-radius: 50%; background: radial-gradient(ellipse at 40% 40%,#82c070,#4a7a3a); z-index: 1; pointer-events: none; opacity: 0.7; }
    .sign { position: absolute; background: #2c1f0e; border: 2px solid #f0c040; border-radius: 3px; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; color: #f0c040; letter-spacing: 0.1em; z-index: 6; pointer-events: none; }
    .compass { position: absolute; bottom: 8px; right: 8px; width: 38px; height: 38px; border-radius: 50%; background: rgba(26,18,9,0.82); border: 1px solid #f0c040; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #f0c040; z-index: 10; pointer-events: none; }
    .legend-wrap { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 8px; padding: 4px 4px 2px; }
    .leg { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #556B55; }
    .leg-sq { width: 12px; height: 12px; border-radius: 2px; flex-shrink: 0; }
    .dir-label { position: absolute; left: 0; width: 52px; display: flex; align-items: center; justify-content: center; z-index: 8; pointer-events: none; }
    .dir-label span { display: block; transform: rotate(-90deg); white-space: nowrap; font-size: 9px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.8); background: rgba(0,0,0,0.35); padding: 2px 6px; border-radius: 2px; }
    @media (max-width: 640px) { .stage { height: 600px; } }
  </style>
</head>
<body data-page="sitemap">

  <!-- NAVIGATION -->
  <nav class="site-nav solid" aria-label="Main navigation">
    <div class="nav-inner">
      <a href="index.html" class="nav-logo" aria-label="Exit 473 home">
        <img src="images/logo.png" alt="Exit 473 logo" class="logo-img" onerror="this.style.display='none'">
        <span class="logo-text">
          <span class="logo-main">Exit 473</span>
          <span class="logo-sub">Grenada, West Indies</span>
        </span>
      </a>
      <ul class="nav-links" role="list">
        <li><a href="index.html">Home</a></li>
        <li><a href="directory.html">Directory</a></li>
        <li><a href="exit473_aerial_map.php" class="active">Site Map</a></li>
        <li><a href="events.html">Events</a></li>
        <li><a href="carnival.html">Carnival</a></li>
        <li><a href="about.html">About</a></li>
        <li><a href="contact.html">Contact</a></li>
        <li><a href="directory.html" class="btn btn-primary">Explore Businesses</a></li>
      </ul>
      <button class="nav-hamburger" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </nav>

  <main>
    <!-- PAGE HERO -->
    <div class="page-hero" aria-labelledby="map-heading">
      <div class="container">
        <span class="section-label">Interactive Map</span>
        <h1 id="map-heading">Site Map</h1>
        <p>Explore Exit 473 — click any building to visit that business.</p>
      </div>
    </div>

    <!-- MAP -->
    <section class="section-sm section-alt" aria-label="Aerial site map">
      <div class="container">
        <div class="map-wrap">
          <div class="map-header">
            <div>
              <div class="map-title">Exit 473 — Market Village</div>
              <div class="map-sub">Click any building to visit · Scroll to zoom · Drag to pan</div>
            </div>
            <div class="zoom-row">
              <button id="zi">+</button>
              <button id="zf">Fit</button>
              <button id="zo">−</button>
            </div>
          </div>
          <div class="stage" id="stage">
            <div id="world"><div id="plot"></div></div>
            <div class="compass">N↑</div>
          </div>
          <div class="legend-wrap">
            <div class="leg"><div class="leg-sq" style="background:#c0392b"></div>Food &amp; Grill</div>
            <div class="leg"><div class="leg-sq" style="background:#27ae60"></div>Juice / Organic</div>
            <div class="leg"><div class="leg-sq" style="background:#2980b9"></div>Retail / Boutique</div>
            <div class="leg"><div class="leg-sq" style="background:#8e44ad"></div>Beauty / Barber</div>
            <div class="leg"><div class="leg-sq" style="background:#e67e22"></div>Art / Culture</div>
            <div class="leg"><div class="leg-sq" style="background:#7f8c8d"></div>Facilities</div>
            <div class="leg"><div class="leg-sq" style="background:#bdc3c7;border:0.5px solid #999"></div>No page yet</div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- FOOTER -->
  <footer class="site-footer" aria-label="Site footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="index.html" class="nav-logo" aria-label="Home">
            <img src="images/logo.png" alt="Exit 473 logo" class="logo-img" onerror="this.style.display='none'">
            <span class="logo-text">
              <span class="logo-main">Exit 473</span>
              <span class="logo-sub">Food & Lifestyle Park · Grenada</span>
            </span>
          </a>
          <p>St. George's most vibrant destination — 10 businesses celebrating the food, culture, and spirit of beautiful Grenada, West Indies.</p>
          <div class="social-links" aria-label="Social media">
            <a href="#" class="social-link" aria-label="Facebook">f</a>
            <a href="#" class="social-link" aria-label="Instagram">📸</a>
            <a href="#" class="social-link" aria-label="WhatsApp">💬</a>
          </div>
        </div>
        <div class="footer-col">
          <h4>Navigate</h4>
          <ul>
            <li><a href="index.html">Home</a></li>
            <li><a href="directory.html">Business Directory</a></li>
            <li><a href="exit473_aerial_map.php">Site Map</a></li>
            <li><a href="events.html">Events Calendar</a></li>
            <li><a href="about.html">About the Park</a></li>
            <li><a href="contact.html">Contact &amp; Location</a></li>
            <li><a href="update-guide.html">Admin Guide</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h4>Categories</h4>
          <ul>
            <li><a href="directory.html?cat=restaurant">Restaurants</a></li>
            <li><a href="directory.html?cat=barbershop">Barbershop</a></li>
            <li><a href="directory.html?cat=cultural">Cultural Arts</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h4>Contact</h4>
          <div class="footer-contact-item"><span>📍</span><span>Maurice Bishop Highway, Grand Anse, St George's, Grenada, W.I.</span></div>
          <div class="footer-contact-item"><span>📞</span><span><a href="tel:+14734037377">+1 (473) 403-7377</a></span></div>
          <div class="footer-contact-item"><span>✉️</span><span><a href="mailto:exit473.gd@gmail.com">exit473.gd@gmail.com</a></span></div>
          <div class="footer-contact-item"><span>🕐</span><span>Mon–Sun · 7am – Midnight</span></div>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; <span id="footer-year">2024</span> Exit 473 · Grenada, West Indies. All rights reserved.</p>
        <p>Built with 🌿 and island pride.</p>
      </div>
    </div>
  </footer>

  <script>
  const BASE = 'https://exit473.com/bizsite/index.php?biz=';
  const ROAD_W = 52, PW = 1040, PH = 500, MX = ROAD_W;

  const buildings = <?= json_encode($buildings, JSON_UNESCAPED_UNICODE) ?>;

  const trees = [
    {x:MX+382,y:76,r:24},{x:MX+708,y:64,r:20},{x:MX+932,y:62,r:18},
    {x:MX+36,y:258,r:28},{x:MX+622,y:248,r:22}
  ];
  const grasses = [
    {x:MX+366,y:218,rx:64,ry:32},{x:MX+720,y:244,rx:42,ry:24},{x:MX+44,y:268,rx:56,ry:28}
  ];

  const plot = document.getElementById('plot');
  plot.style.cssText = `width:${PW}px;height:${PH}px;background:#bfae96;position:relative;`;

  const tex = document.createElement('div');
  tex.style.cssText = `position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,.05) 1px,transparent 1px);background-size:9px 9px;z-index:0;pointer-events:none;`;
  plot.appendChild(tex);

  const road = document.createElement('div');
  road.style.cssText = `position:absolute;left:0;top:0;bottom:0;width:${ROAD_W}px;background:#484840;z-index:3;`;
  const dash = document.createElement('div');
  dash.style.cssText = `position:absolute;left:50%;top:0;bottom:0;width:2px;transform:translateX(-50%);background:repeating-linear-gradient(180deg,#f0c040 0,#f0c040 16px,transparent 16px,transparent 32px);`;
  road.appendChild(dash);
  plot.appendChild(road);

  const topLabel = document.createElement('div');
  topLabel.className = 'dir-label';
  topLabel.style.cssText = `position:absolute;left:0;width:${ROAD_W}px;top:8px;display:flex;align-items:center;justify-content:center;z-index:8;pointer-events:none;`;
  const topSpan = document.createElement('span');
  topSpan.textContent = '▲ To St. George\'s';
  topSpan.style.cssText = `display:block;transform:rotate(-90deg);white-space:nowrap;font-size:9px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,0.9);`;
  topLabel.appendChild(topSpan);
  plot.appendChild(topLabel);

  const roadLabel = document.createElement('div');
  roadLabel.style.cssText = `position:absolute;left:0;top:0;bottom:0;width:${ROAD_W}px;display:flex;align-items:center;justify-content:center;z-index:4;pointer-events:none;`;
  const inner = document.createElement('span');
  inner.textContent = 'Maurice Bishop Highway';
  inner.style.cssText = `display:block;transform:rotate(-90deg);white-space:nowrap;font-size:11px;font-weight:700;letter-spacing:0.13em;text-transform:uppercase;color:#ffffff;text-shadow:0 1px 3px rgba(0,0,0,0.7);`;
  roadLabel.appendChild(inner);
  plot.appendChild(roadLabel);

  const botLabel = document.createElement('div');
  botLabel.style.cssText = `position:absolute;left:0;width:${ROAD_W}px;bottom:8px;display:flex;align-items:center;justify-content:center;z-index:8;pointer-events:none;`;
  const botSpan = document.createElement('span');
  botSpan.textContent = 'To Airport ▼';
  botSpan.style.cssText = `display:block;transform:rotate(-90deg);white-space:nowrap;font-size:9px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,0.9);`;
  botLabel.appendChild(botSpan);
  plot.appendChild(botLabel);

  const aH = document.createElement('div');
  aH.className = 'alley';
  aH.style.cssText = `left:${MX}px;right:0;top:166px;height:142px;z-index:1;`;
  plot.appendChild(aH);

  const sign = document.createElement('div');
  sign.className = 'sign';
  sign.textContent = '473';
  sign.style.cssText = `left:${MX+372}px;top:218px;width:76px;height:52px;`;
  plot.appendChild(sign);

  grasses.forEach(g => {
    const el = document.createElement('div');
    el.className = 'grass';
    el.style.cssText = `left:${g.x-g.rx}px;top:${g.y-g.ry}px;width:${g.rx*2}px;height:${g.ry*2}px;`;
    plot.appendChild(el);
  });

  buildings.forEach(b => {
    const el = document.createElement('div');
    el.className = 'bld' + (b.s ? '' : ' nc');
    el.style.cssText = `left:${b.x}px;top:${b.y}px;width:${b.w}px;height:${b.h}px;background:${b.c};`;
    const lbl = document.createElement('div');
    lbl.className = 'bld-lbl';
    lbl.textContent = b.n;
    el.appendChild(lbl);
    const tip = document.createElement('div');
    tip.className = 'tip';
    tip.textContent = (b.s ? '→ ' : '') + b.n;
    el.appendChild(tip);
    if (b.s) el.addEventListener('click', () => window.open(BASE + b.s, '_blank'));
    plot.appendChild(el);
  });

  trees.forEach(t => {
    const el = document.createElement('div');
    el.className = 'tree';
    el.style.cssText = `left:${t.x-t.r}px;top:${t.y-t.r}px;width:${t.r*2}px;height:${t.r*2}px;`;
    plot.appendChild(el);
  });

  const stage = document.getElementById('stage'), world = document.getElementById('world');
  let sc=1,tx=0,ty=0,drag=false,lx=0,ly=0;
  const mobile = () => window.innerWidth <= 640;
  function applyT(){
    if (mobile()) {
      // rotate(90deg): point (px,py) → screen (-py*sc+tx, px*sc+ty)
      world.style.transform=`translate(${tx}px,${ty}px) scale(${sc}) rotate(90deg)`;
    } else {
      world.style.transform=`translate(${tx}px,${ty}px) scale(${sc})`;
    }
  }
  function fit(){
    const sw=stage.offsetWidth, sh=stage.offsetHeight;
    if (mobile()) {
      // Rotated: PH is effective screen width, PW is effective screen height
      sc = Math.min(sw/PH, sh/PW) * 0.93;
      tx = (sw + PH*sc) / 2;   // shift right by PH*sc to bring rotated content into view
      ty = (sh - PW*sc) / 2;
    } else {
      sc = Math.min(sw/PW, sh/PH) * 0.93;
      tx = (sw - PW*sc) / 2;
      ty = (sh - PH*sc) / 2;
    }
    applyT();
  }
  setTimeout(fit, 80);
  window.addEventListener('resize', fit);

  stage.addEventListener('mousedown', e => {
    if(e.target.classList.contains('bld')||e.target.closest('.bld')) return;
    drag=true; lx=e.clientX; ly=e.clientY; stage.classList.add('drag');
  });
  window.addEventListener('mousemove', e => {
    if(!drag) return; tx+=e.clientX-lx; ty+=e.clientY-ly; lx=e.clientX; ly=e.clientY; applyT();
  });
  window.addEventListener('mouseup', () => { drag=false; stage.classList.remove('drag'); });
  stage.addEventListener('wheel', e => {
    e.preventDefault();
    const r=stage.getBoundingClientRect(), mx=e.clientX-r.left, my=e.clientY-r.top, os=sc;
    sc=Math.max(0.2,Math.min(5,sc*(e.deltaY<0?1.12:0.89)));
    tx=mx-(mx-tx)*(sc/os); ty=my-(my-ty)*(sc/os); applyT();
  },{passive:false});
  let pt=null,ps=1,pt0={x:0,y:0},ptx=0,pty=0;
  stage.addEventListener('touchstart',e=>{
    if(e.touches.length===1){pt=null;ptx=tx;pty=ty;pt0={x:e.touches[0].clientX,y:e.touches[0].clientY};}
    else if(e.touches.length===2){pt={x0:e.touches[0].clientX,y0:e.touches[0].clientY,x1:e.touches[1].clientX,y1:e.touches[1].clientY};ps=sc;}
  },{passive:true});
  stage.addEventListener('touchmove',e=>{
    if(e.touches.length===1&&!pt){tx=ptx+(e.touches[0].clientX-pt0.x);ty=pty+(e.touches[0].clientY-pt0.y);applyT();}
    else if(e.touches.length===2&&pt){
      const d0=Math.hypot(pt.x1-pt.x0,pt.y1-pt.y0),d1=Math.hypot(e.touches[1].clientX-e.touches[0].clientX,e.touches[1].clientY-e.touches[0].clientY);
      sc=Math.max(0.2,Math.min(5,ps*(d1/d0)));applyT();
    }
    e.preventDefault();
  },{passive:false});
  stage.addEventListener('touchend',()=>{pt=null;});
  document.getElementById('zi').onclick=()=>{sc=Math.min(5,sc*1.18);applyT();};
  document.getElementById('zo').onclick=()=>{sc=Math.max(0.2,sc/1.18);applyT();};
  document.getElementById('zf').onclick=fit;
  </script>
  <script src="js/app.js"></script>
</body>
</html>
