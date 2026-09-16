<script>
    function invoiceApp() {
        return {
            isLoading: true,
            isPolling: false,
            pollCount: 0,
            maxPolls: 10,
            pollingInterval: null,
            ticket: null,
            currentTicket: null,
            pastBookings: [],
            isDownloadingPng: false,
            customerName: @json(Auth::user()->name ?? 'Customer VIP'),
            canCancelBooking: @json(Auth::check() && Auth::user()->canCancelBooking()),
            noticeModal: {
                show: false,
                title: '',
                message: '',
                type: 'info',
                buttonText: 'OK, Mengerti',
                onClose: null,
            },

            showNotice(title, message, type = 'info', buttonText = 'OK, Mengerti', onClose = null) {
                this.noticeModal = {
                    show: true,
                    title,
                    message,
                    type,
                    buttonText,
                    onClose,
                };
            },

            handleNoticeClose() {
                this.noticeModal.show = false;
                if (typeof this.noticeModal.onClose === 'function') {
                    const cb = this.noticeModal.onClose;
                    this.noticeModal.onClose = null;
                    cb();
                }
            },

            // Filter & Pagination Riwayat
            searchQuery: '',
            searchDate: '',
            currentPage: 1,
            perPage: 3,

            get filteredPastBookings() {
                let list = this.pastBookings || [];
                if (this.searchQuery && this.searchQuery.trim() !== '') {
                    const q = this.searchQuery.toLowerCase().trim();
                    list = list.filter(b => {
                        const courtName = (b.court && b.court.name) ? b.court.name.toLowerCase() : '';
                        const bookingCode = (b.booking_code) ? b.booking_code.toLowerCase() : '';
                        const bookingId = (b.id) ? b.id.toLowerCase() : '';
                        const status = (b.status) ? b.status.toLowerCase() : '';
                        const rawDate = (b.booking_date) ? String(b.booking_date).toLowerCase() : '';
                        const formattedDate = this.formatDate(b.booking_date).toLowerCase();
                        return courtName.includes(q) || bookingCode.includes(q) || bookingId.includes(q) || status.includes(q) || rawDate.includes(q) || formattedDate.includes(q);
                    });
                }
                if (this.searchDate && this.searchDate.trim() !== '') {
                    const targetDate = this.searchDate.trim();
                    list = list.filter(b => {
                        if (!b.booking_date) return false;
                        const bDate = String(b.booking_date).substring(0, 10);
                        return bDate === targetDate;
                    });
                }
                return list;
            },

            get totalPages() {
                return Math.max(1, Math.ceil(this.filteredPastBookings.length / this.perPage));
            },

            get paginatedBookings() {
                if (this.currentPage > this.totalPages) {
                    this.currentPage = this.totalPages;
                }
                if (this.currentPage < 1) {
                    this.currentPage = 1;
                }
                const start = (this.currentPage - 1) * this.perPage;
                return this.filteredPastBookings.slice(start, start + this.perPage);
            },

            nextPage() {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                }
            },

            prevPage() {
                if (this.currentPage > 1) {
                    this.currentPage--;
                }
            },

            resetFilters() {
                this.searchQuery = '';
                this.searchDate = '';
                this.currentPage = 1;
            },

            // State Metode Pembayaran & Modal (Persis Sama dengan checkout.blade.php)
            showPaymentModal: false,
            selectedMethod: { id: 'qris', code: 'QRIS', name: 'QRIS Instan (GoPay/OVO/BCA)', badge: 'QRIS', fee: 2800, note: 'Konfirmasi Otomatis Midtrans' },
            paymentMethods: [
                { id: 'qris', code: 'QRIS', name: 'QRIS Instan (GoPay/OVO/BCA)', badge: 'QRIS', fee: 2800, note: 'Konfirmasi Otomatis Midtrans' },
                { id: 'bca', code: 'BCA_VA', name: 'BCA Virtual Account', badge: 'BCA', fee: 4440, note: 'Verifikasi Otomatis Midtrans' },
                { id: 'mandiri', code: 'MANDIRI_VA', name: 'Mandiri Virtual Account', badge: 'MDR', fee: 4440, note: 'Verifikasi Otomatis Midtrans' },
                { id: 'bri', code: 'BRI_VA', name: 'BRI Virtual Account', badge: 'BRI', fee: 4440, note: 'Verifikasi Otomatis Midtrans' },
                { id: 'bni', code: 'BNI_VA', name: 'BNI Virtual Account', badge: 'BNI', fee: 4440, note: 'Verifikasi Otomatis Midtrans' },
                { id: 'cimb', code: 'CIMB_VA', name: 'CIMB Virtual Account', badge: 'CIMB', fee: 4440, note: 'Verifikasi Otomatis Midtrans' },
                { id: 'bsi', code: 'BSI_VA', name: 'BSI Virtual Account', badge: 'BSI', fee: 4440, note: 'Syariah Otomatis Midtrans' },
                { id: 'cash', code: 'CASH', name: 'Bayar Tunai di Kasir (Walk-in)', badge: 'CASH', fee: 0, note: 'Bayar di Frontdesk Venue' },
            ],
            isSubmittingPayment: false,
            isCashNotice: false,
            lastSnapToken: null,

            get displayCourtFee() {
                if (this.ticket && this.ticket.order_court_fee) return this.ticket.order_court_fee;
                return this.ticket ? this.ticket.court_fee : 0;
            },

            get displayEquipmentFee() {
                if (this.ticket && this.ticket.order_equipment_fee !== undefined) return this.ticket.order_equipment_fee;
                return this.ticket ? (this.ticket.equipment_fee || 0) : 0;
            },

            get displayGrandTotal() {
                if (this.ticket && this.ticket.order_grand_total) return this.ticket.order_grand_total;
                return this.ticket ? this.ticket.total_amount : 0;
            },

            getPaymentMethodObject(rawCodeOrName) {
                if (!rawCodeOrName) return null;
                const upper = String(rawCodeOrName).toUpperCase();

                if (upper.includes('BCA')) return this.paymentMethods.find(m => m.code === 'BCA_VA') || { id: 'bca', code: 'BCA_VA', name: 'BCA Virtual Account', badge: 'BCA' };
                if (upper.includes('MANDIRI')) return this.paymentMethods.find(m => m.code === 'MANDIRI_VA') || { id: 'mandiri', code: 'MANDIRI_VA', name: 'Mandiri Virtual Account', badge: 'MDR' };
                if (upper.includes('BRI')) return this.paymentMethods.find(m => m.code === 'BRI_VA') || { id: 'bri', code: 'BRI_VA', name: 'BRI Virtual Account', badge: 'BRI' };
                if (upper.includes('BNI')) return this.paymentMethods.find(m => m.code === 'BNI_VA') || { id: 'bni', code: 'BNI_VA', name: 'BNI Virtual Account', badge: 'BNI' };
                if (upper.includes('CIMB')) return this.paymentMethods.find(m => m.code === 'CIMB_VA') || { id: 'cimb', code: 'CIMB_VA', name: 'CIMB Virtual Account', badge: 'CIMB' };
                if (upper.includes('BSI')) return this.paymentMethods.find(m => m.code === 'BSI_VA') || { id: 'bsi', code: 'BSI_VA', name: 'BSI Virtual Account', badge: 'BSI' };
                if (upper.includes('CASH') || upper.includes('TUNAI')) return this.paymentMethods.find(m => m.code === 'CASH') || { id: 'cash', code: 'CASH', name: 'Bayar Tunai di Kasir (Walk-in)', badge: 'CASH' };
                if (upper.includes('QRIS') || upper.includes('GOPAY') || upper.includes('OVO')) return this.paymentMethods.find(m => m.code === 'QRIS') || { id: 'qris', code: 'QRIS', name: 'QRIS Instan (GoPay/OVO/BCA)', badge: 'QRIS' };

                return { id: 'custom', code: upper, name: rawCodeOrName, badge: 'PAY' };
            },

            selectPaymentMethod(m) {
                this.selectedMethod = m;
                this.showPaymentModal = false;
                this.isCashNotice = (m.code === 'CASH');
            },

            openChangeMethodModal() {
                this.showPaymentModal = true;
            },

            continuePayment() {
                return this.payNow();
            },

            async payNow() {
                if (!this.currentTicket) return;
                if (['EXPIRED', 'CANCELLED', 'REFUNDED'].includes(this.currentTicket.status)) {
                    this.showNotice('Reservasi Tidak Aktif', 'Reservasi ini telah kedaluwarsa atau dibatalkan dan tidak dapat diproses lagi. Silakan lakukan booking ulang.', 'error', 'Tutup');
                    return;
                }
                this.isSubmittingPayment = true;

                try {
                    const targetId = this.currentTicket.order_id || this.currentTicket.id;
                    const res = await fetch(`/api/v1/padel/bookings/${targetId}/retry-payment`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            payment_method: this.selectedMethod.code
                        })
                    });

                    const json = await res.json();
                    if (json.success) {
                        if (json.is_cash) {
                            this.isCashNotice = true;
                            await this.loadTicket(this.currentTicket.id);
                        } else if (json.snap_token) {
                            this.lastSnapToken = json.snap_token;
                            this.openSnap(json.snap_token);
                        }
                    } else {
                        this.showNotice('Gagal Memproses Pembayaran', json.message || 'Gagal memproses sesi pembayaran.', 'error', 'Tutup');
                    }
                } catch(e) {
                    console.error('Error retry payment:', e);
                    this.showNotice('Kendala Jaringan', 'Terjadi kendala saat menghubungi gateway pembayaran.', 'error', 'Tutup');
                } finally {
                    this.isSubmittingPayment = false;
                }
            },

            showCancelModal: false,
            isCancellingBooking: false,

            openCancelModal() {
                if (!this.canCancelBooking) {
                    this.showNotice('Akses Ditolak', 'Anda tidak memiliki izin untuk membatalkan pesanan ini.', 'error', 'Tutup');
                    return;
                }
                this.showCancelModal = true;
            },

            cancelActiveBooking() {
                this.openCancelModal();
            },

            async confirmCancelBooking() {
                if (!this.currentTicket) return;
                if (!this.canCancelBooking) {
                    this.showNotice('Akses Ditolak', 'Anda tidak memiliki izin untuk membatalkan pesanan ini.', 'error', 'Tutup');
                    this.showCancelModal = false;
                    return;
                }

                this.isCancellingBooking = true;
                try {
                    // Ambil seluruh booking ID dalam order jika sesi jam berturut-turut
                    const bookingIds = (this.ticket && this.ticket.order_bookings && this.ticket.order_bookings.length > 0)
                        ? this.ticket.order_bookings.map(b => b.id)
                        : [this.currentTicket.id];

                    const res = await fetch('/api/v1/padel/release-slot', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            booking_ids: bookingIds
                        })
                    });

                    const json = await res.json();
                    if (json.success) {
                        localStorage.removeItem('club61_cart');
                        localStorage.removeItem('club61_hold_data');
                        sessionStorage.removeItem('club61_cart');
                        sessionStorage.removeItem('club61_hold_data');
                        sessionStorage.removeItem('vantage_cart');
                        sessionStorage.removeItem('vantage_hold_data');
                        window.dispatchEvent(new CustomEvent('cart-updated'));

                        window.location.href = '{{ route('customer.booking') }}';
                    } else {
                        this.showNotice('Gagal Membatalkan', json.message || 'Gagal membatalkan pesanan.', 'error', 'Tutup');
                        this.isCancellingBooking = false;
                    }
                } catch (e) {
                    console.error('Error cancel booking:', e);
                    this.showNotice('Kesalahan Server', 'Terjadi kesalahan saat menghubungi server.', 'error', 'Tutup');
                    this.isCancellingBooking = false;
                }
            },

            openSnap(token) {
                if (window.snap) {
                    window.snap.pay(token, {
                        onSuccess: async (result) => {
                            await this.loadTicket(this.currentTicket.id);
                        },
                        onPending: async (result) => {
                            await this.loadTicket(this.currentTicket.id);
                        },
                        onError: (result) => {
                            this.showNotice('Pembayaran Ditolak', 'Pembayaran gagal atau kedaluwarsa.', 'error', 'Tutup');
                        },
                        onClose: () => {
                            this.startAutoPolling(this.currentTicket.id);
                        }
                    });
                } else {
                    this.showNotice('Memuat Gateway', 'Komponen Snap Midtrans sedang dimuat. Silakan coba kembali sesaat lagi.', 'info', 'Tutup');
                }
            },

            async init() {
                const urlParams = new URLSearchParams(window.location.search);
                const lookupKey = urlParams.get('booking_id') || urlParams.get('order_id') || urlParams.get('booking_code') || urlParams.get('id');

                let loaded = false;
                if (lookupKey) {
                    loaded = await this.loadTicket(lookupKey);
                }

                if (!loaded) {
                    await this.loadLatestBooking();
                }

                await this.loadMyBookings();
                this.isLoading = false;

                // Handle tombol Back/Forward browser
                window.addEventListener('popstate', async () => {
                    const params = new URLSearchParams(window.location.search);
                    const key = params.get('booking_id') || params.get('order_id') || params.get('booking_code') || params.get('id');
                    if (key) {
                        this.isLoading = true;
                        if (this.pollingInterval) {
                            clearInterval(this.pollingInterval);
                            this.pollingInterval = null;
                            this.isPolling = false;
                        }
                        await this.loadTicket(key);
                        await this.loadMyBookings();
                        this.isLoading = false;
                    }
                });
            },

            async loadTicket(id) {
                try {
                    const res = await fetch(`/api/v1/padel/bookings/${id}/ticket`);
                    const json = await res.json();
                    if (json.success && json.data) {
                        this.ticket = json.data;
                        this.currentTicket = json.data;

                        // Sinkronkan selectedMethod jika booking/order sudah memiliki metode pembayaran riil
                        const rawMethod = this.ticket.payment_method_label 
                            || this.ticket.payment_method 
                            || (this.ticket.order && (this.ticket.order.payment_method_label || this.ticket.order.payment_method));

                        if (rawMethod) {
                            const methodObj = this.getPaymentMethodObject(rawMethod);
                            if (methodObj) {
                                this.selectedMethod = methodObj;
                            }
                            if (rawMethod === 'CASH' || this.ticket.payment_method === 'CASH') {
                                this.isCashNotice = true;
                            }
                        }

                        // QA DEFENSE 2: Jika status masih PENDING / PENDING_PAYMENT, lakukan Auto-Polling
                        if (this.ticket.status === 'PENDING' || this.ticket.status === 'PENDING_PAYMENT') {
                            this.startAutoPolling(id);
                        }

                        return true;
                    }
                    return false;
                } catch(e) {
                    console.error('Gagal mengambil tiket:', e);
                    return false;
                }
            },

            async switchToBooking(id) {
                if (!id) return;
                this.isLoading = true;
                if (this.pollingInterval) {
                    clearInterval(this.pollingInterval);
                    this.pollingInterval = null;
                    this.isPolling = false;
                }

                // Update URL browser tanpa full reload
                const newUrl = new URL(window.location.href);
                newUrl.searchParams.set('booking_id', id);
                newUrl.searchParams.delete('order_id');
                newUrl.searchParams.delete('id');
                newUrl.searchParams.delete('booking_code');
                window.history.pushState({ booking_id: id }, '', newUrl);

                await this.loadTicket(id);
                await this.loadMyBookings();
                this.isLoading = false;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },

            switchSession(sBooking) {
                this.currentTicket = sBooking;
            },

            async loadLatestBooking() {
                try {
                    const res = await fetch('/api/v1/padel/my-bookings');
                    const json = await res.json();
                    if (json.success && json.data && json.data.length > 0) {
                        const latest = json.data[0];
                        await this.loadTicket(latest.id);
                    }
                } catch(e) {
                    console.error('Gagal mengambil booking terbaru:', e);
                }
            },

            async loadMyBookings() {
                try {
                    const res = await fetch('/api/v1/padel/my-bookings');
                    const json = await res.json();
                    if (json.success && json.data) {
                        const currentId = this.ticket?.id;
                        const currentOrderId = this.ticket?.order_id || this.ticket?.order?.id;
                        const currentOrderNumber = this.ticket?.order?.order_number;

                        // Filter keluar tiket saat ini dan tiket lain yang berada dalam order yang sama
                        this.pastBookings = json.data.filter(b => {
                            if (!this.ticket) return true;
                            if (b.id === currentId) return false;
                            if (currentOrderId && b.order_id === currentOrderId) return false;
                            if (currentOrderNumber && (b.order_id === currentOrderNumber || b.order?.order_number === currentOrderNumber)) return false;
                            return true;
                        });
                    }
                } catch(e) {
                    console.error('Gagal mengambil riwayat booking:', e);
                }
            },

            calculateDuration(start, end) {
                if (!start || !end) return 1;
                try {
                    const s = new Date(start).getTime();
                    const e = new Date(end).getTime();
                    if (!isNaN(s) && !isNaN(e)) {
                        return Math.max(1, Math.round((e - s) / (1000 * 60 * 60)));
                    }
                } catch(e) {}
                return 1;
            },

            /**
             * QA DEFENSE 2: Auto-Polling anti race condition Webhook vs Redirect
             */
            startAutoPolling(id) {
                if (this.isPolling) return;
                this.isPolling = true;
                this.pollCount = 0;

                this.pollingInterval = setInterval(async () => {
                    this.pollCount++;
                    try {
                        const res = await fetch(`/api/v1/padel/bookings/${id}/ticket`);
                        const json = await res.json();
                        if (json.success && json.data) {
                            this.ticket = json.data;
                            this.currentTicket = json.data;

                            if (this.ticket.status === 'PAID' || this.ticket.status === 'CONFIRMED' || this.ticket.status === 'CHECKED_IN') {
                                clearInterval(this.pollingInterval);
                                this.isPolling = false;
                            }
                        }
                    } catch(e) {}

                    if (this.pollCount >= this.maxPolls) {
                        clearInterval(this.pollingInterval);
                        this.isPolling = false;
                    }
                }, 3000);
            },

            formatNumber(val) {
                if (!val) return '0';
                return Math.round(val).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            },

            formatDate(val) {
                if (!val) return '-';
                try {
                    const d = new Date(val);
                    if (!isNaN(d.getTime())) {
                        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                        return `${String(d.getDate()).padStart(2, '0')} ${months[d.getMonth()]} ${d.getFullYear()}`;
                    }
                } catch(e) {}
                return String(val).substring(0, 10);
            },

            formatTime(isoString) {
                if (!isoString) return '--:--';
                try {
                    const d = new Date(isoString);
                    if (!isNaN(d.getTime())) {
                        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
                    }
                } catch(e) {}
                return isoString.substring(11, 16) || isoString;
            },

            /**
             * Download E-Tiket Sebagai Gambar PNG Lengkap dengan QR Code, Detail Reservasi & Ringkasan Biaya
             */
            async downloadTicketPng() {
                if (!this.currentTicket) return;
                this.isDownloadingPng = true;

                try {
                    await this.generateAndSaveTicketPng();
                } catch (err) {
                    console.error('Download PNG failed:', err);
                    this.showNotice('Gagal Mengunduh', 'Gagal membuat gambar e-tiket: ' + (err.message || err), 'error', 'Tutup');
                } finally {
                    this.isDownloadingPng = false;
                }
            },

            async generateAndSaveTicketPng() {
                if (!this.currentTicket) return;

                const width = 840;
                const height = 1320;
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');

                // Helper: Draw Rounded Rectangle
                function drawRoundRect(x, y, w, h, radius, fill = true, stroke = false, fillColor = '#ffffff', strokeColor = '#DFC387', lineWidth = 1) {
                    ctx.save();
                    ctx.beginPath();
                    ctx.moveTo(x + radius, y);
                    ctx.lineTo(x + w - radius, y);
                    ctx.quadraticCurveTo(x + w, y, x + w, y + radius);
                    ctx.lineTo(x + w, y + h - radius);
                    ctx.quadraticCurveTo(x + w, y + h, x + w - radius, y + h);
                    ctx.lineTo(x + radius, y + h);
                    ctx.quadraticCurveTo(x, y + h, x, y + h - radius);
                    ctx.lineTo(x, y + radius);
                    ctx.quadraticCurveTo(x, y, x + radius, y);
                    ctx.closePath();
                    if (fill) {
                        ctx.fillStyle = fillColor;
                        ctx.fill();
                    }
                    if (stroke) {
                        ctx.lineWidth = lineWidth;
                        ctx.strokeStyle = strokeColor;
                        ctx.stroke();
                    }
                    ctx.restore();
                }

                // 1. Base Luxury Cream Background
                const bgGrad = ctx.createLinearGradient(0, 0, 0, height);
                bgGrad.addColorStop(0, '#FAF7F0');
                bgGrad.addColorStop(0.5, '#FFFFFF');
                bgGrad.addColorStop(1, '#F7F2E6');
                ctx.fillStyle = bgGrad;
                ctx.fillRect(0, 0, width, height);

                // Outer Gold Borders
                drawRoundRect(20, 20, width - 40, height - 40, 24, false, true, null, '#DFC387', 3);
                drawRoundRect(28, 28, width - 56, height - 56, 18, false, true, null, '#EED9A8', 1);

                // 2. Ticket Header Banner (Deep Emerald Green Gradient)
                const headerH = 175;
                ctx.save();
                ctx.beginPath();
                ctx.moveTo(40 + 20, 40);
                ctx.lineTo(width - 40 - 20, 40);
                ctx.quadraticCurveTo(width - 40, 40, width - 40, 40 + 20);
                ctx.lineTo(width - 40, 40 + headerH);
                ctx.lineTo(40, 40 + headerH);
                ctx.lineTo(40, 40 + 20);
                ctx.quadraticCurveTo(40, 40, 40 + 20, 40);
                ctx.closePath();
                ctx.clip();

                const headGrad = ctx.createLinearGradient(40, 40, width - 40, 40 + headerH);
                headGrad.addColorStop(0, '#183428');
                headGrad.addColorStop(0.6, '#10241B');
                headGrad.addColorStop(1, '#0A1812');
                ctx.fillStyle = headGrad;
                ctx.fillRect(40, 40, width - 80, headerH);
                ctx.restore();

                // Header Badge
                drawRoundRect(60, 58, 270, 26, 13, true, true, 'rgba(223, 195, 135, 0.2)', '#DFC387', 1);
                ctx.fillStyle = '#F5E6BE';
                ctx.font = 'bold 11px sans-serif';
                ctx.fillText('OFFICIAL BOARDING PASS • PADEL PASS', 72, 75);

                // Court Name
                const courtName = (this.currentTicket.court && this.currentTicket.court.name) ? this.currentTicket.court.name : 'Court Arena';
                ctx.fillStyle = '#FFFFFF';
                ctx.font = 'bold 26px serif';
                ctx.fillText(courtName, 60, 118);

                // Date & Time Subtitle
                const bookingDateStr = this.formatDate(this.currentTicket.booking_date);
                const bookingTimeStr = `${this.formatTime(this.currentTicket.start_time)} - ${this.formatTime(this.currentTicket.end_time)} WIB`;
                const duration = this.calculateDuration(this.currentTicket.start_time, this.currentTicket.end_time);
                ctx.fillStyle = '#A7F3D0';
                ctx.font = '14px sans-serif';
                ctx.fillText(`${bookingDateStr} • ${bookingTimeStr} (${duration} Jam)`, 60, 145);

                // Right-side Status Badge
                const isPaid = (this.currentTicket.status === 'PAID' || this.currentTicket.status === 'CONFIRMED' || this.currentTicket.status === 'CHECKED_IN');
                const statusText = isPaid ? (this.currentTicket.status === 'CHECKED_IN' ? 'CHECKED IN' : 'LUNAS (PAID)') : 'PENDING';
                const statusBg = isPaid ? '#10B981' : '#F59E0B';
                const badgeW = 140;
                drawRoundRect(width - 60 - badgeW, 62, badgeW, 30, 15, true, false, statusBg);
                ctx.fillStyle = '#FFFFFF';
                ctx.font = 'bold 12px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(statusText, width - 60 - (badgeW / 2), 82);

                // Booking Code on Header
                const bookingCode = this.currentTicket.booking_code || this.currentTicket.id.substring(0, 10);
                ctx.fillStyle = '#DFC387';
                ctx.font = 'bold 13px monospace';
                ctx.textAlign = 'right';
                ctx.fillText('Kode: #' + bookingCode, width - 60, 125);
                ctx.textAlign = 'left';

                // 3. Perforated Divider Bar with Notches
                const divY = 40 + headerH;
                const barH = 42;
                ctx.fillStyle = '#FAF4E6';
                ctx.fillRect(40, divY, width - 80, barH);
                ctx.strokeStyle = '#DFC387';
                ctx.lineWidth = 1;
                ctx.strokeRect(40, divY, width - 80, barH);

                // Dashed line inside divider
                ctx.save();
                ctx.setLineDash([5, 5]);
                ctx.strokeStyle = '#DFC387';
                ctx.beginPath();
                ctx.moveTo(50, divY + (barH / 2));
                ctx.lineTo(width - 50, divY + (barH / 2));
                ctx.stroke();
                ctx.restore();

                // Side Notches (cutouts)
                ctx.fillStyle = '#FAF7F0';
                ctx.beginPath();
                ctx.arc(40, divY + (barH / 2), 16, 0, Math.PI * 2);
                ctx.fill();
                ctx.strokeStyle = '#DFC387';
                ctx.lineWidth = 1.5;
                ctx.stroke();

                ctx.beginPath();
                ctx.arc(width - 40, divY + (barH / 2), 16, 0, Math.PI * 2);
                ctx.fillStyle = '#FAF7F0';
                ctx.fill();
                ctx.strokeStyle = '#DFC387';
                ctx.lineWidth = 1.5;
                ctx.stroke();

                // Divider Text Pill
                drawRoundRect((width / 2) - 190, divY + 8, 380, 26, 13, true, true, '#FFFFFF', '#DFC387', 1);
                ctx.fillStyle = '#7A5818';
                ctx.font = 'bold 11px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(`STATUS: ${isPaid ? 'VALID ENTRY PASS' : 'MENUNGGU PEMBAYARAN'} • GATE: FRONTDESK`, width / 2, divY + 25);
                ctx.textAlign = 'left';

                // 4. QR Code Hero Section
                const qrSectionY = 275;
                const qrBoxW = 280;
                const qrBoxH = 280;
                const qrBoxX = (width - qrBoxW) / 2;

                drawRoundRect(qrBoxX, qrSectionY, qrBoxW, qrBoxH, 20, true, true, '#FFFFFF', '#DFC387', 2);

                const qrText = this.currentTicket.qr_code_hash || this.currentTicket.booking_code || this.currentTicket.id || 'CLUB61-PASS';
                let qrLoaded = false;

                // Attempt 1: QRCode library
                if (window.QRCode) {
                    try {
                        const qrDiv = document.createElement('div');
                        qrDiv.style.display = 'none';
                        document.body.appendChild(qrDiv);
                        new QRCode(qrDiv, {
                            text: qrText,
                            width: 220,
                            height: 220,
                            colorDark: "#183428",
                            colorLight: "#ffffff",
                            correctLevel: QRCode.CorrectLevel.H
                        });
                        await new Promise(r => setTimeout(r, 60));
                        const qrCanvasEl = qrDiv.querySelector('canvas');
                        if (qrCanvasEl) {
                            ctx.drawImage(qrCanvasEl, qrBoxX + 30, qrSectionY + 30, 220, 220);
                            qrLoaded = true;
                        }
                        document.body.removeChild(qrDiv);
                    } catch(e) {}
                }

                // Attempt 2: Image URL via API
                if (!qrLoaded) {
                    try {
                        const img = new Image();
                        img.crossOrigin = 'anonymous';
                        await new Promise((resolve) => {
                            img.onload = () => {
                                try {
                                    ctx.drawImage(img, qrBoxX + 30, qrSectionY + 30, 220, 220);
                                    qrLoaded = true;
                                } catch(e) {}
                                resolve();
                            };
                            img.onerror = () => resolve();
                            setTimeout(resolve, 2500);
                            img.src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(qrText)}`;
                        });
                    } catch(e) {}
                }

                // Fallback placeholder
                if (!qrLoaded) {
                    drawRoundRect(qrBoxX + 30, qrSectionY + 30, 220, 220, 12, true, true, '#FAF8F2', '#DFC387', 1);
                    ctx.fillStyle = '#8C6418';
                    ctx.font = 'bold 14px monospace';
                    ctx.textAlign = 'center';
                    ctx.fillText('[ QR CODE PASS ]', width / 2, qrSectionY + 130);
                    ctx.font = '11px sans-serif';
                    ctx.fillText(qrText.substring(0, 24), width / 2, qrSectionY + 155);
                    ctx.textAlign = 'left';
                }

                // Hash text under QR box
                ctx.fillStyle = '#8C6418';
                ctx.font = 'bold 14px monospace';
                ctx.textAlign = 'center';
                ctx.fillText(qrText, width / 2, qrSectionY + qrBoxH + 28);

                ctx.fillStyle = '#7A643E';
                ctx.font = '12px sans-serif';
                ctx.fillText('Tunjukkan QR Code ini kepada kasir frontdesk / turnstile gate saat check-in', width / 2, qrSectionY + qrBoxH + 48);
                ctx.textAlign = 'left';

                // 5. DETAIL RESERVASI SECTION
                const detailY = 660;
                drawRoundRect(45, detailY, 6, 20, 3, true, false, '#D4AF37');
                ctx.fillStyle = '#1F170D';
                ctx.font = 'bold 15px sans-serif';
                ctx.fillText('DETAIL RESERVASI LAPANGAN', 58, detailY + 15);

                const detailBoxH = 175;
                drawRoundRect(45, detailY + 28, width - 90, detailBoxH, 16, true, true, '#FFFFFF', '#DFC387', 1.5);

                function drawRow(y, label, value, isBold = false, isEmerald = false) {
                    ctx.fillStyle = '#7A643E';
                    ctx.font = '12px sans-serif';
                    ctx.fillText(label, 65, y);

                    ctx.fillStyle = isEmerald ? '#059669' : (isBold ? '#1F170D' : '#332714');
                    ctx.font = isBold ? 'bold 13px sans-serif' : '13px sans-serif';
                    ctx.textAlign = 'right';
                    ctx.fillText(value, width - 65, y);
                    ctx.textAlign = 'left';

                    ctx.strokeStyle = 'rgba(223, 195, 135, 0.35)';
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(65, y + 10);
                    ctx.lineTo(width - 65, y + 10);
                    ctx.stroke();
                }

                const custName = (this.customerName || 'Customer VIP');
                drawRow(detailY + 60, 'Nama Pemegang Tiket', custName + ' (VIP Member)', true);
                drawRow(detailY + 98, 'Waktu Booking', `${bookingTimeStr} (${duration} Jam)`, true);
                drawRow(detailY + 136, 'Lokasi & Arena', `${courtName} • Indoor Central AC`, true);
                drawRow(detailY + 174, 'Status Check-In', isPaid ? 'SIAP DIGUNAKAN (VALID)' : 'MENUNGGU PEMBAYARAN', true, isPaid);

                // 6. RINGKASAN PEMBAYARAN & BIAYA SECTION
                const summaryY = 885;
                drawRoundRect(45, summaryY, 6, 20, 3, true, false, '#D4AF37');
                ctx.fillStyle = '#1F170D';
                ctx.font = 'bold 15px sans-serif';
                ctx.fillText('RINGKASAN PEMBAYARAN & BIAYA', 58, summaryY + 15);

                const sumBoxH = 245;
                drawRoundRect(45, summaryY + 28, width - 90, sumBoxH, 16, true, true, '#FFFFFF', '#DFC387', 1.5);

                const cFee = this.displayCourtFee;
                const eFee = this.displayEquipmentFee;
                const gTotal = this.displayGrandTotal;
                // Resolusi nama metode pembayaran aktual dari tiket/order
                let payMethod = 'QRIS Instan (GoPay/OVO/BCA)';
                if (this.currentTicket.payment_method_label) {
                    payMethod = this.currentTicket.payment_method_label;
                } else if (this.currentTicket.payment_method) {
                    const mObj = this.getPaymentMethodObject(this.currentTicket.payment_method);
                    payMethod = mObj ? mObj.name : this.currentTicket.payment_method;
                } else if (this.currentTicket.order && this.currentTicket.order.payment_method) {
                    const mObj = this.getPaymentMethodObject(this.currentTicket.order.payment_method);
                    payMethod = mObj ? mObj.name : this.currentTicket.order.payment_method;
                } else if (this.selectedMethod && this.selectedMethod.name) {
                    payMethod = this.selectedMethod.name;
                }

                function drawSumRow(y, label, value, isBold = false) {
                    ctx.fillStyle = '#7A643E';
                    ctx.font = '12px sans-serif';
                    ctx.fillText(label, 65, y);

                    ctx.fillStyle = isBold ? '#1F170D' : '#332714';
                    ctx.font = isBold ? 'bold 13px sans-serif' : '13px sans-serif';
                    ctx.textAlign = 'right';
                    ctx.fillText(value, width - 65, y);
                    ctx.textAlign = 'left';

                    ctx.strokeStyle = 'rgba(223, 195, 135, 0.35)';
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(65, y + 10);
                    ctx.lineTo(width - 65, y + 10);
                    ctx.stroke();
                }

                drawSumRow(summaryY + 60, 'Sewa Lapangan Padel', 'Rp ' + this.formatNumber(cFee), false);
                drawSumRow(summaryY + 98, 'Sewa Peralatan (Raket & Bola)', 'Rp ' + this.formatNumber(eFee), false);
                drawSumRow(summaryY + 136, 'Metode Pembayaran', payMethod, true);

                // Grand Total Highlight Banner
                drawRoundRect(60, summaryY + 160, width - 120, 68, 12, true, true, '#FAF4E6', '#DFC387', 1.5);
                ctx.fillStyle = '#1F170D';
                ctx.font = 'bold 14px sans-serif';
                ctx.fillText('TOTAL PEMBAYARAN', 80, summaryY + 200);

                ctx.fillStyle = '#8C6418';
                ctx.font = 'bold 22px monospace';
                ctx.textAlign = 'right';
                ctx.fillText('Rp ' + this.formatNumber(gTotal), width - 80, summaryY + 202);
                ctx.textAlign = 'left';

                // 7. FOOTER SECTION
                const footerY = 1185;
                ctx.strokeStyle = '#DFC387';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(80, footerY);
                ctx.lineTo(width - 80, footerY);
                ctx.stroke();

                ctx.fillStyle = '#8C6418';
                ctx.font = 'bold 13px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('CLUB 61 PADEL ARENA • PLAY. COMPETE. CONNECT.', width / 2, footerY + 30);

                ctx.fillStyle = '#7A643E';
                ctx.font = '11px sans-serif';
                ctx.fillText('Simpan gambar e-tiket ini di galeri ponsel Anda sebagai bukti reservasi resmi.', width / 2, footerY + 50);

                const now = new Date();
                const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                const timeStampStr = `${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} ${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')} WIB`;
                ctx.fillStyle = '#A89060';
                ctx.font = '10px monospace';
                ctx.fillText(`Diunduh: ${timeStampStr} • Ref: #${bookingCode}`, width / 2, footerY + 70);
                ctx.textAlign = 'left';

                // 8. Download PNG
                canvas.toBlob((blob) => {
                    if (!blob) {
                        const dataUrl = canvas.toDataURL('image/png');
                        const link = document.createElement('a');
                        link.download = `E-Tiket-Club61-${bookingCode}.png`;
                        link.href = dataUrl;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        return;
                    }
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.download = `E-Tiket-Club61-${bookingCode}.png`;
                    link.href = url;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                }, 'image/png');
            }
        }
    }
</script>

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="{{ config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" 
            data-client-key="{{ config('services.midtrans.client_key', 'SB-Mid-client-demo-61') }}"></script>
@endpush
