<?php
use PHPUnit\Framework\TestCase;
require_once(__DIR__.'/../webp-rewrite-image.php');

class ServerRequestTest extends TestCase
{
  protected function setUp()
  {
    $this->defaults = array(
      'DOCUMENT_ROOT' => __DIR__ . '/..',
    );
  }

  private function assertResponseMatchWithPath($res, $path)
  {
    $file = new ImageFile(__DIR__ . '/../' . $path);
    $headers = $res->headers();
    $this->assertEquals($headers['Content-Type'], $file->mime());
    $this->assertEquals($headers['Content-Length'], $file->size());
    $this->assertEquals($headers['Etag'], $file->etag());
    $this->assertEquals($headers['Last-Modified'], Response::formatISO8601($file->mtime()));
  }

  private function assertResponseIsPng($res)
  {
    $headers = $res->headers();
    $this->assertEquals($headers['Content-Type'], 'image/png');
  }

  private function assertResponseIs304($res)
  {
    $this->assertEquals($res->code(), 304);
  }

  public function testWebpAcceptableWebpNewer()
  {
    $server = new Server($this->defaults + array(
      'HTTP_ACCEPT' => '*/*; image/webp',
      'REDIRECT_URL' => '/webp-newer/sample.png',
    ));
    $res = $server->request();
    $this->assertResponseMatchWithPath($res, '/webp-newer/sample.png.webp');
  }

  public function testWebpAcceptableWebpOlder()
  {
    $server = new Server($this->defaults + array(
      'HTTP_ACCEPT' => '*/*; image/webp',
      'REDIRECT_URL' => '/webp-older/sample.png',
    ));
    $res = $server->request();
    $this->assertResponseMatchWithPath($res, '/webp-older/sample.png');
  }

  public function testWebpAcceptableEtagMatch()
  {
    $server = new Server($this->defaults + array(
      'HTTP_ACCEPT' => '*/*; image/webp',
      'REDIRECT_URL' => '/webp-newer/sample.png',
      'HTTP_IF_NONE_MATCH' => '1568672563-5148',
    ));
    $res = $server->request();
    $this->assertResponseIs304($res);
  }

  public function testWebpAcceptableNotModified()
  {
    $server = new Server($this->defaults + array(
      'HTTP_ACCEPT' => '*/*; image/webp',
      'REDIRECT_URL' => '/webp-newer/sample.png',
      'HTTP_IF_MODIFIED_SINCE' => 'Mon, 16 Sep 2019 22:22:43 GMT',
    ));
    $res = $server->request();
    $this->assertResponseIs304($res);
  }

  public function testWebpNotAcceptableWebpNewer()
  {
    $server = new Server($this->defaults + array(
      'HTTP_ACCEPT' => '*/*',
      'REDIRECT_URL' => '/webp-newer/sample.png',
    ));
    $res = $server->request();
    $this->assertResponseMatchWithPath($res, '/webp-newer/sample.png');
  }

  public function testNotWebpAcceptableWebpOlder()
  {
    $server = new Server($this->defaults + array(
      'HTTP_ACCEPT' => '*/*',
      'REDIRECT_URL' => '/webp-older/sample.png',
    ));
    $res = $server->request();
    $this->assertResponseMatchWithPath($res, '/webp-older/sample.png');
  }

  public function testWebpNotAcceptableEtagMatch()
  {
    $server = new Server($this->defaults + array(
      'HTTP_ACCEPT' => '*/*',
      'REDIRECT_URL' => '/webp-newer/sample.png',
      'HTTP_IF_NONE_MATCH' => '1568672499-15789',
    ));
    $res = $server->request();
    $this->assertResponseIs304($res);
  }

  public function testWebpNotAcceptableNotModified()
  {
    $server = new Server($this->defaults + array(
      'HTTP_ACCEPT' => '*/*',
      'REDIRECT_URL' => '/webp-newer/sample.png',
      'HTTP_IF_MODIFIED_SINCE' => 'Mon, 16 Sep 2019 22:21:39 GMT',
    ));
    $res = $server->request();
    $this->assertResponseIs304($res);
  }
}