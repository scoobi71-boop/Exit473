/* ============================================================
   THE SPICE ISLE — Application JavaScript
   Edit SITE_CONFIG below to update park-wide info.
   Business data lives in /data/businesses.csv
   ============================================================ */

/* -------- SITE CONFIGURATION (edit here) -------- */
const SITE_CONFIG = {
  complexName:   'Exit 473',
  tagline:       "Grenada's Premier Business Destination",
  location:      "St. George's, Grenada, West Indies",
  phone:         '+1 (473) 403-7377',
  email:         'exit473.gd@gmail.com',
  address:       'Maurice Bishop Highway, Grand Anse, St George\'s, Grenada, W.I.',
  hours:         'Mon–Sun 7am – Midnight',
  mapLink:       'https://maps.google.com/?q=Exit+473+Grenada',
  social: {
    facebook:  '#',
    instagram: '#',
    whatsapp:  '#',
  },
  csvPath: 'data/businesses.csv',
};

/* -------- CSV PARSER -------- */
function parseCSV(text) {
  const rows = [];
  let inQuote = false, field = '', row = [];

  for (let i = 0; i < text.length; i++) {
    const ch = text[i], nx = text[i + 1];
    if (ch === '"') {
      if (inQuote && nx === '"') { field += '"'; i++; }
      else inQuote = !inQuote;
    } else if (ch === ',' && !inQuote) {
      row.push(field); field = '';
    } else if ((ch === '\n' || ch === '\r') && !inQuote) {
      if (ch === '\r' && nx === '\n') i++;
      row.push(field); field = '';
      if (row.some(f => f.trim())) rows.push(row);
      row = [];
    } else {
      field += ch;
    }
  }
  if (field || row.length) { row.push(field); if (row.some(f => f.trim())) rows.push(row); }
  return rows;
}

function csvToObjects(text) {
  const rows = parseCSV(text);
  if (rows.length < 2) return [];
  const headers = rows[0].map(h => h.trim());
  return rows.slice(1).map(row => {
    const obj = {};
    headers.forEach((h, i) => { obj[h] = (row[i] || '').trim(); });
    return obj;
  });
}

/* -------- DATA LOADER -------- */
let _cachedBusinesses = null;

async function loadBusinesses() {
  if (_cachedBusinesses) return _cachedBusinesses;

  // Primary: fetch CSV so admin edits are always reflected
  try {
    const res = await fetch(SITE_CONFIG.csvPath);
    if (res.ok) {
      const text = await res.text();
      const parsed = csvToObjects(text);
      if (parsed.length > 0) { _cachedBusinesses = parsed; return _cachedBusinesses; }
    }
  } catch (_) {}

  // Fallback: embedded JS data (works without a web server)
  if (window.EXIT473_CSV) {
    _cachedBusinesses = csvToObjects(window.EXIT473_CSV);
    return _cachedBusinesses;
  }

  return [];
}

