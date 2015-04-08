<?php
/**
 * Test {"string"|modifier} syntax
 *
 * @author Damian Kęska <damian.keska@fingo.pl>
 */
class StringsTest extends RainTPLTestCase
{
    public function testSingleFunctionStrippingWhitespaces()
    {
        $this->setup();
        $this->assertEquals('test', $this->engine->drawString("{' test '|trim}", true));
    }

    public function testSingleFunctionStrippingWhitespacesDoubleQuotes()
    {
        $this->setup();
        $this->assertEquals('test', $this->engine->drawString('{" test "|trim}', true));
    }

    public function singleArgumentsInModifiers()
    {
        $this->setup();
        $this->assertEquals('te', $this->engine->drawString('{"test"|substr:2}', true));
    }

    public function multipleArgumentsInModifiers()
    {
        $this->setup();
        $this->assertEquals('te', $this->engine->drawString("{'test'|substr:0,2}", true));
    }

    public function modifierMultipleArgumentsPlusSecondModifier()
    {
        $this->setup();
        $this->assertEquals('te', $this->engine->drawString('{" test "|substr:0,2|trim}', true));
    }

    public function simpleFunctionTestInPHPWay()
    {
        $this->setup();
        $this->assertEquals('te', $this->engine->drawString('{trim(substr(" test ", 0, 2))}', true));
    }
}