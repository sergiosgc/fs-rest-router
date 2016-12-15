<?php
namespace sergiosgc\router;

class Rest {
    private $rootPath = null;
    public function __construct($path = null) {
        if (is_null($path)) {
            $this->rootPath = realpath($_SERVER['DOCUMENT_ROOT']);
        } elseif ($path[0] == '/') {
            $this->rootPath = realpath($path);
        } else {
            $this->rootPath = realpath($_SERVER['DOCUMENT_ROOT'] . '/' . $path);
        }
        if (empty($this->rootPath)) throw new Exception(sprintf("Invalid root path %s", $path));
    }
    public function route() {
        $parts = array_values(array_filter(explode('/', explode('?', $_SERVER['REQUEST_URI'], 2)[0]), function($p) { return $p != ""; }));

        $server = array();
        $cursorPathboundRequestUri = '/';
        $cursorPathboundScriptFilename = $this->rootPath;
        if ($this->scriptFile($cursorPathboundScriptFilename, $this->requestMethod())) {
            $server['ROUTER_PATHBOUND_REQUEST_URI'] = $cursorPathboundRequestUri;
            $server['ROUTER_PATHBOUND_SCRIPT_FILENAME'] = $cursorPathboundScriptFilename;
            $server['ROUTER_UNBOUND_REQUEST_URI'] = implode('/', $parts);
        }
        while (count($parts) && is_dir($cursorPathboundScriptFilename . '/' . $parts[0])) {
            $cursorPathboundRequestUri .= $parts[0] . '/';
            $cursorPathboundScriptFilename .= '/' . $parts[0];
            if ($this->scriptFile($cursorPathboundScriptFilename, $this->requestMethod())) {
                $server['ROUTER_PATHBOUND_REQUEST_URI'] = $cursorPathboundRequestUri;
                $server['ROUTER_PATHBOUND_SCRIPT_FILENAME'] = $cursorPathboundScriptFilename;
		        $server['ROUTER_UNBOUND_REQUEST_URI'] = implode('/', $parts);
            }
			array_shift($parts);
        }
        if (!isset($server['ROUTER_PATHBOUND_REQUEST_URI']) || !isset($server['ROUTER_PATHBOUND_SCRIPT_FILENAME'])) throw new Exception_HTTP_404();
        $scriptFile = $this->scriptFile($server['ROUTER_PATHBOUND_SCRIPT_FILENAME'], $this->requestMethod());
        $server['ROUTER_REQUEST'] = array();
        $paramRegexFile = $server['ROUTER_PATHBOUND_SCRIPT_FILENAME'] . '/' . preg_replace('_\.php$_', '.regex', $scriptFile);
        if (!is_file($paramRegexFile)) $paramRegexFile = $server['ROUTER_PATHBOUND_SCRIPT_FILENAME'] . '/all.php';
        if (is_file($paramRegexFile)) {
            $regex = file_get_contents($paramRegexFile);
            $matchResult = preg_match($regex, $server['ROUTER_UNBOUND_REQUEST_URI'], $matches);
            if ($matchResult === FALSE) throw new Exception(sprintf('Error matching regex %s from %s', $regex, $paramRegexFile));
            if ($matchResult === 0) throw new Exception_HTTP_404();
            $matches = array_filter($matches, function($v, $k) { if (is_string($k)) return $v; }, ARRAY_FILTER_USE_BOTH);
            foreach($matches as $key => $value) $_REQUEST[$key] = $server['ROUTER_REQUEST'] [$key] = $value;
        }
        $server['ROUTER_PATHBOUND_SCRIPT_FILENAME'] .= '/' . $scriptFile;
        foreach ($server as $key => $value) $_SERVER[$key] = $value;
        $this->includeScript($server['ROUTER_PATHBOUND_SCRIPT_FILENAME']);
    }
    protected function requestMethod() {
        return isset($_REQUEST['x-verb']) ? $_REQUEST['x-verb'] : $_SERVER['REQUEST_METHOD'];
    }
    protected function includeScript($script) {
        require($script);
    }
    protected function scriptFile($path, $verb) {
        if (is_file($path . '/' . strtolower($verb) . '.php')) return strtolower($verb) . '.php';
        if (is_file($path . '/get.php') && strtolower($verb) == 'head') return 'get.php';
        return null;
    }


}
