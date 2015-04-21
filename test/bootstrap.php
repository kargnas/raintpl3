<?php

    // define the base directory
    define( "BASE_DIR", dirname(__DIR__) );

    // set the include path
    set_include_path(
            BASE_DIR . DIRECTORY_SEPARATOR . 'library'
            . PATH_SEPARATOR . get_include_path()
    );

    // require Rain autoload
    require_once "Rain/autoload.php";

    class RainTPLTestCase extends PHPUnit_Framework_TestCase
    {
        /**
         * @var RainTPL4|null
         */
        public $engine = null;

        public function setup()
        {
            Rain\Tpl::configure(array(
                'debug' => true,
                'tpl_dir' => '/tmp/',
                'cache_dir' => '/tmp/',
            ));

            $this->engine = new \Rain\Tpl();
        }

        public function setupRainTPL4()
        {
            $this->engine = new \Rain\RainTPL4;
            $this->engine->setConfiguration(array(
                'debug' => true,
                'tpl_dir' => realpath(__DIR__. '/../templates'),
                'cache_dir' => '/tmp/',
            ));
        }

        /**
         * Cut off code that is placed between two strings
         *
         * @param int $starting eg. <code>
         * @param int $ending eg. </code>
         * @param int $i Callstack number
         *
         * @author Damian Kęska <damian.keska@fingo.pl>
         * @return string
         */
        protected function takeCodeBetweenTags($starting, $ending, $i = 2)
        {
            $callStack = debug_backtrace();
            $reflection = new ReflectionMethod($callStack[$i]['class'], $callStack[$i]['function']);
            $phpDoc = $reflection->getDocComment();

            $pos = stripos($phpDoc, $starting);

            if ($pos !== false)
            {
                $endingPos = stripos($phpDoc, $ending, $pos);

                // get text inside of <code></code>
                $body = trim(substr($phpDoc, ($pos + strlen($starting)), ($endingPos - $pos - strlen($starting))));

                // split into lines to strip whitespaces and "*"
                $lines = explode("\n", $body);

                foreach ($lines as &$line)
                {
                    $pos = strpos($line, '*');

                    if ($pos !== false)
                        $line = substr($line, ($pos + 1), strlen($line));
                }

                return trim(implode("\n", $lines));
            }

            return '';
        }

        /**
         * Get test case code from <code></code> tags
         * This is very useful function for keeping well formatted test case in PHPDoc comment that will be loaded into testing code
         *
         * @author Damian Kęska <damian.keska@fingo.pl>
         * @return string
         */
        public function getTestCodeFromPHPDoc()
        {
            return $this->takeCodeBetweenTags('<code>', '</code>', 2);
        }

        public function getExpectationsFromPHPDoc()
        {
            return $this->takeCodeBetweenTags('<expects>', '</expects>', 2);
        }
    }