<?php
namespace Rain\Tpl;
/**
 *  RainTPL
 *  --------
 *  Realized by Federico Ulfo & maintained by the Rain Team
 *  Distributed under GNU/LGPL 3 License
 *
 *  @version 3.0 Alpha milestone: https://github.com/rainphp/raintpl3/issues/milestones?with_issues=no
 */
class Parser
{
    // variables
    public $var = array();

    protected $templateInfo = array(),
              $objectConf = array();

    protected $config = array(
        'ignore_single_quote' => true,
    );

    /**
     * Temporary data for parser when running
     *
     * @var array
     */
    protected $tagData = array(

    );

    /**
     * Plugin container
     *
     * @var \Rain\Tpl\PluginContainer
     */
    protected static $plugins = null;

    // configuration
    protected static $conf = array();

    // tags registered by the developers
    protected static $registered_tags = array();

    // tags natively supported
    protected static $tags = array(
        // @TODO: {capture}
        // @TODO: {block}
        // @TODO: support for syntax "as $key => $value" in {loop} and {foreach}

        'variable' => true, // {$} RainTPL3.1
        'if' => true, // {if}, {elseif} RainTPL3.1
        'else' => true, // {else} RainTPL3.1
        'function' => true, // {function} RainTPL3.1
        'loop' => true, // {loop}, {foreach} RainTPL3.1
        'loop_break' => true, // RainTPL3.1
        'loop_continue' => true, // RainTPL3.1
        'include' => true, // RainTPL3.1
        'autoescape' => true,
        'noparse' => true,
        'ternary' => true,
        'comment' => true, // {*}, {ignore} RainTPL3.1
        'constant' => true,
    );

    /**
     * List of attached functions for parsing blocks
     *
     * Example: 'myTag' => array($object, 'methodName')
     *
     * @var array
     */
    public $blockParserCallbacks = array(

    );

    /**
     * Black list of functions and variables
     *
     * @var array
     */
    protected static $black_list = array(
        'exec', 'shell_exec', 'pcntl_exec', 'passthru', 'proc_open', 'system',
        'posix_kill', 'posix_setsid', 'pcntl_fork', 'posix_uname', 'php_uname',
        'phpinfo', 'popen', 'file_get_contents', 'file_put_contents', 'rmdir',
        'mkdir', 'unlink', 'highlight_contents', 'symlink',
        'apache_child_terminate', 'apache_setenv', 'define_syslog_variables',
        'escapeshellarg', 'escapeshellcmd', 'eval', 'fp', 'fput',
        'ftp_connect', 'ftp_exec', 'ftp_get', 'ftp_login', 'ftp_nb_fput',
        'ftp_put', 'ftp_raw', 'ftp_rawlist', 'highlight_file', 'ini_alter',
        'ini_get_all', 'ini_restore', 'inject_code', 'mysql_pconnect',
        'openlog', 'passthru', 'php_uname', 'phpAds_remoteInfo',
        'phpAds_XmlRpc', 'phpAds_xmlrpcDecode', 'phpAds_xmlrpcEncode',
        'posix_getpwuid', 'posix_kill', 'posix_mkfifo', 'posix_setpgid',
        'posix_setsid', 'posix_setuid', 'posix_uname', 'proc_close',
        'proc_get_status', 'proc_nice', 'proc_open', 'proc_terminate',
        'syslog', 'xmlrpc_entity_decode'
    );

    public function __construct($config, $objectConf, $conf, $plugins, $registered_tags)
    {
        $this->config = $config;
        static::$plugins = $plugins;
        static::$registered_tags = $registered_tags;
    }

    /**
     * Returns plugin container.
     *
     * @return \Rain\Tpl\PluginContainer
     */
    protected static function getPlugins() {
        return static::$plugins
            ?: static::$plugins = new PluginContainer();
    }

