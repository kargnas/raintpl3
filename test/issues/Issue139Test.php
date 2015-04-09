<?php
/**
 * Testcase for issue #139
 *
 * @link https://github.com/rainphp/raintpl3/issues/139
 * @author Damian Kęska <damian.keska@fingo.pl>
 */
class Issue139Test extends RainTPLTestCase
{
    /**
     * Testcase for issue #139
     *
     * @link https://github.com/rainphp/raintpl3/issues/139
     * @author Damian Kęska <damian.keska@fingo.pl>
     */
    public function testIssue139()
    {
        $this->setupRainTPL4();
        $this->engine->assign('array', array(
            1, 2, 3,
        ));
        $this->assertEquals('4', $this->engine->drawString('{function="(count($array) + 1)"}', true));
        $this->assertEquals('4', $this->engine->drawString('{function="(count($array)+1)"}', true));
        $this->assertEquals('4', $this->engine->drawString('{function="(1 + count($array))"}', true));
        $this->assertEquals('4', $this->engine->drawString('{count($array) + 1}', true));
        $this->assertEquals('4', $this->engine->drawString('{count($array)+1}', true));
        $this->assertEquals('4', $this->engine->drawString('{$variable=(count($array)+1)}{$variable}', true));
    }
}