<?php

namespace KaziOmar\SmartUpload\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use KaziOmar\SmartUpload\Tests\TestCase;
use KaziOmar\SmartUpload\Traits\HasUploadPurge;

class TestUser extends Model
{
    use HasUploadPurge;

    protected $table = 'test_users';
    protected $guarded = [];
    protected array $purgeableFiles = ['avatar', 'cover'];
}

class SoftDeletedTestUser extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    use HasUploadPurge;

    protected $table = 'soft_test_users';
    protected $guarded = [];
    protected array $purgeableFiles = ['avatar'];
}

class TestReview extends Model
{
    use HasUploadPurge;

    protected $table = 'test_reviews';
    protected $guarded = [];
    protected $casts = [
        'photos' => 'array',
    ];
    protected array $purgeableFiles = ['photos'];
}

class ModelPurgeTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('avatar')->nullable();
            $table->string('cover')->nullable();
            $table->timestamps();
        });

        Schema::create('soft_test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('avatar')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('test_reviews', function (Blueprint $table) {
            $table->id();
            $table->text('comment')->nullable();
            $table->json('photos')->nullable();
            $table->timestamps();
        });
    }

    public function test_purges_old_file_when_model_attribute_is_updated(): void
    {
        Storage::disk('public')->put('avatars/v1.jpg', 'v1');
        Storage::disk('public')->put('avatars/v2.jpg', 'v2');

        $user = TestUser::create([
            'name' => 'Kazi Omar',
            'avatar' => 'avatars/v1.jpg',
        ]);

        $this->assertTrue(Storage::disk('public')->exists('avatars/v1.jpg'));

        // Update avatar to v2
        $user->update(['avatar' => 'avatars/v2.jpg']);

        // v1 should have been deleted, v2 stays
        $this->assertFalse(Storage::disk('public')->exists('avatars/v1.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('avatars/v2.jpg'));
    }

    public function test_purges_all_files_when_model_is_deleted(): void
    {
        Storage::disk('public')->put('avatars/user-avatar.jpg', 'pic');
        Storage::disk('public')->put('covers/user-cover.jpg', 'banner');

        $user = TestUser::create([
            'name' => 'Kazi Omar',
            'avatar' => 'avatars/user-avatar.jpg',
            'cover' => 'covers/user-cover.jpg',
        ]);

        $this->assertTrue(Storage::disk('public')->exists('avatars/user-avatar.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('covers/user-cover.jpg'));

        // Delete user
        $user->delete();

        // Both files should be auto-purged from storage
        $this->assertFalse(Storage::disk('public')->exists('avatars/user-avatar.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('covers/user-cover.jpg'));
    }

    public function test_respects_soft_deletes_and_only_purges_on_force_delete(): void
    {
        Storage::disk('public')->put('avatars/soft-user.jpg', 'pic');

        $user = SoftDeletedTestUser::create([
            'name' => 'Soft User',
            'avatar' => 'avatars/soft-user.jpg',
        ]);

        $this->assertTrue(Storage::disk('public')->exists('avatars/soft-user.jpg'));

        // Soft delete model -> file must NOT be deleted yet!
        $user->delete();
        $this->assertTrue(Storage::disk('public')->exists('avatars/soft-user.jpg'));

        // Force delete model -> file MUST be deleted!
        $user->forceDelete();
        $this->assertFalse(Storage::disk('public')->exists('avatars/soft-user.jpg'));
    }

    public function test_prevents_data_loss_by_not_deleting_old_file_if_model_update_rolls_back(): void
    {
        Storage::disk('public')->put('avatars/safe-orig.jpg', 'original');

        $user = TestUser::create([
            'name' => 'John Doe',
            'avatar' => 'avatars/safe-orig.jpg',
        ]);

        $this->assertTrue(Storage::disk('public')->exists('avatars/safe-orig.jpg'));

        try {
            DB::transaction(function () use ($user) {
                $user->update(['avatar' => 'avatars/new-uncommitted.jpg']);

                // Simulate DB failure before commit
                throw new \Exception('Failed SQL transaction!');
            });
        } catch (\Exception $e) {
            // Expected
        }

        // Because transaction rolled back, old file MUST STILL EXIST on disk (No data loss!)
        $this->assertTrue(Storage::disk('public')->exists('avatars/safe-orig.jpg'));
    }

    public function test_purges_array_and_json_files_when_model_is_deleted(): void
    {
        Storage::disk('public')->put('reviews/p1.webp', 'photo1');
        Storage::disk('public')->put('reviews/p2.webp', 'photo2');
        Storage::disk('public')->put('reviews/p3.webp', 'photo3');

        $review = TestReview::create([
            'comment' => 'Great product!',
            'photos' => ['reviews/p1.webp', 'reviews/p2.webp', 'reviews/p3.webp'],
        ]);

        $this->assertTrue(Storage::disk('public')->exists('reviews/p1.webp'));
        $this->assertTrue(Storage::disk('public')->exists('reviews/p2.webp'));
        $this->assertTrue(Storage::disk('public')->exists('reviews/p3.webp'));

        // Delete model with JSON array column
        $review->delete();

        // All array elements should be automatically purged
        $this->assertFalse(Storage::disk('public')->exists('reviews/p1.webp'));
        $this->assertFalse(Storage::disk('public')->exists('reviews/p2.webp'));
        $this->assertFalse(Storage::disk('public')->exists('reviews/p3.webp'));
    }

    public function test_purges_only_removed_files_when_array_column_is_partially_updated(): void
    {
        Storage::disk('public')->put('reviews/pic_keep.webp', 'keep');
        Storage::disk('public')->put('reviews/pic_remove.webp', 'remove');
        Storage::disk('public')->put('reviews/pic_new.webp', 'new');

        $review = TestReview::create([
            'comment' => 'Initial review',
            'photos' => ['reviews/pic_keep.webp', 'reviews/pic_remove.webp'],
        ]);

        // User edits review: keeps 'pic_keep.webp', deletes 'pic_remove.webp', adds 'pic_new.webp'
        $review->update([
            'photos' => ['reviews/pic_keep.webp', 'reviews/pic_new.webp'],
        ]);

        // pic_remove.webp MUST be purged!
        $this->assertFalse(Storage::disk('public')->exists('reviews/pic_remove.webp'));

        // pic_keep.webp and pic_new.webp MUST still exist!
        $this->assertTrue(Storage::disk('public')->exists('reviews/pic_keep.webp'));
        $this->assertTrue(Storage::disk('public')->exists('reviews/pic_new.webp'));
    }

    public function test_purges_nested_array_and_object_column_updates_without_array_to_string_warning(): void
    {
        Storage::disk('public')->put('reviews/obj_keep.webp', 'keep');
        Storage::disk('public')->put('reviews/obj_remove.webp', 'remove');
        Storage::disk('public')->put('reviews/obj_new.webp', 'new');

        // Column storing array of objects/DTO-like arrays with 'path' and 'url'
        $review = TestReview::create([
            'comment' => 'Nested array review',
            'photos' => [
                ['path' => 'reviews/obj_keep.webp', 'url' => 'https://example.com/keep'],
                ['path' => 'reviews/obj_remove.webp', 'url' => 'https://example.com/remove'],
            ],
        ]);

        // Update removing obj_remove and adding obj_new
        $review->update([
            'photos' => [
                ['path' => 'reviews/obj_keep.webp', 'url' => 'https://example.com/keep'],
                ['path' => 'reviews/obj_new.webp', 'url' => 'https://example.com/new'],
            ],
        ]);

        // obj_remove must be purged from disk, others preserved, with zero PHP warnings
        $this->assertFalse(Storage::disk('public')->exists('reviews/obj_remove.webp'));
        $this->assertTrue(Storage::disk('public')->exists('reviews/obj_keep.webp'));
        $this->assertTrue(Storage::disk('public')->exists('reviews/obj_new.webp'));
    }
}
