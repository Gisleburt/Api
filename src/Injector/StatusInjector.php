<?php
/**
 * StatusInjector.php
 * @author    Daniel Mason <daniel@danielmason.com>
 * @copyright (c) 2016 Daniel Mason <daniel@danielmason.com>
 * @license   GPL 3
 * @see       https://github.com/AyeAyeApi/Api
 */

namespace AyeAye\Api\Injector;

use AyeAye\Api\Status;

/**
 * Trait StatusInjector
 * Allows the injection and management of a Status object. Provides a default if one isn't set.
 * Note: The default status is "200 OK". If an error occurred the status must be updated.
 * @package AyeAye/Api
 * @see     https://github.com/AyeAyeApi/Api
 */
trait StatusInjector
{
    /**
     * @var Status
     */
    private $status;

    /**
     * @return Status
     */
    public function getStatus()
    {
        if (!$this->status) {
            $this->status = new Status();
        }
        return $this->status;
    }

    /**
     * @param Status $status
     * @return $this
     */
    public function setStatus(Status $status)
    {
        $this->status = $status;
        return $this;
    }
}
