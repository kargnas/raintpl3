<?php
class Issue167Test extends RainTPLTestCase
{
    public function testIssue167()
    {
        $this->setupRainTPL4();

        $this->engine->assign('timestamp', strtotime('2015-03-02 10:30'));
        $this->assertEquals('2015-03-02 - 02-03-2015', $this->engine->drawString("2015-03-02 - {'d-m-Y'|date:\$timestamp}", true));

        $this->engine->assign('timestamp', strtotime('2015-03-03 10:30'));
        $this->assertEquals('2015-03-03 - 03-03-2015', $this->engine->drawString("2015-03-03 - {\"d-m-Y\"|date:\"\$timestamp\"}", true));

        $this->engine->assign('timestamp', strtotime('2015-03-04 10:30'));
        $this->assertEquals('2015-03-04 - 04-03-2015', $this->engine->drawString("2015-03-04 - {'d-m-Y'|date:\"\$timestamp\"}", true));
    }
}