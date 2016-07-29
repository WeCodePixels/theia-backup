<?php

namespace WeCodePixels\TheiaBackupBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\Index;
use WeCodePixels\TheiaBackupBundle\Misc;

/**
 * @ORM\Entity
 * @Table(name="backup_status", indexes={@Index(name="backup_id_idx", columns={"backup_id", "destination", "timestamp"})})
 */
class BackupStatus implements \JsonSerializable
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @ORM\Column(type="text", name="backup_id")`
     */
    private $backupId;

    /**
     * @ORM\Column(type="text")`
     */
    private $destination;

    /**
     * @ORM\Column(type="text")
     */
    private $output;

    /**
     * @ORM\Column(type="datetime")
     */
    private $timestamp;

    public $lastBackupTime;

    public $lastBackupText;

    public $lastBackupAge;

    public $error;

    public function __construct()
    {
        $this->timestamp = new \DateTime();
    }

    public function jsonSerialize()
    {
        return [
            'timestampText' => Misc::getTextForTimestamp($this->timestamp->getTimestamp()),
            'timestampAge' => Misc::getElapsedTime($this->timestamp->getTimestamp()),
            'lastBackupTime' => $this->lastBackupTime,
            'lastBackupText' => $this->lastBackupText,
            'lastBackupAge' => $this->lastBackupAge,
            'error' => $this->error
        ];
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set backupId
     *
     * @param string $backupId
     * @return BackupStatus
     */
    public function setBackupId($backupId)
    {
        $this->backupId = $backupId;

        return $this;
    }

    /**
     * Get backupId
     *
     * @return string
     */
    public function getBackupId()
    {
        return $this->backupId;
    }

    /**
     * Set output
     *
     * @param string $output
     * @return BackupStatus
     */
    public function setOutput($output)
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Get output
     *
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Set timestamp
     *
     * @param \DateTime $timestamp
     * @return BackupStatus
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Get timestamp
     *
     * @return \DateTime
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Set destination
     *
     * @param string $destination
     * @return BackupStatus
     */
    public function setDestination($destination)
    {
        $this->destination = $destination;

        return $this;
    }

    /**
     * Get destination
     *
     * @return string 
     */
    public function getDestination()
    {
        return $this->destination;
    }
}
