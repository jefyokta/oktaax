const $ = (selector) => document.querySelector(selector);
const log = (direction, value) => {
    const list = $('#log');
    const item = document.createElement('li');
    item.className = direction;
    const symbol = { incoming: '↓', outgoing: '↑', system: '·' }[direction];
    const text = typeof value === 'string' ? value : JSON.stringify(value);
    // User-controlled text is never interpreted as HTML. Keep rendering and memory bounded.
    item.textContent = `${new Date().toLocaleTimeString()} ${symbol} ${text.slice(0, 4000)}`;
    list.append(item);
    while (list.children.length > 200) list.firstElementChild.remove();
    list.scrollTop = list.scrollHeight;
};

async function api(path, data) {
    const response = await fetch(path, {
        method: data === undefined ? 'GET' : 'POST',
        credentials: 'same-origin',
        headers:
            data === undefined ? {} : { 'Content-Type': 'application/json' },
        body: data === undefined ? undefined : JSON.stringify(data),
        cache: 'no-store',
    });
    if (!response.ok)
        throw new Error(
            `HTTP ${response.status}: request was rejected. Check the account, password, and allowed origin.`,
        );
    return response.status === 204 ? null : response.json();
}

class SocketClient {
    socket = null;
    path = '/socket/chat';
    phase = 'disconnected';
    wantConnection = false;
    retry = 0;
    reconnectTimer = null;
    pending = new Map();
    rooms = new Set();
    desiredRooms = null;
    clockInterval = null;
    resumeAfterPageShow = false;
    generation = 0;

    setPhase(phase, label = phase) {
        this.phase = phase;
        $('#status').textContent = label;
        $('#status').dataset.phase = phase;
        refreshControls();
    }

    connect(path = this.path) {
        this.disconnect();
        this.path = path;
        this.wantConnection = true;
        this.retry = 0;
        void this.open();
    }

    async open() {
        clearTimeout(this.reconnectTimer);
        if (!this.wantConnection || this.socket) return;
        this.setPhase('connecting');
        const generation = this.generation;
        try {
            // Browser WebSocket errors cannot expose the HTTP handshake status.
            const { user } = await api('/demo/session');
            if (
                generation !== this.generation ||
                !this.wantConnection ||
                this.socket
            )
                return;
            if (currentUser?.id !== user?.id) {
                this.desiredRooms = null;
                this.clockInterval = null;
            }
            setUser(user);
            if (!user) throw new Error('Sign in before connecting.');
            if (this.path === '/socket/status' && user.role !== 'admin')
                throw new Error(
                    'The monitor endpoint requires the admin account.',
                );
        } catch (error) {
            if (generation !== this.generation) return;
            this.wantConnection = false;
            this.setPhase('disconnected');
            showError(error);
            return;
        }
        const path = this.path;
        const url = new URL(path, location.href);
        url.protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
        const socket = new WebSocket(url);
        this.socket = socket;
        socket.binaryType = 'arraybuffer';
        socket.onopen = () => {
            if (this.socket !== socket) return;
            this.setPhase(
                'connecting',
                'Handshake accepted; waiting for ready',
            );
            log('system', `Open ${url.pathname}`);
        };
        socket.onmessage = ({ data }) => {
            if (this.socket !== socket) return;
            try {
                const text =
                    typeof data === 'string'
                        ? data
                        : new TextDecoder('utf-8', { fatal: true }).decode(
                              data,
                          );
                log(
                    'incoming',
                    typeof data === 'string' ? text : `[binary JSON] ${text}`,
                );
                const wire = JSON.parse(text);
                const packet =
                    path === '/socket/alternate'
                        ? {
                              event: wire.type,
                              data: wire.payload,
                              id: wire.request_id,
                          }
                        : wire;
                if (!packet || typeof packet.event !== 'string')
                    throw new Error('Unexpected server envelope.');
                this.receive(packet);
            } catch (error) {
                showError(error);
            }
        };
        socket.onerror = () => {
            if (this.socket === socket)
                log(
                    'system',
                    'Transport error; inspect the following close event.',
                );
        };
        socket.onclose = ({ code, reason, wasClean }) => {
            if (this.socket !== socket) return;
            this.socket = null;
            this.rejectPending(
                `Connection closed (${code}). Requests are not automatically replayed.`,
            );
            this.rooms.clear();
            renderRooms();
            log('system', { close: code, reason, clean: wasClean });
            // Policy, protocol, and intentional closes need user action rather than a reconnect loop.
            if ([1000, 1003, 1007, 1008, 1009, 4401, 4408].includes(code))
                this.wantConnection = false;
            if (code === 4401) setUser(null);
            if (this.wantConnection && $('#reconnect').checked)
                this.scheduleReconnect();
            else
                this.setPhase(
                    'disconnected',
                    `Closed ${code}${reason ? ` · ${reason}` : ''}`,
                );
        };
    }

