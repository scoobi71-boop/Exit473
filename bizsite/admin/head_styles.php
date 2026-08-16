<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#F4F6F8;color:#1E2A35;min-height:100vh}
a{color:#2D6A4F;text-decoration:none}
a:hover{text-decoration:underline}

.wrap{max-width:1060px;margin:0 auto;padding:2rem 1.25rem}
.page-title{font-size:1.5rem;font-weight:700;color:#1E2A35;margin-bottom:1.5rem}

/* Nav */
.admin-nav{background:#1E2A35;height:54px;display:flex;align-items:center;padding:0 1.25rem;gap:1rem;position:sticky;top:0;z-index:100}
.admin-nav .brand{color:#fff;font-weight:700;font-size:1rem;white-space:nowrap}
.admin-nav .brand span{display:block;font-size:.72rem;color:#8EAAB8;font-weight:400;text-transform:uppercase;letter-spacing:.06em}
.nav-links-admin{display:flex;gap:.2rem;flex:1;flex-wrap:wrap}
.nav-links-admin a{color:#8EAAB8;font-size:.85rem;padding:.35rem .7rem;border-radius:6px;transition:background .15s,color .15s}
.nav-links-admin a:hover{background:#2D3E4E;color:#fff;text-decoration:none}
.nav-links-admin a.active{background:#2D6A4F;color:#fff}
.nav-right{margin-left:auto;display:flex;align-items:center;gap:.75rem}
.nav-right a{color:#8EAAB8;font-size:.8rem}
.nav-right a:hover{color:#fff;text-decoration:none}

/* Panels */
.panel{background:#fff;border-radius:10px;padding:1.5rem;box-shadow:0 1px 8px rgba(0,0,0,.06);margin-bottom:1.5rem}
.panel-title{font-size:1rem;font-weight:700;color:#1E2A35;margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:2px solid #EEF2F5}

/* Alerts */
.alert{padding:.7rem 1rem;border-radius:8px;font-size:.88rem;margin-bottom:1rem}
.alert-ok{background:#E6F4ED;border:1px solid #95D5B2;color:#1B5E20}
.alert-error{background:#FFF0F0;border:1px solid #FFAAAA;color:#990000}

/* Forms */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
@media(max-width:600px){.form-grid{grid-template-columns:1fr}}
.form-row{display:flex;flex-direction:column;gap:.35rem}
.form-row label{font-size:.78rem;font-weight:600;color:#4A5568;text-transform:uppercase;letter-spacing:.05em}
.form-row input,.form-row select,.form-row textarea{padding:.6rem .8rem;border:1.5px solid #DDE3EA;border-radius:7px;font-size:.92rem;font-family:inherit;outline:none;width:100%;transition:border-color .15s;background:#fff}
.form-row input:focus,.form-row select:focus,.form-row textarea:focus{border-color:#2D6A4F}
.form-row textarea{resize:vertical}
.form-row .hint{font-size:.75rem;color:#718096;margin-top:.2rem}
.checkbox-label{display:flex;align-items:center;gap:.5rem;font-size:.9rem;font-weight:500;cursor:pointer}
.checkbox-label input[type=checkbox]{width:16px;height:16px;cursor:pointer;accent-color:#2D6A4F}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:.35rem;padding:.5rem 1.1rem;border-radius:7px;font-size:.85rem;font-weight:600;border:none;cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap}
.btn:hover{text-decoration:none}
.btn-primary{background:#2D6A4F;color:#fff}
.btn-primary:hover{background:#1B4D35}
.btn-outline{background:#fff;color:#1E2A35;border:1.5px solid #DDE3EA}
.btn-outline:hover{border-color:#2D6A4F;color:#2D6A4F}
.btn-danger{background:#fff;color:#CC0000;border:1.5px solid #FFAAAA}
.btn-danger:hover{background:#CC0000;color:#fff;border-color:#CC0000}
.btn-sm{padding:.3rem .65rem;font-size:.78rem}

/* Table */
.data-table{width:100%;border-collapse:collapse;font-size:.85rem}
.data-table th{background:#1E2A35;color:#EEF2F5;text-align:left;padding:.55rem .75rem;font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;font-weight:600}
.data-table td{padding:.55rem .75rem;border-bottom:1px solid #EEF2F5;vertical-align:middle}
.data-table tr:hover td{background:#F7FAFC}
.table-wrap{overflow-x:auto}

/* Stats */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:1.5rem}
.stat-card{background:#fff;border-radius:10px;padding:1.1rem;text-align:center;box-shadow:0 1px 8px rgba(0,0,0,.06);text-decoration:none;transition:transform .15s;display:block}
.stat-card:hover{transform:translateY(-2px);text-decoration:none}
.stat-num{font-size:1.9rem;font-weight:800;color:#1E2A35}
.stat-label{font-size:.75rem;text-transform:uppercase;letter-spacing:.07em;color:#718096;margin-top:.2rem}

/* Image preview */
.img-preview{width:80px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #DDE3EA}
</style>
