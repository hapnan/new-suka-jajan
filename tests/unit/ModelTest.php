<?php

namespace Tests\Unit;

use App\Database\Seeds\InitialSeeder;
use App\Models\FavoriteModel;
use App\Models\KulinerModel;
use App\Models\KulinerTagModel;
use App\Models\PhotoModel;
use App\Models\ReviewModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = InitialSeeder::class;

    public function testUpdateRatingRecalculatesAverageAndTotal(): void
    {
        $kulinerModel = new KulinerModel();
        $reviewModel  = new ReviewModel();

        // Kuliner 19 initially has 1 review with rating 4
        $reviewModel->insert([
            'kuliner_id' => 19,
            'user_id'    => 2,
            'rating'     => 2,
            'komentar'   => 'Kurang dingin saat disajikan siang hari.',
        ]);

        $kulinerModel->updateRating(19);
        $updated = $kulinerModel->find(19);

        $this->assertEquals(2, (int) $updated['total_reviews']);
        $this->assertEqualsWithDelta(3.00, (float) $updated['avg_rating'], 0.01);
    }

    public function testFindNearbyReturnsApprovedWithinRadiusOrderedByDistance(): void
    {
        $kulinerModel = new KulinerModel();
        // Coordinates near Simpang Lima Semarang
        $results = $kulinerModel->findNearby(-6.9850, 110.4190, 2.0)->findAll();

        $this->assertNotEmpty($results);
        $prevDistance = 0.0;
        foreach ($results as $row) {
            $this->assertEquals('approved', $row['status']);
            $this->assertLessThanOrEqual(2.0, (float) $row['distance']);
            $this->assertGreaterThanOrEqual($prevDistance, (float) $row['distance']);
            $prevDistance = (float) $row['distance'];
        }
    }

    public function testReviewCanEditEnforcesOwnershipAnd24HourWindow(): void
    {
        $reviewModel = new ReviewModel();
        $reviewId = $reviewModel->insert([
            'kuliner_id' => 19,
            'user_id'    => 2,
            'rating'     => 5,
            'komentar'   => 'Jus mangga sangat segar dan manis alami.',
        ]);

        // Owner within 24h -> true
        $this->assertTrue($reviewModel->canEdit($reviewId, 2));
        // Non-owner -> false
        $this->assertFalse($reviewModel->canEdit($reviewId, 1));

        // Simulate review created 25 hours ago
        $this->db->table('reviews')->where('id', $reviewId)->update([
            'created_at' => date('Y-m-d H:i:s', time() - 90000),
        ]);
        $this->assertFalse($reviewModel->canEdit($reviewId, 2));
    }

    public function testFavoriteToggleAddsAndRemovesRecord(): void
    {
        $favModel = new FavoriteModel();
        $this->assertFalse($favModel->isFavorited(2, 1));

        $added = $favModel->toggleFavorite(2, 1);
        $this->assertTrue($added);
        $this->assertTrue($favModel->isFavorited(2, 1));

        $userFavs = $favModel->getUserFavorites(2);
        $this->assertNotEmpty($userFavs);
        $this->assertEquals(1, (int) $userFavs[0]['kuliner_id']);

        $addedAgain = $favModel->toggleFavorite(2, 1);
        $this->assertFalse($addedAgain);
        $this->assertFalse($favModel->isFavorited(2, 1));
    }

    public function testKulinerTagSyncPhotoModelAndReviewGetByKuliner(): void
    {
        $tagModel = new KulinerTagModel();
        $tagModel->syncTags(1, [1, 2]);
        $tags = $tagModel->getTagsByKuliner(1);
        $this->assertCount(2, $tags);

        $photoModel = new PhotoModel();
        $this->assertEquals(0, $photoModel->countByKuliner(1));
        $photoModel->insert([
            'kuliner_id' => 1,
            'user_id'    => 2,
            'filename'   => 'test.jpg',
            'thumbnail'  => 'thumb_test.jpg',
        ]);
        $this->assertEquals(1, $photoModel->countByKuliner(1));
        $photos = $photoModel->getByKuliner(1);
        $this->assertCount(1, $photos);
        $this->assertEquals('test.jpg', $photos[0]['filename']);

        $reviewModel = new ReviewModel();
        $reviews = $reviewModel->getByKuliner(19);
        $this->assertNotEmpty($reviews);
        $this->assertArrayHasKey('username', $reviews[0]);
    }
}
