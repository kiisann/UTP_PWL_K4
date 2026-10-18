import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;


let audioCtx = null;

function unlockAudio() {
    if (!audioCtx) {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        audioCtx = new Ctx();
    }

    const markReady = () => {
        Alpine.store('alarm').ready = audioCtx.state === 'running';
    };

    if (audioCtx.state === 'suspended') {
        audioCtx.resume().then(markReady).catch(() => {});
    } else {
        markReady();
    }
}

['click', 'keydown', 'touchstart'].forEach((evt) =>
    window.addEventListener(evt, unlockAudio, { passive: true })
);

function beep() {
    if (!audioCtx || audioCtx.state !== 'running') return;

    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.type = 'square';
    osc.frequency.value = 880;
    gain.gain.value = 0.15;
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    osc.start();
    osc.stop(audioCtx.currentTime + 0.25);
}

function tripleBeep() {
    [0, 350, 700].forEach((delay) => setTimeout(beep, delay));
}


Alpine.store('alarm', {
    on: localStorage.getItem('ps_sound') !== 'off',
    ready: false,
    expired: {},

    get count() {
        return Object.keys(this.expired).length;
    },

    toggle() {
        this.on = !this.on;
        localStorage.setItem('ps_sound', this.on ? 'on' : 'off');
        unlockAudio();
    },

    mark(id, isExpired) {
        if (isExpired) {
            this.expired[id] = true;
        } else {
            delete this.expired[id];
        }
    },
});


setInterval(() => {
    const alarm = Alpine.store('alarm');
    if (alarm.on && alarm.count > 0) tripleBeep();
}, 4000);


Alpine.data('sessionTimer', (cfg) => ({
    offset: cfg.serverNow - Date.now(), // selisih jam server dan jam browser
    now: cfg.serverNow,
    ticker: null,

    init() {
        this.update();
        if (!cfg.actualEnd) {
            this.ticker = setInterval(() => this.update(), 1000);
        }
    },

    destroy() {
        clearInterval(this.ticker);
        Alpine.store('alarm').mark(cfg.id, false);
    },

    update() {
        this.now = Date.now() + this.offset;
        Alpine.store('alarm').mark(cfg.id, this.state === 'expired');
    },

    
    get remaining() {
        return Math.ceil((cfg.plannedEnd - this.now) / 1000);
    },

    
    get state() {
        if (cfg.actualEnd) return 'stopped';
        if (cfg.mode === 'open') return 'open';
        if (this.remaining <= 0) return 'expired';
        if (this.remaining <= 600) return 'warning';
        return 'package';
    },

    get display() {
        let seconds;

        if (cfg.actualEnd) {
            seconds = (cfg.actualEnd - cfg.start) / 1000;          // durasi total
        } else if (cfg.mode === 'open') {
            seconds = (this.now - cfg.start) / 1000;               // stopwatch
        } else {
            seconds = this.remaining;                              // countdown
        }

        return this.format(Math.max(0, Math.floor(seconds)));
    },

    // Sewa sementara. Open Bill memakai rumus yang sama dengan BillingCalculatorService
    // (durasi dibulatkan ke atas, minimum tagih, lalu blok pembulatan); Paket memakai harga tetap.
    get estimatedRental() {
        if (cfg.mode !== 'open') return cfg.rental;

        const end = cfg.actualEnd ?? this.now;
        const seconds = Math.max(0, (end - cfg.start) / 1000);
        const actual = Math.ceil(seconds / 60);
        const effective = Math.max(actual, cfg.minCharge);
        const rounding = Math.max(1, cfg.rounding);
        const billed = Math.ceil(effective / rounding) * rounding;

        return Math.round((billed / 60) * cfg.rate * 100) / 100;
    },

    get estimatedTotal() {
        return Math.max(0, this.estimatedRental + cfg.fnb - cfg.discount);
    },

    rupiah(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    },

    get caption() {
        return {
            stopped: 'Durasi main',
            open: 'Sudah berjalan',
            expired: 'WAKTU HABIS!',
            warning: 'Sisa waktu (hampir habis)',
            package: 'Sisa waktu',
        }[this.state];
    },

    get label() {
        return {
            stopped: 'Menunggu Bayar',
            open: 'Open Bill',
            expired: 'Waktu Habis',
            warning: 'Sisa ≤ 10 menit',
            package: 'Main Paket',
        }[this.state];
    },

    format(totalSeconds) {
        const h = Math.floor(totalSeconds / 3600);
        const m = Math.floor((totalSeconds % 3600) / 60);
        const s = totalSeconds % 60;
        return [h, m, s].map((n) => String(n).padStart(2, '0')).join(':');
    },
}));


Alpine.data('dashboard', (cfg) => ({
    selected: null,
    modal: null,
    mode: 'package',
    packages: cfg.packages,
    available: cfg.available,
    signature: cfg.signature,
    poller: null,

    init() {
        this.poller = setInterval(() => this.checkChanges(), 10000);
    },

    async checkChanges() {
        if (this.modal) return;

        try {
            const res = await fetch(cfg.liveUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;

            const data = await res.json();
            if (data.signature !== this.signature) {
                window.location.reload();
            }
        } catch (e) {
            // koneksi putus sebentar: coba lagi 10 detik berikutnya
        }
    },
}));


Alpine.data('shiftBar', (cfg) => ({
    modal: cfg.autoOpen ? 'open' : null, // otomatis terbuka kalau belum ada shift
    loading: false,
    error: null,
    summary: null,
    actual: '',
    startingCash: '',

    async openClose() {
        this.modal = 'close';
        this.actual = '';
        this.error = null;
        this.summary = null;
        this.loading = true;

        try {
            const res = await fetch(cfg.summaryUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('gagal');
            this.summary = await res.json();
        } catch (e) {
            this.error = 'Gagal memuat ringkasan shift. Tutup jendela ini lalu coba lagi.';
        } finally {
            this.loading = false;
        }
    },

   
    get difference() {
        if (!this.summary || this.actual === '') return null;
        return Number(this.actual) - Number(this.summary.expected_cash);
    },

    get balanced() {
        return this.difference !== null && Math.abs(this.difference) < 0.005;
    },

    get differenceText() {
        const d = this.difference;
        if (d === null) return '';
        if (Math.abs(d) < 0.005) return 'Pas, tidak ada selisih';
        return (d < 0 ? 'KURANG ' : 'LEBIH ') + this.rupiah(Math.abs(d));
    },

    rupiah(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    },
}));

Alpine.start();
