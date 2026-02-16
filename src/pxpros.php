<?php

const BR = '<br>';
const RN = "\r\n";
const S = '/';
const R = "\r";
const N = "\n";

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $PAGE = null;
	$files = [];

    if (!isset($argv[1])) error("Invalid argument.");

    if($argv[1] == 'sitemap') {
        if(!$target = realpath($argv[2])) error("Invalid target.");
        if (!$seed = PXPros::findSeed($target)) error("No project configuration found.");
        $prj = new PXPros($seed);
        if(!$files[] = $prj->sitemap()) error("Can't produce the sitemap.");
        succeed($files);
    }

    if (!$target = realpath($argv[1])) error("Invalid target.");

    if (is_dir($target)) {
        if (!$seed = PXPros::findSeed($target)) error("No project configuration found.");
        $prj = new PXPros($seed);
        foreach (dig($target . '/*.php') as $file) {
            $parent = pathinfo(pathinfo($file, PATHINFO_DIRNAME), PATHINFO_BASENAME);
            if (strpos($parent, '_') === 0) continue;
            if (strpos(pathinfo($file, PATHINFO_FILENAME), '_') !== 0) continue;
            $files[] = $prj->render($file);
        }
    } elseif (preg_match('#^_(.*)\.php$#i', pathinfo($target, PATHINFO_BASENAME), $m)) {
        if (!$seed = PXPros::findSeed($target)) error("No project configuration found.");
        $pxpros = new PXPros($seed);
        $files[] = $pxpros->render($target);
    } else {
        throw new Exception("Invalid target.");
    }
} catch(Exception $e) {
    error($e->getMessage());
}

succeed($files);






final class PXPros
{

    const SEED_FILE = '_pxpros.json';

    private $root;
    private $file;
    private $page;
    private $config;
    private $vars = [];
    private $tags = [];
    private $hooks = [];
    private $plugins = [];


    /**
     * __construct
     *
     * @param  mixed $prjfile Project configuration file (_pxprox.json)
     * @return void
     */
    public function __construct($prjfile)
    {
        if (!is_file($prjfile)) return false; //throw error
        if (!$json = file_get_contents($prjfile)) return false; //throw error
        if (!$this->config = json_decode($json)) return false; //throw error
        $this->root = pathinfo($prjfile, PATHINFO_DIRNAME) . S;
        $GLOBALS['PAGE'] = $this;
        $this->includes();
    }


    /**
     * Project and page data getter
     *
     * @param  mixed $name Variable name
     * @return void
     */
    public function __get($name)
    {
        switch ($name) {
            case 'root':
                return $this->root;
            case 'plugins':
                return $this->plugins;
            case 'file':
                return $this->file;
            default:
                if (!empty($this->vars[$name])) return $this->vars[$name];
                elseif (!empty($this->page->{$name})) return $this->page->{$name};
                elseif (!empty($this->config->{$name})) return $this->config->{$name};
                elseif (!empty($this->config->data->{$name})) return $this->config->data->{$name};
        }
    }


    /**
     * Project and page data setter
     *
     * @param  mixed $name
     * @param  mixed $val
     * @return void
     */
    public function __set($name, $val)
    {
        $this->vars[$name] = $val;
    }


    /**
     * Includes base .php files
     *
     * @return void
     */
    private function includes()
    {
        if (!empty($this->config->includes)) foreach ($this->config->includes as $path) {
            if (!is_file(realpath($this->root . $path))) continue;
            else include_once(realpath($this->root . $path));
        }
    }


    /**
     * Render a page
     *
     * @param  mixed $file File to render
     * @return void
     */
    public function render($file)
    {
        global $PAGE;
        $PAGE = $this;
        $dir = pathinfo($file, PATHINFO_DIRNAME) . S;
        $this->page = php_file_info($file);
        $target = $dir . ltrim(pathinfo($file, PATHINFO_FILENAME), '_') . '.html';
        $this->file = realpath($file);
        $this->plugins = [];
        $this->processHook('pre_render', file_get_contents($file));
        
        ob_start();
		if ($this->before) include(realpath($this->root . $this->before));
        $header = ob_get_clean();
        
        ob_start();
        include($file);
        $body = ob_get_clean();
        if($this->indent) {
            $body = join(PHP_EOL, array_map(function($line) {
                return str_repeat(' ', $this->indent) . $line;
            }, explode(PHP_EOL, $body)));
        }
        
        ob_start();
        if ($this->after) include(realpath($this->root . $this->after));
        $footer = ob_get_clean();
        
        $contents = $header . $body . $footer;
        $contents = $this->processTags($contents);
        $contents = $this->processHook('post_render', $contents);
        file_put_contents($target, $contents);
        return realpath($target);
    }



