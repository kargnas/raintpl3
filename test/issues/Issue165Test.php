<?php
/**
 * Test case for issue #165
 *
 * @link https://github.com/rainphp/raintpl3/issues/165
 * @author Damian Kęska <damian@pantheraframework.org>
 */
class Issue165Test extends RainTPLTestCase
{
    /**
     * Test case for issue #165
     *
     * @link https://github.com/rainphp/raintpl3/issues/165
     * @author Damian Kęska <damian@pantheraframework.org>
     */
    public function testIssue165()
    {
        $this->setupRainTPL4();
        $this->assertEquals('//cdn.my-project.com/assets/js/jquery.js', $this->engine->drawString("{\$requirejs_main='//cdn.my-project.com/assets/js/jquery.js'}{\$requirejs_main}", true));
        $this->assertEquals('//cdn.my-project.com/assets/js/jquery.js', $this->engine->drawString('{$requirejs_main="//cdn.my-project.com/assets/js/jquery.js"}{$requirejs_main}', true));
        $this->assertEquals('http://www.example.com', $this->engine->drawString('{$url= \'http://www.example.com\'}{$url}', true));
        $this->assertEquals('http://www.example.com', $this->engine->drawString('{$url=\'http://www.example.com\'}{$url}', true));
    }
}