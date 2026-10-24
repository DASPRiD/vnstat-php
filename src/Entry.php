<?php
namespace Vnstat;

use DateTime;

class Entry
{
    /**
     * @var int
     */
    protected $bytesReceived;

    /**
     * @var int
     */
    protected $bytesSent;

    /**
     * @var bool
     */
    protected $filled;

    /**
     * @var DateTime|null
     */
    protected $dateTime;

    /**
     * @param int           $received
     * @param int           $sent
     * @param bool          $filled
     * @param DateTime|null $dateTime
     */
    public function __construct($received, $sent, $filled, DateTime $dateTime = null)
    {
        $this->bytesReceived = $received;
        $this->bytesSent     = $sent;
        $this->filled        = $filled;
        $this->dateTime      = $dateTime;
    }

    /**
     * @return int
     */
    public function getBytesReceived()
    {
        return $this->bytesReceived;
    }

    /**
     * @return int
     */
    public function getBytesSent()
    {
        return $this->bytesSent;
    }

    /**
     * @return bool
     */
    public function isFilled()
    {
        return $this->filled;
    }

    /**
     * @return DateTime|null
     */
    public function getDateTime()
    {
        return $this->dateTime;
    }
}
