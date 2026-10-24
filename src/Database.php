<?php
namespace Vnstat;

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

        if (!is_scalar($jsonVersion) || '2' !== (string) $jsonVersion) {
            throw new Exception(sprintf(
                'Unsupported vnStat JSON schema version %s. Install vnStat 2.0 or newer.',
                is_scalar($jsonVersion) ? $jsonVersion : get_debug_type($jsonVersion)
            ));
        }

        if (!array_key_exists('interfaces', $data) || [] === $data['interfaces']) {
            throw new Exception('The vnStat database contains no interfaces.');
        }

        $interface = $data['interfaces'][0];
        $this->version = $data['vnstatversion'];
        $this->interface = $interface['name'];
        $this->nick = $interface['alias'];
        $this->createdAt = $this->parseDate($interface['created']);
        $this->updatedAt = $this->parseDate($interface['updated']);
        $this->totalBytesReceived = $interface['traffic']['total']['rx'];
        $this->totalBytesSent = $interface['traffic']['total']['tx'];

        foreach ($interface['traffic']['day'] as $day) {
            $this->days[] = new Entry($day['rx'], $day['tx'], true, $this->parseDate($day));
        }

        foreach ($interface['traffic']['month'] as $month) {
            $this->months[] = new Entry($month['rx'], $month['tx'], true, $this->parseDate($month));
        }

        foreach ($interface['traffic']['top'] as $top) {
            $this->top10[] = new Entry($top['rx'], $top['tx'], true, $this->parseDate($top));
        }

        foreach ($interface['traffic']['hour'] as $hour) {
            $this->hours[$hour['id']] = new Entry($hour['rx'], $hour['tx'], true, $this->parseDate($hour));
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

    private function parseDate(array $data): DateTime
    {
        $result = new DateTime();
        $result->setDate(
            $data['date']['year'],
            $data['date']['month'],
            array_key_exists('day', $data['date']) ? $data['date']['day'] : 1
        );

        if (array_key_exists('time', $data)) {
            $result->setTime($data['time']['hour'], $data['time']['minute'], 0);
        } else {
            $result->setTime(0, 0, 0);
        }

        return $result;
    }
}