    /**
     * registerTag
     *
     * @param  mixed $tag
     * @param  mixed $clb
     * @return void
     */
    public function registerTag($tag, $clb)
    {
        $this->tags[$tag] = $clb;
    }


    /**
     * processTags
     *
     * @return void
     */
    public function processTags($contents)
    {
        foreach ($this->tags as $tag => $clb) {
            $contents = replace_tags($tag, $contents, $clb);
        }
        return $contents;
    }


    /**
     * registerHook
     *
     * @param  string $hook Name of the hook
     * @param  callable $clb The callback
     * @return void
     */
    public function registerHook($hook, $clb)
    {
        $this->hooks[$hook][] = $clb;
    }


    /**
     * processHook
     *
     * @param  string $hook Name of the hook
     * @param  mixed $data The data to be returned by the callback
     * @return mixed
     */
    public function processHook($hook, $data = null)
    {
        if (!empty($this->hooks[$hook])) {
            foreach ($this->hooks[$hook] as $clb) {
                $data = call_user_func($clb, $data);
            }
        }
        return $data;
    }


    /**
     * Method sitemap
     *
     * @return void
     */
    public function sitemap()
    {
        $paths = [];
        $root = realpath($this->root);
        foreach (dig($root . '/_*.php') as $file) {
            $parent = pathinfo(pathinfo($file, PATHINFO_DIRNAME), PATHINFO_BASENAME);
            if (strpos($parent, '_') === 0) continue;
            if (strpos(pathinfo($file, PATHINFO_FILENAME), '_') !== 0) continue;
            $paths[] = str_replace('\\', '/', ltrim(str_replace($root, '', pathinfo(realpath($file), PATHINFO_DIRNAME)), DIRECTORY_SEPARATOR));
        }
        if(empty($paths)) $paths[] = '';

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $urlset = $dom->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');
        $dom->appendChild($urlset);

        foreach($paths as $path) {
            $deep = $path ? count(explode('/', $path)) : 0;
            $priority = sprintf('%0.1f', (10 - $deep) / 10);
            $url = $this->baseurl . ($path ? $path . '/' : '');

            $durl = $dom->createElement('url');
            $urlset->appendChild($durl);

            $durl->appendChild($dom->createElement('loc', $url));
            $durl->appendChild($dom->createElement('changefreq', 'monthly'));
            $durl->appendChild($dom->createElement('priority', $priority));
        }

        $dest = $this->root . 'sitemap.xml';
        file_put_contents($dest, $dom->saveXML());
        return realpath($dest);
    }


    /**
     * Find the currect project configuration file
     *
     * @param  mixed $path Current path
     * @return mixed Returns the project configuration file if exists, otherwise false.
     */
    public static function findSeed($path)
    {
        if (is_file($path)) $path = pathinfo(realpath($path), PATHINFO_DIRNAME);
        elseif (!$path = realpath($path)) return false;
        $recPath = $path;

        do {
            $file = $path . S . self::SEED_FILE;
            if (is_file($file)) return realpath($file);
            $path = pathinfo($path, PATHINFO_DIRNAME);
        } while ($path != pathinfo($path, PATHINFO_DIRNAME));
        
        
        $ignoreDirs = ['.git', 'node_modules', 'vendor'];
        $dir = new RecursiveDirectoryIterator($recPath, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator($dir, function (SplFileInfo $current) use ($ignoreDirs) {
            if ($current->isDir()) {
                return !in_array($current->getFilename(), $ignoreDirs, true);
            }
            return true;
        });
        $it = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && $file->getFilename() === self::SEED_FILE) {
                return $file->getRealPath();
            }
        }
        
