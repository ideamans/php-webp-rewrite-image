<?php

// Config

class Config
{
  public static $SuccessCache = 60 * 60 * 24 * 7; // 7days
  public static $ErrorCache = 10 * 60; // 10 minuts
  
  public static $SuccessDefaultHeaders = array(
    // 'X-My-Header' => 'the value',
  );
  
  public static $ErrorDefaultHeaders = array(
    // 'X-My-Header' => 'the value',
  );
  
  public static $Now = null; // for testing
}


// Library

class ImageFile
{
  public static function joinPath($path1, $path2)
  {
    return implode('/', array(rtrim($path1, '/'), ltrim($path2, '/')));
  }

  public function __construct($path)
  {
    $this->_path = $path;
    $this->_exists = null;
    $this->_isReadable = null;
    $this->_mtime = null;
    $this->_size = null;
    $this->_etag = null;
    $this->_mime = null;
  }

  public function path()
  {
    return $this->_path;
  }
  
  public function exists()
  {
    if ($this->_exists === null) {
      $this->_exists = file_exists($this->path());
    }
    return $this->_exists;
  }

  public function isReadable()
  {
    if ($this->_isReadable === null) {
      $this->_isReadable = is_readable($this->path());
    }
    return $this->_isReadable;
  }

  public function isAvailable()
  {
    return $this->exists() && $this->isReadable();
  }

  public function mtime()
  {
    if ($this->_mtime === null) {
      $this->_mtime = filemtime($this->path());
    }
    return $this->_mtime;
  }

  public function size()
  {
    if ($this->_size === null) {
      $this->_size = filesize($this->path());
    }
    return $this->_size;
  }

  public function etag()
  {
    if ($this->_etag === null) {
      if ($this->isAvailable()) {
        $this->_etag = implode('-', array($this->mtime(), $this->size()));
      } else {
        $this->_etag = '';
      }
    }
    return $this->_etag;
  }

  public function mime()
  {
    if ($this->_mime === null) {
      if (preg_match('/\.webp$/i', $this->path())) {
        $this->_mime = 'image/webp';
      } else if (preg_match('/\.jpe?g$/i', $this->path())) {
        $this->_mime = 'image/jpeg';
      } else if (preg_match('/\.png$/i', $this->path())) {
        $this->_mime = 'image/png';
      } else if (preg_match('/\.gif$/i', $this->path())) {
        $this->_mime = 'image/gif';
      } else {
        $this->_mime = 'application/octet-stream';
      }
    }

    return $this->_mime;
  }

  public function secureContent()
  {
    $content = file_get_contents($this->path());

    // Return only if assumed image data.
    if (substr($content, 0, 4) === "RIFF" && substr($content, 8, 4) === "WEBP") return $content;
    if (substr($content, 0, 2) === "\xFF\xD8") return $content;
    if (substr($content, 0, 4) === "\x89PNG") return $content;
    if (substr($content, 0, 3) === "GIF") return $content;

    return '';
  }
}

class Response
{
  static function formatISO8601($ts)
  {
    return gmdate('D, d M Y H:i:s', $ts) . ' GMT';
  }

  static function fromCode($code)
  {
    return new Response($code);
  }

  static function fromImageFile($file)
  {
    $res = new Response(200, array(
      'Content-Type' => $file->mime(),
      'Content-Length' => $file->size(),
      'Last-Modified' => Response::formatISO8601($file->mtime()),
      'Etag' => $file->etag(),
    ), $file);
    return $res;
  }

  public function __construct($code, $headers = array(), $body = null)
  {
    $this->_code = $code;
    $this->_headers = $headers;
    $this->_body = $body;
  }

  public function code()
  {
    return $this->_code;
  }

  public function isError()
  {
    return $this->code() >= 400;
  }

  public function headers()
  {
    $now = Server::now();
    if ($this->isError()) {
      $cache = Config::$ErrorCache;
      $headers = $this->_headers + Config::$ErrorDefaultHeaders + array(
        'Expires' => Response::formatISO8601($now + $cache),
        'Cache-Control' => "max-age=$cache",
      );
      return $headers;
    } else {
      $cache = Config::$SuccessCache;
      $headers = $this->_headers + Config::$SuccessDefaultHeaders + array(
        'Expires' => Response::formatISO8601($now + $cache),
        'Cache-Control' => "max-age=$cache",
      );
      return $headers;
    }
  }

