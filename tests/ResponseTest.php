<?php
use PHPUnit\Framework\TestCase;
require_once(__DIR__.'/../webp-rewrite-image.php');

class ResponseTest extends TestCase
{
  public function testFormatISO8601()
  {
    $this->assertEquals(Response::formatISO8601(1568672498), 'Mon, 16 Sep 2019 22:21:38 GMT');
  }

  public function testFromCode()
  {
    $res = Response::fromCode(201);
    $this->assertEquals($res->code(), 201);
  }

  public function testImageFile()
  {
    Config::$Now = 1569241907;

    $file = new ImageFile(__DIR__.'/../webp-newer/sample.png');
    $res = Response::fromImageFile($file);
    $this->assertEquals($res->code(), 200);
    $this->assertEquals($res->headers(), array(
      'Content-Type' => 'image/png',
      'Content-Length' => 15789,
      'Last-Modified' => 'Mon, 16 Sep 2019 22:21:39 GMT',
      'Etag' => '1568672499-15789',
      'Expires' => 'Mon, 30 Sep 2019 12:31:47 GMT',
      'Cache-Control' => 'max-age=604800',
    ));
  }

  public function testIsError()
  {
    $this->assertFalse(Response::fromCode(200)->isError());
    $this->assertFalse(Response::fromCode(399)->isError());
    $this->assertTrue(Response::fromCode(400)->isError());
    $this->assertTrue(Response::fromCode(401)->isError());
    $this->assertTrue(Response::fromCode(500)->isError());
  }

  public function testHeaders()
  {
    Config::$Now = 1569241907;

    $error = Response::fromCode(500);
    $this->assertEquals($error->headers(), array(
      'Expires' => 'Mon, 23 Sep 2019 12:41:47 GMT',
      'Cache-Control' => 'max-age=600',
    ));

    $file = new ImageFile(__DIR__.'/../webp-newer/sample.png');
    $success = Response::fromImageFile($file);
    $this->assertEquals($success->headers(), array(
      'Content-Type' => 'image/png',
      'Content-Length' => 15789,
      'Last-Modified' => 'Mon, 16 Sep 2019 22:21:39 GMT',
      'Etag' => '1568672499-15789',
      'Expires' => 'Mon, 30 Sep 2019 12:31:47 GMT',
      'Cache-Control' => 'max-age=604800',
    ));
  }

  public function testBodyAndContent()
  {
    $file = new ImageFile(__DIR__.'/../webp-newer/sample.png');
    $res1 = new Response(200, array(), $file);
    $this->assertEquals($res1->body(), $file);
    $this->assertEquals(strlen($res1->bodyContent()), 15789);

    $res2 = new Response(200, array(), 'string');
    $this->assertEquals($res2->body(), 'string');
    $this->assertEquals(strlen($res2->bodyContent()), 6);
  }
}