        return false;
    }


    public static function findRoot(string $path, bool $abs = false)
    {
        if(!$seed = self::findSeed($path)) return false;
        if(!$root = pathinfo($seed, PATHINFO_DIRNAME)) return false;
        return $abs ?  $root . S : get_relative_path($path, $root);
    }


    public function addPlugin($file) {
        array_push($this->plugins, $file);
    }

}














function error($str)
{
    file_put_contents('php://stderr', json_encode([
        'success' => false,
        'error' => trim($str)
    ], JSON_PRETTY_PRINT), FILE_APPEND);
    exit(1);
}


function succeed($files)
{
    file_put_contents('php://stdout', json_encode([
        'success' => true,
        'files' => $files
    ], JSON_PRETTY_PRINT), FILE_APPEND);
    exit(0);
}



/**
 * Recursevly walk a folder and yield files corresponding to the pattern
 *
 * @param  mixed $path Path and pattern to walk through
 * @return iterable
 */
function dig($path): iterable
{
    $patt = pathinfo($path, PATHINFO_BASENAME);
    $path = pathinfo($path, PATHINFO_DIRNAME);
    if ($path = realpath($path)) {
        $path .= S;
        foreach (glob($path . $patt) as $file) {
            if (!is_dir($file)) yield $file;
        }
        foreach (glob($path . '*', GLOB_ONLYDIR) as $dir) {
            foreach (call_user_func(__FUNCTION__, $dir . S . $patt) as $file) yield $file;
        }
    }
}




/**
 * Parse the first DOCKBLOCK of a file and return attributes as an object
 *
 * @param  mixed $file PHP File to be parse
 * @return void
 */
function php_file_info($file)
{
    static $files = [];
    if (!$file = realpath($file)) return false;
    if (!isset($files[$file])) {
        $tokens = token_get_all(file_get_contents($file));
        foreach ($tokens as $tok) {
            if (!is_array($tok)) continue;
            if ($tok[0] == T_DOC_COMMENT) {
                $block = $tok[1];
                break;
            }
        }
        if (empty($block)) return new stdClass;
        if (!preg_match_all('#@([a-z0-9]+)[\s\t]+([^\n]+)#msi', $block, $m)) $files[$file] = new stdClass;
        else {
            foreach ($m[1] as $k => $v) $info[trim($v)] = trim($m[2][$k]);
            $files[$file] = (object)$info;
        }
    }
    return $files[$file];
}



/**
 * replace_tags
 *
 * @param  mixed $tag
 * @param  mixed $contents
 * @param  mixed $clb
 * @return void
 */
function replace_tags($tag, $contents, $clb)
{
    $contents = preg_replace_callback('#<' . preg_quote($tag, '#') . '([^>]*)>(.*?)</' . preg_quote($tag, '#') . '>#msi', function ($m) use ($clb) {
        return call_user_func($clb, $m[0], parse_html_attributes($m[1]), $m[2]);
    }, $contents);
    return $contents;
}



/**
 * parse_html_attributes
 *
 * @param  mixed $attributes
 * @return void
 */
function parse_html_attributes($attributes)
{
    if (preg_match_all('#(\\w+)\s*=\\s*("[^"]*"|\'[^\']*\'|[^"\'\\s>]*)#i', $attributes, $m)) {
        foreach ($m[1] as $k => $key) {
            $attrs[strtolower($key)] = stripslashes(substr($m[2][$k], 1, -1));;
        }
    }
    return isset($attrs) ? $attrs : [];
}




/**
 * get_relative_path
 *
 * @param  mixed $from
 * @param  mixed $to
 * @return string
 */
function get_relative_path($from, $to)
{
    $from     = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
    $to       = is_dir($to)   ? rtrim($to, '\/') . '/'   : $to;
    $from     = str_replace('\\', '/', $from);
    $to       = str_replace('\\', '/', $to);
    $from     = explode('/', $from);
    $to       = explode('/', $to);
    $relPath  = $to;

    foreach ($from as $depth => $dir) {
        if ($dir === $to[$depth]) {
            array_shift($relPath);
        } else {
            $remaining = count($from) - $depth;
            if ($remaining > 1) {
                $padLength = (count($relPath) + $remaining - 1) * -1;
                $relPath = array_pad($relPath, $padLength, '..');
                break;
            } else {
                $relPath[0] = './' . $relPath[0];
            }
        }
    }
    return $relPath ? implode('/', $relPath) : './';
}

