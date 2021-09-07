<?php
namespace sergiosgc\router;

class Templated {
    public function __construct($subrouter, $rootdir, $templateCompiler, $mediaTypePriorities = null, $wildcard = null) {
        $this->subrouter = $subrouter;
        $this->rootdir = $rootdir;
        $this->templateCompiler = $templateCompiler;
        $this->mediaTypePriorities = is_null($mediaTypePriorities) ? [ 'application/json', 'text/html; charset=UTF-8' ] : $mediaTypePriorities;
        $this->wildcard = $wildcard;
    }
    public function include($veryRandomVariableNameForThescriptFile, $equallyLongStringToAvoidCollisionWithArguments = []) {
        foreach($equallyLongStringToAvoidCollisionWithArguments as $equallyLongStringToAvoidCollisionWithArgumentsKey => $equallyLongStringToAvoidCollisionWithArgumentsValue) {
        if ($equallyLongStringToAvoidCollisionWithArguments) {
            $debug = true;
        }
            $$equallyLongStringToAvoidCollisionWithArgumentsKey = $equallyLongStringToAvoidCollisionWithArgumentsValue;
        }
        unset($equallyLongStringToAvoidCollisionWithArguments);
        unset($equallyLongStringToAvoidCollisionWithArgumentsKey);
        unset($equallyLongStringToAvoidCollisionWithArgumentsValue);
        include($veryRandomVariableNameForThescriptFile);
        $result = get_defined_vars();
        unset($result['veryRandomVariableNameForThescriptFile']);
        return $result;
    }
    public function output($uri = null) {
        list($matchedUri, $args, $scriptFile) = $this->route($uri);
        $mimePrefix = strtr(explode(';', $this->getNegotiatedMediaType())[0], [ '/' => '-']);
        $candidateFilenames = explode('/', $scriptFile);
        $candidateFilenames = array_pop($candidateFilenames);
        $candidateFilenames = [ $mimePrefix . '.' . preg_replace('_\.php$_', '', $candidateFilenames), 'all.' . preg_replace('_\.php$_', '', $candidateFilenames),];
        if (!is_null($this->wildcard)) array_unshift($candidateFilenames, $mimePrefix . '.' . $this->wildcard);
        $candidateFiles = array_filter(
            array_map(
                function ($filename) use ($scriptFile) { 
                    if (file_exists( sprintf('%s/%s.tpl', dirname($scriptFile), $filename) )) return sprintf('%s/%s.tpl', dirname($scriptFile), $filename);
                    if (file_exists( sprintf('%s/%s.php', dirname($scriptFile), $filename) )) return sprintf('%s/%s.php', dirname($scriptFile), $filename);
                    return '';
                },
                $candidateFilenames
            )
        );
        if (0 == count($candidateFiles)) throw new Exception_NoTemplateFound(sprintf('Template for media type %s under %s not found', $mimePrefix, dirname($scriptFile)));
        $template = array_pop($candidateFiles);
        $compiledTemplate = preg_replace('_\.tpl$_', '.php', $template);
        if ($compiledTemplate != $template && (
            (!file_exists($compiledTemplate) || stat($template)['mtime'] > stat($compiledTemplate)['mtime']) ||
            (is_writable($compiledTemplate) && isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] == 'no-cache')
        )) {
            $result = file_put_contents(
                $compiledTemplate, 
                call_user_func($this->templateCompiler, file_get_contents($template))
            );
            if ($result === FALSE) throw new Exception_TemplateWrite(sprintf("Error writing template %s", $compiledTemplate));
        }

        // Run request script
        foreach($args as $key => $val) $_REQUEST[$key] = $val;
        ob_start();
        $result = $this->include($scriptFile);
        $content = ob_get_clean();
        if ($content != '') syslog(LOG_WARNING, sprintf("Script %s produced output:\n%s", $scriptFile, $content));

        // Run output template
        $this->include($compiledTemplate, $result);
    }
    public function preOutput($uri = null) {
        list($matchedUri, $args, $scriptFile) = $this->route($uri);
        $scriptFile = explode('/', $scriptFile);
        array_pop($scriptFile);
        array_push($scriptFile, 'index.pre.php');
        $scriptFile = implode("/", $scriptFile);

        if (file_exists($scriptFile)) $this->include($scriptFile);
    }
    public function route($uri = null) {
        return $this->subrouter->route($uri);
    }
    public function getNegotiatedMediaType() {
        $accept = null;
        if (isset($_REQUEST['x-accept'])) {
            $accept = $_REQUEST['x-accept'];
        } elseif (isset($_SERVER['HTTP_ACCEPT'])) {
            $accept = $_SERVER['HTTP_ACCEPT'];
        }
        if (is_null($accept)) {
            $mediaType = null;
        } else {
            $negotiator = new \Negotiation\Negotiator();
            $mediaType = $negotiator->getBest($accept, $this->mediaTypePriorities);
            if (!is_null($mediaType)) $mediaType = $mediaType->getValue();
        }
        if (is_null($mediaType)) $mediaType = $this->mediaTypePriorities[0];
        return $mediaType;
    }
}
