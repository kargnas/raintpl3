<?php
/**
 * Test case for issue #165
 *
 * @link https://github.com/rainphp/raintpl3/issues/165
 * @author Damian Kęska <damian@pantheraframework.org>
 */
class Issue165Test extends PHPUnit_Framework_TestCase
{
    /**
     * Test case for issue #165
     *
     * @link https://github.com/rainphp/raintpl3/issues/165
     * @author Damian Kęska <damian@pantheraframework.org>
     */
    public function testIssue165()
    {
        Rain\Tpl::configure(array(
            'debug' => true,
            'tpl_dir' => '/tmp/',
            'cache_dir' => '/tmp/',
        ));

        $tpl = new \Rain\Tpl();
        $this->assertEquals('//cdn.my-project.com/assets/js/jquery.js', $tpl->drawString("{\$requirejs_main='//cdn.my-project.com/assets/js/jquery.js'}{\$requirejs_main}", true));
        $this->assertEquals('//cdn.my-project.com/assets/js/jquery.js', $tpl->drawString('{$requirejs_main="//cdn.my-project.com/assets/js/jquery.js"}{$requirejs_main}', true));
        $this->assertEquals('http://www.example.com', $tpl->drawString('{$url= \'http://www.example.com\'}{$url}', true));
        $this->assertEquals('http://www.example.com', $tpl->drawString('{$url=\'http://www.example.com\'}{$url}', true));
    }
}