/* -------- HELPERS -------- */
const CAT_ICONS = {
  restaurant: '🍽️', barbershop: '✂️', cultural: '🎭', bar: '🍹', cafe: '☕',
  'snack-bar': '🥪', 'tea-shop': '🍵', shop: '🛍️', retail: '🛒',
  'local-craft': '🎨', service: '🔧', wellness: '🌿', entertainment: '🎉', other: '🏪'
};
function getCategoryLabel(cat) {
  const labels = { restaurant: 'Restaurant', barbershop: 'Barbershop', cultural: 'Cultural Arts' };
  return labels[cat] || cat.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function tagsArray(biz) {
  return (biz.tags || '').split('|').map(t => t.trim()).filter(Boolean);
}

function isFeatured(biz) {
  return biz.featured === 'true';
}

function isActive(biz) {
  return !biz.active || biz.active === 'true';
}

function hasWebsite(biz) {
  return biz.website && biz.website !== '#' && biz.website.startsWith('http');
}

function makeWebsiteBtn(biz, cls = '') {
  if (hasWebsite(biz)) {
    return `<a href="${esc(biz.website)}" target="_blank" rel="noopener" class="btn btn-primary btn-sm ${cls}">🔗 Visit Website</a>`;
  }
  return `<span class="btn btn-sm" style="opacity:.45;cursor:default;background:var(--gray-200);color:var(--gray-700)">Website Coming Soon</span>`;
}

function esc(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* -------- BUSINESS CARD HTML -------- */
function buildCard(biz, linkTarget = 'business.html') {
  const tags = tagsArray(biz).slice(0, 4);
  const tagHTML = tags.map(t => `<span class="tag">${esc(t)}</span>`).join('');
  const featuredBadge = isFeatured(biz) ? `<span class="featured-badge">⭐ Featured</span>` : '';
  const hoursLines = (biz.hours || '').split('|').map(h => `<span>${esc(h.trim())}</span>`).join('<br>');

  return `
    <div class="business-card" style="--accent:${esc(biz.accent_color || 'var(--teal)')}">
      <div class="card-accent-bar"></div>
      <div class="card-body">
        <div class="card-header">
          <div class="card-icon">${biz.icon || '🏪'}</div>
          <div class="card-meta-top">
            <div class="card-category">${getCategoryLabel(biz.category)}</div>
            <h3 class="card-name">${esc(biz.name)}${featuredBadge}</h3>
          </div>
        </div>
        <p class="card-desc">${esc(biz.short_desc)}</p>
        <div class="card-tags">${tagHTML}</div>
        <div class="card-hours">🕐 <div>${hoursLines}</div></div>
        <div class="card-phone">📞 <a href="tel:${esc(biz.phone)}">${esc(biz.phone)}</a></div>
        <div class="card-actions">
          <a href="${linkTarget}?id=${esc(biz.id)}" class="btn btn-outline btn-sm">Details</a>
          ${makeWebsiteBtn(biz)}
        </div>
      </div>
    </div>`;
}

/* -------- INDEX PAGE -------- */
function initIndexPage(businesses) {
  const active   = businesses.filter(isActive);
  const featured = active.filter(isFeatured).sort((a, b) =>
    (parseInt(a.sort_order, 10) || 999) - (parseInt(b.sort_order, 10) || 999));
  const container = document.getElementById('featured-businesses');
  if (container) {
    container.innerHTML = featured.map(b => buildCard(b)).join('');
  }

  // Counts by category (active businesses only)
  const restCount = active.filter(b => b.category === 'restaurant').length;
  const barbCount = active.filter(b => b.category === 'barbershop').length;
  const cultCount = active.filter(b => b.category === 'cultural').length;
  const allCount  = active.length;

  // Hero stats
  setEl('hero-stat-restaurants', restCount);
  setEl('hero-stat-businesses',  allCount);

  // Category card count badges
  setEl('cat-count-restaurant', restCount + ' restaurant' + (restCount !== 1 ? 's' : '') + ' →');
  setEl('cat-count-barbershop', barbCount + ' barbershop' + (barbCount !== 1 ? 's' : '') + ' →');
  setEl('cat-count-cultural',   cultCount + ' cultural hub' + (cultCount !== 1 ? 's' : '') + ' →');

  // About-teaser highlight & footer count
  setEl('highlight-businesses', allCount + ' curated local businesses');
  setEl('footer-biz-count',     allCount);

  // Floating particles in hero
  spawnParticles();
}

/* -------- ABOUT PAGE -------- */
function initAboutPage(businesses) {
  const restCount = businesses.filter(b => b.category === 'restaurant').length;
  const cultCount = businesses.filter(b => b.category === 'cultural').length;
  const allCount  = businesses.length;

  setEl('about-stat-businesses', allCount);
  setEl('about-stat-restaurants', restCount);
  setEl('about-stat-cultural',    cultCount);
  setEl('footer-biz-count',       allCount);
}

function setEl(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}

function spawnParticles() {
  const container = document.querySelector('.hero-particles');
  if (!container) return;
  const icons = ['🌿','🌺','🥭','🥥','🌴','🪸','🦎','🌸','🍹','✨','🌊','🪴'];
  for (let i = 0; i < 14; i++) {
    const p = document.createElement('span');
    p.className = 'particle';
    p.textContent = icons[Math.floor(Math.random() * icons.length)];
    p.style.left = Math.random() * 100 + '%';
    p.style.animationDuration = (12 + Math.random() * 18) + 's';
    p.style.animationDelay = (Math.random() * 20) + 's';
    p.style.fontSize = (.7 + Math.random() * .8) + 'rem';
    container.appendChild(p);
  }
}

/* -------- DIRECTORY PAGE -------- */
function initDirectoryPage(businesses) {
  businesses = businesses.filter(isActive);
  const searchInput  = document.getElementById('directory-search');
  const catFilter    = document.getElementById('category-filter');
  const sortFilter   = document.getElementById('sort-filter');
  const resultsCount = document.getElementById('results-count');
  const grid         = document.getElementById('business-grid');
  const noResults    = document.getElementById('no-results');

  // Populate dropdown with only the categories present in the data
  if (catFilter) {
    const used = [...new Set(businesses.map(b => b.category).filter(Boolean))].sort();
    used.forEach(cat => {
      const opt = document.createElement('option');
      opt.value = cat;
      opt.textContent = (CAT_ICONS[cat] || '🏪') + ' ' + getCategoryLabel(cat);
      catFilter.appendChild(opt);
    });
  }

  // Apply ?cat= URL param (e.g., from category card links on homepage)
  const urlCat = new URLSearchParams(location.search).get('cat');
  if (urlCat && catFilter) catFilter.value = urlCat;

  function render() {
    const q    = (searchInput?.value || '').toLowerCase();
    const cat  = catFilter?.value || 'all';
    const sort = sortFilter?.value || 'default';

    let filtered = businesses.filter(b => {
      const matchCat  = cat === 'all' || b.category === cat;
      const searchStr = [b.name, b.short_desc, b.description, b.tags].join(' ').toLowerCase();
      const matchQ    = !q || searchStr.includes(q);
      return matchCat && matchQ;
    });

    if (sort === 'name-az') filtered.sort((a,b) => a.name.localeCompare(b.name));
    else if (sort === 'featured') filtered.sort((a,b) => (isFeatured(b)?1:0) - (isFeatured(a)?1:0));
    else filtered.sort((a,b) => (parseInt(a.sort_order,10)||999) - (parseInt(b.sort_order,10)||999));

    if (grid) grid.innerHTML = filtered.length ? filtered.map(b => buildCard(b)).join('') : '';
    if (noResults) noResults.hidden = filtered.length > 0;
    if (resultsCount) resultsCount.textContent = `${filtered.length} business${filtered.length !== 1 ? 'es' : ''}`;
  }

  searchInput?.addEventListener('input', render);
  catFilter?.addEventListener('change', render);
  sortFilter?.addEventListener('change', render);
  render();
}

/* -------- BUSINESS DETAIL PAGE -------- */
function initBusinessPage(businesses) {
  const params = new URLSearchParams(window.location.search);
  const id     = params.get('id');
  const biz    = businesses.find(b => b.id === id);
  const main   = document.getElementById('biz-detail');

  if (!biz) {
    if (main) main.innerHTML = `<div class="container" style="padding:6rem 1.5rem;text-align:center">
      <div style="font-size:3rem;margin-bottom:1rem">😕</div>
      <h2>Business Not Found</h2>
      <p style="color:var(--text-light);margin:1rem 0 2rem">We couldn't find that listing. It may have moved or been removed.</p>
      <a href="directory.html" class="btn btn-teal">← Back to Directory</a>
    </div>`;
    return;
  }

  // Page meta
  document.title = `${biz.name} — ${SITE_CONFIG.complexName}`;
  const bizUrl = `https://exit473.com/business.html?id=${encodeURIComponent(biz.id)}`;
  document.querySelector('link[rel="canonical"]')?.setAttribute('href', bizUrl);
  document.querySelector('meta[property="og:url"]')?.setAttribute('content', bizUrl);
  document.querySelector('meta[property="og:title"]')?.setAttribute('content', `${biz.name} — Exit 473, Grenada`);
  document.querySelector('meta[property="og:description"]')?.setAttribute('content', biz.short_desc);
  document.querySelector('meta[name="twitter:title"]')?.setAttribute('content', `${biz.name} — Exit 473, Grenada`);
  document.querySelector('meta[name="twitter:description"]')?.setAttribute('content', biz.short_desc);
  const ldJson = document.createElement('script');
  ldJson.type = 'application/ld+json';
  ldJson.text = JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'LocalBusiness',
    'name': biz.name,
    'description': biz.description,
    'url': bizUrl,
    'telephone': biz.phone || undefined,
    'email': biz.email || undefined,
    'address': {
      '@type': 'PostalAddress',
      'streetAddress': biz.address || 'Maurice Bishop Highway, Grand Anse',
      'addressLocality': "St. George's",
      'addressCountry': 'GD'
    },
    'openingHours': biz.hours || undefined,
    'parentOrganization': { '@type': 'Organization', 'name': 'Exit 473', 'url': 'https://exit473.com' }
  });
  document.head.appendChild(ldJson);

  const heroEl = document.getElementById('biz-hero');
  if (heroEl) {
    heroEl.style.background = `linear-gradient(155deg, ${biz.accent_color}ee 0%, ${biz.accent_color}99 50%, #1A0505 100%)`;
  }

  const tags = tagsArray(biz);
  const tagHTML = tags.map(t => `<span class="biz-tag">${esc(t)}</span>`).join('');
  const hoursLines = (biz.hours || '').split('|').map(h => `${esc(h.trim())}`).join('<br>');
  const websiteRow = hasWebsite(biz)
    ? `<a href="${esc(biz.website)}" target="_blank" rel="noopener">${esc(biz.website)}</a>`
    : '<em style="color:var(--text-light)">Coming soon</em>';

  if (main) {
    main.innerHTML = `
      <div id="biz-hero" class="biz-hero" style="background:linear-gradient(155deg,${biz.accent_color}ee 0%,${biz.accent_color}88 50%,#0D2B2B 100%)">
        <div class="container">
          <div style="display:grid;grid-template-columns:1fr auto;gap:2rem;align-items:center">
            <div>
              <a href="directory.html" class="biz-hero-back">← Back to Directory</a>
              <div class="biz-hero-tag">${getCategoryLabel(biz.category)}</div>
              <h1>${esc(biz.name)}</h1>
              <p class="biz-hero-sub">${esc(biz.short_desc)}</p>
              <div class="biz-hero-actions">
                ${makeWebsiteBtn(biz, 'btn-lg')}
                <a href="tel:${esc(biz.phone)}" class="btn btn-outline-white btn-lg">📞 Call Now</a>
              </div>
            </div>
            <div class="biz-hero-icon">${biz.icon || '🏪'}</div>
          </div>
        </div>
      </div>

      <div class="biz-content">
        <div class="container">
          <div class="biz-layout">
            <div class="biz-main-content">
              <div class="biz-section">
                <div class="biz-section-title">About</div>
                <p class="biz-description">${esc(biz.description)}</p>
              </div>
              <div class="biz-section">
                <div class="biz-section-title">Tags &amp; Specialties</div>
                <div class="biz-tags-list">${tagHTML}</div>
              </div>
              <div class="biz-section">
                <div class="biz-section-title">Location</div>
                <p style="color:var(--text-light)">${esc(biz.address)}</p>
                <a href="${SITE_CONFIG.mapLink}" target="_blank" class="btn btn-outline btn-sm" style="margin-top:.85rem">📍 View on Map</a>
              </div>
            </div>

            <div class="biz-sidebar">
              <div class="info-card">
                <div class="info-card-header">Business Information</div>
                <div class="info-row">
                  <span class="info-icon">🕐</span>
                  <div><div class="info-label">Hours</div><div class="info-value">${hoursLines}</div></div>
                </div>
                <div class="info-row">
                  <span class="info-icon">📞</span>
                  <div><div class="info-label">Phone</div><div class="info-value"><a href="tel:${esc(biz.phone)}">${esc(biz.phone)}</a></div></div>
                </div>
                <div class="info-row">
                  <span class="info-icon">✉️</span>
                  <div><div class="info-label">Email</div><div class="info-value"><a href="mailto:${esc(biz.email)}">${esc(biz.email)}</a></div></div>
                </div>
                <div class="info-row">
                  <span class="info-icon">📍</span>
                  <div><div class="info-label">Address</div><div class="info-value">${esc(biz.address)}</div></div>
                </div>
              </div>

              <div class="website-cta">
                <p>Visit the official website for menus, bookings & more</p>
                ${makeWebsiteBtn(biz, 'btn-lg')}
              </div>
            </div>
          </div>
        </div>
      </div>`;
  }
}

