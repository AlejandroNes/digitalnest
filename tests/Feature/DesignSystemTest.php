<?php

namespace Tests\Feature;

use App\Models\Deal;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DesignSystemTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_design_preview_renders_app_shell_and_components(): void
    {
        $this->get('/_diseno')
            ->assertOk()
            ->assertSee('name="robots" content="noindex"', false)
            ->assertSee('viewport-fit=cover', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('data-copy-code="NEST20"', false)
            ->assertSeeInOrder(['Inicio', 'Explorar', 'Ofertas', 'Rankings', 'Guardados']);
    }

    public function test_price_label_formats_by_currency(): void
    {
        $usd = new Deal(['currency' => 'USD']);
        $eur = new Deal(['currency' => 'EUR']);

        $this->assertSame('$20', $usd->priceLabel(20));
        $this->assertSame('$2.50', $usd->priceLabel('2.5'));
        $this->assertSame('19,99 €', $eur->priceLabel(19.99));
        $this->assertNull($usd->priceLabel(null));
    }
}
