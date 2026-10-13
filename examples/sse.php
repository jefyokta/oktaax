<?php

use JefyOkta\PhpPromise\Promise;
use Oktaax\Core\Application;
use Oktaax\Core\Container;
use Oktaax\Event\Event;
use Oktaax\Event\EventEmitter;
use Oktaax\Http\Support\SSEResponse;
use Oktaax\Oktaax;

use function Oktaax\Utils\setTimeout;

require_once __DIR__ . "/../vendor/autoload.php";

$app = new Oktaax;

function event(): EventEmitter
{
    /**
     * @disregard
     */
    return Container::get('event');
}
// hei gpt please make sse usage example bellow, with good ui, use tailwind cdn

/**
 * Dashboard UI
 */
$app->get("/", function () {
    Application::getResponse()->header(
        'Content-Type',
        'text/html; charset=utf-8'
    );

    return <<<'HTML'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#080b12">
    <title>Pulse — Event Operations</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui'],
                        mono: ['JetBrains Mono', 'ui-monospace', 'monospace']
                    },
                    colors: {
                        ink: '#080b12',
                        panel: '#0d111b',
                        line: '#202838',
                        accent: '#8b9cff'
                    },
                    boxShadow: {
                        glow: '0 0 32px rgba(111, 130, 255, .12)'
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { scrollbar-width: thin; scrollbar-color: #30384a transparent; }
        body { background-image: radial-gradient(ellipse at 50% -20%, rgba(86,102,210,.13), transparent 45%); }
        .nav-link.active { color: #fff; background: rgba(139,156,255,.1); border-color: rgba(139,156,255,.18); }
        .nav-link.active .nav-icon { color: #aebaff; }
        .grid-bg { background-image: linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px); background-size: 28px 28px; }
        .pulse-dot { box-shadow: 0 0 0 4px rgba(74,222,128,.08), 0 0 14px rgba(74,222,128,.45); }
        .log-row:hover { background: rgba(255,255,255,.025); }
        .mono { font-family: 'DM Mono', ui-monospace, monospace; }
        .view { display: none; }
        .view.active { display: block; }
        .toast { animation: toast-in .18s ease-out; }
        @keyframes toast-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
    </style>
</head>
<body class="min-h-screen text-slate-300 antialiased">
<div class="min-h-screen lg:grid lg:grid-cols-[244px_minmax(0,1fr)]">
    <aside class="border-b border-line bg-[#090d15]/90 lg:sticky lg:top-0 lg:h-screen lg:border-b-0 lg:border-r">
        <div class="flex items-center justify-between px-5 py-5 lg:px-6">
            <a href="#" class="flex items-center gap-3" data-view="overview">
                <div class="grid h-10 w-10 place-items-center rounded-xl border border-indigo-300/20 bg-indigo-400/10 text-indigo-200 shadow-glow">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 2 4 14h7l-1 8 10-13h-7V2Z"/></svg>
                </div>
                <div>
                    <div class="text-[15px] font-bold tracking-tight text-white">pulse<span class="text-indigo-300">.</span></div>
                    <div class="mt-0.5 text-[9px] font-semibold tracking-[.22em] text-slate-600">EVENT OPERATIONS</div>
                </div>
            </a>
            <span class="rounded-md border border-line px-2 py-1 text-[10px] text-slate-500 lg:hidden">DEV</span>
        </div>

        <div class="px-3 pb-4 lg:px-4">
            <div class="mb-2 px-3 pt-4 text-[10px] font-bold uppercase tracking-[.18em] text-slate-600">Workspace</div>
            <nav class="flex gap-1 overflow-x-auto lg:flex-col" aria-label="Main navigation">
                <button class="nav-link active flex shrink-0 items-center gap-3 rounded-lg border border-transparent px-3 py-2.5 text-left text-[12px] font-medium text-slate-400 transition hover:bg-white/[.04]" data-view="overview">
                    <svg class="nav-icon h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
                    Overview
                </button>
                <button class="nav-link flex shrink-0 items-center gap-3 rounded-lg border border-transparent px-3 py-2.5 text-left text-[12px] font-medium text-slate-400 transition hover:bg-white/[.04]" data-view="playground">
                    <svg class="nav-icon h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="m8 8-4 4 4 4m8-8 4 4-4 4m-3-11-2 14"/></svg>
                    Event playground
                </button>
                <button class="nav-link flex shrink-0 items-center gap-3 rounded-lg border border-transparent px-3 py-2.5 text-left text-[12px] font-medium text-slate-400 transition hover:bg-white/[.04]" data-view="stream">
                    <svg class="nav-icon h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l3-8 4 16 3-8h4"/></svg>
                    Live stream
                    <span id="nav-live-dot" class="ml-auto hidden h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                </button>
                <button class="nav-link flex shrink-0 items-center gap-3 rounded-lg border border-transparent px-3 py-2.5 text-left text-[12px] font-medium text-slate-400 transition hover:bg-white/[.04]" data-view="history">
                    <svg class="nav-icon h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12a9 9 0 1 0 2.64-6.36L3 8m0-5v5h5m4-1v5l3 2"/></svg>
                    Event history
                </button>
            </nav>

            <div class="mb-2 mt-8 hidden px-3 text-[10px] font-bold uppercase tracking-[.18em] text-slate-600 lg:block">Connection</div>
            <div class="hidden rounded-xl border border-line bg-white/[.02] p-3 lg:block">
                <div class="flex items-center gap-2">
                    <span id="sidebar-dot" class="h-2 w-2 rounded-full bg-amber-400"></span>
                    <span id="sidebar-status" class="text-xs font-medium text-slate-300">Connecting</span>
                    <span class="ml-auto text-[9px] text-slate-600">SSE</span>
                </div>
                <div class="mt-3 flex items-center justify-between border-t border-line pt-3">
                    <span class="text-[10px] text-slate-500">Endpoint</span>
                    <code class="mono text-[10px] text-indigo-300">/stream</code>
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <span class="text-[10px] text-slate-500">Transport</span>
                    <span class="text-[10px] text-slate-400">HTTP stream</span>
                </div>
            </div>
        </div>

        <div class="hidden px-6 pb-6 lg:absolute lg:bottom-0 lg:block">
            <div class="mb-3 h-px w-48 bg-line"></div>
            <div class="flex items-center gap-2 text-[10px] text-slate-600">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-400"></span>
                Oktaax playground
                <span class="ml-auto mono">v1.0</span>
            </div>
        </div>
    </aside>

    <div class="min-w-0">
        <header class="sticky top-0 z-20 flex h-[62px] items-center justify-between border-b border-line bg-[#080b12]/85 px-5 backdrop-blur-xl sm:px-8">
            <div class="flex min-w-0 items-center gap-2 text-xs">
                <span class="hidden text-slate-600 sm:inline">Workspace</span>
                <svg class="hidden h-3 w-3 text-slate-700 sm:block" viewBox="0 0 16 16" fill="none"><path d="m6 3 5 5-5 5" stroke="currentColor" stroke-width="1.5"/></svg>
                <span id="breadcrumb" class="truncate font-medium text-slate-200">Overview</span>
            </div>
            <div class="flex items-center gap-3">
                <div class="hidden items-center gap-2 rounded-lg border border-line bg-white/[.025] px-3 py-1.5 sm:flex">
                    <span class="mono text-[10px] text-slate-500">ENV</span>
                    <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
                    <span class="text-[11px] text-slate-300">Development</span>
                </div>
                <button id="top-reconnect" class="flex items-center gap-2 rounded-lg border border-line px-3 py-2 text-[11px] font-medium text-slate-300 transition hover:border-indigo-300/30 hover:bg-white/[.04]">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7v5h-5M4 17v-5h5m-4-3a8 8 0 0 1 13.7-2.7L20 12M4 12l1.3 5.7A8 8 0 0 0 19 15"/></svg>
                    Reconnect
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-[1600px] p-5 sm:p-8">
            <section id="view-overview" class="view active">
                <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div class="mb-3 flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[.2em] text-indigo-300">
                            <span class="h-px w-5 bg-indigo-400"></span>
                            Real-time infrastructure
                        </div>
                        <h1 class="text-2xl font-semibold tracking-tight text-white sm:text-[30px]">Event operations</h1>
                        <p class="mt-2 max-w-xl text-xs leading-6 text-slate-500 sm:text-sm">Monitor server-sent events, dispatch test payloads, and inspect your event stream from one workspace.</p>
                    </div>
                    <button data-go="playground" class="flex items-center gap-2 rounded-lg bg-indigo-300 px-4 py-2.5 text-xs font-semibold text-[#0b1020] shadow-glow transition hover:bg-indigo-200">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14"/></svg>
                        Dispatch event
                    </button>
                </div>

                <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-line bg-panel p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">Connection status</span>
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-400/10 text-emerald-300"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h3l2-6 4 12 2-6h3"/></svg></span>
                        </div>
                        <div id="overview-status" class="mt-4 text-2xl font-semibold tracking-tight text-white">Connecting</div>
                        <div class="mt-2 flex items-center gap-2 text-[10px] text-slate-500"><span id="overview-status-dot" class="h-1.5 w-1.5 rounded-full bg-amber-400"></span><span id="connected-since">Waiting for stream</span></div>
                    </div>
                    <div class="rounded-xl border border-line bg-panel p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">Events received</span>
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-sky-400/10 text-sky-300"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 4v16m-6-6 6 6 6-6"/></svg></span>
                        </div>
                        <div id="overview-received" class="mt-4 text-2xl font-semibold tracking-tight text-white">0</div>
                        <div class="mt-2 text-[10px] text-slate-500">Since page opened</div>
                    </div>
                    <div class="rounded-xl border border-line bg-panel p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">Events dispatched</span>
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-violet-400/10 text-violet-300"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5L20 7"/></svg></span>
                        </div>
                        <div id="overview-sent" class="mt-4 text-2xl font-semibold tracking-tight text-white">0</div>
                        <div class="mt-2 text-[10px] text-slate-500">Successful HTTP requests</div>
                    </div>
                    <div class="rounded-xl border border-line bg-panel p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-500">Visible history</span>
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-amber-400/10 text-amber-300"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M4 12h16M4 19h10"/></svg></span>
                        </div>
                        <div id="overview-history" class="mt-4 text-2xl font-semibold tracking-tight text-white">0</div>
                        <div class="mt-2 text-[10px] text-slate-500">Events in local session</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(300px,.8fr)]">
                    <section class="overflow-hidden rounded-xl border border-line bg-panel">
                        <div class="flex items-center justify-between border-b border-line px-5 py-4">
                            <div><h2 class="text-sm font-semibold text-white">Recent activity</h2><p class="mt-1 text-[11px] text-slate-500">Latest messages observed in this session</p></div>
                            <button data-go="history" class="text-[11px] font-medium text-indigo-300 hover:text-indigo-200">View history →</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="border-b border-line bg-white/[.015] text-[9px] uppercase tracking-[.15em] text-slate-600">
                                    <tr><th class="px-5 py-3 font-medium">Event</th><th class="px-4 py-3 font-medium">Direction</th><th class="px-4 py-3 font-medium">Time</th><th class="px-5 py-3 text-right font-medium">Status</th></tr>
                                </thead>
                                <tbody id="recent-table"><tr><td colspan="4" class="px-5 py-12 text-center text-xs text-slate-600">Waiting for activity…</td></tr></tbody>
                            </table>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-xl border border-line bg-panel">
                        <div class="border-b border-line px-5 py-4"><h2 class="text-sm font-semibold text-white">Quick actions</h2><p class="mt-1 text-[11px] text-slate-500">Jump into common workflows</p></div>
                        <div class="space-y-2 p-3">
                            <button data-go="playground" class="group flex w-full items-center gap-3 rounded-lg border border-transparent p-3 text-left transition hover:border-line hover:bg-white/[.025]">
                                <span class="grid h-9 w-9 place-items-center rounded-lg bg-indigo-400/10 text-indigo-300"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m8 8-4 4 4 4m8-8 4 4-4 4m-3-11-2 14"/></svg></span>
                                <span class="min-w-0 flex-1"><span class="block text-xs font-medium text-slate-200">Build a payload</span><span class="mt-1 block text-[10px] text-slate-500">Compose and dispatch a custom event</span></span>
                                <span class="text-slate-600 transition group-hover:translate-x-0.5 group-hover:text-indigo-300">→</span>
                            </button>
                            <button data-go="stream" class="group flex w-full items-center gap-3 rounded-lg border border-transparent p-3 text-left transition hover:border-line hover:bg-white/[.025]">
                                <span class="grid h-9 w-9 place-items-center rounded-lg bg-emerald-400/10 text-emerald-300"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 12h4l3-8 4 16 3-8h4"/></svg></span>
                                <span class="min-w-0 flex-1"><span class="block text-xs font-medium text-slate-200">Inspect live stream</span><span class="mt-1 block text-[10px] text-slate-500">Watch events as they arrive</span></span>
                                <span class="text-slate-600 transition group-hover:translate-x-0.5 group-hover:text-indigo-300">→</span>
                            </button>
                            <button data-go="history" class="group flex w-full items-center gap-3 rounded-lg border border-transparent p-3 text-left transition hover:border-line hover:bg-white/[.025]">
                                <span class="grid h-9 w-9 place-items-center rounded-lg bg-amber-400/10 text-amber-300"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 12a9 9 0 1 0 2.64-6.36L3 8m0-5v5h5m4-1v5l3 2"/></svg></span>
                                <span class="min-w-0 flex-1"><span class="block text-xs font-medium text-slate-200">Search event history</span><span class="mt-1 block text-[10px] text-slate-500">Filter events and inspect payloads</span></span>
                                <span class="text-slate-600 transition group-hover:translate-x-0.5 group-hover:text-indigo-300">→</span>
                            </button>
                        </div>
                        <div class="mx-4 mb-4 rounded-lg border border-line bg-[#090d15] p-3">
                            <div class="mb-2 flex items-center gap-2 text-[10px] font-semibold text-slate-400"><span class="h-1.5 w-1.5 rounded-full bg-indigo-300"></span>Endpoints</div>
                            <div class="flex items-center justify-between py-1"><code class="mono text-[10px] text-slate-400">GET /stream</code><span class="text-[9px] text-emerald-300">SSE</span></div>
                            <div class="flex items-center justify-between py-1"><code class="mono text-[10px] text-slate-400">POST /dispatch</code><span class="text-[9px] text-sky-300">JSON</span></div>
                        </div>
                    </section>
                </div>

                <section class="mt-4 overflow-hidden rounded-xl border border-line bg-panel">
                    <div class="flex items-center justify-between border-b border-line px-5 py-4"><div><h2 class="text-sm font-semibold text-white">Event activity</h2><p class="mt-1 text-[11px] text-slate-500">Incoming and outgoing events over the current session</p></div><span class="mono text-[10px] text-slate-600">SESSION</span></div>
                    <div class="grid-bg relative h-32 overflow-hidden px-5">
                        <div class="absolute inset-x-0 top-1/4 border-t border-dashed border-white/[.05]"></div><div class="absolute inset-x-0 top-2/4 border-t border-dashed border-white/[.05]"></div><div class="absolute inset-x-0 top-3/4 border-t border-dashed border-white/[.05]"></div>
                        <div id="activity-bars" class="absolute inset-x-5 bottom-0 flex h-full items-end gap-1.5 pb-0"></div>
                    </div>
                    <div class="flex items-center gap-4 border-t border-line px-5 py-3 text-[10px] text-slate-500"><span class="flex items-center gap-2"><span class="h-2 w-2 rounded-sm bg-indigo-300"></span>Received</span><span class="flex items-center gap-2"><span class="h-2 w-2 rounded-sm bg-sky-400"></span>Dispatched</span><span class="ml-auto">Visualization uses this page session only</span></div>
                </section>
            </section>

            <section id="view-playground" class="view">
                <div class="mb-8">
                    <div class="mb-3 flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[.2em] text-indigo-300"><span class="h-px w-5 bg-indigo-400"></span>Developer tools</div>
                    <h1 class="text-2xl font-semibold tracking-tight text-white sm:text-[30px]">Event playground</h1>
                    <p class="mt-2 text-xs leading-6 text-slate-500 sm:text-sm">Compose a JSON payload and dispatch it through your application event emitter.</p>
                </div>
                <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(300px,.72fr)]">
                    <section class="overflow-hidden rounded-xl border border-line bg-panel">
                        <div class="flex items-center justify-between border-b border-line px-5 py-4"><div><h2 class="text-sm font-semibold text-white">Compose event</h2><p class="mt-1 text-[11px] text-slate-500">Request builder · JSON</p></div><span class="rounded-md border border-line px-2 py-1 text-[9px] font-semibold text-slate-500">POST</span></div>
                        <form id="dispatch-form" class="space-y-5 p-5">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div><label for="event-type" class="mb-2 block text-[11px] font-semibold text-slate-400">Event name</label><select id="event-type" class="w-full rounded-lg border border-line bg-[#090d15] px-3 py-3 text-xs text-slate-200 outline-none focus:border-indigo-300/60"><option value="notification">notification</option><option value="message">message</option><option value="progress">progress</option><option value="custom">custom…</option></select></div>
                                <div id="custom-event-wrap" class="hidden"><label for="custom-event" class="mb-2 block text-[11px] font-semibold text-slate-400">Custom event name</label><input id="custom-event" maxlength="80" placeholder="order.updated" class="w-full rounded-lg border border-line bg-[#090d15] px-3 py-3 text-xs text-slate-200 outline-none focus:border-indigo-300/60"></div>
                                <div><label for="endpoint" class="mb-2 block text-[11px] font-semibold text-slate-400">Endpoint</label><div class="flex overflow-hidden rounded-lg border border-line bg-[#090d15]"><span class="border-r border-line px-3 py-3 text-[10px] font-semibold text-sky-300">POST</span><input id="endpoint" value="/dispatch" class="mono min-w-0 flex-1 bg-transparent px-3 py-3 text-[11px] text-slate-300 outline-none" readonly></div></div>
                            </div>
                            <div><div class="mb-2 flex items-center justify-between"><label for="payload" class="text-[11px] font-semibold text-slate-400">Request payload</label><button id="format-json" type="button" class="text-[10px] font-medium text-indigo-300 hover:text-indigo-200">Format JSON ↗</button></div><div class="overflow-hidden rounded-lg border border-line bg-[#090d15] focus-within:border-indigo-300/50"><div class="flex items-center gap-2 border-b border-line px-4 py-2"><span class="h-2 w-2 rounded-full bg-rose-400/80"></span><span class="h-2 w-2 rounded-full bg-amber-300/80"></span><span class="h-2 w-2 rounded-full bg-emerald-400/80"></span><span class="ml-2 mono text-[10px] text-slate-600">payload.json</span></div><textarea id="payload" spellcheck="false" class="mono min-h-[260px] w-full resize-y bg-transparent px-4 py-4 text-xs leading-6 text-emerald-200 outline-none" aria-label="JSON request payload">{
  "message": "Hello from Pulse",
  "source": "event-playground",
  "timestamp": "{{timestamp}}"
}</textarea></div><p id="json-hint" class="mt-2 text-[10px] text-slate-600">JSON body is sent to the selected dispatch endpoint.</p></div>
                            <div id="form-error" class="hidden rounded-lg border border-rose-400/20 bg-rose-400/[.06] px-4 py-3 text-xs text-rose-300"></div>
                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4"><div class="text-[10px] text-slate-600">Content-Type: <code class="text-slate-400">application/json</code></div><div class="flex gap-2"><button id="reset-payload" type="button" class="rounded-lg border border-line px-4 py-2.5 text-[11px] font-medium text-slate-400 transition hover:bg-white/[.04] hover:text-white">Reset</button><button id="send-button" type="submit" class="flex items-center gap-2 rounded-lg bg-indigo-300 px-4 py-2.5 text-[11px] font-semibold text-[#0b1020] transition hover:bg-indigo-200 disabled:opacity-50"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4 20-7ZM22 2 11 13"/></svg>Dispatch event</button></div></div>
                        </form>
                    </section>
                    <div class="space-y-4">
                        <section class="overflow-hidden rounded-xl border border-line bg-panel">
                            <div class="border-b border-line px-5 py-4"><h2 class="text-sm font-semibold text-white">Request preview</h2><p class="mt-1 text-[11px] text-slate-500">Outgoing HTTP request</p></div>
                            <div class="space-y-4 p-5">
                                <div><div class="mb-2 text-[9px] font-bold uppercase tracking-[.15em] text-slate-600">URL</div><div class="mono break-all rounded-lg border border-line bg-[#090d15] px-3 py-3 text-[11px] text-slate-300" id="preview-url">POST /dispatch</div></div>
                                <div><div class="mb-2 text-[9px] font-bold uppercase tracking-[.15em] text-slate-600">Event name</div><div id="preview-event" class="mono rounded-lg border border-line bg-[#090d15] px-3 py-3 text-[11px] text-violet-300">notification</div></div>
                                <div><div class="mb-2 text-[9px] font-bold uppercase tracking-[.15em] text-slate-600">Payload size</div><div id="preview-size" class="text-sm font-semibold text-white">— <span class="text-[10px] font-normal text-slate-500">bytes</span></div></div>
                            </div>
                        </section>
                        <section class="overflow-hidden rounded-xl border border-line bg-panel">
                            <div class="border-b border-line px-5 py-4"><h2 class="text-sm font-semibold text-white">Dispatch response</h2><p class="mt-1 text-[11px] text-slate-500">Latest request result</p></div>
                            <div id="dispatch-response" class="p-5"><div class="flex min-h-24 flex-col items-center justify-center text-center"><span class="mb-3 grid h-9 w-9 place-items-center rounded-lg border border-line text-slate-600"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 5h8M8 9h8m-8 4h5M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg></span><p class="text-xs text-slate-500">No request sent yet</p></div></div>
                        </section>
                        <section class="rounded-xl border border-indigo-300/15 bg-indigo-300/[.04] p-4"><div class="flex gap-3"><svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 11v5m0-8h.01"/></svg><div><h3 class="text-xs font-semibold text-indigo-100">How dispatch works</h3><p class="mt-2 text-[11px] leading-5 text-slate-400">The payload is sent to <code class="text-indigo-200">POST /dispatch</code>. Your server then emits the selected event name. All active SSE listeners registered for that event can receive it.</p></div></div></section>
                    </div>
                </div>
            </section>

            <section id="view-stream" class="view">
                <div class="mb-8 flex flex-wrap items-end justify-between gap-4"><div><div class="mb-3 flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[.2em] text-emerald-300"><span class="h-px w-5 bg-emerald-400"></span>Streaming inspector</div><h1 class="text-2xl font-semibold tracking-tight text-white sm:text-[30px]">Live stream</h1><p class="mt-2 text-xs leading-6 text-slate-500 sm:text-sm">Inspect incoming SSE frames and event payloads in real time.</p></div><div class="flex gap-2"><button id="stream-clear" class="rounded-lg border border-line px-3 py-2.5 text-[11px] text-slate-400 hover:bg-white/[.04]">Clear stream</button><button id="stream-reconnect" class="rounded-lg border border-line px-3 py-2.5 text-[11px] text-slate-300 hover:bg-white/[.04]">Reconnect</button></div></div>
                <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-line bg-panel p-4"><div class="text-[10px] text-slate-500">Connection</div><div id="stream-status" class="mt-2 text-lg font-semibold text-white">Connecting</div></div>
                    <div class="rounded-xl border border-line bg-panel p-4"><div class="text-[10px] text-slate-500">Frames observed</div><div id="stream-count" class="mt-2 text-lg font-semibold text-white">0</div></div>
                    <div class="rounded-xl border border-line bg-panel p-4"><div class="text-[10px] text-slate-500">Last event</div><div id="stream-last" class="mt-2 truncate mono text-sm text-white">—</div></div>
                </div>
                <section class="overflow-hidden rounded-xl border border-line bg-panel">
                    <div class="flex items-center justify-between border-b border-line bg-[#0a0e17] px-4 py-3"><div class="flex items-center gap-2"><span class="pulse-dot h-2 w-2 rounded-full bg-emerald-400"></span><span class="text-[11px] font-semibold text-slate-300">Event inspector</span><span class="mono text-[10px] text-slate-600">/stream</span></div><span id="stream-live-label" class="mono text-[9px] uppercase tracking-wider text-slate-600">Listening</span></div>
                    <div id="stream-list" class="max-h-[650px] min-h-[400px] space-y-2 overflow-y-auto p-3"><div id="stream-empty" class="flex min-h-[370px] flex-col items-center justify-center text-center"><div class="mb-4 grid h-12 w-12 place-items-center rounded-xl border border-line bg-white/[.02] text-slate-600"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 12h4l3-8 4 16 3-8h4"/></svg></div><p class="text-xs font-medium text-slate-400">Waiting for incoming frames</p><p class="mt-2 text-[10px] text-slate-600">Dispatch an event from the playground to see it here.</p></div></div>
                </section>
            </section>

            <section id="view-history" class="view">
                <div class="mb-8 flex flex-wrap items-end justify-between gap-4"><div><div class="mb-3 flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[.2em] text-amber-300"><span class="h-px w-5 bg-amber-400"></span>Session records</div><h1 class="text-2xl font-semibold tracking-tight text-white sm:text-[30px]">Event history</h1><p class="mt-2 text-xs leading-6 text-slate-500 sm:text-sm">Search, filter, and inspect events captured during this page session.</p></div><button id="history-export" class="flex items-center gap-2 rounded-lg border border-line px-3 py-2.5 text-[11px] font-medium text-slate-300 hover:bg-white/[.04]"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m-5-5 5 5 5-5M5 17v4h14v-4"/></svg>Export JSON</button></div>
                <section class="overflow-hidden rounded-xl border border-line bg-panel">
                    <div class="flex flex-col gap-3 border-b border-line p-4 sm:flex-row">
                        <div class="relative flex-1"><svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m16 16 4 4"/></svg><input id="history-search" placeholder="Search event name or payload…" class="w-full rounded-lg border border-line bg-[#090d15] py-2.5 pl-9 pr-3 text-xs text-slate-200 outline-none placeholder:text-slate-600 focus:border-indigo-300/50"></div>
                        <select id="history-filter" class="rounded-lg border border-line bg-[#090d15] px-3 py-2.5 text-xs text-slate-300 outline-none focus:border-indigo-300/50"><option value="all">All event types</option><option value="notification">notification</option><option value="message">message</option><option value="progress">progress</option><option value="system">system</option><option value="error">error</option></select>
                        <select id="history-direction" class="rounded-lg border border-line bg-[#090d15] px-3 py-2.5 text-xs text-slate-300 outline-none focus:border-indigo-300/50"><option value="all">All directions</option><option value="in">Incoming</option><option value="out">Outgoing</option></select>
                    </div>
                    <div class="flex items-center justify-between border-b border-line px-4 py-3"><span id="history-result-count" class="text-[10px] text-slate-500">0 records</span><button id="history-clear" class="text-[10px] text-slate-500 hover:text-rose-300">Clear history</button></div>
                    <div class="overflow-x-auto"><table class="w-full text-left"><thead class="bg-white/[.015] text-[9px] uppercase tracking-[.15em] text-slate-600"><tr><th class="px-4 py-3 font-medium">Event</th><th class="px-4 py-3 font-medium">Direction</th><th class="px-4 py-3 font-medium">Payload preview</th><th class="px-4 py-3 font-medium">Time</th><th class="px-4 py-3 font-medium"></th></tr></thead><tbody id="history-table"><tr><td colspan="5" class="px-4 py-14 text-center text-xs text-slate-600">No events recorded yet.</td></tr></tbody></table></div>
                </section>
                <div class="mt-3 text-[10px] text-slate-600">History is held in browser memory and resets when the page is reloaded.</div>
            </section>

            <footer class="mt-10 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5 text-[10px] text-slate-600"><span>Pulse Event Operations <span class="text-slate-700">·</span> Built for local development</span><span class="flex items-center gap-2"><span class="mono">GET /stream</span><span class="text-slate-700">|</span><span class="mono">POST /dispatch</span></span></footer>
        </main>
    </div>
</div>

<div id="toast-root" class="fixed bottom-5 right-5 z-50 flex max-w-[calc(100vw-40px)] flex-col gap-2"></div>

<div id="detail-modal" class="fixed inset-0 z-40 hidden items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
    <div class="w-full max-w-2xl overflow-hidden rounded-xl border border-line bg-[#0d111b] shadow-2xl">
        <div class="flex items-center justify-between border-b border-line px-5 py-4"><div><div class="text-[9px] font-bold uppercase tracking-[.18em] text-slate-600">Event details</div><h2 id="modal-title" class="mt-1 text-sm font-semibold text-white">Payload</h2></div><button id="modal-close" class="rounded-lg border border-line px-3 py-1.5 text-xs text-slate-400 hover:bg-white/[.05]">Close</button></div>
        <pre id="modal-payload" class="mono max-h-[65vh] overflow-auto whitespace-pre-wrap break-words p-5 text-xs leading-6 text-emerald-200"></pre>
    </div>
</div>

<script>
(() => {
    const $ = (selector) => document.querySelector(selector);
    const $$ = (selector) => [...document.querySelectorAll(selector)];

    const state = {
        stream: null,
        connectedAt: null,
        received: 0,
        sent: 0,
        sequence: 0,
        records: [],
        bars: Array.from({ length: 32 }, () => ({ in: 0, out: 0 })),
        activeView: 'overview'
    };

    const labels = {
        overview: 'Overview',
        playground: 'Event playground',
        stream: 'Live stream',
        history: 'Event history'
    };

    const eventColors = {
        notification: 'border-violet-300/20 bg-violet-300/10 text-violet-200',
        message: 'border-sky-300/20 bg-sky-300/10 text-sky-200',
        progress: 'border-amber-300/20 bg-amber-300/10 text-amber-200',
        system: 'border-slate-400/20 bg-slate-400/10 text-slate-300',
        error: 'border-rose-300/20 bg-rose-300/10 text-rose-200'
    };

    function escapeText(value) {
        return String(value ?? '');
    }

    function formatTime(value = new Date()) {
        return new Date(value).toLocaleTimeString(undefined, {
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            fractionalSecondDigits: 3
        });
    }

    function pretty(value) {
        if (typeof value === 'string') {
            try { return JSON.stringify(JSON.parse(value), null, 2); }
            catch { return value; }
        }
        return JSON.stringify(value, null, 2);
    }

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = 'toast flex items-center gap-3 rounded-lg border px-4 py-3 text-xs shadow-2xl ' +
            (type === 'error'
                ? 'border-rose-300/20 bg-[#160e15] text-rose-200'
                : 'border-emerald-300/20 bg-[#0c1717] text-emerald-200');
        const dot = document.createElement('span');
        dot.className = 'h-2 w-2 shrink-0 rounded-full ' + (type === 'error' ? 'bg-rose-400' : 'bg-emerald-400');
        const text = document.createElement('span');
        text.textContent = message;
        toast.append(dot, text);
        $('#toast-root').append(toast);
        setTimeout(() => toast.remove(), 3500);
    }

    function navigate(view) {
        if (!labels[view]) return;
        state.activeView = view;
        $$('.view').forEach((node) => node.classList.toggle('active', node.id === `view-${view}`));
        $$('.nav-link').forEach((node) => node.classList.toggle('active', node.dataset.view === view));
        $('#breadcrumb').textContent = labels[view];
        if (view === 'history') renderHistory();
    }

    $$('[data-view], [data-go]').forEach((node) => {
        node.addEventListener('click', (event) => {
            event.preventDefault();
            navigate(node.dataset.view || node.dataset.go);
        });
    });

    function setConnection(status, text) {
        const connected = status === 'connected';
        const disconnected = status === 'disconnected';
        const dotClass = connected ? 'bg-emerald-400' : disconnected ? 'bg-rose-400' : 'bg-amber-400 animate-pulse';

        $('#sidebar-dot').className = `h-2 w-2 rounded-full ${dotClass}`;
        $('#sidebar-status').textContent = text;
        $('#overview-status').textContent = text;
        $('#stream-status').textContent = text;
        $('#overview-status-dot').className = `h-1.5 w-1.5 rounded-full ${dotClass}`;
        $('#nav-live-dot').classList.toggle('hidden', !connected);
        $('#stream-live-label').textContent = connected ? 'Connected' : disconnected ? 'Disconnected' : 'Reconnecting';
        $('#stream-live-label').className = `mono text-[9px] uppercase tracking-wider ${connected ? 'text-emerald-300' : disconnected ? 'text-rose-300' : 'text-amber-300'}`;

        if (connected && !state.connectedAt) {
            state.connectedAt = Date.now();
            $('#connected-since').textContent = 'Connected just now';
        } else if (disconnected) {
            $('#connected-since').textContent = 'Connection closed';
        } else if (!connected) {
            $('#connected-since').textContent = 'Waiting for stream';
        }
    }

    function updateStats() {
        $('#overview-received').textContent = state.received;
        $('#overview-sent').textContent = state.sent;
        $('#overview-history').textContent = state.records.length;
        $('#stream-count').textContent = state.received;
        $('#stream-last').textContent = state.records[0]?.type || '—';
        renderBars();
        if (state.activeView === 'history') renderHistory();
    }

    function renderBars() {
        const container = $('#activity-bars');
        container.replaceChildren();
        const max = Math.max(1, ...state.bars.map((bar) => bar.in + bar.out));
        state.bars.forEach((bar) => {
            const group = document.createElement('div');
            group.className = 'flex h-full min-w-0 flex-1 items-end justify-center gap-[2px]';
            const incoming = document.createElement('div');
            incoming.className = 'w-1/2 rounded-t-sm bg-indigo-300/80 transition-all';
            incoming.style.height = `${Math.max(bar.in ? 5 : 1, bar.in / max * 78)}%`;
            const outgoing = document.createElement('div');
            outgoing.className = 'w-1/2 rounded-t-sm bg-sky-400/80 transition-all';
            outgoing.style.height = `${Math.max(bar.out ? 5 : 1, bar.out / max * 78)}%`;
            group.append(incoming, outgoing);
            container.append(group);
        });
    }

    function pushRecord(type, payload, direction = 'in', status = 'received', meta = {}) {
        const record = {
            id: ++state.sequence,
            type: String(type || 'message'),
            payload,
            direction,
            status,
            time: new Date().toISOString(),
            ...meta
        };

        state.records.unshift(record);
        if (state.records.length > 500) state.records.length = 500;

        if (direction === 'in') {
            state.received++;
            state.bars[state.bars.length - 1].in++;
        } else if (direction === 'out') {
            state.bars[state.bars.length - 1].out++;
        }

        renderRecent();
        renderStreamRecord(record);
        updateStats();
        return record;
    }

    function badge(type) {
        const node = document.createElement('span');
        node.className = `inline-flex max-w-[180px] truncate rounded-md border px-2 py-1 mono text-[10px] ${eventColors[type] || eventColors.system}`;
        node.textContent = type;
        return node;
    }

    function renderRecent() {
        const tbody = $('#recent-table');
        const records = state.records.slice(0, 6);
        tbody.replaceChildren();

        if (!records.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.className = 'px-5 py-12 text-center text-xs text-slate-600';
            cell.textContent = 'Waiting for activity…';
            row.append(cell);
            tbody.append(row);
            return;
        }

        records.forEach((record) => {
            const row = document.createElement('tr');
            row.className = 'log-row border-b border-line/60 last:border-0';
            const eventCell = document.createElement('td');
            eventCell.className = 'px-5 py-3';
            eventCell.append(badge(record.type));
            const directionCell = document.createElement('td');
            directionCell.className = 'px-4 py-3 text-[10px] text-slate-400';
            directionCell.textContent = record.direction === 'in' ? '↓ Incoming' : '↑ Outgoing';
            const timeCell = document.createElement('td');
            timeCell.className = 'mono whitespace-nowrap px-4 py-3 text-[10px] text-slate-500';
            timeCell.textContent = formatTime(record.time);
            const statusCell = document.createElement('td');
            statusCell.className = 'px-5 py-3 text-right';
            const status = document.createElement('span');
            status.className = 'text-[10px] ' + (record.status === 'error' ? 'text-rose-300' : 'text-emerald-300');
            status.textContent = record.status === 'error' ? 'Failed' : 'OK';
            statusCell.append(status);
            row.append(eventCell, directionCell, timeCell, statusCell);
            tbody.append(row);
        });
    }

    function renderStreamRecord(record) {
        $('#stream-empty')?.remove();

        const card = document.createElement('article');
        card.className = 'rounded-lg border border-line bg-[#090d15] p-4 transition hover:border-slate-600/70';
        const head = document.createElement('div');
        head.className = 'mb-3 flex flex-wrap items-center gap-2';
        head.append(badge(record.type));

        const direction = document.createElement('span');
        direction.className = 'text-[9px] font-semibold tracking-wide ' + (record.direction === 'in' ? 'text-emerald-300' : 'text-sky-300');
        direction.textContent = record.direction === 'in' ? '↓ RECEIVED' : '↑ DISPATCHED';

        const time = document.createElement('time');
        time.className = 'mono ml-auto text-[10px] text-slate-600';
        time.textContent = formatTime(record.time);
        head.append(direction, time);

        const pre = document.createElement('pre');
        pre.className = 'mono overflow-x-auto whitespace-pre-wrap break-words text-[11px] leading-6 text-slate-300';
        pre.textContent = pretty(record.payload);

        const foot = document.createElement('div');
        foot.className = 'mt-3 flex items-center justify-between border-t border-line/70 pt-3';
        const id = document.createElement('span');
        id.className = 'mono text-[9px] text-slate-600';
        id.textContent = `FRAME #${String(record.id).padStart(4, '0')}`;
        const detail = document.createElement('button');
        detail.className = 'text-[10px] text-indigo-300 hover:text-indigo-200';
        detail.textContent = 'Inspect payload ↗';
        detail.addEventListener('click', () => showDetail(record));
        foot.append(id, detail);

        card.append(head, pre, foot);
        $('#stream-list').prepend(card);

        while ($('#stream-list').children.length > 100) {
            $('#stream-list').lastElementChild.remove();
        }
    }

    function renderHistory() {
        const tbody = $('#history-table');
        const query = $('#history-search').value.trim().toLowerCase();
        const type = $('#history-filter').value;
        const direction = $('#history-direction').value;

        const filtered = state.records.filter((record) => {
            const matchesType = type === 'all' || record.type === type;
            const matchesDirection = direction === 'all' || record.direction === direction;
            const matchesQuery = !query || record.type.toLowerCase().includes(query) || pretty(record.payload).toLowerCase().includes(query);
            return matchesType && matchesDirection && matchesQuery;
        });

        $('#history-result-count').textContent = `${filtered.length} ${filtered.length === 1 ? 'record' : 'records'}`;
        tbody.replaceChildren();

        if (!filtered.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 5;
            cell.className = 'px-4 py-14 text-center text-xs text-slate-600';
            cell.textContent = state.records.length ? 'No events match your filters.' : 'No events recorded yet.';
            row.append(cell);
            tbody.append(row);
            return;
        }

        filtered.forEach((record) => {
            const row = document.createElement('tr');
            row.className = 'log-row border-b border-line/60 last:border-0';
            const eventCell = document.createElement('td');
            eventCell.className = 'whitespace-nowrap px-4 py-3';
            eventCell.append(badge(record.type));
            const directionCell = document.createElement('td');
            directionCell.className = 'whitespace-nowrap px-4 py-3 text-[10px] text-slate-400';
            directionCell.textContent = record.direction === 'in' ? '↓ Incoming' : '↑ Outgoing';
            const payloadCell = document.createElement('td');
            payloadCell.className = 'max-w-[300px] px-4 py-3';
            const payloadPreview = document.createElement('div');
            payloadPreview.className = 'mono truncate text-[10px] text-slate-500';
            payloadPreview.textContent = pretty(record.payload).replace(/\s+/g, ' ');
            payloadCell.append(payloadPreview);
            const timeCell = document.createElement('td');
            timeCell.className = 'mono whitespace-nowrap px-4 py-3 text-[10px] text-slate-500';
            timeCell.textContent = formatTime(record.time);
            const actionCell = document.createElement('td');
            actionCell.className = 'whitespace-nowrap px-4 py-3 text-right';
            const inspect = document.createElement('button');
            inspect.className = 'text-[10px] text-indigo-300 hover:text-indigo-200';
            inspect.textContent = 'Inspect';
            inspect.addEventListener('click', () => showDetail(record));
            actionCell.append(inspect);
            row.append(eventCell, directionCell, payloadCell, timeCell, actionCell);
            tbody.append(row);
        });
    }

    function showDetail(record) {
        $('#modal-title').textContent = `${record.type} · ${formatTime(record.time)}`;
        $('#modal-payload').textContent = JSON.stringify({
            id: record.id,
            type: record.type,
            direction: record.direction === 'in' ? 'incoming' : 'outgoing',
            status: record.status,
            timestamp: record.time,
            payload: record.payload
        }, null, 2);
        $('#detail-modal').classList.remove('hidden');
        $('#detail-modal').classList.add('flex');
    }

    function clearHistory() {
        state.records = [];
        state.received = 0;
        state.sent = 0;
        state.bars = Array.from({ length: 32 }, () => ({ in: 0, out: 0 }));
        $('#stream-list').replaceChildren();
        $('#stream-list').innerHTML = '<div id="stream-empty" class="flex min-h-[370px] flex-col items-center justify-center text-center"><p class="text-xs text-slate-500">History cleared. Waiting for incoming frames.</p></div>';
        renderRecent();
        updateStats();
        renderHistory();
    }

    function connect() {
        if (state.stream) state.stream.close();
        setConnection('connecting', 'Connecting');
        state.connectedAt = null;
        const stream = new EventSource('/stream');
        state.stream = stream;

        stream.onopen = () => {
            setConnection('connected', 'Connected');
            pushRecord('system', { message: 'SSE connection established' }, 'in', 'received', { system: true });
            showToast('SSE connection established');
        };

        stream.onmessage = (event) => {
            let payload = event.data;
            try { payload = JSON.parse(event.data); } catch {}
            pushRecord('message', payload, 'in', 'received', { lastEventId: event.lastEventId || null });
        };

        ['notification', 'message', 'progress'].forEach((type) => {
            stream.addEventListener(type, (event) => {
                let payload = event.data;
                try { payload = JSON.parse(event.data); } catch {}
                pushRecord(type, payload, 'in', 'received', { lastEventId: event.lastEventId || null });
            });
        });

        stream.onerror = () => {
            if (stream.readyState === EventSource.CLOSED) {
                setConnection('disconnected', 'Disconnected');
            } else {
                setConnection('connecting', 'Reconnecting');
            }
        };
    }

    $('#top-reconnect').addEventListener('click', connect);
    $('#stream-reconnect').addEventListener('click', connect);
    $('#stream-clear').addEventListener('click', () => {
        $('#stream-list').replaceChildren();
        $('#stream-list').innerHTML = '<div id="stream-empty" class="flex min-h-[370px] flex-col items-center justify-center text-center"><p class="text-xs text-slate-500">Stream view cleared.</p></div>';
    });

    $('#modal-close').addEventListener('click', () => {
        $('#detail-modal').classList.add('hidden');
        $('#detail-modal').classList.remove('flex');
    });
    $('#detail-modal').addEventListener('click', (event) => {
        if (event.target === $('#detail-modal')) $('#modal-close').click();
    });

    $('#event-type').addEventListener('change', () => {
        const custom = $('#event-type').value === 'custom';
        $('#custom-event-wrap').classList.toggle('hidden', !custom);
        updatePreview();
    });

    function selectedEvent() {
        const type = $('#event-type').value;
        return type === 'custom' ? $('#custom-event').value.trim() : type;
    }

    function updatePreview() {
        $('#preview-url').textContent = `POST ${$('#endpoint').value}`;
        $('#preview-event').textContent = selectedEvent() || '(enter a custom event name)';
        try {
            const parsed = JSON.parse($('#payload').value.replaceAll('{{timestamp}}', new Date().toISOString()));
            $('#preview-size').innerHTML = `${new TextEncoder().encode(JSON.stringify(parsed)).length} <span class="text-[10px] font-normal text-slate-500">bytes</span>`;
            $('#json-hint').textContent = 'Valid JSON · Ready to dispatch';
            $('#json-hint').className = 'mt-2 text-[10px] text-emerald-300';
        } catch {
            $('#preview-size').textContent = 'Invalid JSON';
            $('#json-hint').textContent = 'Check the JSON syntax before dispatching.';
            $('#json-hint').className = 'mt-2 text-[10px] text-rose-300';
        }
    }

    $('#payload').addEventListener('input', updatePreview);
    $('#custom-event').addEventListener('input', updatePreview);
    $('#format-json').addEventListener('click', () => {
        try {
            const value = JSON.parse($('#payload').value);
            $('#payload').value = JSON.stringify(value, null, 2);
            updatePreview();
            showToast('JSON formatted');
        } catch {
            showToast('Cannot format invalid JSON', 'error');
        }
    });

    $('#reset-payload').addEventListener('click', () => {
        $('#payload').value = JSON.stringify({
            message: 'Hello from Pulse',
            source: 'event-playground',
            timestamp: '{{timestamp}}'
        }, null, 2);
        $('#event-type').value = 'notification';
        $('#custom-event-wrap').classList.add('hidden');
        updatePreview();
    });

    $('#dispatch-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const errorBox = $('#form-error');
        errorBox.classList.add('hidden');

        let payload;
        try {
            payload = JSON.parse($('#payload').value.replaceAll('{{timestamp}}', new Date().toISOString()));
        } catch {
            errorBox.textContent = 'Payload is not valid JSON. Fix the syntax and try again.';
            errorBox.classList.remove('hidden');
            return;
        }

        const type = selectedEvent();
        if (!type || !/^[a-zA-Z][a-zA-Z0-9_.:-]{0,79}$/.test(type)) {
            errorBox.textContent = 'Event name must start with a letter and contain only letters, numbers, _, ., :, or -.';
            errorBox.classList.remove('hidden');
            return;
        }

        const button = $('#send-button');
        button.disabled = true;
        button.textContent = 'Dispatching…';
        const startedAt = performance.now();

        try {
            const response = await fetch($('#endpoint').value, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ event: type, payload })
            });

            const responseText = await response.text();
            let responseBody = responseText;
            try { responseBody = JSON.parse(responseText); } catch {}

            const elapsed = Math.round(performance.now() - startedAt);
            $('#dispatch-response').replaceChildren();

            const box = document.createElement('div');
            box.className = 'rounded-lg border p-4 ' + (response.ok ? 'border-emerald-300/20 bg-emerald-300/[.04]' : 'border-rose-300/20 bg-rose-300/[.04]');
            const heading = document.createElement('div');
            heading.className = 'flex items-center justify-between text-xs font-semibold ' + (response.ok ? 'text-emerald-200' : 'text-rose-200');
            heading.textContent = response.ok ? 'Request successful' : 'Request failed';
            const status = document.createElement('span');
            status.className = 'mono text-[10px]';
            status.textContent = `HTTP ${response.status}`;
            heading.append(status);
            const meta = document.createElement('div');
            meta.className = 'mt-2 text-[10px] text-slate-500';
            meta.textContent = `${elapsed} ms · ${formatTime()}`;
            const pre = document.createElement('pre');
            pre.className = 'mono mt-4 overflow-x-auto whitespace-pre-wrap break-words text-[10px] leading-5 text-slate-300';
            pre.textContent = pretty(responseBody);
            box.append(heading, meta, pre);
            $('#dispatch-response').append(box);

            if (!response.ok) throw new Error(typeof responseBody === 'string' ? responseBody : `HTTP ${response.status}`);

            state.sent++;
            // A successful dispatch is an outgoing request record; the SSE echo is recorded independently.
            pushRecord(type, payload, 'out', 'sent', { httpStatus: response.status, duration: elapsed });
            showToast(`Event "${type}" dispatched`);
        } catch (error) {
            errorBox.textContent = error.message || 'Unable to dispatch event.';
            errorBox.classList.remove('hidden');
            pushRecord('error', { message: error.message || 'Dispatch failed' }, 'out', 'error');
            showToast(error.message || 'Dispatch failed', 'error');
        } finally {
            button.disabled = false;
            button.innerHTML = '<svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4 20-7ZM22 2 11 13"/></svg>Dispatch event';
            updatePreview();
        }
    });

    ['history-search', 'history-filter', 'history-direction'].forEach((selector) => {
        $(selector).addEventListener('input', renderHistory);
        $(selector).addEventListener('change', renderHistory);
    });

    $('#history-clear').addEventListener('click', () => {
        clearHistory();
        showToast('Session history cleared');
    });

    $('#history-export').addEventListener('click', () => {
        const blob = new Blob([JSON.stringify(state.records, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `pulse-events-${new Date().toISOString().replace(/[:.]/g, '-')}.json`;
        link.click();
        URL.revokeObjectURL(url);
        showToast('Event history exported');
    });

    updatePreview();
    renderRecent();
    renderBars();
    connect();
})();
</script>
</body>
</html>
HTML;
});

