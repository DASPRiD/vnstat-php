<?php
namespace Vnstat;

use Cassandra\Date;
use DateTime;
use DateTimeZone;
use Exception;

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
     * @throws Exception
     */
    public function __construct(string $json)
    {
        $data = json_decode($json, true);

        if (!is_array($data) || !array_key_exists('jsonversion', $data)) {
            $received = trim($json);

            if (strlen($received) > 500) {
                $received = substr($received, 0, 500) . '...';
            }

            throw new Exception(sprintf("Expected vnStat JSON, got:\n\n%s", $received));
        }

        $jsonVersion = $data['jsonversion'];

        if (!in_array($jsonVersion, [1, 2])) {
            throw new Exception('Unknown JSON version');
        }

        if (!array_key_exists('interfaces', $data) || [] === $data['interfaces']) {
            throw new Exception('The vnStat database contains no interfaces.');
        }

        $this->version = $data['vnstatversion'];
        $interface = $data['interfaces'][0];
        $this->createdAt = $this->parseDate($interface['created'], $jsonVersion);
        $this->updatedAt = $this->parseDate($interface['updated'], $jsonVersion);
        $unitMultiplier = 1;
        $daysCategory = 'day';
        $monthsCategory = 'month';
        $hoursCategory = 'hour';
        $topsCategory = 'top';

        switch ($jsonVersion) {
            case 1:
                $unitMultiplier = 1024;
                $this->interface = $interface['id'];
                $this->nick = $interface['nick'];
                $daysCategory = 'days';
                $monthsCategory = 'months';
                $hoursCategory = 'hours';
                $topsCategory = 'tops';
                break;

            case 2:
                $this->interface = $interface['name'];
                $this->nick = $interface['alias'];
                break;
        }

        $this->totalBytesReceived = $interface['traffic']['total']['rx'] * $unitMultiplier;
        $this->totalBytesSent = $interface['traffic']['total']['tx'] * $unitMultiplier;

        foreach ($interface['traffic'][$daysCategory] as $day) {
            $this->days[$day['id']] = new Entry(
                $day['rx'] * $unitMultiplier,
                $day['tx'] * $unitMultiplier,
                true,
                $this->parseDate($day, $jsonVersion)
            );
        }

        foreach ($interface['traffic'][$monthsCategory] as $month) {
            $this->months[$month['id']] = new Entry(
                $month['rx'] * $unitMultiplier,
                $month['tx'] * $unitMultiplier,
                true,
                $this->parseDate($month, $jsonVersion)
            );
        }

        foreach ($interface['traffic'][$topsCategory] as $top) {
            $this->top10[$top['id']] = new Entry(
                $top['rx'] * $unitMultiplier,
                $top['tx'] * $unitMultiplier,
                true,
                $this->parseDate($top, $jsonVersion)
            );
        }

        foreach ($interface['traffic'][$hoursCategory] as $hour) {
            $this->hours[$hour['id']] = new Entry(
                $hour['rx'] * $unitMultiplier,
                $hour['tx'] * $unitMultiplier,
                true,
                $this->parseDate($hour, $jsonVersion)
            );
        }

        for ($i = 0; $i <= 23; ++$i) {
            if (!array_key_exists($i, $this->hours)) {
                $this->hours[$i] = new Entry(0, 0, false, new DateTime());
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

    private function parseDate(array $data, int $jsonVersion) {
        $result = new DateTime();
        $result->setDate(
            $data['date']['year'],
            $data['date']['month'],
            array_key_exists('day', $data['date']) ? $data['date']['day'] : 1
        );

        if (array_key_exists('time', $data)) {
            $result->setTime(
                $data['time']['hour'],
                $data['time'][$jsonVersion === 1 ? 'minutes' : 'minute'],
                0
            );
        } else {
            $result->setTime(0, 0, 0);
        }

        return $result;
    }
}
