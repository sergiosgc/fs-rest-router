<?php
namespace sergiosgc\router;

class Exception_HTTP_404 extends Exception {
    public function __construct($url = null) {
        header('HTTP/1.0 404 Not Found');
        if (is_null($url)) {
            $url = sprintf("%s://%s%s", isset($_REQUEST['HTTPS']) && $_REQUEST['HTTPS'] == 'on' ? 'https' : 'http', $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);
        }
        $this->url = $url;
        parent::__construct(sprintf("Requested URL not found: %s for HTTP verb %s", $url, isset($_REQUEST['x-verb']) ? $_REQUEST['x-verb'] : $_SERVER['REQUEST_METHOD']));
    }
}

