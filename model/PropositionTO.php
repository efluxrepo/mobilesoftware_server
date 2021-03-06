<?php
/**
 * Created by PhpStorm.
 * User: David Campos R.
 * Date: 14/01/2017
 * Time: 15:47
 */

namespace model;


class PropositionTO
{
    private $time;
    private $coordinates;
    private $placeName;
    private $reasonName;
    private $reasonDescription;
    private $proposer;
    private $appointment;

    /**
     * PropositionTO constructor.
     * @param int $appointment
     * @param int $time
     * @param float $coordLat
     * @param float $coordLon
     * @param string $placeName
     * @param string $reasonName
     * @param string|null $reasonDescription
     * @param int $proposer
     * @internal param $coordinates
     */
    public function __construct(int $appointment, int $time, float $coordLat, float $coordLon, string $placeName, $reasonName,
                                $reasonDescription, int $proposer) {
        $this->time = $time;
        $this->coordinates = array('lat' => $coordLat, 'lon' => $coordLon);
        $this->placeName = $placeName;
        $this->reasonName = $reasonName;
        $this->reasonDescription = $reasonDescription;
        $this->proposer = $proposer;
        $this->appointment = $appointment;
    }

    public function toAssociativeArray(): array {
        $userTO = DAOFactory::getInstance()->obtainUsersDAO()->obtainUserTOById($this->getProposer());
        return array(
            "time" => $this->getTimestamp(),
            "coordinates" => $this->getCoordinates(),
            "placeName" => $this->getPlaceName(),
            "reasonName" => $this->getReasonName(),
            "reasonDescription" => $this->getReasonDescription(),
            "proposer" => $userTO->toAssociativeArray(false),
            "appointment" => $this->getAppointmentId()
        );
    }

    public function getTimestamp(): int {
        return $this->time;
    }

    public function getCoordinates(): array {
        return $this->coordinates;
    }

    public function getPlaceName(): string {
        return $this->placeName;
    }

    /**
     * @return string|null
     */
    public function getReasonName() {
        return $this->reasonName;
    }

    public function getReasonDescription() {
        return $this->reasonDescription;
    }

    public function getProposer(): int {
        return $this->proposer;
    }

    public function getAppointmentId(): int {
        return $this->appointment;
    }
}