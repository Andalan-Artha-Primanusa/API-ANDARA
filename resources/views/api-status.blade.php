<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ANDARA API</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; color: #11352a; background: #f5faf7; }
        .shell { width: min(620px, 100%); overflow: hidden; border: 1px solid #d9ebe1; border-radius: 28px; background: #fff; box-shadow: 0 24px 70px rgba(6, 91, 52, .12); }
        .hero { padding: 42px 40px 36px; text-align: center; background: linear-gradient(135deg, #087c43, #075b35); }
        .logo { width: 112px; height: 112px; object-fit: contain; margin: 0 auto 20px; padding: 14px; border-radius: 28px; background: #fff; filter: drop-shadow(0 8px 14px rgba(0,0,0,.18)); }
        h1 { margin: 0; color: #fff; font-size: clamp(28px, 5vw, 42px); letter-spacing: -.04em; }
        .subtitle { margin: 10px 0 0; color: #d8f5e3; font-size: 15px; }
        .content { padding: 28px 32px 32px; }
        .status { display: flex; align-items: center; gap: 12px; padding: 16px; border: 1px solid #cce9d9; border-radius: 16px; background: #f0fbf4; }
        .dot { width: 12px; height: 12px; flex: 0 0 auto; border-radius: 50%; background: #08a85a; box-shadow: 0 0 0 5px #d7f5e2; }
        .status strong { display: block; font-size: 16px; }
        .status span { display: block; margin-top: 3px; color: #668276; font-size: 13px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 18px; }
        .meta-card { padding: 14px 16px; border: 1px solid #e3eee8; border-radius: 14px; }
        .meta-card small { display: block; color: #789086; font-size: 12px; }
        .meta-card b { display: block; margin-top: 5px; color: #1b5d42; font-size: 14px; }
        footer { padding-top: 24px; color: #96aaa0; text-align: center; font-size: 12px; }
        @media (max-width: 480px) { .hero { padding: 34px 24px 30px; } .content { padding: 22px 20px 24px; } .meta { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <main class="shell" aria-label="Status API ANDARA">
        <section class="hero">
            <img class="logo" src="{{ asset('app-logo.png') }}" alt="Logo Andalan">
            <h1>ANDARA API</h1>
            <p class="subtitle">Andalan Digital Human Resource Application</p>
        </section>
        <section class="content">
            <div class="status">
                <span class="dot" aria-hidden="true"></span>
                <div>
                    <strong>API aktif dan siap digunakan</strong>
                    <span>Service berjalan normal dan siap menerima request.</span>
                </div>
            </div>
            <div class="meta">
                <div class="meta-card"><small>Platform</small><b>ANDALAN HRIS</b></div>
                <div class="meta-card"><small>Status</small><b>Operational</b></div>
            </div>
            <footer>Powered by ANDALAN</footer>
        </section>
    </main>
</body>
</html>