    /**
     * Compile the file and save it in the cache
     *
     * @param string $templateName: name of the template
     * @param string $templateBaseDir
     * @param string $templateDirectory
     * @param string $templateFilepath
     * @param string $parsedTemplateFilepath: cache file where to save the template
     */
    public function compileFile($templateName, $templateBasedir, $templateDirectory, $templateFilepath, $parsedTemplateFilepath) {

        // open the template
        $fp = fopen($templateFilepath, "r");

        // lock the file
        if (flock($fp, LOCK_SH))
        {
            // save the filepath in the info
            $this->templateInfo['template_filepath'] = $templateFilepath;

            // read the file
            $this->templateInfo['code'] = $code = fread($fp, filesize($templateFilepath));

            // xml substitution
            $code = preg_replace("/<\?xml(.*?)\?>/s", /*<?*/ "##XML\\1XML##", $code);

            // disable php tag
            if (!$this->config['php_enabled'])
                $code = str_replace(array("<?", "?>"), array("&lt;?", "?&gt;"), $code);

            // xml re-substitution
            $code = preg_replace_callback("/##XML(.*?)XML##/s", function( $match ) {
                    return "<?php echo '<?xml " . stripslashes($match[1]) . " ?>'; ?>";
                }, $code);

            $parsedCode = $this->compileTemplate($code, $isString = false, $templateBasedir, $templateDirectory, $templateFilepath);
            $parsedCode = "<?php if(!class_exists('Rain\Tpl')){exit;}?>" . $parsedCode;

            // fix the php-eating-newline-after-closing-tag-problem
            $parsedCode = str_replace("?>\n", "?>\n\n", $parsedCode);

            // create directories
            if (!is_dir($this->config['cache_dir']))
                mkdir($this->config['cache_dir'], 0755, TRUE);

            // check if the cache is writable
            if (!is_writable($this->config['cache_dir']))
                throw new Exception('Cache directory ' . $this->config['cache_dir'] . 'doesn\'t have write permission. Set write permission or set RAINTPL_CHECK_TEMPLATE_UPDATE to FALSE. More details on http://www.raintpl.com/Documentation/Documentation-for-PHP-developers/Configuration/');

            // write compiled file
            file_put_contents($parsedTemplateFilepath, $parsedCode);

            // release the file lock
            flock($fp, LOCK_EX);
        }

        // close the file
        fclose($fp);
    }

    /**
     * Compile a string and save it in the cache
     *
     * @param string $templateName: name of the template
     * @param string $templateBaseDir
     * @param string $templateFilepath
     * @param string $parsedTemplateFilepath: cache file where to save the template
     * @param string $code: code to compile
     */
    public function compileString($templateName, $templateBasedir, $templateFilepath, $parsedTemplateFilepath, $code)
    {
        // open the template
        $fp = fopen($parsedTemplateFilepath, "w");

        // lock the file
        if (flock($fp, LOCK_SH))
        {
            // xml substitution
            $code = preg_replace("/<\?xml(.*?)\?>/s", "##XML\\1XML##", $code);

            // disable php tag
            if (!$this->config['php_enabled'])
                $code = str_replace(array("<?", "?>"), array("&lt;?", "?&gt;"), $code);

            // xml re-substitution
            $code = preg_replace_callback("/##XML(.*?)XML##/s", function( $match ) {
                    return "<?php echo '<?xml " . stripslashes($match[1]) . " ?>'; ?>";
                }, $code);

            $parsedCode = $this->compileTemplate($code, $isString = true, $templateBasedir, $templateDirectory = null, $templateFilepath);

            $parsedCode = "<?php if(!class_exists('Rain\Tpl')){exit;}?>" . $parsedCode;

            // fix the php-eating-newline-after-closing-tag-problem
            $parsedCode = str_replace("?>\n", "?>\n\n", $parsedCode);

            // create directories
            if (!is_dir($this->config['cache_dir']))
                mkdir($this->config['cache_dir'], 0755, true);

            // check if the cache is writable
            if (!is_writable($this->config['cache_dir']))
                throw new Exception('Cache directory ' . $this->config['cache_dir'] . 'doesn\'t have write permission. Set write permission or set RAINTPL_CHECK_TEMPLATE_UPDATE to false. More details on http://www.raintpl.com/Documentation/Documentation-for-PHP-developers/Configuration/');

            // write compiled file
            fwrite($fp, $parsedCode);

            // release the file lock
            flock($fp, LOCK_UN);
        }

        // close the file
        fclose($fp);
    }

