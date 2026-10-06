{{--
    Cetak struk thermal: club61PrintReceipt('#id-struk' | elemen).

    - Android (tablet / HP / aplikasi Flutter): struk diubah jadi perintah ESC/POS lalu dikirim ke aplikasi RawBT lewat
      link "rawbt:base64,…" — RawBT meneruskan ke printer (MP-58C via Bluetooth/USB) tanpa dialog cetak.
    - Selain Android (PC kasir): struk disalin ke iframe tersembunyi yang isinya HANYA struk, tingginya diukur, lalu
      @page diatur 58mm x tinggi struk. Dulu window.print() mencetak seluruh halaman dan ukuran kertasnya tidak terbaca
      ("58mm auto" tidak valid), jadi kertas mengikuti driver "User Defined" yang panjangnya bermeter-meter.
    - Paksa salah satu cara di perangkat tertentu: localStorage.setItem('club61_print_mode', 'rawbt' | 'browser').
--}}
<script>
    if (! window.club61PrintReceipt) {
        window.club61PrintMode = function () {
            try {
                const forced = localStorage.getItem('club61_print_mode');
                if (forced === 'rawbt' || forced === 'browser') { return forced; }
            } catch (e) {}
            return /Android/i.test(navigator.userAgent) ? 'rawbt' : 'browser';
        };

        /**
         * Struk di layar → byte ESC/POS (teks 32 kolom). Membaca tampilan yang sudah dirender (getComputedStyle), jadi
         * berlaku untuk semua struk tanpa menulis ulang isinya: baris flex "space-between" = label kiri + nominal kanan,
         * text-align = rata, font-weight >= 600 = tebal, huruf jauh lebih besar dari isi struk = tinggi ganda,
         * garis border = baris "-----".
         */
        window.club61ReceiptToEscPos = function (receipt, cols) {
            cols = cols || {{ \App\Support\ReceiptPaper::ESC_POS_COLUMNS }};
            const ESC = 0x1B, GS = 0x1D, LF = 0x0A;
            const bytes = [ESC, 0x40];
            const state = { align: -1, bold: null, big: null };
            // Kartu struk di layar (bingkainya bukan garis struk). Ukuran huruf isinya jadi patokan "huruf besar".
            const card = receipt.matches('[id^="printable-"], #fnbpos-receipt') ? receipt : (receipt.querySelector('[id^="printable-"], #fnbpos-receipt') || receipt);
            const baseSize = parseFloat(getComputedStyle(card).fontSize) || 12;

            // Printer hanya punya huruf ASCII (code page bawaan): huruf beraksen & simbol diganti padanannya.
            const clean = (s) => String(s || '')
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[•·‣●]/g, '-').replace(/[–—−]/g, '-')
                .replace(/[‘’]/g, "'").replace(/[“”]/g, '"').replace(/…/g, '...')
                .replace(/ /g, ' ').replace(/[^\x20-\x7E]/g, '').replace(/\s+/g, ' ').trim();

            const wrap = (text, width) => {
                const lines = [];
                let line = '';
                for (let word of text.split(' ')) {
                    while (word.length > width) {
                        if (line) { lines.push(line); line = ''; }
                        lines.push(word.slice(0, width));
                        word = word.slice(width);
                    }
                    if (! word) { continue; }
                    if (! line) { line = word; } else if (line.length + 1 + word.length <= width) { line += ' ' + word; } else { lines.push(line); line = word; }
                }
                if (line) { lines.push(line); }
                return lines;
            };

            const setAlign = (a) => { if (state.align !== a) { bytes.push(ESC, 0x61, a); state.align = a; } };
            const setBold = (b) => { if (state.bold !== b) { bytes.push(ESC, 0x45, b ? 1 : 0); state.bold = b; } };
            const setBig = (g) => { if (state.big !== g) { bytes.push(GS, 0x21, g ? 0x01 : 0x00); state.big = g; } };
            const emit = (text, opts) => {
                setAlign(opts.align || 0); setBold(!! opts.bold); setBig(!! opts.big);
                for (const ch of text) { bytes.push(ch.charCodeAt(0)); }
                bytes.push(LF);
            };
            let lastWasRule = false;
            const rule = () => {
                if (lastWasRule) { return; }
                emit('-'.repeat(cols), { align: 0 });
                lastWasRule = true;
            };

            const hidden = (el, cs) => el.classList.contains('no-print') || cs.display === 'none' || cs.visibility === 'hidden';
            const isBold = (cs) => (parseInt(cs.fontWeight, 10) || 400) >= 600;
            const isBig = (cs) => parseFloat(cs.fontSize) >= baseSize * 1.2;
            const alignOf = (cs) => (cs.textAlign === 'center' ? 1 : ((cs.textAlign === 'right' || cs.textAlign === 'end') ? 2 : 0));
            const hasBorder = (style, width) => style !== 'none' && style !== 'hidden' && parseFloat(width) > 0;

            const textLines = (el) => String(el.innerText || el.textContent || '').split('\n').map(clean).filter(Boolean);

            const emitText = (el, cs) => {
                const opts = { align: alignOf(cs), bold: isBold(cs), big: isBig(cs) };
                for (const t of textLines(el)) {
                    for (const line of wrap(t, cols)) { emit(line, opts); lastWasRule = false; }
                }
            };

            // Label kiri + nominal kanan. Tidak muat sebaris → label dibungkus, nominal turun rata kanan.
            const emitRow = (cs, kids) => {
                const left = kids.slice(0, -1).map((k) => clean(k.innerText || k.textContent)).filter(Boolean).join(' ');
                const right = clean(kids[kids.length - 1].innerText || kids[kids.length - 1].textContent);
                const opts = { align: 0, bold: isBold(cs) || kids.some((k) => isBold(getComputedStyle(k))), big: isBig(cs) || kids.some((k) => isBig(getComputedStyle(k))) };
                if (! right) { for (const line of wrap(left, cols)) { emit(line, opts); } lastWasRule = false; return; }
                if (left.length + 1 + right.length <= cols) {
                    emit(left + ' '.repeat(cols - left.length - right.length) + right, opts);
                } else {
                    for (const line of wrap(left, cols)) { emit(line, opts); }
                    for (const line of wrap(right, cols)) { emit(' '.repeat(cols - line.length) + line, opts); }
                }
                lastWasRule = false;
            };

            const walk = (el) => {
                const cs = getComputedStyle(el);
                if (hidden(el, cs)) { return; }
                if (hasBorder(cs.borderTopStyle, cs.borderTopWidth)) { rule(); }

                const kids = Array.from(el.children).filter((k) => ! hidden(k, getComputedStyle(k)));
                const isFlex = cs.display.indexOf('flex') !== -1;
                const isRowFlex = isFlex && cs.flexDirection.indexOf('column') === -1;

                if (isRowFlex && kids.length >= 2 && cs.justifyContent.indexOf('space-between') !== -1) {
                    emitRow(cs, kids);
                } else if (isRowFlex || kids.length === 0 || kids.every((k) => getComputedStyle(k).display.indexOf('inline') === 0 || k.tagName === 'BR')) {
                    emitText(el, cs);
                } else {
                    // Campuran: teks lepas di antara blok dicetak sebagai barisnya sendiri.
                    for (const node of el.childNodes) {
                        if (node.nodeType === Node.TEXT_NODE) {
                            const t = clean(node.textContent);
                            if (t) { for (const line of wrap(t, cols)) { emit(line, { align: alignOf(cs), bold: isBold(cs), big: isBig(cs) }); } lastWasRule = false; }
                        } else if (node.nodeType === Node.ELEMENT_NODE) {
                            walk(node);
                        }
                    }
                }

                if (hasBorder(cs.borderBottomStyle, cs.borderBottomWidth)) { rule(); }
            };

            for (const node of card.childNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) { walk(node); }
                else if (node.nodeType === Node.TEXT_NODE && clean(node.textContent)) { emit(clean(node.textContent), { align: 0 }); }
            }

            setAlign(0); setBold(false); setBig(false);
            bytes.push(ESC, 0x64, 4);          // dorong kertas 4 baris supaya bisa disobek
            bytes.push(GS, 0x56, 0x42, 0x00);  // potong (diabaikan printer tanpa pemotong)
            return bytes;
        };

        window.club61PrintRawBt = function (el) {
            const bytes = window.club61ReceiptToEscPos(el);
            let binary = '';
            for (let i = 0; i < bytes.length; i += 4096) {
                binary += String.fromCharCode.apply(null, bytes.slice(i, i + 4096));
            }
            window.location.href = 'rawbt:base64,' + btoa(binary);
        };

        window.club61PrintReceipt = function (source) {
            const el = typeof source === 'string' ? document.querySelector(source) : source;
            if (! el) { window.print(); return; }

            if (window.club61PrintMode() === 'rawbt') {
                window.club61PrintRawBt(el);
                return;
            }

            const old = document.getElementById('club61-receipt-frame');
            if (old) { old.remove(); }

            const frame = document.createElement('iframe');
            frame.id = 'club61-receipt-frame';
            frame.setAttribute('aria-hidden', 'true');
            frame.style.cssText = 'position:fixed;right:0;bottom:0;width:{{ \App\Support\ReceiptPaper::PAPER_MM }}mm;height:0;border:0;opacity:0;pointer-events:none;';
            document.body.appendChild(frame);

            // Gaya halaman ikut disalin supaya class Tailwind / Filament di struk tetap berlaku.
            const pageStyles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style:not([data-club61-receipt-print])')).map((n) => n.outerHTML).join('');
            // Dokumen dibangun lewat DOM, bukan document.write berisi tag head/body/html: Livewire menyisipkan script-nya
            // sebelum tag penutup body PERTAMA di respons — kalau tag itu ada di string JS ini, script terpotong (error).
            const doc = frame.contentDocument;
            doc.open();
            doc.write('<!doctype html>');
            doc.close();
            const base = doc.createElement('base');
            base.href = location.href;
            doc.head.appendChild(base);
            doc.head.insertAdjacentHTML('beforeend', pageStyles);
            const receiptStyle = doc.createElement('style');
            receiptStyle.textContent = @js(\App\Support\ReceiptPaper::frameCss());
            doc.head.appendChild(receiptStyle);
            const rootEl = doc.createElement('div');
            rootEl.id = 'club61-receipt-root';
            rootEl.innerHTML = el.outerHTML;
            doc.body.appendChild(rootEl);

            let printed = false;
            const go = () => {
                if (printed) { return; }
                printed = true;
                const root = doc.getElementById('club61-receipt-root');
                const heightMm = Math.max(
                    {{ \App\Support\ReceiptPaper::MIN_LENGTH_MM }},
                    Math.ceil(root.getBoundingClientRect().height * 25.4 / 96) + {{ \App\Support\ReceiptPaper::BOTTOM_FEED_MM }}
                );
                const page = doc.createElement('style');
                page.textContent = '@page { size: {{ \App\Support\ReceiptPaper::PAPER_MM }}mm ' + heightMm + 'mm; margin: 0; }';
                doc.head.appendChild(page);
                frame.contentWindow.focus();
                frame.contentWindow.print();
                setTimeout(() => frame.remove(), 2000);
            };

            // Tunggu stylesheet & font selesai dimuat supaya tinggi yang diukur sama dengan hasil cetak.
            const links = Array.from(doc.querySelectorAll('link[rel="stylesheet"]'));
            Promise.all(links.map((l) => (l.sheet ? Promise.resolve() : new Promise((resolve) => {
                l.addEventListener('load', resolve);
                l.addEventListener('error', resolve);
                setTimeout(resolve, 1500);
            }))))
                .then(() => (doc.fonts ? doc.fonts.ready : null))
                .then(() => setTimeout(go, 50));
        };
    }
</script>