/* -------- NAVIGATION -------- */
function initNav() {
  const hamburger = document.querySelector('.nav-hamburger');
  const navLinks  = document.querySelector('.nav-links');
  const nav       = document.querySelector('.site-nav');

  // Hamburger toggle
  hamburger?.addEventListener('click', () => {
    navLinks?.classList.toggle('nav-open');
    hamburger.classList.toggle('open');
  });

  // Close menu when a nav link is clicked
  navLinks?.addEventListener('click', e => {
    if (e.target.tagName === 'A') {
      navLinks.classList.remove('nav-open');
      hamburger?.classList.remove('open');
    }
  });

  // Transparent → opaque on scroll (only on pages with transparent nav)
  if (nav?.classList.contains('transparent')) {
    const onScroll = () => {
      nav.classList.toggle('scrolled', window.scrollY > 60);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  // Highlight active nav link
  const path = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links a[href]').forEach(a => {
    const href = a.getAttribute('href').split('?')[0];
    if (href === path || (path === '' && href === 'index.html')) a.classList.add('active');
  });
}

/* -------- FOOTER -------- */
function initFooter() {
  // Inject site config values into footer
  const year = document.getElementById('footer-year');
  if (year) year.textContent = new Date().getFullYear();
}

/* ============================================================
   EVENTS CALENDAR
   ============================================================ */
