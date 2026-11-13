<?php

namespace App\Http\Controllers;

use App\DealType;
use App\Models\Deal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DealController extends Controller
{
    private const PER_PAGE = 24;

    /**
     * Public filter slugs shown as chips on the deals feed.
     *
     * @var array<string, string>
     */
    public const FILTERS = [
        'cupones' => 'Cupones',
        'descuentos' => 'Descuentos',
        'pago-unico' => 'Pago único',
        'prueba-gratis' => 'Prueba gratis',
        'caducan-pronto' => 'Caducan pronto',
    ];

    public function index(Request $request): View
    {
        $filter = $request->validate([
            'filtro' => ['nullable', Rule::in(array_keys(self::FILTERS))],
            'page' => ['nullable', 'integer', 'min:1'],
        ])['filtro'] ?? null;

        $query = Deal::query()->active()->with('tool.logo.model');
        $query = match ($filter) {
            'cupones' => $query->where('type', DealType::Coupon),
            'descuentos' => $query->where('type', DealType::Discount),
            'pago-unico' => $query->where('type', DealType::Lifetime),
            'prueba-gratis' => $query->where('type', DealType::FreeTrial),
            'caducan-pronto' => $query->expiringWithin(3)->reorder()->orderBy('expires_at'),
            default => $query,
        };

        return view('nest.deals.index', [
            'filter' => $filter,
            'deals' => ($filter === 'caducan-pronto' ? $query : $query->ordered())->paginate(self::PER_PAGE)->withQueryString(),
        ]);
    }

    public function show(string $slug): View
    {
        $deal = Deal::query()->visible()->where('slug', $slug)->with('tool.logo.model', 'tool.category')->firstOrFail();

        $related = Deal::query()->active()->whereKeyNot($deal->id)->with('tool.logo.model')
            ->when($deal->tool->tool_category_id, fn (Builder $query, int $sectorId): Builder => $query->inSectors([$sectorId]))
            ->ordered()->limit(4)->get();

        return view('nest.deals.show', ['deal' => $deal, 'related' => $related]);
    }

    /**
     * Outbound redirect that counts the click without storing anything about the visitor.
     */
    public function go(string $slug): RedirectResponse
    {
        $deal = Deal::query()->visible()->where('slug', $slug)->with('tool')->firstOrFail();
        $destination = $deal->outboundUrl();
        abort_if($destination === null, 404);

        Deal::query()->whereKey($deal->id)->toBase()->increment('clicks');

        return redirect()->away($destination)->withHeaders([
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
    }
}