    scheduleReconnect() {
        if (this.retry >= 8) {
            this.wantConnection = false;
            this.setPhase('disconnected', 'Retry limit reached; use Connect');
            return;
        }
        const ceiling = Math.min(30000, 500 * 2 ** this.retry++);
        const delay = Math.round(ceiling / 2 + (Math.random() * ceiling) / 2);
        this.setPhase(
            'reconnecting',
            `Reconnect ${this.retry}/8 in ${delay} ms`,
        );
        this.reconnectTimer = setTimeout(() => void this.open(), delay);
    }

    disconnect() {
        this.generation++;
        this.wantConnection = false;
        clearTimeout(this.reconnectTimer);
        const socket = this.socket;
        this.socket = null;
        this.rejectPending('Connection stopped.');
        if (socket && socket.readyState < WebSocket.CLOSING)
            socket.close(1000, 'Client disconnected');
        this.rooms.clear();
        this.setPhase('disconnected');
        renderRooms();
    }

    resetSession() {
        this.disconnect();
        this.desiredRooms = null;
        this.clockInterval = null;
    }

    encode(event, data, id) {
        const packet =
            this.path === '/socket/alternate'
                ? {
                      type: event,
                      payload: data,
                      ...(id ? { request_id: id } : {}),
                  }
                : { event, data, ...(id ? { id } : {}) };
        return this.wirePayload(JSON.stringify(packet));
    }

    wirePayload(text) {
        return this.path === '/socket/binary'
            ? new TextEncoder().encode(text)
            : text;
    }

    sendRaw(payload, diagnostic = false) {
        if (this.socket?.readyState !== WebSocket.OPEN)
            throw new Error('Connect before sending.');
        const bytes =
            typeof payload === 'string'
                ? new TextEncoder().encode(payload).byteLength
                : payload.byteLength;
        if (!diagnostic && bytes > 16384)
            throw new Error(
                'A normal message must fit the 16 KiB server limit.',
            );
        // Do not build an unbounded queue when the network is slower than the UI.
        if (this.socket.bufferedAmount + bytes > 65536)
            throw new Error(
                'Outgoing buffer is full; wait before sending again.',
            );
        this.socket.send(payload);
        log(
            'outgoing',
            typeof payload === 'string' ? payload : '[binary frame]',
        );
    }

    notify(event, data = null) {
        this.sendRaw(this.encode(event, data));
    }

    request(event, data = {}) {
        if (this.pending.size >= 32)
            return Promise.reject(new Error('Too many pending requests.'));
        const id = crypto.randomUUID();
        return new Promise((resolve, reject) => {
            const timer = setTimeout(() => {
                this.pending.delete(id);
                this.renderPending();
                reject(new Error(`No reply for ${event} within 8 seconds.`));
            }, 8000);
            this.pending.set(id, { resolve, reject, timer });
            this.renderPending();
            try {
                this.sendRaw(this.encode(event, data, id));
            } catch (error) {
                clearTimeout(timer);
                this.pending.delete(id);
                this.renderPending();
                reject(error);
            }
        });
    }

