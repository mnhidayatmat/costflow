<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WccAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WccAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function pngBytes(int $width = 4, int $height = 4): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return ob_get_clean();
    }

    private function png(string $name = 'sig.png', ?string $bytes = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'sig').'.png';
        file_put_contents($path, $bytes ?? $this->pngBytes());

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    public function test_an_engineer_can_upload_a_signature(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->engineer()->create())
            ->post(route('wcc.attachments.store'), ['file' => $this->png()])
            ->assertCreated()
            ->assertJsonStructure(['hash', 'url']);

        $this->assertDatabaseCount('wcc_attachments', 1);

        $attachment = WccAttachment::sole();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame('image/png', $attachment->mime);
    }

    public function test_identical_images_are_stored_once(): void
    {
        Storage::fake('local');

        $user = User::factory()->engineer()->create();
        $bytes = $this->pngBytes();

        $first = $this->actingAs($user)->post(route('wcc.attachments.store'), ['file' => $this->png('a.png', $bytes)]);
        $second = $this->actingAs($user)->post(route('wcc.attachments.store'), ['file' => $this->png('b.png', $bytes)]);

        $this->assertSame($first->json('hash'), $second->json('hash'));
        $this->assertDatabaseCount('wcc_attachments', 1);
    }

    public function test_an_svg_is_rejected_because_it_can_carry_script(): void
    {
        Storage::fake('local');

        $svg = UploadedFile::fake()->createWithContent(
            'x.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->actingAs(User::factory()->engineer()->create())
            ->post(route('wcc.attachments.store'), ['file' => $svg])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('wcc_attachments', 0);
    }

    public function test_an_oversized_image_is_rejected(): void
    {
        Storage::fake('local');

        $huge = UploadedFile::fake()->image('big.png')->size(3 * 1024); // KB

        $this->actingAs(User::factory()->engineer()->create())
            ->post(route('wcc.attachments.store'), ['file' => $huge])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('wcc_attachments', 0);
    }

    public function test_a_guest_cannot_upload_or_read_an_attachment(): void
    {
        Storage::fake('local');

        $attachment = WccAttachment::create([
            'hash' => str_repeat('a', 64),
            'path' => 'wcc-attachments/x.png',
            'mime' => 'image/png',
            'size' => 10,
        ]);

        $this->post(route('wcc.attachments.store'), ['file' => $this->png()])->assertRedirect(route('login'));
        $this->get(route('wcc.attachments.show', $attachment->hash))->assertRedirect(route('login'));
    }

    public function test_an_attachment_is_served_with_hardening_headers(): void
    {
        Storage::fake('local');

        $user = User::factory()->engineer()->create();
        $hash = $this->actingAs($user)->post(route('wcc.attachments.store'), ['file' => $this->png()])->json('hash');

        $this->actingAs($user)
            ->get(route('wcc.attachments.show', $hash))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_the_hash_route_cannot_be_used_for_path_traversal(): void
    {
        $this->actingAs(User::factory()->engineer()->create())
            ->get('/wcc/attachments/'.urlencode('../../.env'))
            ->assertNotFound();
    }
}
