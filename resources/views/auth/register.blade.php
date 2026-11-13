@extends('layouts.app')

@section('title', 'Register - Bastion HRIS')

@push('styles')
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; min-height: 100vh; background: #0a0f1e; overflow-x: hidden; }
    .auth-container { display: flex; min-height: 100vh; }

    .left-panel {
        flex: 1; background: linear-gradient(165deg, #1a3a8f 0%, #0d1f5c 40%, #0a1545 100%);
        display: flex; flex-direction: column; justify-content: center; align-items: center;
        padding: 3rem; position: relative; overflow: hidden;
    }
    .left-panel::before {
        content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
        background: radial-gradient(ellipse at 30% 50%, rgba(59, 130, 246, 0.08) 0%, transparent 60%);
        animation: pulse-bg 8s ease-in-out infinite;
    }
    @keyframes pulse-bg { 0%, 100% { transform: scale(1); opacity: 0.5; } 50% { transform: scale(1.1); opacity: 1; } }

    .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 3rem; z-index: 1; }
    .brand-icon { width: 44px; height: 44px; background: linear-gradient(135deg, #3b82f6, #2563eb); border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 20px rgba(59, 130, 246, 0.4); }
    .brand-icon svg { width: 24px; height: 24px; fill: white; }
    .brand-text h1 { font-size: 1.75rem; font-weight: 700; color: white; }
    .brand-text span { font-size: 0.7rem; font-weight: 600; color: rgba(147, 197, 253, 0.7); text-transform: uppercase; letter-spacing: 3px; }

    .tagline { text-align: center; z-index: 1; }
    .tagline h2 { font-size: 2rem; font-weight: 800; color: white; line-height: 1.2; }
    .tagline h2 span { color: #60a5fa; }
    .tagline p { font-size: 0.9rem; color: rgba(148, 163, 184, 0.6); margin-top: 1rem; line-height: 1.6; max-width: 400px; }

    .footer-left { position: absolute; bottom: 1.5rem; left: 3rem; z-index: 1; }
    .footer-left span { font-size: 0.65rem; color: rgba(148, 163, 184, 0.35); text-transform: uppercase; letter-spacing: 1.5px; }

    .right-panel { width: 480px; min-width: 420px; background: #ffffff; display: flex; flex-direction: column; justify-content: center; padding: 3rem; overflow-y: auto; }

    .register-header h2 { font-size: 1.65rem; font-weight: 700; color: #0f172a; }
    .register-header p { font-size: 0.9rem; color: #64748b; margin-top: 6px; }

    .form-group { margin-top: 1.25rem; }
    .form-group label { display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
    .form-input { width: 100%; padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 0.88rem; font-family: 'Inter', sans-serif; color: #0f172a; background: #f8fafc; transition: all 0.25s; outline: none; }
    .form-input:focus { border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .form-input::placeholder { color: #94a3b8; }

    .btn-primary { width: 100%; padding: 13px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: 10px; font-size: 0.92rem; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer; margin-top: 1.5rem; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s; box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3); }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 30px rgba(37, 99, 235, 0.45); }

    .login-link { text-align: center; margin-top: 2rem; font-size: 0.85rem; color: #64748b; }
    .login-link a { color: #3b82f6; text-decoration: none; font-weight: 600; }
    .login-link a:hover { text-decoration: underline; }

    .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 10px 14px; border-radius: 10px; font-size: 0.82rem; font-weight: 500; margin-top: 1rem; display: none; }
    .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #16a34a; padding: 10px 14px; border-radius: 10px; font-size: 0.82rem; font-weight: 500; margin-top: 1rem; display: none; }
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
            <div class="brand-icon"><svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 16l-4-4 1.41-1.41L11 14.17l6.59-6.59L19 9l-8 8z"/></svg></div>
            <div class="brand-text"><h1>Bastion</h1><span>Enterprise Tier</span></div>
        </div>
        <div class="tagline">
            <h2>Join Our <span>HR Platform</span></h2>
            <p>Create your account and start managing your workforce with enterprise-grade tools.</p>
        </div>
        <div class="footer-left"><span>&copy; 2024 Bastion HRIS</span></div>
    </div>

    <div class="right-panel">
        <div class="register-header">
            <h2>Create your account</h2>
            <p>Fill in your details to get started.</p>
        </div>

        <div class="alert-error" id="errorAlert"></div>
        <div class="alert-success" id="successAlert"></div>

        <form id="registerForm">
            <div class="form-group"><label>Full Name</label><input type="text" id="nameInput" class="form-input" placeholder="John Doe" required></div>
            <div class="form-group"><label>Email Address</label><input type="email" id="emailInput" class="form-input" placeholder="name@company.com" required></div>
            <div class="form-group"><label>Password</label><input type="password" id="passwordInput" class="form-input" placeholder="Minimum 8 characters" required minlength="8"></div>
            <div class="form-group"><label>Confirm Password</label><input type="password" id="passwordConfirmInput" class="form-input" placeholder="Re-enter your password" required></div>
            <button type="submit" class="btn-primary" id="submitBtn"><span id="btnText">Create Account</span></button>
        </form>

        <p class="login-link">Already have an account? <a href="/login">Sign in</a></p>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const errorAlert = document.getElementById('errorAlert');
    const successAlert = document.getElementById('successAlert');

    btn.classList.add('loading'); btnText.textContent = 'Creating account...';
    errorAlert.style.display = 'none'; successAlert.style.display = 'none';

    try {
        const response = await fetch('/api/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                name: document.getElementById('nameInput').value,
                email: document.getElementById('emailInput').value,
                password: document.getElementById('passwordInput').value,
                password_confirmation: document.getElementById('passwordConfirmInput').value
            })
        });
        const data = await response.json();
        if (response.ok && data.success) {
            localStorage.setItem('auth_token', data.data.token);
            localStorage.setItem('user', JSON.stringify(data.data.user || {}));
            window.location.href = '/employee/profile';
        } else {
            const msg = data.message || 'Registration failed.';
            const errors = data.errors ? Object.values(data.errors).flat().join(', ') : '';
            errorAlert.textContent = errors || msg;
            errorAlert.style.display = 'block';
            btn.classList.remove('loading'); btnText.textContent = 'Create Account';
        }
    } catch (error) {
        errorAlert.textContent = 'Connection error.';
        errorAlert.style.display = 'block';
        btn.classList.remove('loading'); btnText.textContent = 'Create Account';
    }
});
</script>
@endpush