const MONTHS_LONG  = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const MONTHS_SHORT = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
const DAYS_SHORT   = ['SUN','MON','TUE','WED','THU','FRI','SAT'];

const CAT_LABELS = {
  music: 'Music', dance: 'Dance', food: 'Food & Drink',
  art: 'Art & Culture', workshop: 'Workshop', general: 'General'
};

let _cachedEvents = null;

async function loadEvents() {
  if (_cachedEvents) return _cachedEvents;

  // Primary: fetch data/events.csv from the server
  try {
    const res = await fetch('data/events.csv');
    if (res.ok) {
      const text = await res.text();
      const parsed = csvToObjects(text);
      if (parsed.length > 0) {
        _cachedEvents = parsed;
        return _cachedEvents;
      }
    }
  } catch (_) {}

  // Fallback: data/events.js sets window.EXIT473_EVENTS_CSV
  if (window.EXIT473_EVENTS_CSV) {
    _cachedEvents = csvToObjects(window.EXIT473_EVENTS_CSV);
    return _cachedEvents;
  }

  return [];
}

function parseTimeTo24h(str) {
  if (!str) return null;
  const m = str.trim().match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
  if (!m) return null;
  let h = parseInt(m[1]), mn = parseInt(m[2]);
  const ap = m[3].toUpperCase();
  if (ap === 'PM' && h !== 12) h += 12;
  if (ap === 'AM' && h === 12) h = 0;
  return `${String(h).padStart(2,'0')}${String(mn).padStart(2,'0')}00`;
}

