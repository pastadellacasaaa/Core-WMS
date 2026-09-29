<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('heading') · Core WMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap">
    <style>
        /* ─────────────────────────────────────────────────────────────
           Core WMS · Agent Manager console
           Cold-chain instrumentation: calm slate surfaces, one warm
           accent for actions, and thermal colour reserved for the one
           fact that matters most about a location — how cold it is.
           ───────────────────────────────────────────────────────────── */
        :root {
            color-scheme: light dark;

            --ground:        #EFF2F4;
            --surface:       #FFFFFF;
            --surface-sunk:  #F5F7F8;
            --surface-bar:   #101A1E;
            --bar-ink:       #E7EDEF;
            --bar-dim:       #8FA2A9;

            --ink:           #0F1518;
            --ink-soft:      #47555B;
            --ink-faint:     #71818A;
            --rule:          #DCE2E5;
            --rule-firm:     #C3CCD1;

            --accent:        #A8542A;
            --accent-hover:  #8E4522;
            --accent-wash:   #FBEFE8;
            --accent-edge:   #E8C6B2;

            --ok:            #1B7048;
            --ok-wash:       #E8F5EE;
            --ok-edge:       #B4DCC6;
            --warn:          #8A5D10;
            --warn-wash:     #FBF1DE;
            --warn-edge:     #EAD3A2;
            --bad:           #A93129;
            --bad-wash:      #FBEBE9;
            --bad-edge:      #EDBFBA;

            --frozen:        #2D6BB5;
            --frozen-wash:   #E9F0F9;
            --chilled:       #217E8F;
            --chilled-wash:  #E4F2F4;
            --dry:           #8A6526;
            --dry-wash:      #F6F0E2;

            --sans: "IBM Plex Sans", ui-sans-serif, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            --mono: "IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;

            --radius: 10px;
            --radius-sm: 6px;
            --shadow: 0 1px 2px rgba(15, 21, 24, .05), 0 6px 20px -14px rgba(15, 21, 24, .35);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --ground:        #0B1013;
                --surface:       #141C20;
                --surface-sunk:  #101819;
                --surface-bar:   #080D0F;
                --bar-ink:       #E7EDEF;
                --bar-dim:       #7E9098;

                --ink:           #E8EEF0;
                --ink-soft:      #A6B4BA;
                --ink-faint:     #7E8D94;
                --rule:          #232E33;
                --rule-firm:     #33424A;

                --accent:        #E08A5C;
                --accent-hover:  #EFA279;
                --accent-wash:   #2A1A12;
                --accent-edge:   #4A2E1F;

                --ok:            #5FC08D;
                --ok-wash:       #10231A;
                --ok-edge:       #244432;
                --warn:          #D9A947;
                --warn-wash:     #241C0D;
                --warn-edge:     #453720;
                --bad:           #E8837A;
                --bad-wash:      #261614;
                --bad-edge:      #4A2A27;

                --frozen:        #6AA3E4;
                --frozen-wash:   #121E2C;
                --chilled:       #56B6C6;
                --chilled-wash:  #102428;
                --dry:           #C9A45E;
                --dry-wash:      #24200F;

                --shadow: 0 1px 2px rgba(0, 0, 0, .5), 0 8px 24px -16px rgba(0, 0, 0, .9);
            }
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--ground);
            color: var(--ink);
            font-family: var(--sans);
            font-size: 14px;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }

        /* ── top bar ───────────────────────────────────────────────── */
        .topbar {
            background: var(--surface-bar);
            color: var(--bar-ink);
            padding: 0 clamp(1rem, 3vw, 2rem);
        }
        .topbar-inner {
            max-width: 1240px; margin: 0 auto;
            min-height: 52px;
            display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
        }
        .mark { display: flex; align-items: baseline; gap: .5rem; }
        .mark-name {
            font-weight: 600; font-size: .9rem; letter-spacing: .09em; text-transform: uppercase;
        }
        .mark-sub { color: var(--bar-dim); font-size: .8rem; }
        .target {
            margin-left: auto; display: flex; align-items: center; gap: .45rem;
            font-family: var(--mono); font-size: .72rem; color: var(--bar-dim);
        }
        .target-dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: #5FC08D; box-shadow: 0 0 0 3px rgba(95, 192, 141, .18);
        }

        /* ── page shell ────────────────────────────────────────────── */
        main { max-width: 1240px; margin: 0 auto; padding: clamp(1.5rem, 3vw, 2.25rem) clamp(1rem, 3vw, 2rem) 4rem; }

        .page-head { display: flex; flex-direction: column; gap: .4rem; margin-bottom: 1.75rem; }
        .page-head h1 {
            margin: 0; font-size: clamp(1.3rem, 2.6vw, 1.6rem); font-weight: 600;
            letter-spacing: -.02em; text-wrap: balance;
        }
        .page-head .lede { margin: 0; color: var(--ink-soft); max-width: 78ch; }
        .endpoint {
            display: inline-flex; align-items: center; gap: .5rem; align-self: flex-start;
            margin-top: .35rem; padding: .3rem .6rem;
            background: var(--surface-sunk); border: 1px solid var(--rule);
            border-radius: var(--radius-sm);
            font-family: var(--mono); font-size: .74rem; color: var(--ink-soft);
            overflow-x: auto; max-width: 100%;
        }
        .endpoint b { color: var(--accent); font-weight: 600; }

        /* ── panels ────────────────────────────────────────────────── */
        .panel {
            background: var(--surface); border: 1px solid var(--rule);
            border-radius: var(--radius); box-shadow: var(--shadow);
            margin-bottom: 1.25rem; overflow: hidden;
        }
        .panel-head {
            display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
            padding: .7rem 1.15rem; border-bottom: 1px solid var(--rule);
            background: var(--surface-sunk);
        }
        .panel-title {
            font-family: var(--mono); font-size: .7rem; font-weight: 600;
            letter-spacing: .12em; text-transform: uppercase; color: var(--ink-soft);
        }
        .panel-note { font-size: .8rem; color: var(--ink-faint); }
        .panel-body { padding: 1.15rem; }
        .panel-body > :first-child { margin-top: 0; }
        .panel-body > :last-child { margin-bottom: 0; }

        .section-title {
            font-size: .95rem; font-weight: 600; margin: 0 0 .75rem;
            display: flex; align-items: center; gap: .55rem; flex-wrap: wrap;
        }

        /* ── status strip ──────────────────────────────────────────── */
        .status {
            margin-left: auto; display: inline-flex; align-items: center; gap: .5rem;
            font-family: var(--mono); font-size: .74rem;
        }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; flex: none; }
        .status.is-ok    { color: var(--ok); }
        .status.is-ok    .status-dot { background: var(--ok); }
        .status.is-bad   { color: var(--bad); }
        .status.is-bad   .status-dot { background: var(--bad); }
        .status-sep { color: var(--ink-faint); }

        /* ── forms ─────────────────────────────────────────────────── */
        .fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; }
        .field { display: flex; flex-direction: column; gap: .3rem; min-width: 0; }
        label {
            font-family: var(--mono); font-size: .68rem; font-weight: 500;
            letter-spacing: .1em; text-transform: uppercase; color: var(--ink-faint);
        }
        input, textarea {
            width: 100%; padding: .5rem .65rem;
            border: 1px solid var(--rule-firm); border-radius: var(--radius-sm);
            background: var(--surface); color: var(--ink);
            font-family: var(--sans); font-size: .9rem; line-height: 1.5;
        }
        input:hover, textarea:hover { border-color: var(--ink-faint); }
        input:focus-visible, textarea:focus-visible, button:focus-visible, a:focus-visible {
            outline: 2px solid var(--accent); outline-offset: 2px; border-color: var(--accent);
        }
        textarea { font-family: var(--mono); font-size: .8rem; resize: vertical; }
        .field-hint { font-size: .74rem; color: var(--ink-faint); }

        .actions { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; margin-top: 1.15rem; }
        button {
            font-family: var(--sans); font-size: .875rem; font-weight: 500;
            padding: .55rem 1.05rem; border-radius: var(--radius-sm);
            border: 1px solid transparent; cursor: pointer;
            transition: background .15s ease, border-color .15s ease, color .15s ease;
        }
        button.primary { background: var(--accent); color: #fff; }
        button.primary:hover { background: var(--accent-hover); }
        button.ghost { background: var(--surface); border-color: var(--rule-firm); color: var(--ink-soft); }
        button.ghost:hover { border-color: var(--ink-faint); color: var(--ink); }
        button.link {
            background: none; border: none; padding: .2rem .35rem;
            color: var(--ink-faint); font-size: .78rem;
        }
        button.link:hover { color: var(--bad); }
        button[disabled] { opacity: .6; cursor: progress; }

        .chips { display: flex; gap: .4rem; flex-wrap: wrap; }
        .chip {
            font-family: var(--mono); font-size: .72rem; padding: .3rem .6rem;
            background: var(--surface); border: 1px solid var(--rule-firm);
            border-radius: 999px; color: var(--ink-soft); cursor: pointer;
        }
        .chip:hover { border-color: var(--accent); color: var(--accent); }

        /* ── tables ────────────────────────────────────────────────── */
        .scroller { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        th, td { text-align: left; padding: .6rem .75rem; vertical-align: top; }
        th {
            font-family: var(--mono); font-size: .66rem; font-weight: 600;
            letter-spacing: .1em; text-transform: uppercase; color: var(--ink-faint);
            border-bottom: 1px solid var(--rule-firm); white-space: nowrap;
        }
        td { border-bottom: 1px solid var(--rule); }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: var(--surface-sunk); }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .code { font-family: var(--mono); font-size: .82rem; }

        .rank {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 22px; height: 22px; padding: 0 .35rem; border-radius: 5px;
            background: var(--accent-wash); border: 1px solid var(--accent-edge);
            color: var(--accent); font-family: var(--mono); font-size: .72rem; font-weight: 600;
        }
        .rank.plain { background: var(--surface-sunk); border-color: var(--rule-firm); color: var(--ink-soft); }

        .reasons { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: .25rem; }
        .reasons li {
            font-size: .8rem; color: var(--ink-soft);
            padding-left: .85rem; position: relative; max-width: 62ch;
        }
        .reasons li::before {
            content: ""; position: absolute; left: 0; top: .55em;
            width: 4px; height: 4px; border-radius: 50%; background: var(--rule-firm);
        }

        /* ── thermal zone chips (the console's signature) ──────────── */
        .thermal {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .18rem .5rem .18rem .4rem; border-radius: var(--radius-sm);
            font-family: var(--mono); font-size: .78rem; font-weight: 500;
            border: 1px solid transparent; white-space: nowrap;
        }
        .thermal-dot { width: 6px; height: 6px; border-radius: 50%; flex: none; }
        .thermal--frozen  { background: var(--frozen-wash);  color: var(--frozen);  border-color: color-mix(in srgb, var(--frozen) 28%, transparent); }
        .thermal--frozen  .thermal-dot { background: var(--frozen); }
        .thermal--chilled { background: var(--chilled-wash); color: var(--chilled); border-color: color-mix(in srgb, var(--chilled) 28%, transparent); }
        .thermal--chilled .thermal-dot { background: var(--chilled); }
        .thermal--dry     { background: var(--dry-wash);     color: var(--dry);     border-color: color-mix(in srgb, var(--dry) 28%, transparent); }
        .thermal--dry     .thermal-dot { background: var(--dry); }
        .thermal--unknown { background: var(--surface-sunk); color: var(--ink-soft); border-color: var(--rule-firm); }
        .thermal--unknown .thermal-dot { background: var(--ink-faint); }

        /* ── notices ───────────────────────────────────────────────── */
        .notice {
            display: flex; gap: .7rem; padding: .8rem 1rem;
            border-radius: var(--radius-sm); border: 1px solid; margin-bottom: 1.25rem;
            font-size: .875rem;
        }
        .notice-label {
            font-family: var(--mono); font-size: .68rem; font-weight: 600;
            letter-spacing: .1em; text-transform: uppercase; flex: none; padding-top: .1rem;
        }
        .notice.bad  { background: var(--bad-wash);  border-color: var(--bad-edge);  color: var(--bad); }
        .notice.warn { background: var(--warn-wash); border-color: var(--warn-edge); color: var(--warn); }
        .notice.ok   { background: var(--ok-wash);   border-color: var(--ok-edge);   color: var(--ok); }
        .notice-body { color: var(--ink); }
        .notice.bad .notice-body, .notice.warn .notice-body, .notice.ok .notice-body { color: inherit; }

        .empty {
            padding: 2.5rem 1.15rem; text-align: center; color: var(--ink-faint);
            font-size: .875rem;
        }

        pre.json {
            margin: 0; padding: 1rem 1.15rem; background: var(--surface-sunk);
            border-top: 1px solid var(--rule);
            overflow-x: auto; max-height: 460px;
            font-family: var(--mono); font-size: .76rem; line-height: 1.55; color: var(--ink-soft);
        }

        /* ── route map ─────────────────────────────────────────────── */
        .zone-split { display: flex; gap: 1.25rem; flex-wrap: wrap; align-items: flex-start; }
        .zone-split > .picks { flex: 1 1 460px; min-width: 0; }
        .zone-split > .maps { flex: 0 1 auto; display: flex; flex-direction: column; gap: 1rem; }
        .maps figure {
            margin: 0; padding: .7rem; background: var(--surface-sunk);
            border: 1px solid var(--rule); border-radius: var(--radius-sm);
        }
        .maps img { display: block; background: #fff; border-radius: 4px; max-width: 100%; height: auto; }
        .maps figcaption {
            margin-bottom: .5rem; text-align: right;
            font-family: var(--mono); font-size: .7rem; color: var(--ink-faint);
        }
        .saving { color: var(--ok); font-weight: 600; }

        code { font-family: var(--mono); font-size: .85em; }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="topbar-inner">
        <div class="mark">
            <span class="mark-name">Core WMS</span>
            <span class="mark-sub">To Simulate Agent Routing</span>
        </div>
        <div class="target">
            <span class="target-dot" aria-hidden="true"></span>
            <span>{{ parse_url((string) config('services.agent_manager.base_url'), PHP_URL_HOST) ?: 'agent manager' }}</span>
        </div>
    </div>
</header>

<main>
    <div class="page-head">
        <h1>@yield('heading')</h1>
        <p class="lede">@yield('lede')</p>
        @hasSection('endpoint')
            <span class="endpoint"><b>POST</b> @yield('endpoint')</span>
        @endif
    </div>

    @if ($errors->any())
        <div class="notice bad">
            <span class="notice-label">Invalid</span>
            <div class="notice-body">
                @foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach
            </div>
        </div>
    @endif

    @if (! empty($error))
        <div class="notice bad">
            <span class="notice-label">Failed</span>
            <div class="notice-body">{{ $error }}</div>
        </div>
    @endif

    @yield('content')
</main>

<script>
    // These calls take a few seconds. Say so, rather than looking frozen.
    document.querySelectorAll('form[data-busy]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button.primary');
            if (button) {
                button.dataset.idle = button.textContent;
                button.textContent = form.dataset.busy;
                button.disabled = true;
            }
        });
    });
</script>
@stack('scripts')
</body>
</html>
