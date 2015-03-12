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
        $result = $tpl->drawString("{\"d-m-Y\"|date:\"\$timestamp\"}", true);
        $this->assertEquals('02-03-2015', $result);
    }
}