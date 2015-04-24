<?php
/**
 * pseudoSandboxing RainTPL4 plugin tests
 *
 * @author Damian Kęska <damian@pantheraframework.org>
 */
class pseudoSandboxingTest extends RainTPLTestCase
{
    /**
     * Base test
     *
     * @author Damian Kęska <damian@pantheraframework.org>
     */
    public function testWhitelist()
    {
        $result = '';
        $this->setupRainTPL4();
        $this->engine->setConfigurationKey('pluginsEnabled', array(
            'pseudoSandboxing',
        ));


        try {
            $this->engine->drawString('{shell_exec("ls -la")}', true);
        } catch (Exception $e) {
            $result = get_class($e);
        }

        $this->assertEquals('Rain\RestrictedException', $result);
    }
}