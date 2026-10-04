<?php

use App\Services\Membership\MembershipFacilityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Club 61 hanya punya Sauna — Ice Bath / Cold Plunge tidak pernah ada. Seeder & konten default lama
 * terlanjur membuatnya, jadi data yang sudah ter-seed dibersihkan di sini:
 *  - fasilitas wellness "Ice Bath / Cold Plunge" + slotnya dihapus, HANYA jika belum pernah ada booking /
 *    waitlist (FK slot → booking cascade; catatan transaksi customer tidak boleh ikut terhapus);
 *  - nama & deskripsi master fasilitas membership SAUNA, dan konten website default, diganti hanya jika
 *    masih persis teks default lama (hasil edit staf lewat admin tidak ditimpa).
 */
return new class extends Migration
{
    public function up(): void
    {
        $iceBathIds = DB::table('wellness_facilities')
            ->where(fn ($q) => $q->where('name', 'like', '%ice bath%')->orWhere('name', 'like', '%cold plunge%'))
            ->pluck('id');

        foreach ($iceBathIds as $facilityId) {
            $slotIds = DB::table('wellness_slots')->where('facility_id', $facilityId)->pluck('id');
            $hasHistory = DB::table('wellness_bookings')->whereIn('slot_id', $slotIds)->exists()
                || DB::table('wellness_waitlists')->whereIn('slot_id', $slotIds)->exists();

            if ($hasHistory) {
                Log::warning('Fasilitas wellness Ice Bath tidak dihapus karena sudah punya booking/waitlist.', ['facility_id' => $facilityId]);

                continue;
            }

            DB::table('wellness_slots')->where('facility_id', $facilityId)->delete();
            DB::table('wellness_facilities')->where('id', $facilityId)->delete();
        }

        DB::table('membership_facilities')->where('code', 'SAUNA')->where('name', 'Sauna & Ice Bath')->update(['name' => 'Sauna', 'updated_at' => now()]);
        DB::table('membership_facilities')->where('code', 'SAUNA')->where('description', 'Sesi sauna & ice bath Club 61.')->update(['description' => 'Sesi sauna Club 61.', 'updated_at' => now()]);

        $replacements = [
            ['company_profile_settings', 'hero_subtitle',
                'Fasilitas terpadu berstandar internasional di Gedung Indosat Medan: {court_count} Lapangan Padel Panoramic Full Indoor ber-AC, Thermal Wellness Recovery (Sauna & Ice Bath 4°C).',
                'Fasilitas terpadu berstandar internasional di Gedung Indosat Medan: {court_count} Lapangan Padel Panoramic Full Indoor ber-AC, Thermal Wellness Recovery (Finnish Sauna).'],
            ['company_profile_settings', 'hero_subtitle_en',
                'An internationally-standard integrated facility at Gedung Indosat Medan: {court_count} Panoramic Full-Indoor Air-Conditioned Padel Courts, plus Thermal Wellness Recovery (Sauna & 4°C Ice Bath).',
                'An internationally-standard integrated facility at Gedung Indosat Medan: {court_count} Panoramic Full-Indoor Air-Conditioned Padel Courts, plus Thermal Wellness Recovery (Finnish Sauna).'],
            ['company_profile_facilities', 'description',
                'Ruang pemulihan pasca-main dengan sauna dan ice bath.',
                'Ruang pemulihan pasca-main dengan sauna.'],
            ['company_profile_facilities', 'description_en',
                'A post-match recovery space with sauna and ice bath.',
                'A post-match recovery space with sauna.'],
        ];

        foreach ($replacements as [$table, $column, $old, $new]) {
            DB::table($table)->where($column, $old)->update([$column => $new]);
        }

        // Kolom JSON amenities: bandingkan isi (bukan string mentah) supaya format encoding tidak berpengaruh.
        foreach (DB::table('company_profile_facilities')->where('title', 'Wellness Suite')->get(['id', 'amenities', 'amenities_en']) as $row) {
            $update = [];
            if (json_decode((string) $row->amenities, true) === ['Sauna', 'Ice bath 4°C', 'Ruang ganti privat']) {
                $update['amenities'] = json_encode(['Sauna', 'Ruang ganti privat']);
            }
            if (json_decode((string) $row->amenities_en, true) === ['Sauna', '4°C ice bath', 'Private changing room']) {
                $update['amenities_en'] = json_encode(['Sauna', 'Private changing room']);
            }
            if ($update) {
                DB::table('company_profile_facilities')->where('id', $row->id)->update($update);
            }
        }

        app(MembershipFacilityService::class)->flush();
    }

    public function down(): void
    {
        // Pembersihan data — tidak dikembalikan.
    }
};
