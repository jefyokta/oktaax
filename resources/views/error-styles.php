:root {
    color-scheme: light;
    --paper: #f7f8f2;
    --surface: #fff;
    --ink: #192c25;
    --muted: #63736b;
    --line: #dce3d9;
    --green: #245b43;
    --lime: #d9ed9b;
    --danger: #b1442c;
    --mono:
        ui-monospace, SFMono-Regular, Consolas, 'Liberation Mono', monospace;
}
* {
    box-sizing: border-box;
}
body {
    margin: 0;
    background: var(--paper);
    color: var(--ink);
    font:
        16px/1.6 -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        sans-serif;
    -webkit-font-smoothing: antialiased;
}
a {
    color: inherit;
}
a:focus-visible,
summary:focus-visible {
    outline: 3px solid var(--green);
    outline-offset: 5px;
}
::selection {
    background: var(--lime);
    color: var(--ink);
}
.shell {
    width: min(1184px, calc(100% - 96px));
    margin: auto;
    min-height: 100svh;
    display: flex;
    flex-direction: column;
}
.masthead {
    min-height: 112px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    border-bottom: 1px solid var(--line);
}
.brand {
    display: inline-flex;
    align-items: center;
    gap: 11px;
    text-decoration: none;
    font-size: 19px;
    font-weight: 750;
    letter-spacing: -0.6px;
}
.brand-mark {
    width: 27px;
    height: 27px;
    background: var(--ink);
    border-radius: 8px;
    display: grid;
    place-items: center;
    color: var(--lime);
}
.brand-mark svg {
    width: 17px;
    height: 17px;
}
.label,
.eyebrow {
    font: 11px/1.5 var(--mono);
    letter-spacing: 1.8px;
    text-transform: uppercase;
}
.label {
    color: var(--muted);
}
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    border: 1px solid var(--line);
    border-radius: 30px;
    padding: 7px 13px;
    font: 11px/1.5 var(--mono);
    white-space: nowrap;
}
.dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #b97e28;
}
.http-main {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 7%;
    align-items: center;
    padding: 88px 0 104px;
}
.status-art {
    position: relative;
    min-height: 320px;
    display: flex;
    align-items: center;
    justify-content: center;
    isolation: isolate;
}
.status-art:before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(var(--line) 1px, transparent 1px),
        linear-gradient(90deg, var(--line) 1px, transparent 1px);
    background-size: 32px 32px;
    mask-image: radial-gradient(ellipse, #000 20%, transparent 72%);
    z-index: -2;
}
.status-code {
    font-size: clamp(112px, 16vw, 210px);
    line-height: 1;
    font-weight: 750;
    letter-spacing: -0.085em;
    position: relative;
    padding-right: 0.085em;
}
.route-art {
    position: absolute;
    width: 100%;
    height: 100%;
    inset: 0;
    z-index: -1;
    color: #8baa75;
}
.status-note {
    position: absolute;
    bottom: 5px;
    left: 50%;
    transform: translateX(-50%);
    font: 10px var(--mono);
    letter-spacing: 1.7px;
    color: var(--muted);
    white-space: nowrap;
}
.eyebrow {
    color: var(--green);
    margin: 0 0 20px;
    display: flex;
    gap: 9px;
    align-items: center;
}
.eyebrow:before {
    content: '';
    display: block;
    width: 22px;
    height: 1px;
    background: currentColor;
}
h1 {
    font-size: clamp(32px, 4.5vw, 52px);
    line-height: 1.12;
    letter-spacing: -2px;
    font-weight: 650;
    margin: 0 0 22px;
    overflow-wrap: anywhere;
}
.description {
    max-width: 430px;
    color: var(--muted);
    line-height: 1.8;
    margin: 0 0 30px;
}
.actions {
    display: flex;
    align-items: center;
    gap: 22px;
    flex-wrap: wrap;
}
.button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 22px;
    padding: 12px 20px;
    background: var(--ink);
    color: #fff;
    border: 1px solid var(--ink);
    border-radius: 7px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    min-height: 46px;
}
.button:hover {
    background: var(--green);
    border-color: var(--green);
}
.text-link {
    font-size: 13px;
    text-underline-offset: 5px;
    text-decoration-color: #a4b3a4;
}
.request-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    align-items: center;
    margin-top: 38px;
    padding-top: 22px;
    border-top: 1px solid var(--line);
    font: 11px/1.7 var(--mono);
    color: var(--muted);
}
.method {
    font-weight: 700;
    color: var(--green);
}
.request-path {
    overflow-wrap: anywhere;
    min-width: 0;
}
.footer {
    padding: 23px 0;
    border-top: 1px solid var(--line);
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: var(--muted);
    font-size: 11px;
}
.footer a {
    text-underline-offset: 3px;
}
.debug-main {
    padding: 44px 0 60px;
    flex: 1;
}
.debug-topline {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 26px;
}
.debug-topline .eyebrow {
    margin: 0;
    color: var(--danger);
}
.debug-flag {
    font: 10px var(--mono);
    letter-spacing: 1px;
    border: 1px solid #dfbfaf;
    background: #fff3e9;
    color: #8e452b;
    padding: 5px 9px;
    border-radius: 4px;
}
.exception-class {
    color: var(--danger);
    font: 12px/1.6 var(--mono);
    overflow-wrap: anywhere;
    margin: 0 0 12px;
}
.exception-title {
    font-size: clamp(27px, 3.3vw, 42px);
    line-height: 1.25;
    max-width: 1000px;
    white-space: pre-wrap;
}
.debug-request {
    display: flex;
    gap: 14px;
    align-items: baseline;
    font: 12px/1.8 var(--mono);
    padding: 17px 0 28px;
    overflow-wrap: anywhere;
}
.debug-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(240px, 1fr);
    gap: 22px;
    align-items: start;
}
.panel {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 10px;
    overflow: hidden;
    min-width: 0;
}
.panel-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    font-size: 12px;
    font-weight: 600;
}
.panel-count {
    font: 10px var(--mono);
    color: var(--muted);
}
.file-location {
    padding: 16px 20px;
    font: 11px/1.8 var(--mono);
    overflow-wrap: anywhere;
}
.file-location span {
    color: var(--muted);
}
.source-code {
    background: #192c25;
    color: #dfebdc;
    overflow: auto;
    padding: 18px 0;
    font: 12px/1.9 var(--mono);
    tab-size: 4;
}
.source-line {
    display: flex;
    min-width: max-content;
    padding: 0 20px 0 0;
    border-left: 3px solid transparent;
}
.source-line.active {
    background: #304330;
    border-color: #d9ed9b;
}
.line-number {
    flex: 0 0 57px;
    user-select: none;
    color: #91a18f;
    text-align: right;
    padding-right: 18px;
}
.source-line.active .line-number {
    color: var(--lime);
}
.source-line code {
    font: inherit;
    white-space: pre;
    display: block;
}
.trace-list {
    margin: 0;
    padding: 0;
    list-style: none;
}
.trace-item {
    border-top: 1px solid var(--line);
    padding: 13px 16px;
    font: 11px/1.7 var(--mono);
    overflow-wrap: anywhere;
}
.trace-item:first-child {
    border-top: 0;
}
.trace-call {
    color: var(--ink);
    margin-top: 7px;
    white-space: pre-wrap;
}
.trace-file {
    color: var(--muted);
    margin-top: 7px;
}
.trace-item summary {
    cursor: pointer;
    display: list-item;
    list-style-position: inside;
    color: var(--muted);
}
.trace-item details[open] summary {
    color: var(--green);
}
.trace-scroll {
    max-height: 540px;
    overflow: auto;
}
.debug-note {
    color: var(--muted);
    font-size: 12px;
    margin: 22px 0 0;
}
.debug-note code {
    font: 11px var(--mono);
    color: var(--ink);
}
.empty-source {
    margin: 0;
    padding: 25px 20px;
    color: var(--muted);
    font-size: 12px;
}
.error-name {
    margin-top: 14px;
    color: var(--muted);
    font: 11px var(--mono);
}
@media (min-width: 1500px) {
    .http-main {
        padding-top: 120px;
        padding-bottom: 140px;
    }
}
@media (max-width: 760px) {
    .shell {
        width: calc(100% - 40px);
    }
    .masthead {
        min-height: 80px;
    }
    .masthead > .label {
        display: none;
    }
    .http-main {
        grid-template-columns: 1fr;
        gap: 38px;
        padding: 35px 0 52px;
    }
    .status-art {
        min-height: 230px;
    }
    .status-code {
        font-size: 148px;
    }
    .status-note {
        bottom: 0;
    }
    .http-copy {
        max-width: 480px;
        margin: auto;
        width: 100%;
    }
    h1 {
        letter-spacing: -1.3px;
    }
    .description {
        margin-bottom: 26px;
    }
    .request-strip {
        margin-top: 28px;
    }
    .footer {
        font-size: 10px;
        flex-wrap: wrap;
    }
    .debug-main {
        padding: 30px 0 40px;
    }
    .debug-grid {
        grid-template-columns: 1fr;
    }
    .debug-topline {
        gap: 10px;
    }
    .debug-flag {
        font-size: 9px;
    }
    .exception-title {
        letter-spacing: -1px;
    }
    .panel-header {
        padding: 14px 16px;
    }
    .trace-scroll {
        max-height: none;
    }
    .debug-request {
        font-size: 11px;
        gap: 10px;
    }
}
@media (prefers-reduced-motion: no-preference) {
    .button {
        transition:
            background 0.15s,
            border-color 0.15s;
    }
}
