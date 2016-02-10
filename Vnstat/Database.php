<?php
namespace Vnstat;

use DateTime;

class Database
{
    /**
     * @var int
     */
    protected $version;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var string
     */
    protected $interface;

    /**
     * @var string
     */
    protected $nick;

    /**
     * @var DateTime
     */
    protected $createdAt;

    /**
     * @var DateTime
     */
    protected $updatedAt;

    /**
     * @var int
     */
    protected $totalBytesReceived = 0;

    /**
     * @var int
     */
    protected $totalBytesSent = 0;

    /**
     * @var DateTime
     */
    protected $bootedAt;

    /**
     * @var Entry[]
     */
    protected $days = [];

    /**
     * @var Entry[]
     */
    protected $months = [];

    /**
     * @var Entry[]
     */
    protected $top10 = [];

    /**
     * @var Entry[]
     */
    protected $hours = [];

    /**
     * @param string|null $interface
     */
    public function __construct($interface = null)
    {
        $command = 'vnstat --dumpdb';

        if (null !== $interface) {
            $command .= sprintf(' --iface %s', $interface);
        }

        $data = explode("\n", shell_exec($command));

        foreach ($data as $datum) {
            if ($datum === '') {
                continue;
            }

            $values = explode(';', $datum);
            $type   = $values[0];

            switch ($type) {
                case 'version':
                    $this->version = (int) $values[1];
                    break;

                case 'active':
                    $this->active = (bool) $values[1];
                    break;

                case 'interface':
                    $this->interface = $values[1];
                    break;

                case 'nick':
                    $this->nick = $values[1];
                    break;

                case 'created':
                    $this->createdAt = new DateTime('@' . $values[1]);
                    break;

                case 'updated':
                    $this->updatedAt = new DateTime('@' . $values[1]);
                    break;

                case 'totalrx':
                    $this->totalBytesReceived += $values[1] * 1024 * 1024;
                    break;

                case 'totaltx':
                    $this->totalBytesSent += $values[1] * 1024 * 1024;
                    break;

                case 'totalrxk':
                    $this->totalBytesReceived += $values[1] * 1024;
                    break;

                case 'totaltxk':
                    $this->totalBytesSent += $values[1] * 1024;
                    break;

                case 'btime':
                    $this->bootedAt = new DateTime('@' . $values[1]);
                    break;

                case 'd':
                    $this->days[(int) $values[1]] = new Entry(
                        $values[3] * 1024 * 1024 + $values[5] * 1024,
                        $values[4] * 1024 * 1024 + $values[6] * 1024,
                        (bool) $values[7],
                        empty($values[2]) ? null : new DateTime('@' . $values[2])
                    );
                    break;

                case 'm':
                    $this->months[(int) $values[1]] = new Entry(
                        $values[3] * 1024 * 1024 + $values[5] * 1024,
                        $values[4] * 1024 * 1024 + $values[6] * 1024,
                        (bool) $values[7],
                        empty($values[2]) ? null : new DateTime('@' . $values[2])
                    );
                    break;

                case 't':
                    $this->top10[(int) $values[1]] = new Entry(
                        $values[3] * 1024 * 1024 + $values[5] * 1024,
                        $values[4] * 1024 * 1024 + $values[6] * 1024,
                        (bool) $values[7],
                        empty($values[2]) ? null : new DateTime('@' . $values[2])
                    );
                    break;

                case 'h':
                    $this->hours[(int) $values[1]] = new Entry(
                        $values[3] * 1024,
                        $values[4] * 1024,
                        !empty($values[2]),
                        empty($values[2]) ? null : new DateTime('@' . $values[2])
                    );
                    break;
            }
        }
    }

    /**
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * @return string
     */
    public function getInterface()
    {
        return $this->interface;
    }

    /**
     * @return string
     */
    public function getNick()
    {
        return $this->nick;
    }

    /**
     * @return DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @return int
     */
    public function getTotalBytesReceived()
    {
        return $this->totalBytesReceived;
    }

    /**
     * @return int
     */
    public function getTotalBytesSent()
    {
        return $this->totalBytesSent;
    }

    /**
     * @return DateTime
     */
    public function getBootedAt()
    {
        return $this->bootedAt;
    }

    /**
     * @return Entry[]
     */
    public function getDays()
    {
        return $this->days;
    }

    /**
     * @return Entry[]
     */
    public function getMonths()
    {
        return $this->months;
    }

    /**
     * @return Entry[]
     */
    public function getTop10()
    {
        return $this->top10;
    }

    /**
     * @return Entry[]
     */
    public function getHours()
    {
        return $this->hours;
    }
}