/**
 * SSE stream endpoint.
 */
$app->get("/stream", function () {
    $sse = new SSEResponse;
    $sse->start(Application::getResponse());
    $sse->send([], ["event" => "connect"]);
    $events = ['notification', 'message', 'progress', 'custom'];

    $listener = function (Event $event) use ($sse) {
        if (!$sse->isStopped()) {
            $sse->send($event->data, ['event' => $event->name]);
        }
    };

    foreach ($events as $type) {
        event()->on($type, $listener);
    }
    $sse->on("close", function () use ($events, $listener) {
        foreach ($events as $ev) {
            event()->off($ev, $listener);
        }
    });
    setTimeout(function () use ($events, $listener, $sse) {
        $sse->send("Limit reach", ["event" => "closed"]);
        $sse->stop();
        foreach ($events as $ev) {
            event()->off($ev, $listener);
        }
    }, 10000);
});

/**
 * Dispatch event endpoint.
 */
$app->post("/dispatch", function () {
    $data = Application::getRequest()->json();

    $type = $data['event'] ?? 'notification';
    $payload = $data['payload'] ?? null;

    if (
        !is_string($type)
        || !preg_match('/^[a-zA-Z][a-zA-Z0-9_.:-]{0,79}$/', $type)
    ) {
        throw new InvalidArgumentException('Invalid event name.');
    }

    if (!is_array($payload) && !is_string($payload) && !is_numeric($payload) && !is_bool($payload) && $payload !== null) {
        throw new InvalidArgumentException('Invalid event payload.');
    }

    event()->emit($type, $payload);

    return [
        'success' => true,
        'event' => $type,
        'timestamp' => date(DATE_ATOM),
    ];
});

$app->listen(3000, function ($url) {
    echo "Pulse dashboard running at {$url}\n";
});
