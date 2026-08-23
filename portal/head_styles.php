<link rel="stylesheet" href="../css/styles.css?v=3">
<style>
body[data-portal] { background: var(--sand); min-height: 100vh; }
.portal-nav { background: var(--red); padding: 0 1.25rem; display: flex; align-items: center; gap: 1.5rem; height: 56px; }
.portal-nav .brand { color: #fff; font-weight: 700; font-size: 1.05rem; font-family: var(--font-body); }
.portal-nav .brand span { display: block; font-size: .72rem; font-weight: 400; letter-spacing: .06em; text-transform: uppercase; color: var(--gold-light); }
.portal-nav-links { display: flex; gap: .25rem; flex: 1; }
.portal-nav-links a { color: rgba(255,255,255,.85); font-size: .88rem; padding: .4rem .75rem; border-radius: 6px; text-decoration: none; }
.portal-nav-links a:hover, .portal-nav-links a.active { background: rgba(0,0,0,.18); color: #fff; }
.portal-nav-right a { color: rgba(255,255,255,.85); font-size: .82rem; text-decoration: none; }
.portal-nav-right a:hover { color: var(--gold-light); }
.portal-wrap { max-width: 560px; margin: 0 auto; padding: 3rem 1.25rem; }
.portal-wrap.wide { max-width: 900px; }
.portal-card { background: #fff; border-radius: 14px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,.08); border: 1px solid var(--gray-300); }
.portal-card h1 { font-family: var(--font-body); font-size: 1.4rem; color: var(--text); margin-bottom: .35rem; }
.portal-card .portal-sub { color: var(--text-light); font-size: .92rem; margin-bottom: 1.5rem; }
.portal-form-row { margin-bottom: 1.1rem; }
.portal-form-row label { display: block; font-size: .82rem; font-weight: 700; color: var(--text); text-transform: uppercase; letter-spacing: .04em; margin-bottom: .35rem; }
.portal-form-row input, .portal-form-row textarea { width: 100%; padding: .65rem .85rem; border: 1.5px solid var(--gray-300); border-radius: 8px; font-size: .95rem; font-family: inherit; outline: none; }
.portal-form-row input:focus, .portal-form-row textarea:focus { border-color: var(--red); }
.portal-form-row .hint { font-size: .78rem; color: var(--text-light); margin-top: .3rem; }
.portal-alert { padding: .65rem .9rem; border-radius: 8px; font-size: .88rem; margin-bottom: 1.25rem; }
.portal-alert-error { background: #FFF0F0; border: 1px solid #FFAAAA; color: #990000; }
.portal-alert-ok { background: #E8F5E9; border: 1px solid #A5D6A7; color: #1B5E20; }
.portal-links { text-align: center; font-size: .88rem; color: var(--text-light); margin-top: 1.25rem; }
.portal-links a { color: var(--red); text-decoration: none; }
.portal-links a:hover { text-decoration: underline; }
.btn-danger { background: #fff; color: var(--red); border: 1.5px solid #FFAAAA; }
.btn-danger:hover { background: var(--red); color: #fff; border-color: var(--red); }
</style>
