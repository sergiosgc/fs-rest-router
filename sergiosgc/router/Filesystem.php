<?php
namespace sergiosgc\router;

class Filesystem {
    public function __construct($paths) {
        $this->paths = is_array($paths) ? $paths : [ $paths ];
    }
    public function addPath($path, $prepend = false) {
        if ($prepend) array_unshift($this->paths, $path); else array_push($this->paths, $path);
    }
    public function output($uri = null, $filenames = null) {
        list($matchedUri, $args, $scriptFile) = $this->route($uri, $filenames);
        foreach($args as $key => $val) $_REQUEST[$key] = $val;
        $this->include($scriptFile);
    }
    public function include($scriptFile) {
        include($scriptFile);
    }
    public function route($uri = null, $filenames = null, $debug = false) {
        $uri = is_null($uri) ? explode('?', $_SERVER['REQUEST_URI'], 2)[0] : $uri;
        $uriArray = array_values(array_filter(
            explode('/', $uri),
            function ($part) { return $part != '' && $part != '.' && $part != '..'; }
        ));
        $filenames = is_null($filenames) ? 'index' : $filenames;
        $filenames = is_array($filenames) ? $filenames : [ $filenames ];
        foreach ($this->paths as $path) if ($candidate = $this->matchUri($path, $uriArray, $filenames, $debug)) return $candidate;
        if (!$debug) try {
            ob_start();
            $this->route($uri, $filenames, true);
            syslog(LOG_WARNING, ob_get_clean());
        } catch (Exception $e) { } // Logging an explanation was a best effort
        if (!$debug) throw new Exception_HTTP_404(sprintf('URI not found %s for verb filenames [%s]', $uri, implode(',', $filenames)));
    }
    protected function matchUri($path, $uri, $filenames, $debug, $skipRegexes = false) {
        if ($debug) printf("Attempting to match URI '%s' at '%s'\n", implode('/', $uri), $path);
        $regexMatched = false;
        foreach ($filenames as $filename) if (!$skipRegexes && file_exists($path . '/' . $filename . '.regex')) {
            if (isset($uri[0]) && preg_match( trim(file_get_contents( $path . '/' . $filename . '.regex')), $uri[0], $matches )) {
                $regexMatched = true;
                $matchedUri = $matches[0];
                if ($debug) printf("Regex '%s' matched. Recursing. \n", file_get_contents(trim( $path . '/' . $filename . '.regex')));
                $candidate = $this->matchUri($path, array_values(array_slice( $uri, 1 )), $filenames, $debug, true);
                if ($candidate) {
                    $extractedArgs = [];
                    foreach($matches as $key => $val) if (is_string($key)) $extractedArgs[$key] = $val;
                    return [
                        $matches[0] . '/' . $candidate[0], /* matched uri */
                        array_merge($extractedArgs, $candidate[1]), /* extracted arguments */
                        $candidate[2] /* script file */
                    ];
                }
            } else if ($debug) printf("Regex '%s' does not match\n", file_get_contents(trim( $path . '/' . $filename . '.regex')));
        }
        if (!$regexMatched && 0 == count($uri)) {
            foreach ($filenames as $filename) if (file_exists($path . '/' . $filename . '.php')) {
                if ($debug) printf("Returning existing file: %s\n", $path . '/' . $filename . '.php');
                return [ 
                    '', /* matched uri */
                    [], /* extracted arguments */
                    $path . '/' . $filename . '.php' /* script file */
                ];
            } else if ($debug) printf("File '%s' does not exist.\n", $path . '/' . $filename . '.php');
            if ($debug) printf("Empty uri. Returning false.");
            return false;
        }
        if (!$regexMatched && is_dir( $path . '/' . $uri[0])) {
            if ($debug) printf("Directory '%s' exists. Recursing.\n", $uri[0]);
            $candidate = $this->matchUri( $path . '/' . $uri[0], array_values(array_slice( $uri, 1 )), $filenames, $debug);
            if ($candidate) return [
                $uri[0] . '/' . $candidate[0], /* matched uri */
                $candidate[1], /* extracted arguments */
                $candidate[2] /* script file */
            ];
        }
        if ($debug) printf("No more match mechanisms. Returning false. Unmatched URI at this point: %s\n", implode('/', $uri));
        return false;
    }
}
