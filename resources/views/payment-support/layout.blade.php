<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Support a medical bill') &middot; eMed</title>
    <style>
        /*
          Self contained on purpose. The person opening this link is a friend of
          a patient, on whatever phone they happen to have, and possibly on a bad
          connection — so there is no build step, no CDN and no font to fetch.
        */
        :root {
            --purple: #6d3ff2;
            --purple-dark: #5a2fd8;
            --purple-soft: #f3efff;
            --ink: #1a1a2e;
            --muted: #6b6b80;
            --line: #e8e6f0;
            --green: #16a34a;
            --red: #dc2626;
            --bg: #faf9ff;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font: 15px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .wrap { max-width: 460px; margin: 0 auto; padding: 24px 18px 56px; }

        .brand {
            display: flex; align-items: center; justify-content: center;
            gap: 8px; margin-bottom: 22px;
            font-weight: 700; letter-spacing: -0.3px; color: var(--purple);
        }
        .brand-mark {
            width: 30px; height: 30px; border-radius: 50%;
            background: var(--purple); color: #fff;
            display: grid; place-items: center; font-size: 14px;
        }

        .card {
            background: #fff; border: 1px solid var(--line);
            border-radius: 16px; padding: 20px; margin-bottom: 14px;
        }

        h1 { font-size: 20px; line-height: 1.35; margin: 0 0 6px; letter-spacing: -0.3px; }
        h2 { font-size: 15px; margin: 0 0 14px; }
        p  { margin: 0 0 10px; }
        .muted { color: var(--muted); }
        .center { text-align: center; }
        .small { font-size: 13px; }

        .avatar {
            width: 62px; height: 62px; border-radius: 50%;
            background: var(--purple); color: #fff;
            display: grid; place-items: center;
            font-size: 24px; font-weight: 700; margin: 0 auto 12px;
        }

        .amount-row { display: flex; gap: 12px; margin: 14px 0 10px; }
        .amount-row > div { flex: 1; }
        .amount-label { font-size: 12px; color: var(--muted); margin-bottom: 2px; }
        .amount-value { font-size: 17px; font-weight: 700; }
        .amount-value.raised { color: var(--green); }

        .bar { height: 8px; border-radius: 99px; background: var(--line); overflow: hidden; }
        .bar > span { display: block; height: 100%; background: var(--purple); border-radius: 99px; }
        .bar-meta { display: flex; justify-content: space-between; margin-top: 6px; font-size: 12px; color: var(--muted); }

        .goal {
            background: var(--purple-soft); border-radius: 12px;
            padding: 14px; margin-bottom: 14px;
        }
        .goal-label { font-size: 12px; color: var(--purple); font-weight: 600; margin-bottom: 2px; }
        .goal-value { font-size: 24px; font-weight: 700; letter-spacing: -0.5px; }

        label { display: block; font-size: 13px; font-weight: 600; margin: 14px 0 6px; }

        input[type=text], input[type=email], input[type=number] {
            width: 100%; padding: 12px 14px; font-size: 15px;
            border: 1px solid var(--line); border-radius: 10px;
            background: #fff; color: var(--ink);
        }
        input:focus { outline: 2px solid var(--purple); outline-offset: -1px; border-color: transparent; }

        .chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .chip {
            border: 1px solid var(--line); background: #fff; color: var(--ink);
            border-radius: 99px; padding: 8px 14px; font-size: 14px;
            cursor: pointer; font-weight: 600;
        }
        .chip:hover { border-color: var(--purple); color: var(--purple); }

        .check { display: flex; gap: 10px; align-items: flex-start; margin-top: 14px; }
        .check input { margin-top: 3px; }

        .note {
            display: flex; gap: 10px; align-items: flex-start;
            background: var(--purple-soft); border-radius: 12px;
            padding: 12px 14px; margin: 16px 0; font-size: 13px;
        }

        button.primary {
            width: 100%; padding: 14px; margin-top: 16px;
            background: var(--purple); color: #fff;
            border: 0; border-radius: 12px;
            font-size: 15px; font-weight: 700; cursor: pointer;
        }
        button.primary:hover { background: var(--purple-dark); }
        button.primary:disabled { opacity: .6; cursor: not-allowed; }

        .alert { border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; font-size: 14px; }
        .alert.error { background: #fef2f2; color: var(--red); border: 1px solid #fecaca; }

        .status-icon {
            width: 68px; height: 68px; border-radius: 50%;
            display: grid; place-items: center; margin: 0 auto 14px;
            font-size: 32px; color: #fff;
        }
        .status-icon.ok   { background: var(--green); }
        .status-icon.bad  { background: var(--red); }

        .kv { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid var(--line); font-size: 14px; }
        .kv:last-child { border-bottom: 0; }
        .kv span:first-child { color: var(--muted); }
        .kv span:last-child { font-weight: 600; text-align: right; }

        .supporters { margin-top: 4px; }
        .supporter { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--line); font-size: 14px; }
        .supporter:last-child { border-bottom: 0; }
        .supporter .when { font-size: 12px; color: var(--muted); }

        .foot { text-align: center; font-size: 12px; color: var(--muted); margin-top: 18px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">
            <span class="brand-mark">e</span> eMed Diaries
        </div>

        @yield('content')

        <p class="foot">Payments are processed securely by Paystack.</p>
    </div>
</body>
</html>
