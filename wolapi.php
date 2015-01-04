<?php
/**
 * Wake on Lan JSON API
 *
 * @copyright  Copyright (c) 2010-2015 Yunhui Fu <yhfudev@gmail.com>
 * @license    GNU GPL 2.0 and later
 */

/*
  Error code:
   0 -- no error
   1 -- task error
  -1 -- fatal error, the application should be reset
 */

//require_once 'config.php';
if(file_exists('config.php')) {
    require_once('config.php');
} else {
    $arr = array(
        'success' => "-1",
        'message' => "You need config.php to finish this request.",
        );
    echo json_encode($arr);
    exit();
}

require_once 'netaddr.php';
require_once 'wolfunc.php';

function process_wol($cidr, $mac, $selfip) {
    $paramok = true;
    $addr = new netaddress();

    if ($paramok) {
        $ret = $addr->setValue($cidr);
        if ($ret < 0) {
            $paramok = false;
            $msg .= $addr->getErrMsg ($ret) .": " . $cidr;
        }
    }
    //$paramok = false; $msg="Debug"; // debug
    if (! $paramok) {
        $arr = array(
            'success' => "-1",
            'message' => $msg,
            );
        return ($arr);
    }

    $dq_host = $addr->getHost();

    $cdr_nmask = $addr->getCMask();
    $bin_host = dqtobin($dq_host);
    $bin_bcast = (str_pad(substr($bin_host,0,$cdr_nmask),32,1));
    $bcast_ipv4 = bintodq ($bin_bcast);
    $bin_net=(str_pad(substr($bin_host,0,$cdr_nmask),32,0));

    $wolip = $dq_host;
    if (isInNetwork ($cidr, $selfip)) {
        $wolip = $bcast_ipv4;
    }

    $v = new wakeonlan();
    $v->setMac($mac);
    $v->setIpv4($wolip);
    $v->setPort(9);
    $v->wake();

    $arr = array(
        'success' => "0",
        'message' =>  "OK", //"sent wol to " . $wolip,
        );
    return ($arr);
}

// type: wakestatus or sleepstatus
function process_status ($computerrecord, $type, $requestedtimes, $waitedtime) {

    if ("wakestatus" != $type && "sleepstatus" != $type) {
        $arr = array(
            'success' => "-1",
            'message' => "No such status: " . $type,
            );
        return $arr;
    }
    $arr = array(
        'success' => "1",
        'message' => "No status available.",
        );

    $gotidx = $requestedtimes;
    if (isset($computerrecord[$type])) {
        $keys = array_keys ($computerrecord[$type]);
        if ($requestedtimes >= count($keys)) {
            // binary search?
            for ($i = count($keys); $i > 0; $i --) {
                if ($keys[$i - 1] <= $waitedtime) {
                    break;
                }
            }
            $gotidx = $i - 1;
        }

        $arr = array(
            'success' => "0",
            'message' => $computerrecord[$type][ $keys[$gotidx] ], // . "["."gotidx=".$gotidx.", requestitme=".$requestedtimes.", waitedtime=".$waitedtime."]",
            'elasp'   => $keys[$gotidx],
            'nexttime' => "0",
            'islast'  => "0",
            );
        if ($gotidx + 1 >= count($keys)) {
            $arr['islast'] = "1";
            $arr['nexttime'] = $keys[count($keys) - 1];
        } else {
            $arr['nexttime'] = $keys[$gotidx + 1];
        }
    }

    return ($arr);
}

function process_ping ($cidr) {
    $paramok = true;
    $addr = new netaddress();

    if ($paramok) {
        $ret = $addr->setValue($cidr);
        if ($ret < 0) {
            $paramok = false;
            $msg .= $addr->getErrMsg ($ret) .": " . $cidr;
        }
    }
    if (! $paramok) {
        $arr = array(
            'success' => "-1",
            'message' => $msg,
            );
        return ($arr);
    }

    $dq_host = $addr->getHost();

    $arr = array(
        'success' => "1",
        'message' => "",
        );
    $pinginfo = exec ("ping -c 1 " . $dq_host);
    if ($pinginfo == "") {
        $arr = array(
            'success' => "1",
            'message' => "It's asleep!",
            );
    } else {
        $arr = array(
            'success' => "0",
            'message' => "It's awake.",
            );
    }
    return ($arr);
}

