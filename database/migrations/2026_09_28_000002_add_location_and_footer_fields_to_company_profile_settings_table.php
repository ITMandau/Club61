<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profile_settings', function (Blueprint $table) {
            $table->string('whatsapp_number', 30)->nullable()->after('portal_domain_text');
            $table->string('maps_embed_url', 500)->nullable()->after('whatsapp_number');
            $table->string('footer_tagline', 200)->nullable()->after('maps_embed_url');
            $table->json('footer_social_links')->nullable()->after('footer_tagline');
        });
    }

    public function down(): void
    {
        Schema::table('company_profile_settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_number', 'maps_embed_url', 'footer_tagline', 'footer_social_links']);
        });
    }
};
