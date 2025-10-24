<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AdminLog Dashboard</title>
  <style>
    :root {
      --bg: #0f172a;
      --panel: #111827;
      --muted: #94a3b8;
      --text: #e5e7eb;
      --accent: #22c55e;
      --accent-2: #60a5fa;
      --border: #1f2937;
      --card: #0b1220;
    }
    * { box-sizing: border-box; }
    html, body { height: 100%; }
    body {
      margin: 0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Helvetica Neue, Arial, "Apple Color Emoji", "Segoe UI Emoji";
      background: radial-gradient(1200px 800px at 0% 0%, #0b1020 0%, var(--bg) 35%, #070b17 100%);
      color: var(--text);
    }
    .container {
      max-width: 1100px;
      margin: 0 auto;
      padding: 32px 20px 48px;
    }
    header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 24px;
    }
    h1 {
      font-size: 24px;
      margin: 0;
      letter-spacing: 0.3px;
    }
    .subtitle {
      color: var(--muted);
      margin: 6px 0 0 0;
      font-size: 14px;
    }
    .panel {
      background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.0));
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 18px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.35);
    }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 14px;
    }
    .card {
      display: block;
      text-decoration: none;
      color: inherit;
      background: radial-gradient(600px 400px at 0% 0%, rgba(96,165,250,0.08), rgba(34,197,94,0.06) 60%, rgba(255,255,255,0.02) 100%), var(--card);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 16px;
      transition: transform 120ms ease, border-color 120ms ease, background 200ms ease, box-shadow 120ms ease;
      min-height: 84px;
    }
    .card:hover { transform: translateY(-2px); border-color: #2a3b52; box-shadow: 0 12px 26px rgba(0,0,0,0.45); }
    .card h3 { margin: 0 0 8px 0; font-size: 16px; font-weight: 600; }
    .card p { margin: 0; color: var(--muted); font-size: 13px; line-height: 1.35; }
    .section-title { margin: 18px 0 12px; font-size: 13px; color: var(--muted); letter-spacing: 0.3px; text-transform: uppercase; }
    .footer { margin-top: 30px; text-align: center; color: var(--muted); font-size: 12px; }
    .tag { display: inline-block; padding: 2px 8px; border-radius: 999px; background: rgba(96,165,250,0.12); color: #c7d2fe; border: 1px solid #273449; font-size: 11px; }
  </style>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 24 24'%3E%3Cpath fill='%23a7f3d0' d='M3 5a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v2H3z'/%3E%3Cpath fill='%2399f6e4' d='M3 9h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'/%3E%3Cpath fill='%2322c55e' d='M7 12h4v2H7zm0 3h6v2H7z'/%3E%3C/svg%3E" />
</head>
<body>
  <div class="container">
    <header>
      <div>
        <h1>AdminLog Dashboard</h1>
        <p class="subtitle">Quick links to all tools in this app.</p>
      </div>
      <span class="tag">v1</span>
    </header>

    <section class="panel" aria-labelledby="primary-links">
      <div class="section-title" id="primary-links">Primary</div>
      <div class="grid">
        <a class="card" href="admin_subscriptions.html">
          <h3>Admin Subscriptions</h3>
          <p>Analyze and view admin subscription data.</p>
        </a>
        <a class="card" href="payments.html">
          <h3>Payments</h3>
          <p>Inspect payment activity and summaries.</p>
        </a>
        <a class="card" href="adminlogs.html">
          <h3>Admin Logs</h3>
          <p>View administrative action logs.</p>
        </a>
        <a class="card" href="loginlogs.html">
          <h3>Login Logs</h3>
          <p>Review login attempts and activity.</p>
        </a>
        <a class="card" href="dashboard.html">
          <h3>Main Dashboard</h3>
          <p>Main dashboard page.</p>
        </a>
      </div>
    </section>

    <!-- <section class="panel" aria-labelledby="other-tools" style="margin-top:16px;">
      <div class="section-title" id="other-tools">Other Tools</div>
      <div class="grid">
        <a class="card" href="first_payment_analyzer.php">
          <h3>First Payment Analyzer</h3>
          <p>Run first-time payment checks.</p>
        </a>
        <a class="card" href="analyze_subscriptions.php">
          <h3>Analyze Subscriptions (PHP)</h3>
          <p>Server-side subscription analytics.</p>
        </a>
        <a class="card" href="fetch_logs.php">
          <h3>Fetch Logs</h3>
          <p>Retrieve general logs from server.</p>
        </a>
        <a class="card" href="fetch_login_logs.php">
          <h3>Fetch Login Logs</h3>
          <p>Pull latest login logs via PHP.</p>
        </a>
      </div>
    </section> -->

    <div class="footer">&copy; <?php echo date('Y'); ?> AdminLog</div>
  </div>
</body>
</html>
