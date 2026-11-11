<?php

namespace Tests\Feature;

use App\Filament\Pages\GeneralSettings;
use App\Filament\Pages\SearchSettings;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Livewire\MediaLibraryBrowser;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectImage;
use App\Models\User;
use App\Services\MediaUploader;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MediaSelectionExperienceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('public');
        $this->actingAs(User::factory()->administrator()->create());
    }

    private function image(string $name, int $width): MediaAsset
    {
        return app(MediaUploader::class)->upload(auth()->user(), UploadedFile::fake()->image($name.'.png', $width, 80), $name, 'Descripción de '.$name)['asset'];
    }

    #[TestWith([CreateProject::class, '1200 × 675 px'])]
    #[TestWith([CreateService::class, '1200 × 800 px'])]
    #[TestWith([CreatePost::class, '1200 × 630 px'])]
    #[TestWith([CreateTestimonial::class, '400 × 400 px'])]
    #[TestWith([GeneralSettings::class, '512 × 512 px'])]
    #[TestWith([SearchSettings::class, '1200 × 630 px'])]
    public function test_consumers_show_recommended_dimensions_before_selecting(string $page, string $recommendation): void
    {
        Livewire::test($page)->assertOk()->assertSee($recommendation);
    }

    public function test_assistant_avatar_can_be_selected_and_guidance_is_not_a_dimension_requirement(): void
    {
        $asset = $this->image('Retrato', 120);
        Livewire::test(MediaLibraryBrowser::class, ['target' => 'avatar', 'context' => 'assistant_avatar'])
            ->assertOk()->assertSee('200 × 200 px')->assertSee('120 × 80 px')
            ->callTableAction('selectImage', $asset)->assertSet('selectedMediaId', $asset->getFirstMedia('images')->id);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_multiple_selection_keeps_order_across_search_and_can_be_removed(): void
    {
        $first = $this->image('Oficina', 120);
        $second = $this->image('Equipo', 121);
        $firstId = $first->getFirstMedia('images')->id;
        $secondId = $second->getFirstMedia('images')->id;
        Livewire::test(MediaLibraryBrowser::class, ['target' => 'gallery', 'context' => 'gallery', 'multiple' => true])
            ->callTableAction('selectImage', $second)->callTableAction('selectImage', $first)
            ->assertSet('selectedMediaIds', [$secondId, $firstId])->assertSee('2 imágenes seleccionadas')
            ->searchTable('Sin coincidencias')->assertSet('selectedMediaIds', [$secondId, $firstId])
            ->call('removeSelection', $secondId)->assertSet('selectedMediaIds', [$firstId])
            ->assertDispatched('media-library-selection', target: 'gallery', mediaIds: [$firstId])
            ->searchTable('Equipo')->callTableAction('selectImage', $second)->assertSet('selectedMediaIds', [$firstId, $secondId])
            ->callTableAction('selectImage', $second)->assertSet('selectedMediaIds', [$firstId]);
    }

    public function test_project_selection_uses_one_existing_image_without_copying_media(): void
    {
        $id = $this->image('Primera', 120)->getFirstMedia('images')->id;
        $files = Storage::disk('public')->allFiles();
        Livewire::test(CreateProject::class)
            ->fillForm(['title' => 'Proyecto', 'project_category_id' => ProjectCategory::factory()->create()->id])
            ->callAction(TestAction::make('chooseImage')->schemaComponent('image_id'), ['selected_media_id' => $id])
            ->assertHasNoActionErrors()->call('create')->assertHasNoFormErrors();
        $this->assertSame($id, Project::query()->firstOrFail()->image_id);
        $this->assertDatabaseCount('project_images', 0);
        $this->assertDatabaseCount('media', 1);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_editing_a_project_preserves_legacy_gallery_captions_and_order(): void
    {
        $firstId = $this->image('Existente', 120)->getFirstMedia('images')->id;
        $secondId = $this->image('Otra', 121)->getFirstMedia('images')->id;
        $project = Project::factory()->withCategory()->create(['image_id' => $firstId]);
        $existing = ProjectImage::factory()->for($project)->create(['media_id' => $secondId, 'caption' => 'Pie de foto conservado', 'sort_order' => 7]);
        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->fillForm(['title' => 'Título actualizado'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Pie de foto conservado', $existing->fresh()->caption);
        $this->assertSame(7, $existing->fresh()->sort_order);
        $this->assertSame([$secondId], $project->galleryImages()->pluck('media_id')->all());
        $this->assertDatabaseCount('project_images', 1);
    }

    public function test_cancelling_image_selection_preserves_unsaved_project(): void
    {
        $id = $this->image('Sin guardar', 120)->getFirstMedia('images')->id;
        Livewire::test(CreateProject::class)->fillForm(['title' => 'Título sin guardar'])
            ->mountAction(TestAction::make('chooseImage')->schemaComponent('image_id'))
            ->fillForm(['selected_media_id' => $id])->unmountAction()
            ->assertSet('data.title', 'Título sin guardar')->assertSet('data.image_id', null);
        $this->assertDatabaseCount('project_images', 0);
        $this->assertDatabaseCount('projects', 0);
    }

    #[TestWith([[]])]
    #[TestWith([[999999]])]
    #[TestWith([['../foto.png']])]
    #[TestWith([[[1]]])]
    public function test_invalid_image_payload_does_not_change_project_image(array $ids): void
    {
        Livewire::test(CreateProject::class)
            ->callAction(TestAction::make('chooseImage')->schemaComponent('image_id'), ['selected_media_id' => $ids])
            ->assertHasActionErrors(['selected_media_id'])->assertSet('data.image_id', null);
        $this->assertDatabaseCount('project_images', 0);
    }

    public function test_duplicate_multiple_selection_toggles_off_without_creating_references(): void
    {
        $asset = $this->image('Repetida', 120);
        $id = $asset->getFirstMedia('images')->id;
        Livewire::test(MediaLibraryBrowser::class, ['target' => 'selection', 'context' => 'gallery', 'multiple' => true])
            ->call('selectMedia', $asset->id)->assertSet('selectedMediaIds', [$id])
            ->call('selectMedia', $asset->id)->assertSet('selectedMediaIds', []);
        $this->assertDatabaseCount('project_images', 0);
    }

    public function test_image_confirmation_revalidates_original_file(): void
    {
        $media = $this->image('Original', 120)->getFirstMedia('images');
        $page = Livewire::test(CreateProject::class)->mountAction(TestAction::make('chooseImage')->schemaComponent('image_id'))
            ->fillForm(['selected_media_id' => $media->id]);
        Storage::disk('public')->delete($media->getPathRelativeToRoot());
        $page->callMountedAction()->assertHasActionErrors(['selected_media_id'])->assertSet('data.image_id', null);
    }

    public function test_revoked_permission_blocks_image_confirmation(): void
    {
        $id = $this->image('Privada', 120)->getFirstMedia('images')->id;
        $page = Livewire::test(CreateProject::class)->mountAction(TestAction::make('chooseImage')->schemaComponent('image_id'))
            ->fillForm(['selected_media_id' => $id]);
        User::query()->whereKey(auth()->id())->update(['is_active' => false]);
        $page->callMountedAction()->assertSet('data.image_id', null);
        $this->assertDatabaseCount('project_images', 0);
    }

    #[TestWith(['unknown', false])]
    #[TestWith(['favicon', true])]
    public function test_browser_rejects_unsupported_context_or_multiple_mode(string $context, bool $multiple): void
    {
        Livewire::test(MediaLibraryBrowser::class, ['target' => 'field', 'context' => $context, 'multiple' => $multiple])->assertForbidden();
    }
}
