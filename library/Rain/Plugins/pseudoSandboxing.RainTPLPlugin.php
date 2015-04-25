<?php
/**
 * Simple sandboxing plugin, uses PHP Parser (Lexer)
 *
 * @package Rain\Plugins
 * @author Damian Kęska <damian@pantheraframework.org>
 */
class pseudoSandboxing extends Rain\Tpl\RainTPL4Plugin
{
    protected $parser = null;

    public function init()
    {
        $libDir = null;

        if (is_dir(__DIR__. '/../../../vendor/PHP-Parser'))
            $libDir = __DIR__. '/../../../vendor/PHP-Parser';
        elseif (is_dir(__DIR__. '../share/PHP-Parser'))
            $libDir = __DIR__. '../share/PHP-Parser';
        elseif ($this->engine->getConfigurationKey('PHP-ParserPath') && is_dir($this->engine->getConfigurationKey('PHP-ParserPath')))
            $libDir = $this->engine->getConfigurationKey('PHP-ParserPath');

        if ($libDir)
        {
            require_once $libDir . '/lib/bootstrap.php';
            require_once __DIR__. '/pseudoSandboxingVisitor.php';
        }

        if (!class_exists('PhpParser\Autoloader'))
        {
            throw new \Rain\Tpl\NotFoundException('pseudoSandboxing plugin turned on, but could not find PHP-Parser library. Please clone a "https://github.com/nikic/PHP-Parser" repository and point to it using "PHP-ParserPath" configuration key in RainTPL', 1);
        }

        $this->engine->connectEvent('parser.compileTemplate.after', array($this, 'afterCompile'));
    }

    /**
     * Execute a code review after compilation
     *
     * @param array $input array($parsedCode, $templateFilepath)
     *
     * @throws \Rain\InvalidConfiguration
     * @throws \Rain\RestrictedException
     *
     * @author Damian Kęska <damian.keska@fingo.pl>
     * @return array
     */
    public function afterCompile($input)
    {
        $collector = new NodeVisitor_pseudoSandboxing;
        $traverser = new PhpParser\NodeTraverser;
        $traverser->addVisitor($collector);

        $this->parser = new PhpParser\Parser(new PhpParser\Lexer\Emulative);
        $stmts = $this->parser->parse($input[0]);
        $stmts = $traverser->traverse($stmts);

        /**
         * Whitelist support
         */
        if ($this->engine->getConfigurationKey('sandboxMode', 'whitelist') == 'whitelist')
        {
            $whitelist = $this->engine->getConfigurationKey('sandboxWhitelist');

            if (!is_array($whitelist))
            {
                throw new Rain\InvalidConfiguration('Missing configuration key "sandboxWhitelist", please set it using setConfigurationKey in RainTPL', 2);
            }

            foreach ($collector->calledFunctions as $functionName => $count)
            {
                if (!in_array($functionName, $whitelist))
                {
                    throw new Rain\RestrictedException('Method "' .$functionName. '" was blocked from use in this template', 1);
                }
            }
        }

        /**
         * Blacklist support
         */
        elseif ($this->engine->getConfigurationKey('sandboxMode') == 'blacklist')
        {
            $blacklist = $this->engine->getConfigurationKey('sandboxBlacklist');

            if (!is_array($blacklist))
                throw new Rain\InvalidConfiguration('Missing configuration key "sandboxBlacklist", please set it using setConfigurationKey in RainTPL', 2);

            foreach ($collector->calledFunctions as $functionName => $count)
            {
                if (in_array($functionName, $blacklist))
                {
                    throw new Rain\RestrictedException('Method "' .$functionName. '" was blocked from use in this template', 1);
                }
            }
        }

        return $input;
    }
}