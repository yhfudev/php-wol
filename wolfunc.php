<?php
/**
 * Wake on Lan functions
 *
 * @copyright  Copyright (c) 2010-2015 Yunhui Fu <yhfudev@gmail.com>
 * @license    GNU GPL 2.0 and later
 */

// wol("192.168.0.255", "00:0e:0c:38:0e:68", 7);
function wol($broadcast, $mac, $socket_number)
{
    $mac = str_replace("-", ":", $mac);
    $mac_array = split(':', $mac);

    $hwaddr = '';

    foreach($mac_array AS $octet)
    {
        $hwaddr .= chr(hexdec($octet));
    }

    // Create Magic Packet
    $packet = '';
    for ($i = 1; $i <= 6; $i++)
    {
        $packet .= chr(255);
    }

    for ($i = 1; $i <= 16; $i++)
    {
        $packet .= $hwaddr;
    }

    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if ($sock)
    {
        $options = socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, TRUE); // socket_set_option($sock, 1, 6, true);

        if ($options >=0)
        {    
            $e = socket_sendto($sock, $packet, strlen($packet), 0, $broadcast, $socket_number);
        }
        socket_close($sock);
    }
}

function wol2($broadcast, $mac, $socket_number)
{
    $last_line = system("wol -v -p $socket_number -h $broadcast $mac", $retval);
    //exec("wakeonlan $mac");
    //echo "Last line of the output:<pre>$last_line<pre><hr />";
    //echo "Return value: " . $retval;
}

/**
    * Validate MAC
    * @param string $address
    * @return bool 
    */
function isPhysicalAddress($address)
{
    return preg_match('/^([A-Fa-f0-9]{2}[:\-]?){6}$/',$address);
}

/**
    * Check valid ip address
    * @param string $ip
    * @return bool
    */
function isIp4Adress($ip_addr)
{
    //first of all the format of the ip address is matched
    if(preg_match ("/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/",$ip_addr))
    {
        //now all the intger values are separated
        $parts = explode(".",$ip_addr);
        //now we need to check each part can range from 0-255
        foreach ($parts as $ip_parts)
        {
            if(intval($ip_parts) > 255 || intval($ip_parts) < 0) {
                return false; //if number is not within range of 0-255
            }
        }
        return true;
    } else {
        return false; //if format of ip address doesn't matches
    }
}

class wakeonlan
{
    public function __construct()
    {
        $this->mac = "";
        $this->ip = "";
        $this->port = 9;
    }
    public function setMac($mac) {
        if (! isPhysicalAddress($mac)) {
echo "<p>DEBUG: MAC address '$mac' is not valid</p>";
            throw new Exception(sprintf("MAC address '%s' is not valid", $mac));
        }
        $this->mac = $mac;
    }

    public function setIpv4($ip) {
        if (! isIp4Adress($ip)) {
            throw new Exception(sprintf("IP address '%s' is not valid", $ip));
        }
        $this->ip = $ip;
    }
    public function setPort($port) {
        if(!is_int($port)) {
            throw new Exception(sprintf("Port '%s' is not valid", $port));
        }

        $this->port = $port;
    }
    public function wake () {
        if (! isPhysicalAddress($this->mac)) {
echo "<p>DEBUG: 2 MAC address '$mac' is not valid</p>";
            throw new Exception(sprintf("MAC address '%s' is not valid", $mac));
        }
        if (! isIp4Adress($this->ip)) {
            throw new Exception(sprintf("IP address '%s' is not valid", $ip));
        }
        if(!is_int($this->port)) {
            throw new Exception(sprintf("Port '%s' is not valid", $port));
        }
        wol ($this->ip, $this->mac, $this->port);
    }
}

?>