function buildCalendarUrl(ev, rrule = null) {
  const d = ev.date.replace(/-/g, '');
  const st = parseTimeTo24h(ev.time);
  const et = parseTimeTo24h(ev.end_time);
  const dates = st ? `${d}T${st}/${d}T${et || st}` : d;
  const p = new URLSearchParams({
    action: 'TEMPLATE',
    text: ev.title,
    dates,
    details: ev.description,
    location: `${ev.venue}, Exit 473, St. George's, Grenada, W.I.`,
  });
  if (rrule) p.append('recur', rrule);
  return `https://calendar.google.com/calendar/render?${p}`;
}

function buildEventCard(ev, isPast = false) {
  const d   = new Date(ev.date + 'T12:00:00');
  const mon = MONTHS_SHORT[d.getMonth()];
  const day = d.getDate();
  const dow = DAYS_SHORT[d.getDay()];
  const isFree    = ev.free === 'true';
  const timeRange = ev.time ? (ev.end_time ? `${ev.time} – ${ev.end_time}` : ev.time) : 'Time TBA';
  const rec       = (ev.recurring || '').toLowerCase().trim();
  const isRecurring = rec === 'weekly' || rec === 'monthly';
  const recLabel  = rec === 'weekly' ? 'Repeats weekly' : rec === 'monthly' ? 'Repeats monthly' : ev.recurring;
  const rrule     = rec === 'weekly' ? 'RRULE:FREQ=WEEKLY' : rec === 'monthly' ? 'RRULE:FREQ=MONTHLY' : null;
  const calUrl        = buildCalendarUrl(ev);
  const calSeriesUrl  = rrule ? buildCalendarUrl(ev, rrule) : null;
  const moreBtn   = (ev.website && ev.website !== '#')
    ? `<a href="${esc(ev.website)}" target="_blank" rel="noopener" class="btn btn-primary btn-sm">More Info →</a>` : '';

  // Photo thumbnail strip
  const imgPaths = (ev.images || '').split('|').map(s => s.trim()).filter(Boolean);
  const photoStrip = imgPaths.length > 0 ? `
    <button class="event-photo-btn" data-images="${esc(imgPaths.join('|'))}" data-event-id="${esc(ev.id)}" data-event-title="${esc(ev.title)}" aria-label="View ${imgPaths.length} photo${imgPaths.length !== 1 ? 's' : ''}">
      <img src="${esc(imgPaths[0])}" alt="${esc(ev.title)}" class="event-photo-thumb" loading="lazy">
      <span class="event-photo-overlay">
        <span class="event-photo-icon">📷</span>
        <span class="event-photo-count">${imgPaths.length > 1 ? imgPaths.length + ' photos' : 'View photo'}</span>
      </span>
    </button>` : '';

  return `
    <div class="event-card" id="event-${esc(ev.id)}">
      <div class="event-date-block">
        <div class="event-month">${mon}</div>
        <div class="event-day">${day}</div>
        <div class="event-dow">${dow}</div>
      </div>
      ${photoStrip}
      <div class="event-body">
        <div class="event-badges">
          <span class="event-cat-badge cat-${esc(ev.category)}">${CAT_LABELS[ev.category] || esc(ev.category)}</span>
          ${isFree ? '<span class="event-free-badge">Free Entry</span>' : '<span class="event-paid-badge">Ticketed</span>'}
          ${isRecurring ? `<span class="event-recurring-badge">🔄 ${esc(recLabel)}</span>` : ''}
        </div>
        <h3 class="event-title">${esc(ev.title)}</h3>
        <div class="event-meta">
          <span>🕐 ${esc(timeRange)}</span>
          <span>📍 ${esc(ev.venue)}</span>
        </div>
        <p class="event-desc">${esc(ev.description)}</p>
        <div class="event-actions">
          <a href="${calUrl}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">+ This date</a>
          ${calSeriesUrl ? `<a href="${esc(calSeriesUrl)}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">+ Add ${rec} series</a>` : ''}
          ${moreBtn}
        </div>
      </div>
    </div>`;
}