    /**
     * Split code into parts that should contain {code} tavar_dump($templateFilepath);gs and HTML as separate elements
     *
     * @param string $code Input TPL code
     * @author Damian Kęska <damian.keska@fingo.pl>
     * @return array
     */
    protected function prepareCodeSplit($code)
    {
        $split = array();
        $cursor = 0;
        $current = 0;
        $blockPositions = array();
        $arrIndex = -1;
        $lastBlockType = '';

        while ($current !== false)
        {
            $current = strpos($code, '{', $cursor);

            if ($current === false)
                break;

            $sChar = substr($code, $current + 1, 1);
            $sCharMatch = (substr($code, $current + 1, 1) === ' ' || $sChar === "\t" || $sChar === "\n" || $sChar === "\r" || ($this->config['ignore_single_quote'] && $sChar === "'")); // condition that check if there is any space or special character after "{"

            if (!$sCharMatch)
            {
                /**
                 * Template tags
                 */
                $currentEnding = strpos($code, '}', $current) + 1;

                // before our {code} block
                $split[] = substr($code, $cursor, ($current - $cursor)); $arrIndex++;

                // our {code} block
                $split[] = substr($code, $current, ($currentEnding - $current)); $arrIndex++;

                $blockPositions[$arrIndex] = $current. '|' .($currentEnding - $current);
                $cursor = $currentEnding;
                $lastBlockType = 1;

            } else {
                /**
                 * HTML & Javascript & JSON code
                 */
                $next = strpos($code, '{', $current + 1);

                if (!$next)
                    break;

                // take all data to "{"
                if ($sCharMatch)
                {
                    // divide into bigger blocks
                    if ($lastBlockType === 1) {
                        $split[] = substr($code, $cursor, ($current - $cursor));
                        $arrIndex++;
                    } else
                        $split[$arrIndex] .= substr($code, $cursor, ($current - $cursor)); // append to existing block
                }

                // take all data after "{" until next "{"
                if ($lastBlockType === 1)
                {
                    $split[] = substr($code, $current, ($next - $current));
                    $arrIndex++;
                } else
                    $split[$arrIndex] .= substr($code, $current, ($next - $current)); // append to existing block

                $cursor = $next;
                $lastBlockType = 0;
            }
        }

        // the rest of code
        $split[] = substr($code, $cursor, (strlen($code) - $cursor));

        // remove empty entry from beginning if exists
        if (isset($split[0]) && !$split[0])
            unset($split[0]);

        // uncomment to see how the template is divided into parts
        //print_r($split);

        return array($split, $blockPositions);
    }

    /**
     * Compile template
     * @access protected
     *
     * @param string $code : code to compile
     * @param $isString
     * @param $templateBasedir
     * @param $templateDirectory
     * @param $templateFilepath
     * @throws \Rain\Tpl_Exception
     * @throws string
     * @return null|string
     */
    protected function compileTemplate($code, $isString, $templateBasedir, $templateDirectory, $templateFilepath)
    {
        $parsedCode = '';

        // execute plugins, before parse
        $context = static::getPlugins()->createContext(array(
            'code' => $code,
            'template_basedir' => $templateBasedir,
            'template_filepath' => $templateFilepath,
            'conf' => $this->config,
        ));

        static::getPlugins()->run('beforeParse', $context);
        $code = $context->code;

        // remove comments
        if ($this->config['remove_comments'])
        {
            $code = preg_replace('/<!--(.*)-->/Uis', '', $code);
        }

        // Testing parser configuration:
        //$this->config['ignore_single_quote'] = true;
        //$this->config['ignore_unknown_tags'] = true;

        list($codeSplit, $blockPositions) = $this->prepareCodeSplit($code);

        if ($templateFilepath == '/var/www/html/apps/panthera/.fw/lib//templates/admin/templates/dashWidgets/gallery.tpl')
        {
            var_dump($codeSplit);
            exit;
        }

        // new code
        if ($codeSplit)
        {
            // pass all blocks to this parser
            $passAllBlocksTo = '';
            $this->tagData = array(

            );

            // uncomment line below to take a look what we have to parse
            // var_dump($codeSplit);

            /**
             * Loop over all code parts and execute actions on tags found in code
             *
             * Every code part begins with a HTML code or with a "{" that should be our TAG
             * For every found tag there is a callback executed to parse TPL -> PHP code
             *
             * Places where we are looking for callbacks are in order:
             * 1. $this->{tagName}BlockParser()
             * 2. $this->blockParserCallbacks[{tagName}]()
             * 3. {tagName}()
             *
             * @author Damian Kęska <damian.keska@fingo.pl>
             */
            foreach ($codeSplit as $index => $part)
            {
                // run tag parsers only on tags, exclude "{ " from parsing
                $starts = substr($part, 1, 1);

                if (substr($part, 0, 1) !== '{' || $starts == ' ' || $starts == "\n" || $starts == "\t" || ($this->config['ignore_single_quote'] && $starts == "'") || strpos($part, "\n") !== false)
                    continue;

                // tag parser found?
                $found = false;

                foreach (static::$tags as $tagName => $tag)
                {
                    if ($passAllBlocksTo)
                        $tagName = $passAllBlocksTo;

                    $method = null;

                    // select a method source that will parse selected tag
                    if (method_exists($this, $tagName. 'BlockParser'))
                        $method = array($this, $tagName. 'BlockParser');

                    elseif (isset($this->blockParserCallbacks[$tagName]) && is_callable($this->blockParserCallbacks[$tagName]))
                        $method = $this->blockParserCallbacks[$tagName];

                    elseif(function_exists($tagName. 'BlockParser'))
                        $method = $tagName. 'BlockParser';


                    if ($method)
                    {
                        $originalPart = $part;

                        if (!isset($this->tagData[$tagName]))
                        {
                            $this->tagData[$tagName] = array(
                                'level' => 0,
                                'count' => 0,
                            );
                        }

                        $result = call_user_func_array($method, array(
                            &$this->tagData[$tagName], &$part, &$tag, $templateFilepath, $index, $blockPositions, $code, &$passAllBlocksTo,
                        ));

                        $codeSplit[$index] = $part;

                        if ($codeSplit[$index] !== $originalPart || $result === true)
                        {
                            $found = true;
                            break;
                        }
                    }
                }

                if ($found === false && !$this->config['ignore_unknown_tags'])
                {
                    $pos = $this->findLine($index, $blockPositions, $code);
                    $e = new SyntaxException('Error! Unknown tag "' .$part. '", loaded by ' .$templateFilepath. ' at line ' .$pos['line']. ', offset ' .$pos['offset'], 1, null, $pos['line'], $templateFilepath);
                    throw $e->templateFile($templateFilepath);
                }
            }

            if ($this->tagData)
            {
                foreach ($this->tagData as $tag => $data)
                {
                    if (isset($data['level']) && intval($data['level']) > 1)
                    {
                        $e = new SyntaxException("Error! You need to close an {' .$tag. '} tag, in file ".$templateFilepath, 2, null, 'unknown', $templateFilepath);
                        throw $e->templateFile($templateFilepath);
                    }
                }
            }

            $parsedCode = join('', $codeSplit);
        }

        // optimize output
        $parsedCode = str_replace('?><?php', '', $parsedCode);

        //$this->config['print_parsed_code'] = true;

        if ($this->config['print_parsed_code'])
        {
            print($parsedCode);
            exit;
        }

        // Execute plugins, after_parse
        $context->code = $parsedCode;
        static::getPlugins()->run('afterParse', $context);

        return $context->code;
    }

