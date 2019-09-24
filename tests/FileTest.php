<?php
use PHPUnit\Framework\TestCase;
require_once(__DIR__.'/../webp-rewrite-image.php');

class FileTest extends TestCase
{
  protected function setUp()
  {
    chmod(__DIR__.'/../webp-newer/not-readable.txt', 0200);
  }

  protected function tearDown()
  {
    chmod(__DIR__.'/../webp-newer/not-readable.txt', 0644);
  }

  public function testJoinPath()
  {
    $this->assertEquals(ImageFile::joinPath('/path/to', 'file.jpg'), '/path/to/file.jpg');
    $this->assertEquals(ImageFile::joinPath('/path/to/', '/file.jpg'), '/path/to/file.jpg');
    $this->assertEquals(ImageFile::joinPath('/path/to//', '//file.jpg'), '/path/to/file.jpg');
  }

  public function testPath()
  {
    $file = new ImageFile('/path/to/file.jpg');
    $this->assertEquals($file->path(), '/path/to/file.jpg');
  }

  public function testExists()
  {
    $exists = new ImageFile(__DIR__.'/../webp-newer/sample.png');
    $this->assertTrue($exists->exists());

    $notfound = new ImageFile(__DIR__.'/../webp-newer/not-found.png');
    $this->assertFalse($notfound->exists());
  }

  public function testIsReadable()
  {
    $readable = new ImageFile(__DIR__.'/../webp-newer/sample.png');
    $this->assertTrue($readable->isReadable());

    $notreadable = new ImageFile(__DIR__.'/../webp-newer/not-readable.png');
    $this->assertFalse($notreadable->isReadable());
  }

  public function testIsAvailable()
  {
    $available = new ImageFile(__DIR__.'/../webp-newer/sample.png');
    $this->assertTrue($available->isAvailable());

    $notfound = new ImageFile(__DIR__.'/../webp-newer/not-found.png');
    $this->assertFalse($notfound->isAvailable());

    $notreadable = new ImageFile(__DIR__.'/../webp-newer/not-readable.png');
    $this->assertFalse($notreadable->isAvailable());
  }

  public function testMtime()
  {
    $file = new ImageFile(__DIR__.'/../webp-newer/sample.png');
    $this->assertEquals($file->mtime(), 1568672499);
  }

  public function testSize()
  {
    $file = new ImageFile(__DIR__.'/../webp-newer/sample.png');
    $this->assertEquals($file->size(), 15789);
  }

  public function testEtag()
  {
    $file = new ImageFile(__DIR__.'/../webp-newer/sample.png');
    $this->assertEquals($file->etag(), '1568672499-15789');
  }

  public function testMime()
  {
    $webp = new ImageFile('/path/to/image.WEBP');
    $this->assertEquals($webp->mime(), 'image/webp');

    $jpg1 = new ImageFile('/path/to/image.JPG');
    $this->assertEquals($jpg1->mime(), 'image/jpeg');

    $jpg2 = new ImageFile('/path/to/image.JPEG');
    $this->assertEquals($jpg2->mime(), 'image/jpeg');

    $png = new ImageFile('/path/to/image.PNG');
    $this->assertEquals($png->mime(), 'image/png');

    $gif = new ImageFile('/path/to/image.GIF');
    $this->assertEquals($gif->mime(), 'image/gif');
  }

  public function testSecureContent()
  {
    $webp = new ImageFile(__DIR__ . '/../webp-newer/sample.png.webp');
    $this->assertNotEquals($webp->secureContent(), '');

    $png = new ImageFile(__DIR__ . '/../webp-newer/sample.png');
    $this->assertNotEquals($png->secureContent(), '');

    $jpg = new ImageFile(__DIR__ . '/files/lenna.jpg');
    $this->assertNotEquals($jpg->secureContent(), '');

    $gif = new ImageFile(__DIR__ . '/files/earth.gif');
    $this->assertNotEquals($gif->secureContent(), '');

    $txt = new ImageFile(__DIR__ . '/files/dummy.txt');
    $this->assertEquals($txt->secureContent(), '');
  }
}