function expandRecurringEvents(events) {
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const horizon = new Date(today);
  horizon.setMonth(horizon.getMonth() + 6); // generate up to 6 months ahead
  const expanded = [];

  events.forEach(ev => {
    const rec = (ev.recurring || '').toLowerCase().trim();
    if (rec !== 'weekly' && rec !== 'monthly') { expanded.push(ev); return; }

    const maxOccurrences = parseInt(ev.recur_count, 10) || 0;
    let cur = new Date(ev.date + 'T12:00:00');
    let i = 0;
    while (cur <= horizon && (maxOccurrences === 0 || i < maxOccurrences)) {
      expanded.push({ ...ev, date: cur.toISOString().slice(0, 10), id: `${ev.id}${i > 0 ? '-r' + i : ''}` });
      i++;
      cur = new Date(cur);
      if (rec === 'weekly')  cur.setDate(cur.getDate() + 7);
      if (rec === 'monthly') cur.setMonth(cur.getMonth() + 1);
    }
  });

  return expanded;
}

/* ---- Event lightbox ---- */
function createEventsLightbox() {
  const lb = document.createElement('div');
  lb.id = 'ev-lightbox';
  lb.className = 'ev-lb';
  lb.setAttribute('role', 'dialog');
  lb.setAttribute('aria-modal', 'true');
  lb.innerHTML = `
    <div class="ev-lb-backdrop"></div>
    <button class="ev-lb-btn ev-lb-close" aria-label="Close">✕</button>
    <button class="ev-lb-btn ev-lb-prev" aria-label="Previous">&#8249;</button>
    <button class="ev-lb-btn ev-lb-next" aria-label="Next">&#8250;</button>
    <div class="ev-lb-inner">
      <img class="ev-lb-img" src="" alt="">
      <div class="ev-lb-footer">
        <div class="ev-lb-counter"></div>
        <button class="ev-lb-event-link" aria-label="Go to event"></button>
      </div>
    </div>`;
  document.body.appendChild(lb);

  let paths = [], idx = 0, eventTitle = '', eventId = '';

  function open(imgPaths, startIdx, title, id) {
    paths = imgPaths; idx = startIdx; eventTitle = title; eventId = id;
    render();
    lb.classList.add('active');
    document.body.style.overflow = 'hidden';
    lb.querySelector('.ev-lb-img').focus();
  }
  function close() {
    lb.classList.remove('active');
    document.body.style.overflow = '';
  }
  function move(dir) {
    idx = (idx + dir + paths.length) % paths.length;
    render();
  }
  function render() {
    lb.querySelector('.ev-lb-img').src = paths[idx];
    lb.querySelector('.ev-lb-counter').textContent =
      paths.length > 1 ? `${idx + 1} / ${paths.length}` : '';
    lb.querySelector('.ev-lb-prev').style.display = paths.length > 1 ? '' : 'none';
    lb.querySelector('.ev-lb-next').style.display = paths.length > 1 ? '' : 'none';
    lb.querySelector('.ev-lb-event-link').textContent = eventTitle ? `↓ ${eventTitle}` : '';
  }

  lb.querySelector('.ev-lb-backdrop').addEventListener('click', close);
  lb.querySelector('.ev-lb-close').addEventListener('click', close);
  lb.querySelector('.ev-lb-prev').addEventListener('click', e => { e.stopPropagation(); move(-1); });
  lb.querySelector('.ev-lb-next').addEventListener('click', e => { e.stopPropagation(); move(1); });
  lb.querySelector('.ev-lb-event-link').addEventListener('click', () => {
    close();
    const card = document.getElementById('event-' + eventId);
    if (card) {
      setTimeout(() => {
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.classList.add('ev-card-highlight');
        setTimeout(() => card.classList.remove('ev-card-highlight'), 1800);
      }, 150);
    }
  });
  document.addEventListener('keydown', e => {
    if (!lb.classList.contains('active')) return;
    if (e.key === 'Escape')     close();
    if (e.key === 'ArrowLeft')  move(-1);
    if (e.key === 'ArrowRight') move(1);
  });

  return { open };
}