    /**
     * Find a line number and byte offset of {code} tag in compiled file
     *
     * @param int $partIndex Code part index
     * @param array $codeSplit Splitted code (and not only code) parts
     * @param array $blockPositions Index of positions of all splitted code parts
     *
     * @author Damian Kęska <damian.keska@fingo.pl>
     * @return array
     */
    protected function findLine($partIndex, $blockPositions, $code)
    {
        if (!isset($blockPositions[$partIndex]))
        {
            return array(
                'line' => 'unknown',
                'offset' => 'unknown',
            );
        }

        $blockPosition = explode('|', $blockPositions[$partIndex]);
        $codeString = substr($code, 0, $blockPosition[0]);
        $lines = explode("\n", $codeString);

        return array(
            'line' => count($lines),
            'offset' => $blockPosition[0],
        );
    }

    protected function varReplace($html, $loopLevel = NULL, $escape = TRUE, $echo = FALSE, $updateModifier = TRUE)
    {
        // change variable name if loop level
        if (!empty($loopLevel))
            $html = preg_replace(array('/(\$key)\b/', '/(\$value)\b/', '/(\$counter)\b/'), array('${1}' . $loopLevel, '${1}' . $loopLevel, '${1}' . $loopLevel), $html);

        // if it is a variable
        if (preg_match_all('/(\$[a-z_A-Z][^\s]*)/', $html, $matches)) {
            // substitute . and [] with [" "]
            for ($i = 0; $i < count($matches[1]); $i++) {

                $rep = preg_replace('/\[(\${0,1}[a-zA-Z_0-9]*)\]/', '["$1"]', $matches[1][$i]);
                //$rep = preg_replace('/\.(\${0,1}[a-zA-Z_0-9]*)/', '["$1"]', $rep);
                $rep = preg_replace( '/\.(\${0,1}[a-zA-Z_0-9]*(?![a-zA-Z_0-9]*(\'|\")))/', '["$1"]', $rep );
                $html = str_replace($matches[0][$i], $rep, $html);
            }

            // update modifier
            if ($updateModifier)
            {
                $html = $this->modifierReplace($html);
            }

            // if does not initialize a value, e.g. {$a = 1}
            if (!preg_match('/\$.*=.*/', $html)) {

                // escape character
                if ($this->config['auto_escape'] && $escape)
                    //$html = "htmlspecialchars( $html )";
                    $html = "htmlspecialchars( $html, ENT_COMPAT, '" . $this->config['charset'] . "', FALSE )";

                // if is an assignment it doesn't add echo
                if ($echo)
                    $html = "echo " . $html;
            }
        }

        return $html;
    }

