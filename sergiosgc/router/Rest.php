<?php
namespace sergiosgc\router;

class Rest {
    static $debug = false;
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
    public function extractRegex($verb, $fsPath, $uri) {
        $verb = strtolower($verb);
        $candidates = [
            $fsPath . '/' . $verb . '.regex',
            $fsPath . '/all.regex'
        ];
        foreach ($candidates as $regexFile) {
            if (!is_file($regexFile)) continue;
            if (static::$debug) printf("Extracting regex from file %s<br>\n", $regexFile);
            $regex = file_get_contents($regexFile);
            $matchResult = preg_match($regex, $uri, $matches);
            if ($matchResult === FALSE) throw new Exception(sprintf('Error matching regex %s from %s', $regex, $regexFile));
            if ($matchResult === 0) {
                if (static::$debug) printf("Regex %s did not match uri %s<br>\n", $regex, $uri);
                return [ false, '' ];
            }
            $matchedUri = $matches[0];
            $matches = array_filter($matches, function($v, $k) { if (is_string($k)) return $v; }, ARRAY_FILTER_USE_BOTH);
            if (isset($matches['matchedUri'])) {
                $matchedUri = $matches['matchedUri'];
                unset($matches['matchedUri']);
            }
            if (static::$debug) {
                printf("Successful match. Matched URI: %s<br>\n", $matchedUri);
                foreach($matches as $k => $v) printf("  %s = %s<br>\n", $k, $v);
            }
            return [ $matches, $matchedUri ];
        }
        if (static::$debug) printf("No regex found for %s on %s<br>\n", $verb, $fsPath);
        return [ false, '' ];
    }
    public function getScriptFilename($verb, $fsPath) {
        $verb = strtolower($verb);
        $candidates = [
            $fsPath . '/' . $verb . '.php',
            $fsPath . '/all.regex',
            $fsPath . '/index.php'
        ];
        foreach ($candidates as $scriptFile) if (is_file($scriptFile)) return $scriptFile;
        return false;
    }
    public function _route($verb, $fsPath, $uriArray) {
        if (static::$debug) {
            printf("Routing for %s on path %s and URI %s</br>\n", $verb, $fsPath, implode('/', $uriArray));
        }
        // Complete URL consumed. Test if regex required further URL data or if script is missing. Return sucesss otherwise
        if (count($uriArray) == 0) { 
            list($extractedArguments, $dummy) = $this->extractRegex($verb, $fsPath, implode('/', $uriArray));
            $scriptFilename = $this->getScriptFilename($verb, $fsPath);
            if ($extractedArguments === false || $scriptFilename === false) return [ false, false, false, [] ];
            return [ true, '', $scriptFilename, $extractedArguments ];
        }
        // Simple filesystem based match. If recursively successfull, prepend consumed directory and return success
        if (is_dir($fsPath . '/' . $uriArray[0])) {
            if (static::$debug) printf("Recursing because filesystem matched<br>\n");
            list($subResult, $pathboundRequestUri, $scriptFilename, $extractedArguments) = $this->_route($verb, $fsPath . '/' . $uriArray[0], array_slice($uriArray, 1));
            if ($subResult) return [ true, $uriArray[0] . '/' . $pathboundRequestUri, $scriptFilename, $extractedArguments ];
        }
        // Try to consume part of the URI using regex and proceed recursively
        list($localExtractedArguments, $matchedUri) = $this->extractRegex($verb, $fsPath, implode('/', $uriArray));
        if ($localExtractedArguments === false) return [ false, false, false, [] ];
        if (substr(implode('/', $uriArray), 0, strlen($matchedUri)) != $matchedUri) throw new Exception(sprintf('Regex resulted in an URI match not present at the start of the URI. match=%s uri=%s',
            $matchedUri,
            implode('/', $uriArray)
        ));
        $remainingUriArray = array_values(array_filter(explode('/', substr(implode('/', $uriArray), strlen($matchedUri))), function($p) { return $p != ""; }));
        //  - Regex consumed everything. Stop here
        if (count($remainingUriArray) == 0) {
            $scriptFilename = $this->getScriptFilename($verb, $fsPath);
            if ($scriptFilename === false) return [ false, false, false, [] ];

            return [ true, '', $scriptFilename, $localExtractedArguments ];
        }
        //  - Regex left unconsumed URI, continue recursively
        if (static::$debug) printf("Recursing on unconsumed URI after regex match<br>\n");
        list($subResult, $pathboundRequestUri, $scriptFilename, $extractedArguments) = $this->_route($verb, $fsPath, $remainingUriArray);
        if ($subResult) return [ true, $pathboundRequestUri, $scriptFilename, array_merge($localExtractedArguments, $extractedArguments) ];
        // 404
        return [ false, false, false, [] ];
    }
    public function route() {
        if (static::$debug) header('Content-type: text/plain');
        list($result, $pathboundRequestUri, $scriptFilename, $extractedArguments) = $this->_route(
            $this->requestMethod(),
            $this->rootPath,
            array_values(array_filter(explode('/', explode('?', $_SERVER['REQUEST_URI'], 2)[0]), function($p) { return $p != ""; }))
            );
        if (!$result) throw new Exception_HTTP_404();
        $_SERVER['ROUTER_PATHBOUND_REQUEST_URI'] = $pathboundRequestUri;
        $_SERVER['ROUTER_PATHBOUND_SCRIPT_FILENAME'] = $scriptFilename;
        $_SERVER['ROUTER_REQUEST'] = $extractedArguments;
        $_REQUEST = array_merge($_REQUEST, $_SERVER['ROUTER_REQUEST']);
        if (static::$debug) {
            printf("Would now include %s\n", $_SERVER['ROUTER_PATHBOUND_SCRIPT_FILENAME']);
            exit(0);
        } else {
            $this->includeScript($_SERVER['ROUTER_PATHBOUND_SCRIPT_FILENAME']);
        }
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
