<?php

use App\Models\Setting\CompanyProfileSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Venue tidak lagi dipromosikan sebagai "panoramic" / "ber-AC" (satu lapangan outdoor). Teks bawaan yang masih
 * menyebutnya diganti teks venue yang sekarang (sama dengan sandbox). Teks yang sudah diubah staf lewat Konten
 * Website / Master Data dan tidak menyebut panoramic / AC tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_profile_settings')) {
            DB::table('company_profile_settings')->where('hero_subtitle', 'like', '%anoramic%')
                ->update(['hero_subtitle' => CompanyProfileSetting::DEFAULT_HERO_SUBTITLE]);
            DB::table('company_profile_settings')->where('hero_subtitle_en', 'like', '%anoramic%')
                ->update(['hero_subtitle_en' => CompanyProfileSetting::DEFAULT_HERO_SUBTITLE_EN]);

            foreach (DB::table('company_profile_settings')->get(['id', 'facility_cards']) as $row) {
                $cards = json_decode((string) $row->facility_cards, true);
                if (! is_array($cards)) {
                    continue;
                }
                $changed = false;
                foreach ($cards as &$card) {
                    foreach (['subtitle', 'subtitle_en'] as $key) {
                        if (isset($card[$key]) && stripos((string) $card[$key], 'panoramic') !== false) {
                            $card[$key] = '';
                            $changed = true;
                        }
                    }
                }
                unset($card);
                if ($changed) {
                    DB::table('company_profile_settings')->where('id', $row->id)->update(['facility_cards' => json_encode($cards)]);
                }
            }
        }

        if (Schema::hasTable('company_profile_facilities')) {
            DB::table('company_profile_facilities')->where('description', 'like', '%anoramic%')
                ->update(['description' => 'Lapangan padel, standar internasional.']);
            DB::table('company_profile_facilities')->where('description_en', 'like', '%anoramic%')
                ->update(['description_en' => 'Internationally-standard padel courts.']);

            foreach (DB::table('company_profile_facilities')->get(['id', 'amenities', 'amenities_en']) as $row) {
                $update = [];
                foreach (['amenities', 'amenities_en'] as $column) {
                    $items = json_decode((string) $row->{$column}, true);
                    if (! is_array($items)) {
                        continue;
                    }
                    $kept = array_values(array_filter($items, fn ($item) => stripos((string) $item, 'panoramic') === false));
                    if (count($kept) !== count($items)) {
                        $update[$column] = json_encode($kept);
                    }
                }
                if ($update) {
                    DB::table('company_profile_facilities')->where('id', $row->id)->update($update);
                }
            }
        }

        if (Schema::hasTable('padel_courts')) {
            DB::table('padel_courts')->whereIn('description', ['Indoor • Central AC', 'Outdoor • Open Air Court'])->update(['description' => null]);
        }

        Cache::forget(CompanyProfileSetting::CACHE_KEY);
    }

    public function down(): void
    {
        // Teks lama sengaja tidak dikembalikan.
    }
};
