<?php

namespace Tests\Unit;

use ClassyFashion\Repositories\AfricanCatalogueRepository;
use ClassyFashion\Support\Photography;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Protect the sourcing, photo review and distribution contract before import.
 */
class AfricanCatalogueTest extends TestCase
{
    /**
     * Load the same source manifest used by the explicit installation command.
     */
    protected function manifest(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../packages/ClassyFashion/src/Resources/african-catalogue/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Validation has no database dependency or side effects.
     */
    protected function catalogue(): AfricanCatalogueRepository
    {
        return (new ReflectionClass(AfricanCatalogueRepository::class))->newInstanceWithoutConstructor();
    }

    /**
     * Every listing and campaign image has an explicit person-free review.
     */
    public function test_the_published_selection_is_balanced_and_reviewed(): void
    {
        $manifest = $this->manifest();
        $rows = $this->catalogue()->validate($manifest);
        $counts = array_values(array_count_values(array_column($rows, 'collection')));
        sort($counts);

        $this->assertCount(122, $rows);
        $this->assertCount(24, $manifest['collections']);
        $this->assertSame(array_merge(array_fill(0, 22, 5), [6, 6]), $counts);

        foreach ($rows as $row) {
            $this->assertTrue(Photography::allows($row['image_path']));
            $this->assertFalse($row['review']['contains_people']);
            $this->assertSame([], $row['gallery']);
        }

        foreach ($manifest['presentation']['campaign_slides'] as $slide) {
            $this->assertTrue(Photography::allows($slide['path'], 'hero_slide'));
            $this->assertContains($slide['path'], array_column($rows, 'image_path'));
        }

        $this->assertFalse(Photography::allows('products/african-20261108/unreviewed.jpg'));
    }

    /**
     * An unchecked photo cannot enter the collection despite valid source metadata.
     */
    public function test_a_photo_containing_a_person_is_rejected(): void
    {
        $manifest = $this->manifest();
        $manifest['products'][0]['review']['contains_people'] = true;
        $this->expectException(InvalidArgumentException::class);
        $this->catalogue()->validate($manifest);
    }

    /**
     * A full catalogue still fails if one product moves into the wrong collection.
     */
    public function test_an_uneven_collection_distribution_is_rejected(): void
    {
        $manifest = $this->manifest();
        $manifest['products'][0]['collection'] = 'coastal-blues';
        $this->expectException(InvalidArgumentException::class);
        $this->catalogue()->validate($manifest);
    }

    /**
     * Retailer names in an untrusted hostname do not establish provenance.
     */
    public function test_an_unapproved_retailer_is_rejected(): void
    {
        $manifest = $this->manifest();
        $manifest['products'][0]['image_source_url'] = 'https://www.jumia.co.ke.example.com/product.html';
        $this->expectException(InvalidArgumentException::class);
        $this->catalogue()->validate($manifest);
    }

    /**
     * Different product identifiers cannot disguise a repeated photograph.
     */
    public function test_a_duplicate_photograph_is_rejected(): void
    {
        $manifest = $this->manifest();
        $manifest['products'][1]['image_sha256'] = $manifest['products'][0]['image_sha256'];
        $this->expectException(InvalidArgumentException::class);
        $this->catalogue()->validate($manifest);
    }
}
