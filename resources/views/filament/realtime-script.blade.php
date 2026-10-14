{{--
    Update realtime panel admin (Reverb lewat Echo bawaan Filament) + polling cadangan. Dipasang sekali lewat render hook
    panels::body.end. Event Echo diteruskan jadi event browser:
      club61:board-changed     { dates: ['2026-10-14'] | ['*'] }   — booking / lapangan berubah
      club61:signature-changed { kind: 'published' | 'seen', screen_id, seen_at }
      club61:realtime-state    { connected: true | false }

    Halaman jadwal cukup menaruh elemen tersembunyi di dalam komponennya:
      <div hidden x-data="club61LiveRefresh({ dateProp: 'selectedDate', fallback: 10, safety: 60 })"></div>
    → $wire.$refresh() saat tanggal yang sedang dibuka berubah; tanpa Reverb / koneksi putus → refresh tiap `fallback` dtk.
--}}
<script data-navigate-once>
    (function () {
        if (window.club61Realtime) {
            return;
        }

        var realtime = window.club61Realtime = { connected: false, bound: false, registered: false };

        function emit(name, detail) {
            window.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
        }

        function setConnected(value) {
            if (realtime.connected === value) {
                return;
            }
            realtime.connected = value;
            emit('club61:realtime-state', { connected: value });
        }

        function bind() {
            if (realtime.bound || ! window.Echo) {
                return;
            }
            realtime.bound = true;
            window.Echo.channel(@js(\App\Support\Realtime::BOARD_CHANNEL)).listen(@js('.'.\App\Support\Realtime::BOARD_EVENT), function (e) { emit('club61:board-changed', e); });
            window.Echo.channel(@js(\App\Support\Realtime::SIGNATURE_CHANNEL)).listen(@js('.'.\App\Support\Realtime::SIGNATURE_EVENT), function (e) { emit('club61:signature-changed', e); });

            var connection = window.Echo.connector && window.Echo.connector.pusher && window.Echo.connector.pusher.connection;
            if (connection) {
                connection.bind('state_change', function (states) { setConnected(states.current === 'connected'); });
                setConnected(connection.state === 'connected');
            }
        }

        window.addEventListener('EchoLoaded', bind);
        bind();

        function register() {
            if (! window.Alpine || realtime.registered) {
                return;
            }
            realtime.registered = true;

            window.Alpine.data('club61LiveRefresh', function (options) {
                options = options || {};

                return {
                    refreshTimer: null,
                    intervalTimer: null,

                    init() {
                        this.onBoard = (e) => {
                            var dates = (e.detail && e.detail.dates) || [];
                            var current = options.dateProp ? this.$wire.get(options.dateProp) : null;
                            if (! current || dates.indexOf('*') !== -1 || dates.indexOf(current) !== -1) {
                                this.queue(250);
                            }
                        };
                        // Tersambung lagi → refresh sekali (mengejar perubahan selama putus); interval ikut disesuaikan.
                        this.onState = (e) => {
                            if (e.detail.connected) {
                                this.queue(250);
                            }
                            this.schedule();
                        };
                        this.onVisible = () => {
                            if (document.visibilityState === 'visible' && ! realtime.connected) {
                                this.queue(300);
                            }
                        };
                        window.addEventListener('club61:board-changed', this.onBoard);
                        window.addEventListener('club61:realtime-state', this.onState);
                        document.addEventListener('visibilitychange', this.onVisible);
                        this.schedule();
                    },

                    destroy() {
                        window.removeEventListener('club61:board-changed', this.onBoard);
                        window.removeEventListener('club61:realtime-state', this.onState);
                        document.removeEventListener('visibilitychange', this.onVisible);
                        clearTimeout(this.refreshTimer);
                        clearInterval(this.intervalTimer);
                    },

                    // Reverb tersambung → hanya refresh jaga-jaga (safety, 0 = tidak ada); tidak → polling cadangan.
                    schedule() {
                        clearInterval(this.intervalTimer);
                        var seconds = realtime.connected ? (options.safety || 0) : (options.fallback || 30);
                        if (seconds > 0) {
                            this.intervalTimer = setInterval(() => {
                                if (document.visibilityState === 'visible') {
                                    this.queue(0);
                                }
                            }, seconds * 1000);
                        }
                    },

                    queue(delay) {
                        clearTimeout(this.refreshTimer);
                        this.refreshTimer = setTimeout(() => this.run(), delay);
                    },

                    // Staf sedang mengetik / popup Filament terbuka → ditunda, tidak menimpa yang sedang dikerjakan.
                    busy() {
                        var active = document.activeElement;
                        var typing = active && /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName) && this.$root.closest('[wire\\:id]')?.contains(active);

                        return typing || document.querySelector('.fi-modal-open') !== null;
                    },

                    run() {
                        // Mis. POS di langkah bayar / struk: tidak ada jadwal yang ditampilkan → tidak perlu refresh.
                        if (options.when && ! options.when()) {
                            return;
                        }
                        if (this.busy()) {
                            this.queue(2000);

                            return;
                        }
                        this.$wire.$refresh();
                    },
                };
            });
        }

        // Status Online / Offline layar TV di halaman Signage. Online = TV melapor dalam 2 menit terakhir (sama dengan
        // SignatureScreen::isOnline). Waktu dibandingkan dengan jam server, bukan jam laptop admin.
        function registerScreenStatus() {
            if (! window.Alpine || realtime.screenStatusRegistered) {
                return;
            }
            realtime.screenStatusRegistered = true;

            var wib = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Jakarta', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false });

            window.Alpine.data('club61ScreenStatus', function (options) {
                return {
                    seenAt: options.seenAt,
                    offset: Date.parse(options.serverNow) - Date.now(),
                    now: Date.now(),
                    ticks: 0,

                    init() {
                        this.onSignature = (e) => {
                            if (e.detail.kind === 'seen' && e.detail.screen_id === options.id && e.detail.seen_at) {
                                this.seenAt = e.detail.seen_at;
                            }
                        };
                        window.addEventListener('club61:signature-changed', this.onSignature);
                        this.timer = setInterval(() => {
                            this.now = Date.now();
                            // Tanpa realtime: tanya server tiap 60 dtk (method renderless, halaman tidak dirender ulang).
                            if (! realtime.connected && document.visibilityState === 'visible' && ++this.ticks % 6 === 0) {
                                this.$wire.screenLastSeen().then((value) => { this.seenAt = value; });
                            }
                        }, 10000);
                    },

                    destroy() {
                        window.removeEventListener('club61:signature-changed', this.onSignature);
                        clearInterval(this.timer);
                    },

                    get online() {
                        return !! this.seenAt && this.now + this.offset - Date.parse(this.seenAt) < 120000;
                    },

                    get label() {
                        if (this.online) {
                            return 'Online';
                        }
                        if (! this.seenAt) {
                            return 'Belum pernah dibuka';
                        }
                        var parts = {};
                        wib.formatToParts(new Date(this.seenAt)).forEach(function (p) { parts[p.type] = p.value; });

                        return 'Offline sejak ' + parts.day + '/' + parts.month + ' ' + parts.hour + ':' + parts.minute;
                    },
                };
            });
        }

        document.addEventListener('alpine:init', register);
        document.addEventListener('alpine:init', registerScreenStatus);
        register();
        registerScreenStatus();
    })();
</script>
