<?php
/**
 * Config file for WoL
 *
 * @copyright  Copyright (c) 2010-2015 Yunhui Fu <yhfudev@gmail.com>
 * @license    GNU GPL 2.0 and later
 */

$ENABLE_SLEEP = false;

$LIST_COMPUTERS = array( array(
        "name" => "home-nas",
        "mac"  => "00:0e:0c:54:12:93",
        "cidr" => "192.168.0.5/25",
        "icon" => "IntelChassisSC5295-E.jpg",
        "wakestatus" => array(
        // time(seconds) => "message"
              0 => "Starting",
              2 => "Loading LSI MegaRAID firmware",
              4 => "BIOS continued",
              6 => "Booting OS",
              8 => "Got IP address",
             10 => "Ready",
        ),
        "sleepstatus" => array(
              0 => "Go to sleep",
              2 => "Disconnected",
              4 => "Release IP",
              6 => "Shutted down",
        ),
    ), array(
        "name" => "home-hpdv4",
        "mac"  => "00:1e:ec:59:19:85",
        "cidr" => "192.168.0.51/25",
        "icon" => "hp-dv4.jpg",
    ) );

?>