    /**
     * Determine if the tag is selected tag ending
     *
     * @param string $tagBody Tag body string
     * @param array|string $endings Possible endings, or ending (if passed a string)
     * @author Damian Kęska <damian.keska@fingo.pl>
     * @return bool
     */
    protected function parseTagEnding($tagBody, $endings)
    {
        $tagBody = strtolower($tagBody);

        if (substr($tagBody, 0, 2) !== '{/' /*|| substr($tagBody, 0, 4) == '{end'*/)
        {
            return false;
        }

        if (!is_array($endings))
            $endings = array($endings);

        if (!$endings) return false;

        foreach ($endings as $endingKeyword)
        {
            if ($tagBody === '{/' .$endingKeyword. '}')
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse tag arguments
     *
     * Example input:
     * {foreach from="$test123" item="i" key="k"}
     *
     * Output:
     * from: $test123
     * item: i
     * key: k
     *
     * @param string $tagBody
     * @author Damian Kęska <damian.keska@fino.pl>
     * @return array
     */
    protected function parseTagArguments($tagBody)
    {
        // strip tag out of "{" and "}"
        if (substr($tagBody, 0, 1) == '{')
            $tagBody = substr($tagBody, 1, (strlen($tagBody) - 2));

        // this not includes a value with spaces inside as we will be passing mostly variables here
        $args = explode(' ', $tagBody);

        $argsAssoc = array();

        foreach ($args as $arg)
        {
            $equalsPos = strpos($arg, '=');

            if ($equalsPos === false)
                continue;

            $value = trim(substr($arg, ($equalsPos + 2), strlen($arg)));
            $argsAssoc[trim(substr($arg, 0, $equalsPos))] = substr($value, 0, (strlen($value) - 1));
        }

        return $argsAssoc;
    }

    /**
     * Parse variables {$var}
     *
     * Example input:
     * {$test}
     * {$test|trim}
     * {$test|str_replace:"a":"b"|trim|ucfirst}
     *
     * @param $tagData
     * @param $part
     * @param $tag
     *
     * @author Damian Kęska <damian.keska@fingo.pl>
     * @return null
     */
    protected function variableBlockParser(&$tagData, &$part, &$tag)
    {
        if (substr($part, 0, 2) != '{$')
            return false;

        $var = substr($part, 1, (strlen($part) - 2));

        // check if variable is begin assigned
        $equalsPos = strpos($part, '=');
        $firstModificator = strpos($part, "|");

        if ($equalsPos !== false && ($firstModificator === false || $equalsPos < $firstModificato))
        {
            $part = "<?php " . $this->parseModifiers($var, true) . ";?>";
            return true;
        }

        // variables substitution (eg. {$title})
        $part = "<?=" . $this->parseModifiers($var, true) . ";?>";
        return true;
    }

    /**
     * {if} code block parser
     *
     * Examples:
     * {if="$test > 5"} then here{/if}
     * {if $test > 5} then something here{/if}
     *
     * @param $tagData
     * @param $part
     * @param $tag
     *
     * @regex /{if="([^"]*)"}/
     * @regex ({if.*?})
     * @author Damian Kęska <damian.keska@fingo.pl>
     *
     * @return null|bool
     */
    protected function ifBlockParser(&$tagData, &$part, &$tag)
    {
        $lowerPart = strtolower($part);
        $ending = $this->parseTagEnding($lowerPart, array(
            'if', 'elseif'
        ));

        /**
         * {/if} - closing
         */
        if ($ending === true)
        {
            $tagData['level']--;
            $part = '<?php }?>';
            return true;
        }

        /**
         * {if} - opening
         */

        $tagType = substr($lowerPart, 0, 4);
        $elseTagType = substr($lowerPart, 0, 8);

        if ($tagType === '{if=')
        {
            $type = 'if';
            $posX = 2; // include =" at beginning
            $posY = 2; // include " at ending
            $len = 3;

        } elseif ($tagType === '{if ') {
            $type = 'if';
            $posX = 1;
            $posY = 1;
            $len = 3;

        } elseif ($elseTagType === '{elseif=') {
            $type = '}elseif';
            $posX = 2;
            $posY = 2;
            $len = 7;

        } elseif ($elseTagType === '{elseif ') {
            $type = '}elseif';
            $posX = 1;
            $posY = 1;
            $len = 7;

        } else
            return false;


        if ($type === 'if')
        {
            $tagData['level']++;
            $tagData['count']++;
        }

        $part = '<?php ' .$type. '(' .$this->varReplace(substr($part, $len + $posX, (strlen($part) - ($posY + $len + $posX))), $this->tagData['loop']['level'], $escape = FALSE). '){?>';

        return true;
    }

    /**
     * {else} instruction, could be used only inside of {if} block
     *
     * @param $tagData
     * @param $part
     * @param $tag
     *
     * @throws SyntaxException
     * @author Damian Kęska <damian.keska@fingo.pl>
     * @return null|void
     */
    protected function elseBlockParser(&$tagData, &$part, &$tag, $templateFilePath, $blockIndex, $blockPositions, $code)
    {
        $p = strtolower($part);

        if ($p === '{else}')
        {
            if ((isset($this->tagData['if']['level']) || $this->tagData['if']['level'] < 1) && (isset($this->tagData['loop']['level']) || $this->tagData['loop']['level'] < 1))
            {
                $context = $this->findLine($blockIndex, $blockPositions, $code);
                $e = new SyntaxException('Trying to use {else} outside of a loop', 3, null, $context['line'], $templateFilePath);
            }

            $part = '<?php }else{?>';
        }
    }

    /**
     * Parse a template comment - {*}, {/*}, {*, *}, {ignore}, {/ignore}
     *
     * @param $tagData
     * @param $part
     * @param $tag
     * @param $templateFilePath
     * @param $blockIndex
     * @param $blockPositions
     * @param $code
     * @param $passAllBlocksTo
     *
     * @author Damian Kęska <damian.keska@fingo.pl>
     * @return bool
     */
    protected function commentBlockParser(&$tagData, &$part, &$tag, $templateFilePath, $blockIndex, $blockPositions, $code, &$passAllBlocksTo)
    {
        $lowerPart = strtolower($part);

        if (substr($lowerPart, 0, 2) === '{*' || $lowerPart === '{*}' || $lowerPart === '{ignore}')
        {
            $tagData['level']++;
            $tagData['count']++;
            $passAllBlocksTo = 'comment';
            $part = '';
            return true;

        } elseif (substr($lowerPart, -2) === '*}' || $lowerPart === '{/*}' || $lowerPart === '{/ignore}') {
            $tagData['level']--;
            $passAllBlocksTo = '';
            $part = '';
            return true;
        }

        // erase all inside a comment block
        if ($passAllBlocksTo === 'comment' && $tagData['level'] > 0)
        {
            $part = '';
            return true;
        }
    }

    /**
     * {function} instruction, handles also strings and it's modificators
     *
     * Examples:
     * {function="test()"}
     * {test()}
     * {test()|trim}
     * {"TEST"|strtolower}
     *
     * @param array $tagData
     * @param string $part
     * @param string $tag
     *
     * @author Damian Kęska <damian.keska@fingo.pl>
     * @return null|void
     */
    protected function functionBlockParser(&$tagData, &$part, &$tag)
    {
        $isDirectFunction = is_callable(substr($part, 1, strpos($part, '(') - 1));
        $isString = in_array(substr($part, 1, 1), array("'", '"'));

        if (substr(strtolower($part), 0, 9) !== '{function' && !$isDirectFunction && !$isString)
            return false;

        if ($isDirectFunction || $isString)
        {
            $function = substr($part, 1, strlen($part) - 2);

        } else {
            $count = 2;

            if (substr($part, -2) !== '"}' && substr($part, -2) !== "'}")
                $count = 1;

            // get function
            $function = str_replace(')"|', ')|', substr($part, 11, ((strlen($part) - 11) - $count)));
        }

        // check black list
        $this->blackList(substr($function, 0, strpos(str_replace(' ', '', $function), '(')));

        // function
        $part = "<?php echo ".$this->parseModifiers($function). ";?>";
    }

    public function includeBlockParser(&$tagData, &$part, &$tag, $templateFilePath, $blockIndex, $blockPositions, $code)
    {
        $lowerPart = strtolower($part);

        if(substr($lowerPart, 0, 8) !== '{include')
            return false;

        $tagBody = substr($part, 8, strlen($part) - 9);

        // {include="/path/to/template/file"}
        if (substr($tagBody, 0, 1) == '=')
            $includeTemplate = trim(substr($tagBody, 1, strlen($tagBody) - 1), '"' . "'");
        else {
            $args = $this->parseTagArguments($tagBody);

            // smarty-like {include file="/path/to/template/file"}
            if (isset($args['file']))
                $includeTemplate = $args['file'];
            elseif (isset($args['path']))
                $includeTemplate = $args['path'];
            else {
                $context = $this->findLine($blockIndex, $blockPositions, $code);
                throw new SyntaxException('Cannot find path attribute for {include} tag, expecting "file" or "path" attribute. Example: {include file="/path/to/file"}', 4, null, $context['line'], $templateFilePath);
            }
        }

        // resolved path
        $path = '';

        // select in all include paths
        if (isset($this->config['tpl_dir']))
        {
            $tplDir = $this->config['tpl_dir'];

            if (!is_array($tplDir) || is_string($tplDir))
                $tplDir = array($tplDir);

            // include current directory
            $tplDir[] = dirname($templateFilePath);

            foreach ($tplDir as $dir)
            {
                if (is_file($dir . '/' .$includeTemplate))
                    $path = $dir . '/' .$includeTemplate;
                elseif (is_file($dir . '/' .$includeTemplate. '.tpl'))
                    $path = $dir . '/' .$includeTemplate. '.tpl';

                if ($path) break;
            }

            // if its a path string OR a $variable OR a constant
            if ($path)
            {
                $part = '<?php require $this->checkTemplate("' . $path . '");?>';
                return true;

            } elseif (substr($includeTemplate, 0, 1) == '$' || defined($includeTemplate)) {

                $part = '<?php require $this->checkTemplate(' . $path . ');?>';
                return true;
            }

            $context = $this->findLine($blockIndex, $blockPositions, $code);
            throw new NotFoundException('Cannot find template "' . $includeTemplate . '" that was tried to be included from ' .$templateFilePath. ' at line ' .$context['line']. ', offset ' .$context['offset']);
        }

        return false;
    }

    /**
     * {loop} and {foreach}
     *
     * Usage examples:
     * {loop="$fromVariable" as $key => $value}
     * {loop="$fromVariable"}
     * {foreach="$fromVariable" as $key => $value}
     * {foreach="$fromVariable"}
     * {foreach from="$fromVariable" item="i" key="k"}
     *
     * @param $tagData
     * @param $part
     * @param $tag
     *
     * @throws SyntaxException
     * @author Damian Kęska <damian.keska@fingo.pl>
     * @return null
     */
    protected function loopBlockParser(&$tagData, &$part, &$tag)
    {
        $lowerPart = strtolower($part);

        $ending = $this->parseTagEnding($lowerPart, array(
            'loop', 'foreach',
        ));

        /**
         * Previous it was loop_close
         *
         * @keywords loop_close, {/loop}, {/foreach}
         */
        if ($ending === true)
        {
            $tagData['level']--;
            $part = '<?php }?>';
            return true;
        }

        // validate if its a {loop} or {foreach} tag
        if (substr($part, 0, 5) != '{loop' && substr($part, 0, 8) != '{foreach')
        {
            return false;
        }

        $arguments = $this->parseTagArguments($part);
        $var = null;

        // "from"
        if (isset($arguments['from']))
            $var = $arguments['from'];
        elseif (isset($arguments['foreach']))
            $var = $arguments['foreach'];
        else
            $var = $arguments['loop'];

        if (!$var)
            throw new SyntaxException("Syntax error in foreach/loop, there is no array given to iterate on. Code: ".$part);

        // increase the loop counter
        $tagData['count']++;
        $tagData['level']++;

        //replace the variable in the loop
        //$var = $this->varReplace($var, $tagData['level'] - 1, false);

        // prefix, example: $value1, $value2 etc. by default shoud be just $value
        $valuesPrefix = '';

        if ($tagData['level'] > 0)
            $valuesPrefix = $tagData['level'];

        // check variable black list
        $this->blackList($var);

        if (preg_match('#\(#', $var))
        {
            $newvar = "\$newvar{$valuesPrefix}";
            $assignNewVar = "$newvar=$var;";
        } else {
            $newvar = $var;
            $assignNewVar = null;
        }

        // replace array modificators eg. $array.test to $array["test"]
        $newvar = $this->varReplace($newvar, null, false, false, false);

        if ($assignNewVar)
            $assignNewVar = $this->varReplace($newvar, null, false, false, false);

        // loop variables
        $counter = "\$counter".$valuesPrefix;

        // key
        if (isset($arguments['key']))
            $key = "\$".$arguments['key'];
        else
            $key = "\$key".$valuesPrefix;

        if (isset($arguments['value']))
            $value = "\$".$arguments['value'];
        elseif (isset($arguments['item']))
            $value = "\$".$arguments['item'];
        else
            $value = "\$value".$valuesPrefix;

        // result code passed by reference
        $part = "<?php $counter=-1; $assignNewVar if(isset($newvar)&&(is_array($newvar)||$newvar instanceof Traversable)&& sizeof($newvar))foreach($newvar as ".$key." => ".$value."){ $counter++; ?>";
    }

    /**
     * {break} instruction, could be used only inside of {foreach} or {loop} blocks
     *
     * @param $tagData
     * @param $part
     * @param $tag
     *
     * @throws SyntaxException
     * @author Damian Kęska <damian.keska@fingo.pl>
     * @return null|void
     */
    protected function loop_breakBlockParser(&$tagData, &$part, &$tag)
    {
        $p = strtolower($part);

        if ($p == '{break}')
        {
            if ((isset($this->tagData['foreach']['level']) || $this->tagData['foreach']['level'] < 1) && (isset($this->tagData['loop']['level']) || $this->tagData['loop']['level'] < 1))
                throw new SyntaxException('Trying to use {break} outside of a loop');

            $part = '<?php break;?>';
        }
    }

    /**
     * {continue} instruction, could be used only inside of {foreach} or {loop} blocks
     *
     * @param $tagData
     * @param $part
     * @param $tag
     * @throws SyntaxException
     * @author Damian Kęska <damian.keska@fingo.pl>
     * @return null|void
     */
    protected function loop_continueBlockParser(&$tagData, &$part, &$tag)
    {
        $p = strtolower($part);

        if ($p == '{continue}')
        {
            if ((isset($this->tagData['foreach']['level']) || $this->tagData['foreach']['level'] < 1) && (isset($this->tagData['loop']['level']) || $this->tagData['loop']['level'] < 1))
                throw new SyntaxException('Trying to use {continue} outside of a loop');

            $part = '<?php continue;?>';
        }
    }

    protected function conReplace($html) {
        $html = $this->modifierReplace($html);
        return $html;
    }

    protected function modifierReplace($html)
    {
        $this->blackList($html);

        if (strpos($html,'|') !== false && substr($html,strpos($html,'|')+1,1) != "|")
        {
            preg_match('/([\$a-z_A-Z0-9\(\),\[\]"->]+)\|([\$a-z_A-Z0-9\(\):,\[\]"->]+)/i', $html,$result);

            $function_params = $result[1];
            $result[2] = str_replace("::", "@double_dot@", $result[2] );
            $explode = explode(":",$result[2]);
            $function = str_replace('@double_dot@', '::', $explode[0]);
            $params = isset($explode[1]) ? "," . $explode[1] : null;

            $html = str_replace($result[0],$function . "(" . $function_params . "$params)",$html);

            if (strpos($html,'|') !== false && substr($html,strpos($html,'|')+1,1) != "|")
            {
                $html = $this->modifierReplace($html);
            }
        }

        return $html;
    }

    protected function blackList($html) {

        if (!static::$conf['sandbox'] || !static::$black_list)
            return true;

        if (empty(static::$conf['black_list_preg']))
            static::$conf['black_list_preg'] = '#[\W\s]*' . implode('[\W\s]*|[\W\s]*', static::$black_list) . '[\W\s]*#';

        // check if the function is in the black list (or not in white list)
        if (preg_match(static::$conf['black_list_preg'], $html, $match)) {

            // find the line of the error
            $line = 0;
            $rows = explode("\n", $this->templateInfo['code']);
            while (!strpos($rows[$line], $html) && $line + 1 < count($rows))
                $line++;

            // stop the execution of the script
            $e = new SyntaxException('Syntax ' . $match[0] . ' not allowed in template: ' . $this->templateInfo['template_filepath'] . ' at line ' . $line);
            throw $e->templateFile($this->templateInfo['template_filepath'])
                ->tag($match[0])
                ->templateLine($line);

            return false;
        }
    }

    public static function reducePath($path)
    {
        // reduce the path
        $path = str_replace("://", "@not_replace@", $path);
        $path = preg_replace("#(/+)#", "/", $path);
        $path = preg_replace("#(/\./+)#", "/", $path);
        $path = str_replace("@not_replace@", "://", $path);

        while (preg_match( '#\.\./#', $path))
        {
            $path = preg_replace('#\w+/\.\./#', '', $path );
        }

        return $path;
    }

    /**
     * Parse modifiers on a string or variable, function
     *
     * @param string $var Variable/string/function input string
     *
     * @author Damian Kęska <damian.keska@fingo.pl>
     * @return string Output
     */
    protected function parseModifiers($var, $useVarReplace = false)
    {
        $functions = explode('|', $var);
        $result = $functions[0];

        if ($useVarReplace === true)
            $result = $functions[0] = $this->varReplace($result, $this->tagData['loop']['level'], true, false, false);

        foreach ($functions as $function)
        {
            if ($function === $result)
                continue;

            // security
            $this->blackList($function);

            // arguments
            $args = explode(':', str_replace('::', '\@;;', $function));

            // our result string
            $result = $args[0]. '(' .$result;

            foreach ($args as $i => $arg)
            {
                if ($i === 0)
                    continue;

                $result .= ', ' .str_replace('\@;;', '::', $arg);
            }

            $result .= ')';
        }

        return $result;
    }
}
