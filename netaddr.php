<?php
/**
 * PHP network address functions
 *
 * @copyright  Copyright (c) 2010-2015 Yunhui Fu <yhfudev@gmail.com>
 * @license    GNU GPL 2.0 and later
 */

function binnmtowm($binin){
    $binin=rtrim($binin, "0");
    if (!ereg("0",$binin) ){
        return str_pad(str_replace("1","0",$binin), 32, "1");
    } else {
        return "1010101010101010101010101010101010101010";
    }
}

function bintocdr ($binin){
    return strlen(rtrim($binin,"0"));
}

function bintodq ($binin) {
    if ($binin=="N/A") return $binin;
    $binin=explode(".", chunk_split($binin,8,"."));
    for ($i=0; $i<4 ; $i++) {
        $dq[$i]=bindec($binin[$i]);
    }
    return implode(".",$dq) ;
}

function bintoint ($binin){
    return bindec($binin);
}

function binwmtonm($binin){
    $binin=rtrim($binin, "1");
    if (!ereg("1",$binin)){
        return str_pad(str_replace("0","1",$binin), 32, "0");
    } else {
        return "1010101010101010101010101010101010101010";
    }
}

function cdrtobin ($cdrin){
    return str_pad(str_pad("", $cdrin, "1"), 32, "0");
}

function dotbin($binin,$cdr_nmask){
    // splits 32 bit bin into dotted bin octets
    if ($binin == "N/A") return $binin;
    $oct = rtrim(chunk_split($binin,8,"."),".");
    if ($cdr_nmask > 0) {
        $offset=sprintf("%u",$cdr_nmask/8) + $cdr_nmask ;
        return substr($oct,0,$offset ) . "&nbsp;&nbsp;&nbsp;" . substr($oct,$offset) ;
    } else {
        return $oct;
    }
}

function dqtobin($dqin) {
    $dq = explode(".",$dqin);
    for ($i=0; $i<4 ; $i++) {
        $bin[$i] = str_pad(decbin($dq[$i]), 8, "0", STR_PAD_LEFT);
    }
    return implode("",$bin);
}

function inttobin ($intin) {
    return str_pad(decbin($intin), 32, "0", STR_PAD_LEFT);
}

class netaddress
{
    public function __construct()
    {
        $this->dq_host = "";
        $this->bin_nmask = "";
        $this->bin_wmask = "";
        $this->cdr_nmask = 0;
    }
    public function getHost() {
        return $this->dq_host;
    }
    public function getNMask() {
        return $this->bin_nmask;
    }
    public function getWMask() {
        return $this->bin_wmask;
    }
    public function getCMask() {
        return $this->cdr_nmask;
    }
    public function getErrMsg ($errno) {
        switch($errno) {
        case -2:
            return "Invalid CIDR value. Try an integer 0 - 32.";
        case -3:
            return "Invalid Netmask.";
        }
        return "Unknown";
    }
    public function setValue($my_net_info) {
        if (ereg("/",$my_net_info)){  //if cidr type mask
            $dq_host = strtok("$my_net_info", "/");
            $cdr_nmask = strtok("/");
            if (!($cdr_nmask >= 0 && $cdr_nmask <= 32)) {
                return -2;
            }
            $bin_nmask=cdrtobin($cdr_nmask);
            $bin_wmask=binnmtowm($bin_nmask);
        } else { //Dotted quad mask?
            $dqs=explode(" ", $my_net_info);
            $dq_host=$dqs[0];
            $bin_nmask=dqtobin($dqs[1]);
            $bin_wmask=binnmtowm($bin_nmask);
            if (ereg("0",rtrim($bin_nmask, "0"))) {  //Wildcard mask then? hmm?
                $bin_wmask=dqtobin($dqs[1]);
                $bin_nmask=binwmtonm($bin_wmask);
                if (ereg("0",rtrim($bin_nmask, "0"))){ //If it's not wcard, whussup?
                    return -3;
                }
            }
            $cdr_nmask=bintocdr($bin_nmask);
        }
        $this->dq_host = $dq_host;
        $this->bin_nmask = $bin_nmask;
        $this->bin_wmask = $bin_wmask;
        $this->cdr_nmask = $cdr_nmask;
        return 0;
    }
}

// check if the cidr is in the same network
function isInNetwork ($cidr, $selfip) {
    $addr = new netaddress();
    $addr->setValue($cidr);
    $dq_host = $addr->getHost();

    $cdr_nmask = $addr->getCMask();
    $bin_host = dqtobin($dq_host);
    $bin_bcast = (str_pad(substr($bin_host,0,$cdr_nmask),32,1));
    $bcast_ipv4 = bintodq ($bin_bcast);
    $bin_net=(str_pad(substr($bin_host,0,$cdr_nmask),32,0));

    $addrself = new netaddress();
    $addrself->setValue($selfip . "/" . $cdr_nmask);
    $bin_net2=(str_pad(substr(dqtobin($addrself->getHost()), 0, $cdr_nmask), 32, 0));

    if (bintodq($bin_net) == bintodq($bin_net2)) {
        return true;
    }
    return false;
}

