<?php
use PHPUnit\Framework\TestCase;
require_once(__DIR__.'/../webp-rewrite-image.php');

class ServerTest extends TestCase
{
  protected function setUp()
  {
    chmod(__DIR__.'/../webp-newer/not-readable.txt', 0200);
  }

  protected function tearDown()
  {
    chmod(__DIR__.'/../webp-newer/not-readable.txt', 0644);
  }

  public function testFilesAndFile()
  {
    $server = new Server(array());
    $this->assertEquals($server->files(), array());
    $this->assertTrue($server->filesEmpty());

    $server->files(array(new ImageFile('/path/to/file.jpg')));
    $this->assertEquals(count($server->files()), 1);
    $this->assertEquals($server->files()[0]->path(), '/path/to/file.jpg');
    $this->assertFalse($server->filesEmpty());

    $server->appendFile(new ImageFile('/path/to/file.png'));
    $this->assertEquals(count($server->files()), 2);
    $this->assertEquals($server->files()[1]->path(), '/path/to/file.png');
    $this->assertFalse($server->filesEmpty());

    $server->file(new ImageFile('/path/to/file.png'));
    $this->assertEquals($server->file()->path(), '/path/to/file.png');
  }

  public function testWebpAcceptable()
  {
    $acceptable = new Server(array(
      'HTTP_ACCEPT' => '*/*; image/webp',
    ));
    $this->assertTrue($acceptable->webpAcceptable());

    $notacceptable = new Server(array(
      'HTTP_ACCEPT' => '*/*',
    ));
    $this->assertFalse($notacceptable->webpAcceptable());
  }

  public function testCanditate()
  {
    $acceptable = new Server(array(
      'HTTP_ACCEPT' => '*/*; image/webp',
      'DOCUMENT_ROOT' => '/var/www/html',
      'REDIRECT_URL' => '/path/to/image.jpg',
    ));
    $acceptable->canditate();

    $paths = array_map(function($file) {
      return $file->path();
    }, $acceptable->files());
    $this->assertEquals($paths, array(
      '/var/www/html/path/to/image.jpg.webp',
      '/var/www/html/path/to/image.jpg',
    ));

    $notacceptable = new Server(array(
      'HTTP_ACCEPT' => '*/*',
      'DOCUMENT_ROOT' => '/var/www/html',
      'REDIRECT_URL' => '/path/to/image.jpg',
    ));
    $notacceptable->canditate();

    $paths = array_map(function($file) {
      return $file->path();
    }, $notacceptable->files());
    $this->assertEquals($paths, array(
      '/var/www/html/path/to/image.jpg',
    ));

    $withqs = new Server(array(
      'HTTP_ACCEPT' => '*/*',
      'DOCUMENT_ROOT' => '/var/www/html',
      'REDIRECT_URL' => '/path/to/image.jpg?name=value',
    ));
    $withqs->canditate();

    $paths = array_map(function($file) {
      return $file->path();
    }, $withqs->files());
    $this->assertEquals($paths, array(
      '/var/www/html/path/to/image.jpg',
    ));
  }

  public function testSecureCanditate()
  {
    $goup = new Server(array(
      'HTTP_ACCEPT' => '*/*; image/webp',
      'DOCUMENT_ROOT' => '/var/www/html',
      'REDIRECT_URL' => '/path/../image.jpg',
    ));
    $goup->canditate();

    $this->assertTrue($goup->filesEmpty());

    $notimage = new Server(array(
      'HTTP_ACCEPT' => '*/*; image/webp',
      'DOCUMENT_ROOT' => '/var/www/html',
      'REDIRECT_URL' => '/path/id_rsa',
    ));
    $notimage->canditate();

    $this->assertTrue($notimage->filesEmpty());
  }

  public function testFilterExists()
  {
    $server = new Server(array());
    $server->files(array(
      new ImageFile(__DIR__.'/../webp-newer/not-found.png'),
      new ImageFile(__DIR__.'/../webp-newer/not-readable.txt'),
      new ImageFile(__DIR__.'/../webp-newer/sample.png'),
    ));

    $server->filterExists();
    $paths = array_map(function($file) {
      return $file->path();
    }, $server->files());

    $this->assertEquals($paths, array(
      __DIR__.'/../webp-newer/not-readable.txt',
      __DIR__.'/../webp-newer/sample.png',
    ));
  }

  public function testFilterReadable()
  {
    $server = new Server(array());
    $server->files(array(
      new ImageFile(__DIR__.'/../webp-newer/not-found.png'),
      new ImageFile(__DIR__.'/../webp-newer/not-readable.txt'),
      new ImageFile(__DIR__.'/../webp-newer/sample.png'),
    ));

    $server->filterReadable();
    $paths = array_map(function($file) {
      return $file->path();
    }, $server->files());

    $this->assertEquals($paths, array(
      __DIR__.'/../webp-newer/sample.png',
    ));
  }

  public function testChooseTheNewestFile()
  {
    $webpnewer = new Server(array());
    $webpnewer->files(array(
      new ImageFile(__DIR__.'/../webp-newer/sample.png.webp'),
      new ImageFile(__DIR__.'/../webp-newer/sample.png'),
    ));
    $webpnewer->chooseTheNewestFile();
    $this->assertEquals($webpnewer->file()->path(), __DIR__.'/../webp-newer/sample.png.webp');

    $webpolder = new Server(array());
    $webpolder->files(array(
      new ImageFile(__DIR__.'/../webp-older/sample.png.webp'),
      new ImageFile(__DIR__.'/../webp-older/sample.png'),
    ));
    $webpolder->chooseTheNewestFile();
    $this->assertEquals($webpolder->file()->path(), __DIR__.'/../webp-older/sample.png');
  }

  public function testIfEtagMatchWithClientCache()
  {
    $noheader = new Server(array());
    $this->assertFalse($noheader->ifEtagMatchWithClientCache());

    $match = new Server(array(
      'HTTP_IF_NONE_MATCH' => '1568672499-15789',
    ));
    $match->file(new ImageFile(__DIR__.'/../webp-newer/sample.png'));
    $this->assertTrue($match->ifEtagMatchWithClientCache());

    $notmatch = new Server(array(
      'HTTP_IF_NONE_MATCH' => '1568672499-15788',
    ));
    $notmatch->file(new ImageFile(__DIR__.'/../webp-newer/sample.png'));
    $this->assertFalse($notmatch->ifEtagMatchWithClientCache());
  }

  public function testIfModifiedAfterClientCache()
  {
    $noheader = new Server(array());
    $this->assertTrue($noheader->ifModifiedAfterClientCache());

    $after = new Server(array(
      'HTTP_IF_MODIFIED_SINCE' => 'Mon, 16 Sep 2019 22:21:40 GMT',
    ));
    $after->file(new ImageFile(__DIR__.'/../webp-newer/sample.png'));
    $this->assertFalse($after->ifModifiedAfterClientCache());

    $equal = new Server(array(
      'HTTP_IF_MODIFIED_SINCE' => 'Mon, 16 Sep 2019 22:21:39 GMT',
    ));
    $equal->file(new ImageFile(__DIR__.'/../webp-newer/sample.png'));
    $this->assertFalse($equal->ifModifiedAfterClientCache());

    $before = new Server(array(
      'HTTP_IF_MODIFIED_SINCE' => 'Mon, 16 Sep 2019 22:21:38 GMT',
    ));
    $before->file(new ImageFile(__DIR__.'/../webp-newer/sample.png'));
    $this->assertTrue($before->ifModifiedAfterClientCache());
  }
}