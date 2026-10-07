<?php

namespace Tests\Feature\Padel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTicketCardTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_card_shows_order_level_tax_service_and_grand_total(): void
    {
        // Regresi: kotak detail tiket dulu cuma menjumlah sewa lapangan + alat (total_amount per
        // booking), jadi biaya layanan dari Pengaturan Biaya & Pajak tidak pernah muncul di situ.
        $html = $this->actingAs(User::factory()->customer()->create())
            ->get('/invoice')
            ->assertOk()
            ->getContent();

        $card = substr($html, strpos($html, 'Total ticket amount') - 4000, 5000);

        $this->assertStringContainsString('ticket.order.service_charge', $card);
        $this->assertStringContainsString('ticket.order.tax_amount', $card);
        $this->assertStringContainsString('formatNumber(displayGrandTotal)', $html);
        $this->assertStringNotContainsString('formatNumber(currentTicket.total_amount)', $html);
    }
}