function initEventsPage(events) {
  events = expandRecurringEvents(events);
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const getDate = ev => new Date(ev.date + 'T12:00:00');
  const lightbox = createEventsLightbox();

  const container     = document.getElementById('events-container');
  const pastSection   = document.getElementById('past-events-section');
  const pastContainer = document.getElementById('past-events-container');
  const showPastBtn   = document.getElementById('past-toggle-btn');
  const countEl       = document.getElementById('events-upcoming-count');
  const catBtns       = document.querySelectorAll('.cat-btn');
  let showPast = false;
  let activeCat = 'all';

  function filterAndRender() {
    const upcoming = events
      .filter(ev => getDate(ev) >= today && (activeCat === 'all' || ev.category === activeCat))
      .sort((a, b) => a.date.localeCompare(b.date));
    const past = events
      .filter(ev => getDate(ev) < today && (activeCat === 'all' || ev.category === activeCat))
      .sort((a, b) => b.date.localeCompare(a.date));

    if (countEl) countEl.textContent = upcoming.length;

    if (container) {
      if (upcoming.length === 0) {
        const noData = events.length === 0;
        container.innerHTML = `<div class="no-events-placeholder"><div class="icon">📅</div><h3>${noData ? 'Events data not loaded' : 'No upcoming events'}</h3><p>${noData ? 'Upload <strong>data/events.csv</strong> to your web server to display events.' : 'Check back soon — new events are added regularly.'}</p></div>`;
      } else {
        const grouped = {};
        upcoming.forEach(ev => {
          const d = getDate(ev);
          const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
          if (!grouped[key]) grouped[key] = { label: `${MONTHS_LONG[d.getMonth()]} ${d.getFullYear()}`, events: [] };
          grouped[key].events.push(ev);
        });
        container.innerHTML = Object.values(grouped).map(g => `
          <div class="events-month-group">
            <div class="month-header">
              <h2 class="month-title">${g.label}</h2>
              <span class="month-count">${g.events.length} event${g.events.length !== 1 ? 's' : ''}</span>
            </div>
            <div class="events-list">${g.events.map(e => buildEventCard(e)).join('')}</div>
          </div>`).join('');
      }
    }

    if (pastContainer && showPast) {
      pastContainer.innerHTML = past.length
        ? `<div class="events-list">${past.map(e => buildEventCard(e, true)).join('')}</div>`
        : `<p style="color:var(--text-light)">No past events to show.</p>`;
    }
  }

  // Delegated click handler for photo thumbnails
  document.addEventListener('click', e => {
    const btn = e.target.closest('.event-photo-btn');
    if (!btn) return;
    const paths = btn.dataset.images.split('|').filter(Boolean);
    lightbox.open(paths, 0, btn.dataset.eventTitle || '', btn.dataset.eventId || '');
  });

  catBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      activeCat = btn.dataset.cat || 'all';
      catBtns.forEach(b => b.classList.toggle('active', b === btn));
      filterAndRender();
    });
  });

  showPastBtn?.addEventListener('click', () => {
    showPast = !showPast;
    if (pastSection) pastSection.hidden = !showPast;
    showPastBtn.textContent = showPast ? '▲ Hide Past Events' : '▼ Show Past Events';
    filterAndRender();
  });

  filterAndRender();
}

/* ============================================================
   SPOTLIGHT CAROUSEL (sponsor slots / business spotlight / event promo)
   ============================================================ */
let _cachedSpotlight = null;

async function loadSpotlight() {
  if (_cachedSpotlight) return _cachedSpotlight;

  try {
    const res = await fetch('data/spotlight.csv');
    if (res.ok) {
      const text = await res.text();
      const parsed = csvToObjects(text);
      if (parsed.length > 0) { _cachedSpotlight = parsed; return _cachedSpotlight; }
    }
  } catch (_) {}

  if (window.EXIT473_SPOTLIGHT_CSV) {
    _cachedSpotlight = csvToObjects(window.EXIT473_SPOTLIGHT_CSV);
    return _cachedSpotlight;
  }

  return [];
}

const SPOTLIGHT_BADGE_DEFAULT = { sponsor: 'Sponsored', business: 'Spotlight', event: 'Upcoming Event' };
const SPOTLIGHT_ICON_DEFAULT  = { sponsor: '🤝', business: '⭐', event: '📅' };
const SPOTLIGHT_LINK_DEFAULT  = { sponsor: 'Learn More →', business: 'View Business →', event: 'See Event Details →' };

function isSpotlightActive(item) {
  if (item.active === 'false') return false;
  const today = new Date(); today.setHours(0, 0, 0, 0);
  if (item.start_date) {
    const sd = new Date(item.start_date + 'T00:00:00');
    if (today < sd) return false;
  }
  if (item.end_date) {
    const ed = new Date(item.end_date + 'T00:00:00');
    if (today > ed) return false;
  }
  return true;
}

