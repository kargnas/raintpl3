<?php
namespace Rain\Tpl;

/**
 * RainTPL4 Events Handler
 *
 * @package Rain\Modules
 * @author Damian Kęska <damian@pantheraframework.org>
 */
trait RainTPLEventsHandler
{
    public $events = array(

    );

    protected $__eventsSortingCache = array();

    /**
     * Connect a function callback
     *
     * @param string $eventName Event to connect to
     * @param callable $callable Callable function
     * @param null $priority (Optional) Priority
     *
     * @author Damian Kęska <damian@pantheraframework.org>
     * @return bool
     */
    public function connectEvent($eventName, $callable, $priority = null)
    {
        if (!isset($this->events[$eventName]))
            $this->events[$eventName] = array();

        if (!is_callable($callable))
            return false;

        if ($priority && is_int($priority) && !isset($events[$eventName][$priority]))
            $this->events[$eventName][$priority] = $callable;
        else
            $this->events[$eventName][] = $callable;

        if (isset($this->__eventsSortingCache[$eventName]))
            unset($this->__eventsSortingCache[$eventName]);

        return true;
    }

    /**
     * Execute all listeners on selected event
     *
     * @param string $eventName Event name to execute actions for
     * @param mixed $data (Optional) Input data
     *
     * @author Damian Kęska <damian@pantheraframework.org>
     * @return mixed
     */
    public function executeEvent($eventName, $data = null)
    {
        if (!isset($this->events[$eventName]) || !$this->events[$eventName])
        {
            return $data;
        }

        // sort descending
        if (!isset($this->__eventsSortingCache[$eventName]))
        {
            ksort($this->events[$eventName]);
            $this->events[$eventName] = array_reverse($this->events[$eventName]);
            $this->__eventsSortingCache[$eventName] = true;
        }

        foreach ($this->events[$eventName] as $priority => $eventHandlerCallableFunction)
        {
            $callbackData = $eventHandlerCallableFunction($data);

            if (!is_null($callbackData))
                $data = $callbackData;
        }

        return $data;
    }
}