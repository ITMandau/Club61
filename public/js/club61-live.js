/*
 * Club61 Live — client WebSocket kecil (protokol Pusher, dipakai Reverb) untuk TV Digital Signage & halaman Book Court.
 * Sengaja ES5 tanpa library: browser TV lama tidak menjalankan Laravel Echo. Hanya channel publik (berisi tanggal yang
 * berubah, tanpa data customer). Kalau koneksi putus, onState(false) dipanggil → halaman memakai polling cadangan.
 *
 *   var live = Club61Live.connect({ key: '...', host: 'club61.id', port: 443, tls: true });  // null kalau realtime mati
 *   live.on('booking-board', 'board.changed', function (data) { ... });
 *   live.onState(function (connected) { ... });
 */
(function (root) {
    'use strict';

    var MAX_BACKOFF_MS = 30000;

    function connect(config) {
        if (!config || !config.key || !config.host || typeof root.WebSocket === 'undefined') {
            return null;
        }

        var url = (config.tls ? 'wss://' : 'ws://') + config.host + ':' + config.port
            + '/app/' + encodeURIComponent(config.key) + '?protocol=7&client=club61-live&version=1.0&flash=false';
        var handlers = {};       // channel -> event -> [fn]
        var stateHandlers = [];
        var socket = null;
        var connected = false;
        var stopped = false;
        var attempts = 0;
        var activityMs = 30000;
        var idleTimer = null;
        var pongTimer = null;
        var retryTimer = null;

        function setState(value) {
            if (connected === value) { return; }
            connected = value;
            for (var i = 0; i < stateHandlers.length; i++) {
                try { stateHandlers[i](value); } catch (e) { /* penerima error tidak boleh memutus koneksi */ }
            }
        }

        function send(event, data) {
            if (socket && socket.readyState === 1) {
                socket.send(JSON.stringify({ event: event, data: data || {} }));
            }
        }

        function subscribe(channel) {
            send('pusher:subscribe', { auth: '', channel: channel });
        }

        // Tidak ada pesan selama activity_timeout → kirim ping; tidak dibalas 30 dtk → koneksi dianggap mati.
        function resetIdle() {
            clearTimeout(idleTimer);
            clearTimeout(pongTimer);
            idleTimer = setTimeout(function () {
                send('pusher:ping');
                pongTimer = setTimeout(function () { if (socket) { socket.close(); } }, 30000);
            }, activityMs);
        }

        function dispatch(channel, event, raw) {
            var list = handlers[channel] && handlers[channel][event];
            if (!list) { return; }
            var data = raw;
            if (typeof raw === 'string') {
                try { data = JSON.parse(raw); } catch (e) { data = {}; }
            }
            for (var i = 0; i < list.length; i++) {
                try { list[i](data || {}); } catch (e) { /* lanjut ke penerima berikutnya */ }
            }
        }

        function scheduleRetry() {
            if (stopped) { return; }
            clearTimeout(retryTimer);
            var delay = Math.min(MAX_BACKOFF_MS, 1000 * Math.pow(2, attempts)) + Math.floor(Math.random() * 1000);
            attempts++;
            retryTimer = setTimeout(open, delay);
        }

        function open() {
            try {
                socket = new root.WebSocket(url);
            } catch (e) {
                scheduleRetry();
                return;
            }

            socket.onmessage = function (message) {
                var payload;
                try { payload = JSON.parse(message.data); } catch (e) { return; }
                resetIdle();

                if (payload.event === 'pusher:connection_established') {
                    var info = {};
                    try { info = JSON.parse(payload.data); } catch (e) { /* biarkan default */ }
                    if (info.activity_timeout) { activityMs = info.activity_timeout * 1000; }
                    attempts = 0;
                    for (var channel in handlers) {
                        if (handlers.hasOwnProperty(channel)) { subscribe(channel); }
                    }
                    setState(true);
                } else if (payload.event === 'pusher:ping') {
                    send('pusher:pong');
                } else if (payload.event === 'pusher:error') {
                    var error = {};
                    try { error = typeof payload.data === 'string' ? JSON.parse(payload.data) : (payload.data || {}); } catch (e) { /* abaikan */ }
                    // 4000–4099: ditolak permanen (key salah, origin tidak diizinkan) → berhenti, pakai polling saja.
                    if (error.code >= 4000 && error.code < 4100) { stopped = true; }
                } else if (payload.channel && payload.event && payload.event.indexOf('pusher') !== 0) {
                    dispatch(payload.channel, payload.event, payload.data);
                }
            };

            socket.onclose = function () {
                clearTimeout(idleTimer);
                clearTimeout(pongTimer);
                socket = null;
                setState(false);
                scheduleRetry();
            };

            socket.onerror = function () {
                // onclose selalu menyusul; reconnect diatur di sana.
            };
        }

        open();

        return {
            on: function (channel, event, fn) {
                var isNew = !handlers[channel];
                handlers[channel] = handlers[channel] || {};
                (handlers[channel][event] = handlers[channel][event] || []).push(fn);
                if (isNew && connected) { subscribe(channel); }
            },
            onState: function (fn) {
                stateHandlers.push(fn);
            },
            connected: function () {
                return connected;
            },
            close: function () {
                stopped = true;
                clearTimeout(retryTimer);
                clearTimeout(idleTimer);
                clearTimeout(pongTimer);
                if (socket) { socket.close(); }
            }
        };
    }

    var api = { connect: connect };
    root.Club61Live = api;
    if (typeof module !== 'undefined' && module.exports) { module.exports = api; }
})(typeof window !== 'undefined' ? window : (typeof globalThis !== 'undefined' ? globalThis : this));
