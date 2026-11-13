<?php

namespace Tests\Feature;

use App\DealType;
use App\Filament\Resources\Deals\Pages\CreateDeal;
use App\Filament\Resources\Deals\Pages\ListDeals;
use App\Filament\Resources\Rankings\Pages\EditRanking;
use App\Filament\Resources\Rankings\RelationManagers\ToolsRelationManager;
use App\Filament\Resources\ToolCategories\Pages\EditToolCategory;
use App\Models\Deal;
use App\Models\Ranking;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use App\PublicationStatus;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class DealAndRankingAdministrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_permissions_by_role(): void
    {
        $deal = Deal::factory()->create();
        $ranking = Ranking::factory()->create();

        $this->actingAs(User::factory()->administrator()->create());
        $this->get('/admin/deals')->assertOk();
        $this->get('/admin/rankings')->assertOk();
        $this->get('/admin/rankings/'.$ranking->slug.'/edit')->assertOk();
        $this->assertTrue(Gate::allows('delete', $deal));

        $this->actingAs(User::factory()->create(['role' => UserRole::Editor]));
        $this->assertTrue(Gate::allows('update', $deal));
        $this->assertTrue(Gate::allows('create', Ranking::class));
        $this->assertFalse(Gate::allows('delete', $deal));
        $this->assertFalse(Gate::allows('delete', $ranking));

        $this->actingAs(User::factory()->create(['role' => UserRole::User]));
        $this->get('/admin/deals')->assertForbidden();
        $this->assertFalse(Gate::allows('viewAny', Deal::class));
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/deals')->assertRedirect();
    }

    public function test_coupon_requires_code_and_rejects_unsafe_url(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $tool = Tool::factory()->published()->create();

        Livewire::test(CreateDeal::class)
            ->fillForm([
                'tool_id' => $tool->id,
                'title' => 'Cupón 20%',
                'slug' => 'cupon-20',
                'type' => DealType::Coupon->value,
                'code' => '',
                'url' => 'javascript:alert(1)',
                'currency' => 'USD',
                'status' => PublicationStatus::Draft->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['code' => 'required', 'url']);

        Livewire::test(CreateDeal::class)
            ->fillForm([
                'tool_id' => $tool->id,
                'title' => 'Cupón 20%',
                'slug' => 'cupon-20',
                'type' => DealType::Coupon->value,
                'code' => 'SAVE20',
                'url' => 'https://partner.example/offer',
                'original_price' => 50,
                'deal_price' => 40,
                'currency' => 'USD',
                'status' => PublicationStatus::Published->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $deal = Deal::query()->where('slug', 'cupon-20')->firstOrFail();
        $this->assertSame(20, $deal->discount_percent);
        $this->assertNotNull($deal->published_at);
    }

    public function test_verify_action_and_filters(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $active = Deal::factory()->published()->create(['verified_at' => null]);
        $expired = Deal::factory()->published()->expired()->create();

        Livewire::test(ListDeals::class)
            ->callTableAction('verify', $active)
            ->filterTable('active')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$expired]);

        $this->assertNotNull($active->fresh()->verified_at);
    }

    public function test_ranking_relation_manager_attaches_tools_with_position(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $ranking = Ranking::factory()->create();
        $tool = Tool::factory()->published()->create();

        Livewire::test(ToolsRelationManager::class, ['ownerRecord' => $ranking, 'pageClass' => EditRanking::class])
            ->callTableAction('attach', data: ['recordId' => $tool->id, 'position' => 1, 'best_for' => 'Equipos'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, $ranking->tools()->first()->pivot->position);
    }

    public function test_sector_cannot_be_its_own_parent(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $parent = ToolCategory::factory()->create();
        $sector = ToolCategory::factory()->create();

        Livewire::test(EditToolCategory::class, ['record' => $sector->slug])
            ->fillForm(['parent_id' => $parent->id, 'icon' => 'sparkles', 'accent_color' => '#7C3AED'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($sector->fresh()->parent->is($parent));

        Livewire::test(EditToolCategory::class, ['record' => $sector->slug])
            ->fillForm(['parent_id' => $sector->id])
            ->call('save')
            ->assertHasFormErrors(['parent_id']);
    }
}
