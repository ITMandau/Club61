{{--
    Cetak struk thermal: club61PrintReceipt('#id-struk' | elemen).

    - Android (tablet / HP / aplikasi Flutter): struk digambar ke kanvas hitam-putih (selebar kepala print 384 titik),
      diubah jadi gambar ESC/POS lalu dikirim ke aplikasi RawBT lewat link "rawbt:base64,…" — RawBT meneruskan ke printer
      (MP-58C via Bluetooth/USB) tanpa dialog cetak.
    - Selain Android (PC kasir): gambar yang sama dicetak lewat iframe tersembunyi yang isinya HANYA gambar struk, dengan
      @page 58mm x tinggi struk. Dulu window.print() mencetak seluruh halaman dan ukuran kertasnya tidak terbaca
      ("58mm auto" tidak valid), jadi kertas mengikuti driver "User Defined" yang panjangnya bermeter-meter.
    - Paksa salah satu cara di perangkat tertentu: localStorage.setItem('club61_print_mode', 'rawbt' | 'browser').
--}}
@php($paper = \App\Support\ReceiptPaper::class)
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
         * Struk di layar → daftar baris. Membaca tampilan yang sudah dirender (getComputedStyle), jadi berlaku untuk
         * semua struk tanpa menulis ulang isinya: baris flex "space-between" = label kiri + nominal kanan, text-align =
         * rata, font-weight >= 600 = tebal, ukuran huruf relatif terhadap isi struk, garis border = garis pemisah.
         */
        window.club61ReceiptLines = function (receipt) {
            // Kartu struk di layar (bingkainya bukan garis struk). Ukuran huruf isinya jadi patokan skala.
            const card = receipt.matches('[id^="printable-"], #fnbpos-receipt') ? receipt : (receipt.querySelector('[id^="printable-"], #fnbpos-receipt') || receipt);
            const baseSize = parseFloat(getComputedStyle(card).fontSize) || 12;
            const lines = [];

            const tidy = (s) => String(s || '').replace(/ /g, ' ').replace(/[ \t]+/g, ' ').trim();
            const hidden = (el, cs) => el.classList.contains('no-print') || cs.display === 'none' || cs.visibility === 'hidden';
            const isBold = (cs) => (parseInt(cs.fontWeight, 10) || 400) >= 600;
            const scaleOf = (cs) => Math.round((parseFloat(cs.fontSize) / baseSize) * 100) / 100;
            const alignOf = (cs) => (cs.textAlign === 'center' ? 'center' : ((cs.textAlign === 'right' || cs.textAlign === 'end') ? 'right' : 'left'));
            const hasBorder = (style, width) => style !== 'none' && style !== 'hidden' && parseFloat(width) > 0;
            const rule = () => { if (lines.length && lines[lines.length - 1].kind !== 'rule') { lines.push({ kind: 'rule' }); } };
            const text = (t, cs) => { t = tidy(t); if (t) { lines.push({ kind: 'text', text: t, align: alignOf(cs), bold: isBold(cs), scale: scaleOf(cs) }); } };

            const walk = (el) => {
                const cs = getComputedStyle(el);
                if (hidden(el, cs)) { return; }
                if (hasBorder(cs.borderTopStyle, cs.borderTopWidth)) { rule(); }

                const kids = Array.from(el.children).filter((k) => ! hidden(k, getComputedStyle(k)));
                const isRowFlex = cs.display.indexOf('flex') !== -1 && cs.flexDirection.indexOf('column') === -1;

                if (isRowFlex && kids.length >= 2 && cs.justifyContent.indexOf('space-between') !== -1) {
                    const last = kids[kids.length - 1];
                    const styles = kids.map((k) => getComputedStyle(k));
                    lines.push({
                        kind: 'row',
                        left: tidy(kids.slice(0, -1).map((k) => k.innerText || k.textContent).join(' ')),
                        right: tidy(last.innerText || last.textContent),
                        bold: isBold(cs) || styles.some(isBold),
                        scale: Math.max(scaleOf(cs), ...styles.map(scaleOf)),
                    });
                } else if (isRowFlex || kids.length === 0 || kids.every((k) => getComputedStyle(k).display.indexOf('inline') === 0 || k.tagName === 'BR')) {
                    String(el.innerText || el.textContent || '').split('\n').forEach((t) => text(t, cs));
                } else {
                    // Campuran: teks lepas di antara blok dicetak sebagai barisnya sendiri.
                    for (const node of el.childNodes) {
                        if (node.nodeType === Node.TEXT_NODE) { text(node.textContent, cs); }
                        else if (node.nodeType === Node.ELEMENT_NODE) { walk(node); }
                    }
                }

                if (hasBorder(cs.borderBottomStyle, cs.borderBottomWidth)) { rule(); }
            };

            for (const node of card.childNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) { walk(node); }
                else if (node.nodeType === Node.TEXT_NODE) { text(node.textContent, getComputedStyle(card)); }
            }
            while (lines.length && lines[lines.length - 1].kind === 'rule') { lines.pop(); }
            return lines;
        };

        /**
         * Daftar baris → kanvas selebar kepala print (384 titik), dipakai RawBT maupun cetak dari PC. Isi struk
         * 0.75rem dari {{ $paper::BASE_FONT_PX }}px, dikonversi dari 96 dpi layar ke {{ $paper::RASTER_DPI }} dpi printer.
         */
        window.club61ReceiptCanvas = function (receipt) {
            const lines = window.club61ReceiptLines(receipt);
            const W = {{ $paper::RASTER_DOTS }};
            const dpi = {{ $paper::RASTER_DPI }};
            const dotsPerMm = dpi / 25.4;
            const side = Math.round({{ $paper::SIDE_PADDING_MM }} * dotsPerMm);
            const inner = W - side * 2;
            const bodyPx = {{ $paper::BASE_FONT_PX }} * 0.75 * (dpi / 96);
            const family = @js($paper::FONT_STACK);
            const lineHeight = 1.45;
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const fontOf = (l) => (l.bold ? 'bold ' : '') + Math.round(bodyPx * (l.scale || 1)) + 'px ' + family;
            // Huruf sedikit direnggangkan supaya tidak mepet.
            const spacing = (l) => (Math.round(bodyPx * (l.scale || 1) * 0.02 * 10) / 10) + 'px';
            const applyFont = (l) => { ctx.font = fontOf(l); if ('letterSpacing' in ctx) { ctx.letterSpacing = spacing(l); } };

            const wrap = (str, maxWidth) => {
                const out = [];
                let line = '';
                for (const word of str.split(' ')) {
                    const next = line ? line + ' ' + word : word;
                    if (ctx.measureText(next).width <= maxWidth || ! line) { line = next; } else { out.push(line); line = word; }
                }
                if (line) { out.push(line); }
                // Kata yang lebih lebar dari kertas dipotong per huruf.
                return out.flatMap((l) => {
                    if (ctx.measureText(l).width <= maxWidth) { return [l]; }
                    const parts = [];
                    let cur = '';
                    for (const ch of l) { if (ctx.measureText(cur + ch).width > maxWidth && cur) { parts.push(cur); cur = ch; } else { cur += ch; } }
                    if (cur) { parts.push(cur); }
                    return parts;
                });
            };

            // Tata letak dulu (butuh measureText), baru gambar setelah tinggi kanvas diketahui.
            const ops = [];
            let y = Math.round(dotsPerMm);
            for (const l of lines) {
                if (l.kind === 'rule') {
                    y += Math.round(bodyPx * 0.35);
                    ops.push({ kind: 'rule', y });
                    y += Math.round(bodyPx * 0.35) + 2;
                    continue;
                }
                applyFont(l);
                const step = Math.round(bodyPx * (l.scale || 1) * lineHeight);
                if (l.kind === 'text') {
                    for (const t of wrap(l.text, inner)) { ops.push({ kind: 'text', line: l, text: t, align: l.align, y }); y += step; }
                    continue;
                }
                const gap = Math.round(bodyPx * 0.5);
                const rightW = ctx.measureText(l.right).width;
                if (l.right && ctx.measureText(l.left).width + gap + rightW <= inner) {
                    ops.push({ kind: 'text', line: l, text: l.left, align: 'left', y });
                    ops.push({ kind: 'text', line: l, text: l.right, align: 'right', y });
                    y += step;
                } else {
                    for (const t of wrap(l.left, inner)) { ops.push({ kind: 'text', line: l, text: t, align: 'left', y }); y += step; }
                    if (l.right) { for (const t of wrap(l.right, inner)) { ops.push({ kind: 'text', line: l, text: t, align: 'right', y }); y += step; } }
                }
            }
            y += Math.round({{ $paper::BOTTOM_FEED_MM }} * dotsPerMm);

            canvas.width = W;
            canvas.height = Math.max(8, Math.ceil(y / 8) * 8);
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#000000';
            ctx.textBaseline = 'top';
            for (const op of ops) {
                if (op.kind === 'rule') {
                    for (let x = side; x < W - side; x += 10) { ctx.fillRect(x, op.y, 6, 2); }
                    continue;
                }
                applyFont(op.line);
                ctx.textAlign = op.align;
                const x = op.align === 'center' ? W / 2 : (op.align === 'right' ? W - side : side);
                ctx.fillText(op.text, x, op.y);
            }

            // Hitam murni / putih murni (tanpa abu-abu pinggiran huruf): abu-abu dicetak printer thermal sebagai
            // titik-titik → huruf belang & buram. Gambar yang sama dipakai RawBT (Android) dan cetak dari PC.
            const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const px = img.data;
            for (let i = 0; i < px.length; i += 4) {
                const lum = px[i] * 0.299 + px[i + 1] * 0.587 + px[i + 2] * 0.114;
                const v = lum < 160 ? 0 : 255;
                px[i] = px[i + 1] = px[i + 2] = v;
                px[i + 3] = 255;
            }
            ctx.putImageData(img, 0, 0);
            return canvas;
        };

        /**
         * Kanvas → byte ESC/POS gambar (GS v 0). Hitam murni (ambang batas, tanpa abu-abu) supaya tidak belang di
         * kertas thermal. Dikirim per potongan 128 baris — sebagian printer menolak gambar yang terlalu tinggi.
         */
        window.club61CanvasToEscPos = function (canvas) {
            const ESC = 0x1B, GS = 0x1D;
            const { width, height } = canvas;
            const pixels = canvas.getContext('2d').getImageData(0, 0, width, height).data;
            const bytesPerRow = Math.ceil(width / 8);
            const out = [ESC, 0x40];
            for (let top = 0; top < height; top += 128) {
                const rows = Math.min(128, height - top);
                out.push(GS, 0x76, 0x30, 0x00, bytesPerRow & 0xFF, bytesPerRow >> 8, rows & 0xFF, rows >> 8);
                for (let yy = top; yy < top + rows; yy++) {
                    for (let bx = 0; bx < bytesPerRow; bx++) {
                        let byte = 0;
                        for (let bit = 0; bit < 8; bit++) {
                            const x = bx * 8 + bit;
                            if (x >= width) { continue; }
                            const i = (yy * width + x) * 4;
                            const lum = pixels[i] * 0.299 + pixels[i + 1] * 0.587 + pixels[i + 2] * 0.114;
                            if (pixels[i + 3] > 0 && lum < 160) { byte |= 0x80 >> bit; }
                        }
                        out.push(byte);
                    }
                }
            }
            out.push(ESC, 0x64, 3);          // dorong kertas supaya bisa disobek
            out.push(GS, 0x56, 0x42, 0x00);  // potong (diabaikan printer tanpa pemotong)
            return out;
        };

        window.club61PrintRawBt = async function (el) {
            if (document.fonts && document.fonts.ready) { try { await document.fonts.ready; } catch (e) {} }
            const bytes = window.club61CanvasToEscPos(window.club61ReceiptCanvas(el));
            let binary = '';
            for (let i = 0; i < bytes.length; i += 4096) {
                binary += String.fromCharCode.apply(null, bytes.slice(i, i + 4096));
            }
            window.location.href = 'rawbt:base64,' + btoa(binary);
        };

        /**
         * PC kasir: gambar struk yang sama dengan RawBT dicetak lewat iframe tersembunyi, kertas = 58mm x tinggi struk.
         * Dulu struk dicetak sebagai teks HTML — Chrome menghaluskan pinggiran huruf (abu-abu) dan driver POS58
         * mengubahnya jadi titik-titik, jadi hasilnya belang & tidak setajam test page Windows.
         */
        window.club61PrintBrowser = async function (el) {
            if (document.fonts && document.fonts.ready) { try { await document.fonts.ready; } catch (e) {} }
            const canvas = window.club61ReceiptCanvas(el);
            const heightMm = Math.max({{ $paper::MIN_LENGTH_MM }}, Math.ceil(canvas.height * 25.4 / {{ $paper::RASTER_DPI }}));

            const old = document.getElementById('club61-receipt-frame');
            if (old) { old.remove(); }
            const frame = document.createElement('iframe');
            frame.id = 'club61-receipt-frame';
            frame.setAttribute('aria-hidden', 'true');
            frame.style.cssText = 'position:fixed;right:0;bottom:0;width:{{ $paper::PAPER_MM }}mm;height:0;border:0;opacity:0;pointer-events:none;';
            document.body.appendChild(frame);

            // Dokumen dibangun lewat DOM, bukan document.write berisi tag head/body/html: Livewire menyisipkan script-nya
            // sebelum tag penutup body PERTAMA di respons — kalau tag itu ada di string JS ini, script terpotong (error).
            const doc = frame.contentDocument;
            doc.open();
            doc.write('<!doctype html>');
            doc.close();
            const style = doc.createElement('style');
            style.textContent = '@page { size: {{ $paper::PAPER_MM }}mm ' + heightMm + 'mm; margin: 0; }'
                + ' html, body { margin: 0; padding: 0; background: #FFFFFF; }'
                + ' img { display: block; width: {{ $paper::PRINT_MM }}mm; height: auto; image-rendering: pixelated; }';
            doc.head.appendChild(style);
            const img = doc.createElement('img');
            img.alt = '';

            let printed = false;
            const go = () => {
                if (printed) { return; }
                printed = true;
                frame.contentWindow.focus();
                frame.contentWindow.print();
                setTimeout(() => frame.remove(), 2000);
            };
            img.onload = () => setTimeout(go, 50);
            img.src = canvas.toDataURL('image/png');
            doc.body.appendChild(img);
        };

        window.club61PrintReceipt = function (source) {
            const el = typeof source === 'string' ? document.querySelector(source) : source;
            if (! el) { window.print(); return; }

            if (window.club61PrintMode() === 'rawbt') {
                window.club61PrintRawBt(el);
            } else {
                window.club61PrintBrowser(el);
            }
        };
    }
</script>
