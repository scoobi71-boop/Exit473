<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#F7F3F3;color:#2D1E1E;min-height:100vh}
a{color:#CC0000;text-decoration:none}
a:hover{text-decoration:underline}

/* Layout */
.wrap{max-width:1100px;margin:0 auto;padding:2rem 1.25rem}
.page-title{font-size:1.6rem;font-weight:700;color:#1A0505;margin-bottom:1.5rem}

/* Nav */
.admin-nav{background:#1A0505;padding:0 1.25rem;display:flex;align-items:center;gap:1.5rem;height:56px;position:sticky;top:0;z-index:100}
.admin-nav .brand{color:#F5C840;font-weight:700;font-size:1.05rem;letter-spacing:.02em;white-space:nowrap}
.admin-nav .brand span{color:#fff;font-weight:400;font-size:.8rem;display:block;letter-spacing:.06em;text-transform:uppercase;line-height:1}
.nav-links-admin{display:flex;gap:.25rem;flex:1;flex-wrap:wrap}
.nav-links-admin a{color:#D4C0C0;font-size:.88rem;padding:.4rem .75rem;border-radius:6px;transition:background .15s,color .15s}
.nav-links-admin a:hover{background:#3D0A0A;color:#fff;text-decoration:none}
.nav-links-admin a.active{background:#CC0000;color:#fff}
.nav-right{margin-left:auto;display:flex;align-items:center;gap:.75rem}
.nav-right a{color:#D4C0C0;font-size:.82rem}
.nav-right a:hover{color:#F5C840;text-decoration:none}

/* Panels */
.panel{background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 2px 12px rgba(26,5,5,.07);margin-bottom:1.5rem}
.panel-title{font-size:1.05rem;font-weight:700;color:#1A0505;margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:2px solid #F0E8E8}

/* Alerts */
.alert{padding:.7rem 1rem;border-radius:8px;font-size:.9rem;margin-bottom:1rem}
.alert-ok{background:#E8F5E9;border:1px solid #A5D6A7;color:#1B5E20}
.alert-error{background:#FFF0F0;border:1px solid #FFAAAA;color:#990000}

/* Forms */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
@media(max-width:600px){.form-grid{grid-template-columns:1fr}}
.form-row{display:flex;flex-direction:column;gap:.4rem}
.form-row label{font-size:.82rem;font-weight:600;color:#3D2E2E;text-transform:uppercase;letter-spacing:.04em}
.form-row input,.form-row select,.form-row textarea{padding:.6rem .8rem;border:1.5px solid #EAE2E2;border-radius:7px;font-size:.95rem;font-family:inherit;transition:border-color .15s;outline:none;width:100%;background:#fff}
.form-row input:focus,.form-row select:focus,.form-row textarea:focus{border-color:#CC0000}
.form-row textarea{resize:vertical}
.checkbox-label{display:flex;align-items:center;gap:.5rem;font-size:.9rem;font-weight:600;color:#3D2E2E;cursor:pointer}
.checkbox-label input[type=checkbox]{width:16px;height:16px;cursor:pointer}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:.35rem;padding:.55rem 1.1rem;border-radius:7px;font-size:.88rem;font-weight:600;border:none;cursor:pointer;transition:background .15s,color .15s,border-color .15s;text-decoration:none;white-space:nowrap}
.btn:hover{text-decoration:none}
.btn-primary{background:#CC0000;color:#fff}
.btn-primary:hover{background:#990000}
.btn-outline{background:#fff;color:#3D2E2E;border:1.5px solid #D0C4C4}
.btn-outline:hover{background:#F7F0F0;border-color:#CC0000;color:#CC0000}
.btn-danger{background:#fff;color:#CC0000;border:1.5px solid #FFAAAA}
.btn-danger:hover{background:#CC0000;color:#fff;border-color:#CC0000}
.btn-sm{padding:.35rem .7rem;font-size:.78rem}

/* Stats */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem}
.stat-card{background:#fff;border-radius:12px;padding:1.25rem 1rem;text-align:center;box-shadow:0 2px 12px rgba(26,5,5,.07);text-decoration:none;transition:transform .15s,box-shadow .15s;display:block}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(26,5,5,.12);text-decoration:none}
.stat-icon{font-size:1.8rem;margin-bottom:.4rem}
.stat-num{font-size:2rem;font-weight:800;color:#1A0505}
.stat-label{font-size:.78rem;text-transform:uppercase;letter-spacing:.07em;color:#7A6565;margin-top:.2rem}
</style>
