@extends('layouts.app')

@section('title', 'Employee Profile - Bastion HRIS')

@push('styles')
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #1e293b; min-height: 100vh; }

    .app-layout { display: flex; min-height: 100vh; }

    /* ========== SIDEBAR (BIRU) ========== */
    .sidebar {
        width: 260px;
        background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);
        box-shadow: 4px 0 25px rgba(30, 58, 138, 0.15);
        display: flex; flex-direction: column;
        position: fixed; top: 0; left: 0; height: 100vh; z-index: 50;
    }

    .sidebar-brand { padding: 1.5rem; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.12); }
    .sidebar-brand-icon { width: 36px; height: 36px; background: rgba(255,255,255,0.2); border-radius: 10px; backdrop-filter: blur(10px); display: flex; align-items: center; justify-content: center; }
    .sidebar-brand-icon svg { width: 18px; height: 18px; fill: white; }
    .sidebar-brand-text { font-size: 1.15rem; font-weight: 700; color: white; }
    .sidebar-brand-sub { font-size: 0.6rem; color: rgba(191,219,254,0.7); text-transform: uppercase; letter-spacing: 2px; }

    .sidebar-nav { padding: 1rem 0; flex: 1; overflow-y: auto; }
    .nav-section { padding: 0.5rem 1.5rem; font-size: 0.6rem; font-weight: 700; color: rgba(191,219,254,0.5); text-transform: uppercase; letter-spacing: 2px; margin-top: 0.75rem; }

    .nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 1.5rem; color: rgba(219,234,254,0.75); text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; border-left: 3px solid transparent; }
    .nav-item:hover { color: white; background: rgba(255,255,255,0.08); }
    .nav-item.active { color: white; background: rgba(255,255,255,0.12); border-left-color: white; }
    .nav-item svg { width: 20px; height: 20px; fill: currentColor; flex-shrink: 0; }

    .sidebar-footer { padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.12); }
    .sidebar-user { display: flex; align-items: center; gap: 10px; }
    .sidebar-avatar { width: 36px; height: 36px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; color: white; }
    .sidebar-user-name { font-size: 0.82rem; font-weight: 600; color: white; }
    .sidebar-user-role { font-size: 0.68rem; color: rgba(191,219,254,0.6); }

    /* ========== MAIN (PUTIH) ========== */
    .main-content { flex: 1; margin-left: 260px; min-height: 100vh; background: #f1f5f9; }

    .top-bar { padding: 1rem 2rem; background: #ffffff; border-bottom: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 40; }
    .breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: #94a3b8; }
    .breadcrumb a { color: #94a3b8; text-decoration: none; }
    .breadcrumb a:hover { color: #3b82f6; }
    .breadcrumb .current { color: #1e293b; font-weight: 600; }

    .top-actions { display: flex; align-items: center; gap: 12px; }
    .btn-icon { width: 38px; height: 38px; border-radius: 10px; border: 1px solid #e2e8f0; background: white; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
    .btn-icon:hover { border-color: #3b82f6; color: #3b82f6; background: #eff6ff; }
    .btn-icon svg { width: 18px; height: 18px; fill: currentColor; }
    .notification-dot { position: relative; }
    .notification-dot::after { content: ''; position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; border: 2px solid white; }

    .page-content { padding: 2rem; }

    /* Profile Header */
    .profile-header { background: linear-gradient(135deg, #1e3a8a, #2563eb, #3b82f6); border-radius: 20px; padding: 2rem; display: flex; align-items: flex-start; gap: 2rem; margin-bottom: 1.5rem; position: relative; overflow: hidden; box-shadow: 0 10px 40px rgba(37,99,235,0.2); }
    .profile-header::before { content: ''; position: absolute; top: -50%; right: -20%; width: 400px; height: 400px; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); }
    .profile-avatar { width: 100px; height: 100px; border-radius: 20px; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; color: white; flex-shrink: 0; border: 2px solid rgba(255,255,255,0.25); }
    .profile-info { flex: 1; z-index: 1; }
    .profile-name { font-size: 1.5rem; font-weight: 700; color: white; display: flex; align-items: center; gap: 10px; }
    .status-badge { padding: 4px 12px; border-radius: 50px; font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-badge.active { background: rgba(255,255,255,0.2); color: #bbf7d0; }
    .profile-position { font-size: 0.95rem; color: rgba(219,234,254,0.8); margin-top: 4px; }
    .profile-meta { display: flex; gap: 2rem; margin-top: 1rem; }
    .meta-item { display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: rgba(219,234,254,0.7); }
    .meta-item svg { width: 16px; height: 16px; fill: rgba(219,234,254,0.5); }
    .profile-actions { display: flex; gap: 10px; z-index: 1; }

    .btn { padding: 10px 20px; border-radius: 10px; font-size: 0.82rem; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.25s; border: none; }
    .btn-primary-sm { background: white; color: #1e3a8a; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    .btn-primary-sm:hover { box-shadow: 0 6px 25px rgba(0,0,0,0.15); transform: translateY(-1px); }
    .btn-outline { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3); }
    .btn-outline:hover { background: rgba(255,255,255,0.25); }
    .btn svg { width: 16px; height: 16px; fill: currentColor; }

    /* Tabs */
    .tabs-container { display: flex; gap: 4px; background: #ffffff; border-radius: 14px; padding: 4px; margin-bottom: 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow-x: auto; }
    .tab-btn { padding: 10px 20px; border-radius: 10px; border: none; background: transparent; color: #64748b; font-size: 0.82rem; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; transition: all 0.25s; white-space: nowrap; }
    .tab-btn:hover { color: #3b82f6; background: #eff6ff; }
    .tab-btn.active { background: #2563eb; color: white; box-shadow: 0 2px 8px rgba(37,99,235,0.3); }

    /* Cards */
    .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
    .card { background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04); border-radius: 16px; padding: 1.5rem; transition: all 0.3s; }
    .card:hover { border-color: #bfdbfe; box-shadow: 0 4px 12px rgba(59,130,246,0.08); }
    .card-title { font-size: 0.95rem; font-weight: 700; color: #1e293b; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 8px; }
    .card-title svg { width: 18px; height: 18px; fill: #3b82f6; }
    .content-full { grid-column: span 2; }

    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .info-label { font-size: 0.7rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
    .info-value { font-size: 0.88rem; color: #1e293b; font-weight: 500; }

    /* Documents */
    .doc-list { display: flex; flex-direction: column; gap: 10px; }
    .doc-item { display: flex; align-items: center; gap: 14px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; transition: all 0.2s; }
    .doc-item:hover { border-color: #bfdbfe; background: #eff6ff; }
    .doc-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .doc-icon.pdf { background: #fef2f2; }
    .doc-icon.pdf svg { fill: #ef4444; }
    .doc-icon.img { background: #eff6ff; }
    .doc-icon.img svg { fill: #3b82f6; }
    .doc-icon svg { width: 20px; height: 20px; }
    .doc-info { flex: 1; }
    .doc-name { font-size: 0.85rem; font-weight: 600; color: #1e293b; }
    .doc-meta { font-size: 0.72rem; color: #94a3b8; margin-top: 2px; }
    .doc-action { padding: 6px 14px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; font-size: 0.75rem; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; transition: all 0.2s; }
    .doc-action:hover { border-color: #3b82f6; color: #3b82f6; background: #eff6ff; }

    /* Salary */
    .salary-table { width: 100%; border-collapse: collapse; }
    .salary-table th { text-align: left; padding: 10px 0; font-size: 0.7rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #e2e8f0; }
    .salary-table td { padding: 14px 0; font-size: 0.85rem; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
    .salary-amount { font-weight: 700; color: #059669; }
    .salary-change { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 600; }
    .salary-change.up { background: #ecfdf5; color: #059669; }

    /* Timeline */
    .timeline { position: relative; padding-left: 24px; }
    .timeline::before { content: ''; position: absolute; left: 7px; top: 0; bottom: 0; width: 2px; background: #dbeafe; }
    .timeline-item { position: relative; margin-bottom: 1.5rem; }
    .timeline-item::before { content: ''; position: absolute; left: -21px; top: 6px; width: 12px; height: 12px; border-radius: 50%; background: #2563eb; border: 3px solid white; box-shadow: 0 0 0 2px #dbeafe; }
    .timeline-item.past::before { background: #cbd5e1; box-shadow: 0 0 0 2px #e2e8f0; }
    .timeline-date { font-size: 0.72rem; color: #94a3b8; font-weight: 600; }
    .timeline-title { font-size: 0.88rem; font-weight: 600; color: #1e293b; margin-top: 2px; }
    .timeline-desc { font-size: 0.78rem; color: #64748b; margin-top: 4px; line-height: 1.5; }

    @media (max-width: 1200px) { .content-grid { grid-template-columns: 1fr; } .content-full { grid-column: span 1; } }
    @media (max-width: 768px) { .sidebar { display: none; } .main-content { margin-left: 0; } .profile-header { flex-direction: column; align-items: center; text-align: center; } .profile-meta { justify-content: center; flex-wrap: wrap; } .profile-actions { justify-content: center; } }
</style>
@endpush

@section('content')
<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon"><svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 16l-4-4 1.41-1.41L11 14.17l6.59-6.59L19 9l-8 8z"/></svg></div>
            <div><div class="sidebar-brand-text">Bastion</div><div class="sidebar-brand-sub">HRIS Platform</div></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">Main</div>
            <a href="#" class="nav-item"><svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg> Dashboard</a>
            <div class="nav-section">Human Resources</div>
            <a href="#" class="nav-item active"><svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg> Employees</a>
            <a href="#" class="nav-item"><svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg> Departments</a>
            <a href="#" class="nav-item"><svg viewBox="0 0 24 24"><path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/></svg> Positions</a>
            <div class="nav-section">Finance</div>
            <a href="#" class="nav-item"><svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg> Salary & Payroll</a>
            <div class="nav-section">System</div>
            <a href="#" class="nav-item"><svg viewBox="0 0 24 24"><path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.61-.22l-2.49 1a7.03 7.03 0 0 0-1.69-.98l-.38-2.65A.49.49 0 0 0 14 2h-4a.49.49 0 0 0-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1a.5.5 0 0 0-.61.22l-2 3.46a.49.49 0 0 0 .12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46a.5.5 0 0 0-.12-.64l-2.11-1.65zM12 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z"/></svg> Settings</a>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-avatar" id="userAvatar">SA</div>
                <div><div class="sidebar-user-name" id="userName">Admin</div><div class="sidebar-user-role" id="userRole">HR Administrator</div></div>
            </div>
            <div style="margin-top:10px;">
                <button onclick="logout()" class="btn btn-outline" style="width:100%; justify-content:center;">
                    <svg viewBox="0 0 24 24">
                        <path d="M16 13v-2H7V8l-5 4 5 4v-3h9zm3-10H9c-1.1 0-2 .9-2 2v4h2V5h10v14H9v-4H7v4c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/>
                    </svg>
                    Logout
                </button>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="breadcrumb"><a href="#">Dashboard</a><span>/</span><a href="#">Employees</a><span>/</span><span class="current" id="breadcrumbName">Employee</span></div>
            <div class="top-actions">
                <button class="btn-icon notification-dot"><svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg></button>
                <button class="btn-icon"><svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg></button>
            </div>
        </div>

        <div class="page-content">
            <div class="profile-header">
                <div class="profile-avatar" id="profileAvatar">BS</div>
                <div class="profile-info">
                    <div class="profile-name"><span id="profileName">Budi Santoso</span><span class="status-badge active">Active</span></div>
                    <div class="profile-position" id="profilePosition">Software Engineer — IT Department</div>
                    <div class="profile-meta">
                        <div class="meta-item"><svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg><span id="profileEmail">budi.s@example.com</span></div>
                        <div class="meta-item"><svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg><span id="profileCode">EMP-001</span></div>
                    </div>
                </div>
                <div class="profile-actions">
                    <button class="btn btn-primary-sm"><svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg> Edit Profile</button>
                    <button class="btn btn-outline"><svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg> Export</button>
                </div>
            </div>

            <div class="tabs-container">
                <button class="tab-btn active" onclick="switchTab('profile')">Profile Details</button>
                <button class="tab-btn" onclick="switchTab('contract')">Contract & Status</button>
                <button class="tab-btn" onclick="switchTab('documents')">Documents</button>
                <button class="tab-btn" onclick="switchTab('salary')">Salary History</button>
            </div>

            <div id="tab-profile" class="tab-content">
                <div class="content-grid">
                    <div class="card">
                        <div class="card-title"><svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg> Personal Information</div>
                        <div class="info-grid">
                            <div class="info-item"><div class="info-label">Full Name</div><div class="info-value" id="infoName">Budi Santoso</div></div>
                            <div class="info-item"><div class="info-label">Employee Code</div><div class="info-value" id="infoCode">EMP-001</div></div>
                            <div class="info-item"><div class="info-label">Email</div><div class="info-value" id="infoEmail">budi.s@example.com</div></div>
                            <div class="info-item"><div class="info-label">Hire Date</div><div class="info-value" id="infoHireDate">01 January 2024</div></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-title"><svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg> Employment Details</div>
                        <div class="info-grid">
                            <div class="info-item"><div class="info-label">Department</div><div class="info-value" id="infoDept">IT Department</div></div>
                            <div class="info-item"><div class="info-label">Position</div><div class="info-value" id="infoPosition">Software Engineer</div></div>
                            <div class="info-item"><div class="info-label">Salary</div><div class="info-value" id="infoSalary" style="color:#059669;font-weight:700;">Rp 15,000,000</div></div>
                            <div class="info-item"><div class="info-label">Status</div><div class="info-value"><span class="status-badge active">Active</span></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-contract" class="tab-content" style="display:none;">
                <div class="content-grid">
                    <div class="card content-full">
                        <div class="card-title"><svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg> Contract History</div>
                        <div class="timeline">
                            <div class="timeline-item"><div class="timeline-date">01 Jan 2025 — Present</div><div class="timeline-title">Permanent Employee — Software Engineer</div><div class="timeline-desc">Full-time permanent contract. Salary Rp 15,000,000/month.</div></div>
                            <div class="timeline-item past"><div class="timeline-date">01 Jan 2024 — 31 Dec 2024</div><div class="timeline-title">Contract Employee — Junior Developer</div><div class="timeline-desc">12-month fixed-term contract. Performance: Excellent.</div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-documents" class="tab-content" style="display:none;">
                <div class="content-grid">
                    <div class="card content-full">
                        <div class="card-title"><svg viewBox="0 0 24 24"><path d="M20 6h-8l-2-2H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6 10H6v-2h8v2zm4-4H6v-2h12v2z"/></svg> Employee Documents</div>
                        <div class="doc-list">
                            <div class="doc-item"><div class="doc-icon img"><svg viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg></div><div class="doc-info"><div class="doc-name">KTP — Kartu Tanda Penduduk</div><div class="doc-meta">JPG · 1.2 MB</div></div><button class="doc-action">View</button></div>
                            <div class="doc-item"><div class="doc-icon pdf"><svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg></div><div class="doc-info"><div class="doc-name">NPWP — Nomor Pokok Wajib Pajak</div><div class="doc-meta">PDF · 856 KB</div></div><button class="doc-action">View</button></div>
                            <div class="doc-item"><div class="doc-icon pdf"><svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg></div><div class="doc-info"><div class="doc-name">Kontrak Kerja</div><div class="doc-meta">PDF · 2.4 MB</div></div><button class="doc-action">View</button></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-salary" class="tab-content" style="display:none;">
                <div class="content-grid">
                    <div class="card content-full">
                        <div class="card-title"><svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg> Salary History</div>
                        <table class="salary-table">
                            <thead><tr><th>Effective Date</th><th>Amount</th><th>Change</th><th>Notes</th></tr></thead>
                            <tbody>
                                <tr><td>01 Jan 2025</td><td class="salary-amount">Rp 15,000,000</td><td><span class="salary-change up">↑ +87.5%</span></td><td>Promotion to permanent</td></tr>
                                <tr><td>01 Jan 2024</td><td class="salary-amount">Rp 8,000,000</td><td>—</td><td>Initial contract</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tabName).style.display = 'block';
    event.target.classList.add('active');
}

// Load user data from localStorage
const user = JSON.parse(localStorage.getItem('user') || '{}');
if (user.name) {
    const initials = user.name.split(' ').map(w => w[0]).join('').toUpperCase().substring(0, 2);
    document.getElementById('profileAvatar').textContent = initials;
    document.getElementById('userAvatar').textContent = initials;
    document.getElementById('profileName').textContent = user.name;
    document.getElementById('userName').textContent = user.name;
    document.getElementById('breadcrumbName').textContent = user.name;
    document.getElementById('infoName').textContent = user.name;
    if (user.email) {
        document.getElementById('profileEmail').textContent = user.email;
        document.getElementById('infoEmail').textContent = user.email;
    }
    if (user.roles && user.roles.length > 0) {
        document.getElementById('userRole').textContent = user.roles[0].replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
}

async function logout() {
    if (!confirm('Are you sure you want to logout?')) return;

    try {
        const token = localStorage.getItem('auth_token'); // ✅ FIX

        if (token) {
            await fetch('/api/logout', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            });
        }

        // clear storage
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');

        window.location.href = '/login';

    } catch (error) {
        console.error('Logout error:', error);

        localStorage.clear();
        window.location.href = '/login';
    }
}

if (!localStorage.getItem('auth_token')) {
    window.location.href = '/login';
}

</script>
@endpush
