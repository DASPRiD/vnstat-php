<?php
namespace Vnstat;

use Cassandra\Date;
use DateTime;
use DateTimeZone;

class Database
{
    /**
     * @var int
     */
    protected $version;

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
        $command = 'vnstat --json';

        if (null !== $interface) {
            $command .= sprintf(' --iface %s', $interface);
        }

        $data = json_decode(shell_exec($command), true);

        $this->version = $data['vnstatversion'];
        $interface = $data['interfaces'][0];
        $this->interface = $interface['id'];
        $this->nick = $interface['nick'];
        $this->createdAt = $this->parseDate($interface['created']);
        $this->updatedAt = $this->parseDate($interface['updated']);

        $this->totalBytesReceived = $interface['traffic']['total']['rx'] * 1024;
        $this->totalBytesSent = $interface['traffic']['total']['tx'] * 1024;

        foreach ($interface['traffic']['days'] as $day) {
            $this->days[$day['id']] = new Entry(
                $day['rx'] * 1024,
                $day['tx'] * 1024,
                true,
                $this->parseDate($day)
            );
        }

        foreach ($interface['traffic']['months'] as $month) {
            $this->months[$month['id']] = new Entry(
                $month['rx'] * 1024,
                $month['tx'] * 1024,
                true,
                $this->parseDate($month)
            );
        }

        foreach ($interface['traffic']['tops'] as $top) {
            $this->top10[$top['id']] = new Entry(
                $top['rx'] * 1024,
                $top['tx'] * 1024,
                true,
                $this->parseDate($top)
            );
        }

        foreach ($interface['traffic']['hours'] as $hour) {
            $this->hours[$hour['id']] = new Entry(
                $hour['rx'] * 1024,
                $hour['tx'] * 1024,
                true,
                $this->parseDate($hour)
            );
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

    private function parseDate(array $data) {
        $result = new DateTime();
        $result->setDate(
            $data['date']['year'],
            $data['date']['month'],
            array_key_exists('day', $data['date']) ? $data['date']['day'] : 1
        );

        if (array_key_exists('time', $data)) {
            $result->setTime(
                $data['time']['hour'],
                $data['time']['minutes'],
                0
            );
        } else {
            $result->setTime(0, 0, 0);
        }

        return $result;
    }
}
