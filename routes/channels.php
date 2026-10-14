<?php

use Illuminate\Support\Facades\Broadcast;

// Notifikasi Filament per user (aktif begitu Echo panel admin menyala) — hanya pemilik akun yang boleh berlangganan.
// Channel layar jadwal (booking-board) & Digital Signage (signature) publik dan hanya berisi tanggal, jadi tidak di sini.
Broadcast::channel('App.Models.User.{id}', fn ($user, string $id) => (string) $user->getKey() === $id);