function tr(){
    echo "\t<tr>";
    for($i=0; $i<func_num_args(); $i++) {
        echo "<td>".func_get_arg($i)."</td>";
    }
    echo "</tr>\n";
}

function showaddress ($my_net_info)
{
    $test = new netaddress();
    $ret = $test->setValue($my_net_info);
    if ($ret < 0) {
        tr($test->getErrMsg ($ret) .": $my_net_info" );
        print "$end";
        exit;
    }

    $dq_host=$test->getHost();
    $bin_nmask=$test->getNMask();
    $bin_wmask=$test->getWMask();
    $cdr_nmask=$test->getCMask();

    $bin_host=dqtobin($dq_host);
    $bin_bcast=(str_pad(substr($bin_host,0,$cdr_nmask),32,1));
    $bin_net=(str_pad(substr($bin_host,0,$cdr_nmask),32,0));
    $bin_first=(str_pad(substr($bin_net,0,31),32,1));
    $bin_last=(str_pad(substr($bin_bcast,0,31),32,0));
    $host_total=(bindec(str_pad("",(32-$cdr_nmask),1)) - 1);

    if ($host_total <= 0){  //Takes care of 31 and 32 bit masks.
        $bin_first="N/A" ; $bin_last="N/A" ; $host_total="N/A";
        if ($bin_net === $bin_bcast) $bin_bcast="N/A";
    }

    //Determine Class
    if (ereg('^0',$bin_net)){
        $class="A";
        $dotbin_net= "<font color=\"Green\">0</font>" . substr(dotbin($bin_net,$cdr_nmask),1) ;
    }elseif (ereg('^10',$bin_net)){
        $class="B";
        $dotbin_net= "<font color=\"Green\">10</font>" . substr(dotbin($bin_net,$cdr_nmask),2) ;
    }elseif (ereg('^110',$bin_net)){
        $class="C";
        $dotbin_net= "<font color=\"Green\">110</font>" . substr(dotbin($bin_net,$cdr_nmask),3) ;
    }elseif (ereg('^1110',$bin_net)){
        $class="D";
        $dotbin_net= "<font color=\"Green\">1110</font>" . substr(dotbin($bin_net,$cdr_nmask),4) ;
        $special="<font color=\"Green\">Class D = Multicast Address Space.</font>";
    }else{
        $class="E";
        $dotbin_net= "<font color=\"Green\">1111</font>" . substr(dotbin($bin_net,$cdr_nmask),4) ;
        $special="<font color=\"Green\">Class E = Experimental Address Space.</font>";
    }

    if (ereg('^(00001010)|(101011000001)|(1100000010101000)',$bin_net)){
        $special='<a href="http://www.ietf.org/rfc/rfc1918.txt">( RFC-1918 Private Internet Address. )</a>';
    }

    // Print Results
    tr('Address:',"<font color=\"blue\">$dq_host</font>",
        '<font color="brown">'.dotbin($bin_host,$cdr_nmask).'</font>');
    tr('Netmask:','<font color="blue">'.bintodq($bin_nmask)." = $cdr_nmask</font>",
        '<font color="red">'.dotbin($bin_nmask, $cdr_nmask).'</font>');
    tr('Wildcard:', '<font color="blue">'.bintodq($bin_wmask).'</font>',
        '<font color="brown">'.dotbin($bin_wmask, $cdr_nmask).'</font>');
    tr('Network:', '<font color="blue">'.bintodq($bin_net).'</font>',
        "<font color=\"brown\">$dotbin_net</font>","<font color=\"Green\">(Class $class)</font>");
    tr('Broadcast:','<font color="blue">'.bintodq($bin_bcast).'</font>',
        '<font color="brown">'.dotbin($bin_bcast, $cdr_nmask).'</font>');
    tr('HostMin:', '<font color="blue">'.bintodq($bin_first).'</font>',
        '<font color="brown">'.dotbin($bin_first, $cdr_nmask).'</font>');
    tr('HostMax:', '<font color="blue">'.bintodq($bin_last).'</font>',
        '<font color="brown">'.dotbin($bin_last, $cdr_nmask).'</font>');
    @tr('Hosts/Net:', '<font color="blue">'.$host_total.'</font>', "$special");
    print "$end";
}

?>
