<?php
/**
 * {function} tag test
 *
 * @author Damian Kęska <damian@pantheraframework.org>
 */
class FunctionTest extends RainTPLTestCase
{
    /**
     * {function="Date( 'c', $value.Time )"}">{$value.Time|FullDate}
     *
     * @author Damian Kęska <damian@pantheraframework.org>
     */
    public function testFunctionVarReplace()
    {
        $this->setupRainTPL4();
        $this->engine->assign('value', array(
            'Time' => strtotime('2015-05-05 10:00'),
        ));
        //$this->engine->setConfigurationKey('print_parsed_code', true);
        $this->assertEquals('', $this->engine->drawString('{function="Date( \'c\', $value.Time )"}">{$value.Time|FullDate}', true));
    }
}

function FullDate($input)
{
    return date('Y-m-d H:i', $input);
}