    receive(packet) {
        const pending = this.pending.get(packet.id);
        if (pending) {
            clearTimeout(pending.timer);
            this.pending.delete(packet.id);
            this.renderPending();
            if (packet.event === 'error')
                pending.reject(
                    new Error(`${packet.data?.code}: ${packet.data?.message}`),
                );
            else pending.resolve(packet);
        }
        if (packet.event === 'error' && !pending)
            $('#notice').textContent =
                `${packet.data?.code}: ${packet.data?.message}`;
        if (packet.event === 'heartbeat.ping' && $('#heartbeat').checked)
            this.notify('heartbeat.pong', { nonce: packet.data.nonce });
        if (packet.event === 'ready') {
            this.retry = 0;
            setUser(packet.data.user);
            $('#worker').textContent = packet.data.worker;
            this.rooms = new Set(packet.data.rooms);
            this.setPhase('connected', 'Connected');
            renderRooms();
            if (this.path === '/socket/chat') void this.restoreSubscriptions();
        }
        if (packet.event === 'chat.joined' || packet.event === 'chat.left') {
            this.rooms = new Set(packet.data.rooms);
            this.desiredRooms = new Set(this.rooms);
            renderRooms();
        }
        if (packet.event === 'clock.started')
            this.clockInterval = packet.data.milliseconds;
        if (packet.event === 'clock.stopped') this.clockInterval = null;
        if (['state', 'status.tick'].includes(packet.event))
            $('#snapshot').textContent = JSON.stringify(packet.data, null, 2);
    }

    async restoreSubscriptions() {
        const wanted =
            this.desiredRooms === null
                ? new Set(this.rooms)
                : new Set(this.desiredRooms);
        this.desiredRooms = wanted;
        try {
            for (const room of wanted)
                if (!this.rooms.has(room))
                    await this.request('chat.join', { room });
            for (const room of [...this.rooms])
                if (!wanted.has(room))
                    await this.request('chat.leave', { room });
            if (this.clockInterval !== null)
                await this.request('clock.start', {
                    milliseconds: this.clockInterval,
                });
        } catch (error) {
            showError(error);
        }
    }

    rejectPending(message) {
        for (const { timer, reject } of this.pending.values()) {
            clearTimeout(timer);
            reject(new Error(message));
        }
        this.pending.clear();
        this.renderPending();
    }

    renderPending() {
        $('#pending').textContent = String(this.pending.size);
    }
}

let currentUser = null;
const client = new SocketClient();

function showError(error) {
    $('#notice').textContent = error.message;
    log('system', error.message);
}

function refreshControls() {
    const connected = client.phase === 'connected';
    $('#connect').disabled =
        !currentUser ||
        ['connecting', 'connected', 'reconnecting'].includes(client.phase);
    $('#disconnect').disabled = !client.socket && !client.wantConnection;
    for (const button of document.querySelectorAll(
        '[data-event], [data-fault], #raw-send',
    ))
        button.disabled = !connected;
    for (const button of document.querySelectorAll('[data-chat]'))
        button.disabled = !connected || client.path !== '/socket/chat';
    $('#backend-notice').disabled = currentUser?.role !== 'admin';
    $('[data-fault="binary"]').textContent =
        client.path === '/socket/binary'
            ? 'Text frame · closes'
            : 'Binary · closes';
}

function setUser(user) {
    currentUser = user;
    $('#identity').textContent = user
        ? `${user.name} / ${user.role}`
        : 'Not signed in';
    $('#admin-actions').hidden = user?.role !== 'admin';
    const selected = $('#room').value;
    $('#room').replaceChildren(
        ...(user?.rooms ?? ['lobby']).map((room) => {
            const option = document.createElement('option');
            option.value = option.textContent = room;
            return option;
        }),
    );
    if ([...$('#room').options].some(({ value }) => value === selected))
        $('#room').value = selected;
    refreshControls();
}

function renderRooms() {
    $('#rooms').textContent = [...client.rooms].join(', ') || '—';
}
const run = (callback) => async (event) => {
    event?.preventDefault();
    $('#notice').textContent = '';
    try {
        await callback();
    } catch (error) {
        showError(error);
    }
};

