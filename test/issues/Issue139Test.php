<?php
/**
 * Testcase for issue #139
 *
 * @link https://github.com/rainphp/raintpl3/issues/139
 * @author Damian Kęska <damian.keska@fingo.pl>
 */
class Issue139Test extends PHPUnit_Framework_TestCase
{
    /**
     * Testcase for issue #139
     *
     * @link https://github.com/rainphp/raintpl3/issues/139
     * @author Damian Kęska <damian.keska@fingo.pl>
     */
    public function testIssue139()
    {
        Rain\Tpl::configure(array(
            'debug' => true,
            'tpl_dir' => '/tmp/',
            'cache_dir' => '/tmp/',
        ));

        $tpl = new \Rain\Tpl();
        $tpl->assign('array', array(
            1, 2, 3,
        ));

        $this->assertEquals('4', $tpl->drawString('{function="(count($array) + 1)"}', true));
        $this->assertEquals('4', $tpl->drawString('{function="(count($array)+1)"}', true));
        $this->assertEquals('4', $tpl->drawString('{function="(1 + count($array))"}', true));
        $this->assertEquals('4', $tpl->drawString('{count($array) + 1}', true));
        $this->assertEquals('4', $tpl->drawString('{count($array)+1}', true));
        $this->assertEquals('4', $tpl->drawString('{$variable=(count($array)+1)}{$variable}', true));
    }
}