  public function body()
  {
    return $this->_body;
  }

  public function bodyContent()
  {
    if ($this->body() === null) return;
    if (is_string($this->body())) return $this->body();
    return $this->body()->secureContent();
  }
}

class Server {
  public static function now()
  {
    if (Config::$Now !== null) return Config::$Now;
    return time();
  }

  public function __construct($server)
  {
    $this->_server = $server;
    $this->_webpAcceptable = null;
    $this->_files = array();
    $this->_file = null;
  }

  public function server($name, $default = null)
  {
    if (isset($this->_server[$name])) {
      return $this->_server[$name];
    } else if ($default !== null) {
      return $default;
    }

    throw new Exception("Requires server varialble $name");
  }

  public function files($files = null)
  {
    if ($files !== null) $this->_files = $files;
    return $this->_files;
  }

  public function appendFile($file) {
    $this->_files []= $file;
  }

  public function file($file = null)
  {
    if ($file !== null) $this->_file = $file;
    return $this->_file;
  }

  public function filesEmpty()
  {
    return empty($this->files());
  }

  public function webpAcceptable()
  {
    if ($this->_webpAcceptable === null) {
      $accept = $this->server('HTTP_ACCEPT', '');
      $this->_webpAcceptable = !!preg_match('/image\/webp/i', $accept);
    }
    return $this->_webpAcceptable;
  }

  public function canditate()
  {
    // Security check
    $redirect_url = $this->server('REDIRECT_URL');
    $redirect_url = preg_replace('/\?.*$/', '', $redirect_url); // Remove query string
    if (!preg_match('/\.(jpe?g|png|gif)$/i', $redirect_url)) return; // return; // Only image files
    if (strpos($redirect_url, '..') !== false) return; // Disallow going up

    $path = ImageFile::joinPath($this->server('DOCUMENT_ROOT'), $redirect_url);
    if ($this->webpAcceptable()) {
      $this->appendFile(new ImageFile($path . '.webp'));
    }
    $this->appendFile(new ImageFile($path));
  }

  public function filterExists()
  {
    $filtered = array_filter($this->files(), function($file) {
      return $file->exists();
    });
    $this->files(array_values($filtered));
  }

  public function filterReadable()
  {
    $filtered = array_filter($this->files(), function($file) {
      return $file->isReadable();
    });
    $this->files(array_values($filtered));
  }

  public function chooseTheNewestFile()
  {
    $sort = $this->files();
    usort($sort, function($a, $b) {
      return $b->mtime() - $a->mtime();
    });
    $this->files($sort);
    $this->file($sort[0]);
  }

  public function ifEtagMatchWithClientCache()
  {
    $if_none_match = $this->server('HTTP_IF_NONE_MATCH', '');
    if (!$if_none_match) return false;
    return $this->file()->etag() === $if_none_match;
  }

  public function ifModifiedAfterClientCache()
  {
    $if_modified_since = $this->server('HTTP_IF_MODIFIED_SINCE', '');
    if (!$if_modified_since) return true;

    $dt = strtotime($if_modified_since);
    if ($dt === false) return true;

    return $this->file()->mtime() > $dt;
  }

  public function request()
  {
    $this->canditate();

    $this->filterExists();
    if ($this->filesEmpty()) {
      return Response::fromCode(404);
    }

    $this->filterReadable();
    if ($this->filesEmpty()) {
      return Response::fromCode(403);
    }

    $this->chooseTheNewestFile();

    if ($this->ifEtagMatchWithClientCache()) {
      return Response::fromCode(304);
    }

    if (! $this->ifModifiedAfterClientCache()) {
      return Response::fromCode(304);
    }

    return Response::fromImageFile($this->file());
  }

  public function run()
  {
    try {
      $res = $this->request();
    } catch(Exception $ex) {
      $res = Response::from_code(500, null, array(), $ex->message);
    }

    http_response_code($res->code());
    foreach ($res->headers() as $key => $value) {
      header("$key: $value");
    }
  
    if ($this->server('REQUEST_METHOD', 'GET') !== 'HEAD') {
      echo $content;
    }
  }
}

if (isset($_SERVER['DOCUMENT_ROOT']) && isset($_SERVER['REDIRECT_URL'])) {
  $server = new Server($_SERVER);
  $server->run();
}