function process_shutdown_irek($cidr) {
    $paramok = true;
    $addr = new netaddress();

    if ($paramok) {
        $ret = $addr->setValue($cidr);
        if ($ret < 0) {
            $paramok = false;
            $msg .= $addr->getErrMsg ($ret) .": " . $cidr;
        }
    }
    if (! $paramok) {
        $arr = array(
            'success' => "-1",
            'message' => $msg,
            );
        return ($arr);
    }

    $dq_host = $addr->getHost();

    $arr = array(
        'success' => "1",
        'message' => "",
        );

    //This is the Port being used by the Windows SleepOnLan Utility to initiate a Sleep State
    //http://www.ireksoftware.com/SleepOnLan/
    $COMPUTER_SLEEP_CMD_PORT = 7760;
    $COMPUTER_SLEEP_CMD = "suspend";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://" . $dq_host . ":" . $COMPUTER_SLEEP_CMD_PORT . "/" .  $COMPUTER_SLEEP_CMD);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    $ret = curl_exec($ch);
    if ($ret === false) {
        $msg = "Command Failed: " . curl_error($ch);
    } else {
        $msg = "Command Succeeded!";
    }
    curl_close($ch);
    if ($ret === false) {
        $arr = array(
            'success' => "1",
            'message' => $msg,
            );
    } else {
        $arr = array(
            'success' => "0",
            'message' => $msg,
            );
    }
    return ($arr);
}

function process_shutdown_magic($cidr, $mac, $selfip) {
    return process_wol ($cidr, $mac, $selfip);
}

    if (empty($_POST['cmd'])) {
        exit();
    }

    $paramok = true;
    $msg = "Parameter Error: ";

    $i = $_POST['idx'];
    if (0 <= $i && $i < count($LIST_COMPUTERS)) {
        $paramok = true;
    } else {
        $paramok = false;
        $msg .= "idx ".$i." overflow";
    }

    if ("wol" == $_POST['cmd']) {
        $arr = process_wol ($LIST_COMPUTERS[$i]["cidr"], $LIST_COMPUTERS[$i]["mac"], $_SERVER["SERVER_ADDR"]);
        echo json_encode($arr);
        exit();
    }

    if ("status" == $_POST['cmd']) {
        $type = "";
        if (isset($_POST['type'])) {
            $type = $_POST['type'];
        }
        $requestedtimes = 0;
        if (isset($_POST['requestedtimes'])) {
            $requestedtimes = $_POST['requestedtimes'];
        }
        $waitedtime = 0;
        if (isset($_POST['waitedtime'])) {
            $waitedtime = $_POST['waitedtime'];
        }
        $arr = process_status ($LIST_COMPUTERS[$i], $type, $requestedtimes, $waitedtime);
        echo json_encode($arr);
        exit();
    }

    if ("ping" == $_POST['cmd']) {
        $arr = process_ping ($LIST_COMPUTERS[$i]["cidr"]);
        if ($arr['success'] == 0) {
            // delay ?
            $off = rand (0.5, 1);
            usleep ($off * 1000000); // 500000: 0.5秒
        }
        echo json_encode($arr);
        exit();
    }

    if ("shutdown" == $_POST['cmd']) {
        if ($ENABLE_SLEEP) {
            $arr = process_shutdown_irek ($LIST_COMPUTERS[$i]["cidr"]);
            if ($arr['success'] == 1) {
                $arr = process_shutdown_magic ($LIST_COMPUTERS[$i]["cidr"], $LIST_COMPUTERS[$i]["mac"], $_SERVER["SERVER_ADDR"]);
            }
        } else {
            $arr = array(
                'success' => "-1",
                'message' => "Function disabled, plese setup ENABLE_SLEEP in your config.php.",
                );
        }
        echo json_encode($arr);
        exit();
    }

?>
