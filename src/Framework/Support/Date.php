<?php

namespace Framework\Support;

use DateTime;
use DateInterval;

class Date
{
    /**
     *
     * @var \DateTime
     */
    protected $_dateTime;
    
    /**
     * Create the new instance of Date in DateTime.
     *
     * @return void
     */
    public function __construct()
    {
        $this->_dateTime = new DateTime();
    }

    /**
     * Return the new Date instance.
     *
     * @return static
     */
    public static function now()
    {
        return new static();
    }

    /**
     * Create new DateTime instance
     *
     * @return \DateTime
     */
    public function createFromBase()
    {
        return $this->_dateTime = new DateTime();
    }

    /**
     * Sub the minutes from the time.
     *
     * @param  int $minutes
     * @return $this
     */
    public function subMinutes($minutes)
    {
        $this->_dateTime->sub(new DateInterval("PT{$minutes}M"));

        return $this;
    }

    /**
     * Add the minutes from the time.
     *
     * @param  int $minutes
     * @return $this
     */
    public function addMinutes($minutes)
    {
        $this->_dateTime->add(new DateInterval("PT{$minutes}M"));

        return $this;
    }
    
    /**
     * Return the Unix timestamp.
     *
     * @return int
     */
    public function getTimestamp()
    {
        return $this->_dateTime->getTimestamp();
    }
    
    /**
     * Return the base DateTime instance.
     *
     * @return \DateTime|null
     */
    public function toDateTime()
    {
        return $this->_dateTime;
    }
}
