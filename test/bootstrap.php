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
    }