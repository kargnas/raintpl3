<?php
class Issue167Test extends PHPUnit_Framework_TestCase
{
    public function testIssue167()
    {
        Rain\Tpl::configure(array(
            'debug' => true,
            'tpl_dir' => '/tmp/',
            'cache_dir' => '/tmp/',
        ));

        $tpl = new \Rain\Tpl();
        $tpl->assign('timestamp', strtotime('2015-03-02 10:30'));
        $this->assertEquals('2015-03-02 - 02-03-2015', $tpl->drawString("2015-03-02 - {'d-m-Y'|date:\$timestamp}", true));

        $tpl->assign('timestamp', strtotime('2015-03-03 10:30'));
        $this->assertEquals('2015-03-03 - 03-03-2015', $tpl->drawString("2015-03-03 - {\"d-m-Y\"|date:\"\$timestamp\"}", true));

        $tpl->assign('timestamp', strtotime('2015-03-04 10:30'));
        $this->assertEquals('2015-03-04 - 04-03-2015', $tpl->drawString("2015-03-04 - {'d-m-Y'|date:\"\$timestamp\"}", true));
    }
}