$('#username').addEventListener('change', () => {
    $('#password').value = `${$('#username').value}-demo`;
});
$('#login-form').addEventListener(
    'submit',
    run(async () => {
        client.resetSession();
        const { user } = await api('/demo/session', {
            username: $('#username').value,
            password: $('#password').value,
        });
        setUser(user);
        client.connect($('#endpoint').value);
    }),
);
$('#logout').addEventListener(
    'click',
    run(async () => {
        client.resetSession();
        await api('/demo/logout', {});
        setUser(null);
    }),
);
$('#connect').addEventListener('click', () =>
    client.connect($('#endpoint').value),
);
$('#disconnect').addEventListener('click', () => client.disconnect());
$('#endpoint').addEventListener('change', () => {
    client.disconnect();
    const alternate = $('#endpoint').value === '/socket/alternate';
    $('#raw').value = JSON.stringify(
        alternate
            ? {
                  type: 'echo',
                  payload: 'Alternate codec',
                  request_id: 'manual-1',
              }
            : { event: 'state.get', data: {}, id: 'manual-1' },
        null,
        2,
    );
});
$('#join').addEventListener(
    'click',
    run(() => client.request('chat.join', { room: $('#room').value })),
);
$('#leave').addEventListener(
    'click',
    run(() => client.request('chat.leave', { room: $('#room').value })),
);
$('#chat-form').addEventListener(
    'submit',
    run(() =>
        client.request('chat.send', {
            room: $('#room').value,
            text: $('#text').value,
        }),
    ),
);
$('#typing').addEventListener(
    'click',
    run(() => client.request('chat.typing', { room: $('#room').value })),
);
$('#announce').addEventListener(
    'click',
    run(() => client.request('admin.announce', { text: $('#text').value })),
);
$('#backend-notice').addEventListener(
    'click',
    run(async () => {
        await api('/demo/notice', { text: $('#text').value });
        log('system', 'Backend notice accepted via HTTP.');
    }),
);
for (const button of document.querySelectorAll('[data-event]')) {
    button.addEventListener(
        'click',
        run(() => {
            let event = button.dataset.event;
            let data = {};
            if (event === 'echo') data = $('#text').value;
            if (event === 'clock.start') data = { milliseconds: 1000 };
            if (event === 'reminder.schedule')
                data = { milliseconds: 2000, text: $('#text').value };
            if (event === 'document.preview') data = { text: $('#text').value };
            if (event === 'validation.example') {
                event = 'chat.send';
                data = {};
            }
            return client.request(event, data);
        }),
    );
}
$('#raw-send').addEventListener(
    'click',
    run(() => client.sendRaw(client.wirePayload($('#raw').value))),
);
for (const button of document.querySelectorAll('[data-fault]')) {
    button.addEventListener(
        'click',
        run(() => {
            const kind = button.dataset.fault;
            const payload =
                kind === 'binary'
                    ? client.path === '/socket/binary'
                        ? '{}'
                        : new Uint8Array([1, 2, 3])
                    : client.wirePayload(
                          kind === 'json' ? '{' : 'x'.repeat(17000),
                      );
            client.sendRaw(payload, true);
        }),
    );
}
$('#clear').addEventListener('click', () => $('#log').replaceChildren());
$('#reconnect').addEventListener('change', () => {
    if (!$('#reconnect').checked && client.phase === 'reconnecting')
        client.disconnect();
});
window.addEventListener('online', () => {
    if (client.wantConnection && !client.socket) void client.open();
});
window.addEventListener('pagehide', () => {
    client.resumeAfterPageShow = client.wantConnection;
    client.disconnect();
});
window.addEventListener('pageshow', (event) => {
    if (event.persisted && client.resumeAfterPageShow)
        client.connect($('#endpoint').value);
});

setUser(null);
try {
    const { user } = await api('/demo/session');
    setUser(user);
} catch (error) {
    showError(error);
}