function buildSpotlightSlide(item) {
  const type    = item.type || 'sponsor';
  const badge   = item.badge_label || SPOTLIGHT_BADGE_DEFAULT[type] || 'Spotlight';
  const hasLink = item.link_url && item.link_url !== '#';
  const linkTxt = item.link_label || SPOTLIGHT_LINK_DEFAULT[type] || 'Learn More →';
  const visual  = item.image
    ? `<div class="spotlight-visual" style="background-image:url('${esc(item.image)}')"></div>`
    : `<div class="spotlight-visual spotlight-visual-icon spotlight-type-${esc(type)}"><span>${SPOTLIGHT_ICON_DEFAULT[type] || '✨'}</span></div>`;

  return `
    <div class="spotlight-slide">
      ${visual}
      <div class="spotlight-content">
        <span class="spotlight-badge spotlight-badge-${esc(type)}">${esc(badge)}</span>
        <h3 class="spotlight-title">${esc(item.title)}</h3>
        ${item.subtitle ? `<div class="spotlight-subtitle">${esc(item.subtitle)}</div>` : ''}
        <p class="spotlight-desc">${esc(item.description)}</p>
        ${hasLink ? `<a href="${esc(item.link_url)}" class="btn btn-primary btn-sm">${esc(linkTxt)}</a>` : ''}
      </div>
    </div>`;
}

function initSpotlightCarousel(items) {
  const section = document.getElementById('spotlight-section');
  const track   = document.getElementById('spotlight-track');
  const dots    = document.getElementById('spotlight-dots');
  if (!section || !track) return;

  const active = items.filter(isSpotlightActive)
    .sort((a, b) => (parseInt(a.sort_order, 10) || 999) - (parseInt(b.sort_order, 10) || 999));

  if (active.length === 0) { section.hidden = true; return; }
  section.hidden = false;

  track.innerHTML = active.map(buildSpotlightSlide).join('');

  const prevBtn = section.querySelector('.spotlight-prev');
  const nextBtn = section.querySelector('.spotlight-next');

  if (active.length <= 1) {
    if (dots) dots.innerHTML = '';
    if (prevBtn) prevBtn.hidden = true;
    if (nextBtn) nextBtn.hidden = true;
    return;
  }

  if (dots) {
    dots.innerHTML = active.map((_, i) =>
      `<button class="spotlight-dot${i === 0 ? ' active' : ''}" data-i="${i}" aria-label="Go to slide ${i + 1}"></button>`).join('');
  }

  let current = 0;
  let timer   = null;

  function render() {
    track.style.transform = `translateX(-${current * 100}%)`;
    dots?.querySelectorAll('.spotlight-dot').forEach((d, i) => d.classList.toggle('active', i === current));
  }
  function goTo(i) {
    current = (i + active.length) % active.length;
    render();
  }
  function startAutoplay() {
    stopAutoplay();
    timer = setInterval(() => goTo(current + 1), 6000);
  }
  function stopAutoplay() {
    if (timer) clearInterval(timer);
  }

  prevBtn?.addEventListener('click', () => { goTo(current - 1); startAutoplay(); });
  nextBtn?.addEventListener('click', () => { goTo(current + 1); startAutoplay(); });
  dots?.addEventListener('click', e => {
    const dot = e.target.closest('.spotlight-dot');
    if (!dot) return;
    goTo(parseInt(dot.dataset.i, 10));
    startAutoplay();
  });
  section.addEventListener('mouseenter', stopAutoplay);
  section.addEventListener('mouseleave', startAutoplay);

  render();
  startAutoplay();
}

/* -------- MAIN INIT -------- */
document.addEventListener('DOMContentLoaded', async () => {
  initNav();
  initFooter();

  const page = document.body.dataset.page;
  if (!page || page === 'contact' || page === 'guide') return;

  if (page === 'events') {
    const events = await loadEvents();
    initEventsPage(events);
    return;
  }

  const businesses = await loadBusinesses();

  if (businesses.length === 0) {
    const errMsg = document.createElement('div');
    errMsg.className = 'error-message container';
    errMsg.style.margin = '2rem auto';
    errMsg.innerHTML = '<strong>⚠️ Note:</strong> Business data could not be loaded. Please open this site from a web server rather than directly as a file. See the <a href="update-guide.html">Update Guide</a> for hosting tips.';
    document.querySelector('main')?.prepend(errMsg);
  }

  switch (page) {
    case 'index':     initIndexPage(businesses);     break;
    case 'directory': initDirectoryPage(businesses); break;
    case 'business':  initBusinessPage(businesses);  break;
    case 'about':     initAboutPage(businesses);     break;
  }

  if (page === 'index' || page === 'directory') {
    const spotlight = await loadSpotlight();
    initSpotlightCarousel(spotlight);
  }
});
