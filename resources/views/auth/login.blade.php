@extends('layouts.app')

@section('title', 'Sign In - Bastion HRIS')

@push('styles')
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; min-height: 100vh; background: #0a0f1e; overflow-x: hidden; }

    .auth-container { display: flex; min-height: 100vh; }

    /* ========== LEFT PANEL ========== */
    .left-panel {
        flex: 1;
        background: linear-gradient(165deg, #1a3a8f 0%, #0d1f5c 40%, #0a1545 100%);
        display: flex; flex-direction: column; justify-content: center; align-items: center;
        padding: 3rem; position: relative; overflow: hidden;
    }

    .left-panel::before {
        content: ''; position: absolute; top: -50%; left: -50%;
        width: 200%; height: 200%;
        background: radial-gradient(ellipse at 30% 50%, rgba(59, 130, 246, 0.08) 0%, transparent 60%);
        animation: pulse-bg 8s ease-in-out infinite;
    }

    @keyframes pulse-bg {
        0%, 100% { transform: scale(1); opacity: 0.5; }
        50% { transform: scale(1.1); opacity: 1; }
    }

    .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 3rem; z-index: 1; }

    .brand-icon {
        width: 44px; height: 44px;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        border-radius: 12px; display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 20px rgba(59, 130, 246, 0.4);
    }

    .brand-icon svg { width: 24px; height: 24px; fill: white; }
    .brand-text h1 { font-size: 1.75rem; font-weight: 700; color: white; letter-spacing: -0.5px; }
    .brand-text span { font-size: 0.7rem; font-weight: 600; color: rgba(147, 197, 253, 0.7); text-transform: uppercase; letter-spacing: 3px; }

    .dashboard-preview {
        width: 100%; max-width: 480px;
        background: rgba(15, 23, 42, 0.7); border: 1px solid rgba(59, 130, 246, 0.15);
        border-radius: 16px; overflow: hidden; backdrop-filter: blur(20px);
        box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5); z-index: 1;
    }

    .preview-header {
        padding: 12px 16px; background: rgba(15, 23, 42, 0.9);
        border-bottom: 1px solid rgba(59, 130, 246, 0.1);
        display: flex; align-items: center; gap: 8px;
    }

    .preview-dot { width: 10px; height: 10px; border-radius: 50%; }
    .preview-dot.red { background: #ef4444; }
    .preview-dot.yellow { background: #eab308; }
    .preview-dot.green { background: #22c55e; }

    .preview-title { font-size: 0.65rem; color: rgba(148, 163, 184, 0.6); margin-left: 8px; text-transform: uppercase; letter-spacing: 1px; }
    .preview-body { padding: 20px; }

    .preview-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px; }

    .stat-card { background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(59, 130, 246, 0.08); border-radius: 10px; padding: 12px; }
    .stat-label { font-size: 0.6rem; color: rgba(148, 163, 184, 0.5); text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-value { font-size: 1.1rem; font-weight: 700; margin-top: 4px; }
    .stat-value.blue { color: #60a5fa; }
    .stat-value.green { color: #4ade80; }
    .stat-value.purple { color: #a78bfa; }

    .preview-chart { height: 80px; background: rgba(30, 41, 59, 0.4); border-radius: 10px; border: 1px solid rgba(59, 130, 246, 0.08); position: relative; overflow: hidden; }
    .chart-bars { display: flex; align-items: flex-end; gap: 5px; height: 100%; padding: 10px; }
    .chart-bar { flex: 1; border-radius: 3px 3px 0 0; animation: grow-bar 1.5s ease-out forwards; opacity: 0.8; }
    @keyframes grow-bar { from { height: 0; } }

    .encryption-badge {
        display: inline-flex; align-items: center; gap: 10px;
        background: linear-gradient(135deg, #ea580c, #f97316); color: white;
        padding: 10px 20px; border-radius: 50px; margin-top: 1.5rem; z-index: 1;
        box-shadow: 0 8px 30px rgba(234, 88, 12, 0.35);
    }
    .encryption-badge svg { width: 20px; height: 20px; fill: white; }
    .encryption-badge .badge-text { display: flex; flex-direction: column; }
    .encryption-badge .badge-label { font-size: 0.55rem; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; opacity: 0.85; }
    .encryption-badge .badge-value { font-size: 0.85rem; font-weight: 700; }

    .tagline { margin-top: 2.5rem; text-align: center; z-index: 1; }
    .tagline h2 { font-size: 2rem; font-weight: 800; color: white; line-height: 1.2; letter-spacing: -0.5px; }
    .tagline h2 span { color: #60a5fa; }
    .tagline p { font-size: 0.9rem; color: rgba(148, 163, 184, 0.6); margin-top: 1rem; line-height: 1.6; max-width: 400px; }

    .footer-left { position: absolute; bottom: 1.5rem; left: 3rem; display: flex; align-items: center; gap: 1rem; z-index: 1; }
    .footer-left span { font-size: 0.65rem; color: rgba(148, 163, 184, 0.35); text-transform: uppercase; letter-spacing: 1.5px; }

    /* ========== RIGHT PANEL ========== */
    .right-panel {
        width: 480px; min-width: 420px; background: #ffffff;
        display: flex; flex-direction: column; justify-content: center; padding: 3rem; overflow-y: auto;
    }

    .login-header h2 { font-size: 1.65rem; font-weight: 700; color: #0f172a; letter-spacing: -0.5px; }
    .login-header p { font-size: 0.9rem; color: #64748b; margin-top: 6px; }

    .form-group { margin-top: 1.5rem; }
    .form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
    .form-group .forgot-link { float: right; font-size: 0.78rem; color: #3b82f6; text-decoration: none; font-weight: 500; }
    .form-group .forgot-link:hover { text-decoration: underline; }

    .form-input {
        width: 100%; padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px;
        font-size: 0.88rem; font-family: 'Inter', sans-serif; color: #0f172a; background: #f8fafc;
        transition: all 0.25s; outline: none;
    }
    .form-input:focus { border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .form-input::placeholder { color: #94a3b8; }

    .password-wrapper { position: relative; }
    .password-toggle { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #94a3b8; padding: 4px; }
    .password-toggle:hover { color: #64748b; }

    .remember-row { display: flex; align-items: center; gap: 8px; margin-top: 1.25rem; }
    .remember-row input[type="checkbox"] { width: 16px; height: 16px; accent-color: #3b82f6; border-radius: 4px; }
    .remember-row label { font-size: 0.82rem; color: #64748b; }

    .btn-primary {
        width: 100%; padding: 13px;
        background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: 10px;
        font-size: 0.92rem; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer;
        margin-top: 1.5rem; display: flex; align-items: center; justify-content: center; gap: 8px;
        transition: all 0.3s; box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
    }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 30px rgba(37, 99, 235, 0.45); }
    .btn-primary:active { transform: translateY(0); }

    .divider { display: flex; align-items: center; gap: 1rem; margin: 1.75rem 0; }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
    .divider span { font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 500; }

    .social-buttons { display: flex; flex-direction: column; gap: 10px; }
    .btn-social {
        width: 100%; padding: 11px; border: 1.5px solid #e2e8f0; border-radius: 10px; background: white;
        font-size: 0.85rem; font-weight: 500; font-family: 'Inter', sans-serif; color: #334155; cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.25s;
    }
    .btn-social:hover { border-color: #cbd5e1; background: #f8fafc; }
    .btn-social img, .btn-social svg { width: 18px; height: 18px; }

    .signup-link { text-align: center; margin-top: 2rem; font-size: 0.85rem; color: #64748b; }
    .signup-link a { color: #3b82f6; text-decoration: none; font-weight: 600; }
    .signup-link a:hover { text-decoration: underline; }

    .soc-badge {
        text-align: center; margin-top: 1.5rem;
        display: flex; align-items: center; justify-content: center; gap: 6px;
        font-size: 0.68rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;
    }
    .soc-badge svg { width: 14px; height: 14px; fill: #22c55e; }

    .alert-error {
        background: #fef2f2; border: 1px solid #fecaca; color: #dc2626;
        padding: 10px 14px; border-radius: 10px; font-size: 0.82rem; font-weight: 500;
        margin-top: 1rem; display: none;
    }

    .btn-primary.loading { opacity: 0.7; pointer-events: none; }

    @media (max-width: 960px) {
        .left-panel { display: none; }
        .right-panel { width: 100%; min-width: unset; max-width: 480px; margin: 0 auto; }
        .auth-container { background: #ffffff; justify-content: center; }
    }
</style>
@endpush

@section('content')
<div class="auth-container">
    <div class="left-panel">
        <div class="brand">
            <div class="brand-icon">
                <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 16l-4-4 1.41-1.41L11 14.17l6.59-6.59L19 9l-8 8z"/></svg>
            </div>
            <div class="brand-text"><h1>Bastion</h1><span>Enterprise Tier</span></div>
        </div>

        <div class="dashboard-preview">
            <div class="preview-header">
                <div class="preview-dot red"></div><div class="preview-dot yellow"></div><div class="preview-dot green"></div>
                <span class="preview-title">Bastion HRIS — Dashboard</span>
            </div>
            <div class="preview-body">
                <div class="preview-grid">
                    <div class="stat-card"><div class="stat-label">Employees</div><div class="stat-value blue">1,247</div></div>
                    <div class="stat-card"><div class="stat-label">Attendance</div><div class="stat-value green">96.8%</div></div>
                    <div class="stat-card"><div class="stat-label">Payroll</div><div class="stat-value purple">$2.1M</div></div>
                </div>
                <div class="preview-chart">
                    <div class="chart-bars">
                        <div class="chart-bar" style="height:45%;background:#3b82f6"></div>
                        <div class="chart-bar" style="height:65%;background:#3b82f6"></div>
                        <div class="chart-bar" style="height:35%;background:#3b82f6"></div>
                        <div class="chart-bar" style="height:80%;background:#60a5fa"></div>
                        <div class="chart-bar" style="height:55%;background:#3b82f6"></div>
                        <div class="chart-bar" style="height:70%;background:#3b82f6"></div>
                        <div class="chart-bar" style="height:40%;background:#3b82f6"></div>
                        <div class="chart-bar" style="height:90%;background:#60a5fa"></div>
                        <div class="chart-bar" style="height:50%;background:#3b82f6"></div>
                        <div class="chart-bar" style="height:75%;background:#3b82f6"></div>
                        <div class="chart-bar" style="height:60%;background:#3b82f6"></div>
                        <div class="chart-bar" style="height:85%;background:#60a5fa"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="encryption-badge">
            <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
            <div class="badge-text"><span class="badge-label">Encryption</span><span class="badge-value">256-bit AES Active</span></div>
        </div>

        <div class="tagline">
            <h2>Dari Absensi Sampai<br>Gajian, <span>Semua Beres</span></h2>
            <p>Automate your human resources ecosystem with military-grade security and intuitive editorial design.</p>
        </div>

        <div class="footer-left"><span>&copy; 2024 Bastion HRIS. Secure Data Environment.</span></div>
    </div>

    <div class="right-panel">
        <div class="login-header">
            <h2>Sign in to your account</h2>
            <p>Welcome back. Please enter your credentials.</p>
        </div>

        <div class="alert-error" id="errorAlert"></div>

        <form id="loginForm">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" id="emailInput" class="form-input" placeholder="name@company.com" required>
            </div>
            <div class="form-group">
                <label>Password <a href="#" class="forgot-link">Forgot password?</a></label>
                <div class="password-wrapper">
                    <input type="password" id="passwordInput" class="form-input" placeholder="••••••••" required>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <div class="remember-row">
                <input type="checkbox" id="remember"><label for="remember">Stay signed in for 30 days</label>
            </div>
            <button type="button" class="btn-primary" id="submitBtn">
                <span id="btnText">Sign In</span>
                <svg id="btnIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            </button>
        </form>

        <div class="divider"><span>Or continue with</span></div>

        <div class="social-buttons">
            <button class="btn-social" type="button">
                <svg viewBox="0 0 24 24" width="18" height="18"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                Sign in with Google
            </button>
            <button class="btn-social" type="button">
                <svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="10" fill="none" stroke="#f97316" stroke-width="2"/><circle cx="12" cy="12" r="3" fill="#f97316"/></svg>
                Sign in with SSO
            </button>
        </div>

        <p class="signup-link">Don't have an account yet? <a href="/register">Contact Sales</a></p>
        <div class="soc-badge">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            SOC2 Compliant &amp; Encrypted
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function togglePassword() {
    const input = document.getElementById('passwordInput');
    input.type = input.type === 'password' ? 'text' : 'password';
}

document.getElementById('submitBtn').addEventListener('click', async function() {

    const btn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnIcon = document.getElementById('btnIcon');
    const errorAlert = document.getElementById('errorAlert');

    btn.classList.add('loading');
    btnText.textContent = 'Signing in...';
    btnIcon.style.display = 'none';
    errorAlert.style.display = 'none';

    try {
        const response = await fetch('/api/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                email: document.getElementById('emailInput').value,
                password: document.getElementById('passwordInput').value
            })
        });

        const data = await response.json();
        console.log(data);

        if (response.ok && data.data && data.data.token) {
            localStorage.setItem('auth_token', data.data.token);
            localStorage.setItem('user', JSON.stringify(data.data.user || {}));

            window.location.href = '/employee/profile';
        } else {
            throw new Error(data.message || 'Login gagal');
        }

    } catch (err) {
        console.error(err);
        errorAlert.textContent = err.message;
        errorAlert.style.display = 'block';

        btn.classList.remove('loading');
        btnText.textContent = 'Sign In';
        btnIcon.style.display = 'inline';
    }
});
</script>
@endpush
