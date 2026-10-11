<?php
/**
 * portal/admin_look.php
 *
 * Gives portal back-office pages (portal/admin/*, portal/event-edit.php) the
 * look of the main admin (/admin): it outputs admin/head_styles.php and maps
 * the portal's own classes (portal-card, portal-form-row, portal-alert...)
 * onto the admin panel / form-row / alert styles.
 */
function admin_look_head() {
    include dirname(__DIR__) . '/admin/head_styles.php'; ?>
<style>
:root { --gray-300:#EAE2E2; --text-light:#7A6565; --text:#2D1E1E; --red:#CC0000; }
.portal-card, .media-card { background:#fff; border-radius:12px; padding:1.5rem; box-shadow:0 2px 12px rgba(26,5,5,.07); margin-bottom:1.5rem; border:0; }
.portal-card h1 { font-size:1.6rem; font-weight:700; color:#1A0505; margin-bottom:.35rem; }
.portal-card h2, .media-card h2 { font-size:1.05rem; font-weight:700; color:#1A0505; margin-bottom:1rem !important; padding-bottom:.75rem; border-bottom:2px solid #F0E8E8; }
.portal-card h3, .media-card h3 { font-size:.95rem; font-weight:700; color:#3D2E2E; }
.portal-sub { color:#7A6565; font-size:.9rem; margin-bottom:1rem; }
.portal-alert { padding:.7rem 1rem; border-radius:8px; font-size:.9rem; margin-bottom:1rem; }
.portal-alert-ok { background:#E8F5E9; border:1px solid #A5D6A7; color:#1B5E20; }
.portal-alert-error { background:#FFF0F0; border:1px solid #FFAAAA; color:#990000; }
.portal-form-row { display:flex; flex-direction:column; gap:.4rem; margin-bottom:1rem; }
.portal-form-row label, .form-row label { font-size:.82rem; font-weight:600; color:#3D2E2E; text-transform:uppercase; letter-spacing:.04em; }
.portal-form-row input, .portal-form-row textarea, .portal-form-row select,
.panel input[type=number], .panel input[type=text], .portal-card input[type=number], .portal-card input[type=text] { padding:.55rem .75rem; border:1.5px solid #EAE2E2; border-radius:7px; font-size:.92rem; font-family:inherit; outline:none; background:#fff; }
.portal-form-row input:focus, .portal-form-row textarea:focus, .panel input:focus { border-color:#CC0000; }
.hint { font-size:.78rem; color:#7A6565; }
input[type=file] { font-size:.85rem; max-width:100%; }
.status-pill { display:inline-block; padding:.2rem .6rem; border-radius:99px; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.status-pending { background:#FFF8E1; color:#8D6E00; border:1px solid #FFE082; }
.status-published { background:#E8F5E9; color:#1B5E20; border:1px solid #A5D6A7; }
.status-rejected { background:#FFEBEE; color:#B71C1C; border:1px solid #FFCDD2; }
.status-cancelled { background:#ECEFF1; color:#455A64; border:1px solid #CFD8DC; }
.data-table { width:100%; border-collapse:collapse; font-size:.88rem; }
.data-table th { background:#1A0505; color:#F5C840; text-align:left; padding:.6rem .75rem; font-weight:600; font-size:.78rem; text-transform:uppercase; letter-spacing:.06em; }
.data-table td { padding:.6rem .75rem; border-bottom:1px solid #EAE2E2; vertical-align:top; }
.data-table tr:hover td { background:#FFF9F7; }
.table-wrap { overflow-x:auto; }
.stat-card { cursor:default; }
@media (max-width:700px) {
  .admin-nav { height:auto; flex-wrap:wrap; padding:.6rem 1rem; gap:.5rem; position:static; }
  .nav-right { margin-left:0; flex-wrap:wrap; }
  .wrap { padding:1.25rem .85rem; }
}
</style>
<?php }
