<?php
namespace sergiosgc\router;

class Rest {
    public function __construct($paths) {
        if (!is_a($paths, '\sergiosgc\router\Filesystem')) $paths = new Filesystem($paths);
        $this->subrouter = $paths;
    }
    public function include($scriptFile) {
        include($scriptFile);
    }
    public function output($uri = null) {
        list($matchedUri, $args, $scriptFile) = $this->route($uri);
        foreach($args as $key => $val) $_REQUEST[$key] = $val;
        $this->include($scriptFile);
    }
    public function route($uri = null) {
        $verb = isset($_REQUEST['x-verb']) ? $_REQUEST['x-verb'] : ( isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : ( php_sapi_name() == "cli" ? 'cli' : 'get') );
        $verb = strtolower($verb);
        return $this->subrouter->route($uri, [ $verb, 'all' ]);
    }
}