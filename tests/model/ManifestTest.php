<?php

namespace IIIF\Tests\model;

use IIIF\Model\Image;
use IIIF\Model\LazyManifest;
use IIIF\Model\Manifest;
use IIIF\Model\Region;
use IIIF\ResourceFactory;
use PHPUnit\Framework\TestCase;

class ManifestTest extends TestCase
{
    public function test_can_construct()
    {
        $manifest = Manifest::fromJson(file_get_contents(__DIR__.'/../fixtures/manifest-a.json'));
        $this->assertInstanceOf(Manifest::class, $manifest);
    }

    public function test_can_find_canvas_inside_self()
    {
        $manifest = Manifest::fromJson(file_get_contents(__DIR__.'/../fixtures/manifest-a.json'));
        $canvas = $manifest->containsCanvas('http://dams.llgc.org.uk/iiif/2.0/4654878/canvas/4654882.json');
        $this->assertTrue($canvas);

        $canvas = $manifest->containsCanvas('http://dams.llgc.org.uk/iiif/2.0/4654878/canvas/DOES_NOT_EXIST.json');
        $this->assertFalse($canvas);

        $canvas = $manifest->getCanvas('http://dams.llgc.org.uk/iiif/2.0/4654878/canvas/4654882.json');
        $this->assertNotNull($canvas);
        $this->assertEquals('http://dams.llgc.org.uk/iiif/2.0/4654878/canvas/4654882.json', $canvas->getId());
    }

    public function test_can_get_thumbnails()
    {
        $manifest = Manifest::fromJson(file_get_contents(__DIR__.'/../fixtures/manifest-a.json'));
        $thumbnails = $manifest->getThumbnails();
        $this->assertNotEmpty($thumbnails);
    }

    public function test_region_selection()
    {
        $url = 'https://presley.dlcs-ida.org/iiif/idatest01/_roll_M-1473_18_cvs-2-11/canvas/c7#xywh=478,1329,146,38';
        $region = Region::fromUrlTarget($url);
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals(478, $region->getX());
        $this->assertEquals(1329, $region->getY());
        $this->assertEquals(146, $region->getWidth());
        $this->assertEquals(38, $region->getHeight());

        $url = 'https://presley.dlcs-ida.org/iiif/idatest01/_roll_M-1473_18_cvs-2-11/canvas/c7#xywh=pixel:478,1329,146,38';
        $region = Region::fromUrlTarget($url);
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals(478, $region->getX());
        $this->assertEquals(1329, $region->getY());
        $this->assertEquals(146, $region->getWidth());
        $this->assertEquals(38, $region->getHeight());

        $url = 'https://presley.dlcs-ida.org/iiif/idatest01/_roll_M-1473_18_cvs-2-11/canvas/c7#xywh=percent:50,40,10,15';
        $region = Region::fromUrlTarget($url);
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals(50, $region->getX());
        $this->assertEquals(40, $region->getY());
        $this->assertEquals(10, $region->getWidth());
        $this->assertEquals(15, $region->getHeight());
    }

    public function test_region_image()
    {
        $manifest = Manifest::fromJson(file_get_contents(__DIR__.'/../fixtures/manifest-a.json'));
        $imageUrl = $manifest->getCanvasRegionFromUrl('http://dams.llgc.org.uk/iiif/2.0/4654878/canvas/4654882.json#xywh=pixel:478,1329,146,38');
        $this->assertEquals('http://dams.llgc.org.uk/iiif/2.0/image/4654882/478,1329,146,38/256,/0/default.jpg', $imageUrl);
    }

    public function test_manifest_without_image_service()
    {
        $manifest = Manifest::fromJson(file_get_contents(__DIR__.'/../fixtures/manifest-b.json'));
        $thumbnails = $manifest->getThumbnails();
        $this->assertNotEmpty($thumbnails);

        $images = $manifest->getDefaultSequence()->get(0)->getImages();
        $this->assertNotEmpty($images);
        /** @var Image $firstImage */
        $firstImage = $images[0];
        $url = $firstImage->getImageService()->getRegion(Region::create(500, 500, 50, 50));
        $this->assertEquals('https://dlcs-ida.org/iiif-img/2/1/M-1473_R-18_0003/500,500,50,50/256,/0/default.jpg', $url);
    }

    public function test_manifest_getting_all_canvases()
    {
        $manifest = Manifest::fromJson(file_get_contents(__DIR__.'/../fixtures/manifest-b.json'));
        $thumbnails = $manifest->getCanvases();
        $this->assertNotEmpty($thumbnails);
    }

    public function test_is_manifest()
    {
        $manifest1 = json_decode(file_get_contents(__DIR__.'/../fixtures/manifest-a.json'), true);
        $this->assertTrue(Manifest::isManifest($manifest1));

        $manifest2 = json_decode(file_get_contents(__DIR__.'/../fixtures/manifest-b.json'), true);
        $this->assertTrue(Manifest::isManifest($manifest2));

        $collection1 = json_decode(file_get_contents(__DIR__.'/../fixtures/collection-member-field.json'), true);
        $this->assertFalse(Manifest::isManifest($collection1));

        $collection2 = json_decode(file_get_contents(__DIR__.'/../fixtures/collection-manifest-field.json'), true);
        $this->assertFalse(Manifest::isManifest($collection2));
    }

    public function test_lazy_manifest()
    {
        $manifest = LazyManifest::fromArray([
            '@id' => __DIR__.'/../fixtures/manifest-a.json',
        ]);
        $thumbnails = $manifest->getThumbnails();
        $this->assertNotEmpty($thumbnails);
    }

    public function test_manifest_get_id()
    {
        $id = __DIR__.'/../fixtures/manifest-a.json';

        $manifest = LazyManifest::fromArray([
            '@id' => $id,
        ]);

        $this->assertEquals($id, $manifest->getId());
    }

    public function test_manifest_with_meta_data()
    {
        $manifest = Manifest::fromArray(['@id' => '1234']);
        $manifestWithThumbnails = $manifest->withMetaData([
            'thumbnails' => 'I AM THUMBNAILS',
        ]);

        $this->assertNotEquals($manifest, $manifestWithThumbnails);

        $this->assertNull($manifest->thumbnails);
        $this->assertEquals('I AM THUMBNAILS', $manifestWithThumbnails->thumbnails);

        $manifest = Manifest::fromArray(['@id' => '1234']);
        $manifest->addMetaData([
            'thumbnails' => 'I AM THUMBNAILS ALSO',
        ]);

        $this->assertEquals('I AM THUMBNAILS ALSO', $manifest->thumbnails);
        $this->assertEquals('I AM THUMBNAILS ALSO', $manifest->thumbnails());
    }

    public function test_creation_using_factory()
    {
        $manifest1 = json_decode(file_get_contents(__DIR__.'/../fixtures/manifest-a.json'), true);
        $manifest = ResourceFactory::createManifest($manifest1);
        $this->assertInstanceOf(Manifest::class, $manifest);

        $manifest1 = json_decode(file_get_contents(__DIR__.'/../fixtures/manifest-a.json'), true);
        $manifest = ResourceFactory::create($manifest1);
        $this->assertInstanceOf(Manifest::class, $manifest);

        $manifest = ResourceFactory::create(__DIR__.'/../fixtures/manifest-a.json', function ($file) {
            return json_decode(file_get_contents($file), true);
        });
        $this->assertInstanceOf(Manifest::class, $manifest);
    }
}
