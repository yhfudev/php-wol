<?php
//require_once 'config.php';
if(file_exists('config.php')) {
    require_once('config.php');
} else {
    $arr = array(
        'success' => "-1",
        'message' => "You need to setup config.php to finish this request.",
        );
    echo json_encode($arr);
    exit();
}
?>

<!DOCTYPE HTML>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Wake on Lan</title>
    <!-- script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script-->
    <!-- script type="text/javascript" src="http://code.jquery.com/jquery-1.11.2.min.js"></script-->
    <!-- script type="text/javascript" src="http://code.jquery.com/jquery-2.1.3.min.js"></script>-->
    <!-- script type="text/javascript" src="http://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
    <link rel="stylesheet" href="http://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css" /-->

    <script type="text/javascript" src="js/jquery-1.11.2.min.js"></script>
    <script type="text/javascript" src="js/jquery.mobile-1.4.5.min.js"></script>
    <link rel="stylesheet" href="css/jquery.mobile-1.4.5.min.css" />

    <!-- script type="text/javascript" src="http://fgnass.github.io/spin.js/spin.js"></script>
    <script type="text/javascript" src="http://fgnass.github.io/spin.js/jquery.spin.js"></script-->
    <script type="text/javascript" src="js/spin.js"></script>
    <script type="text/javascript" src="js/jquery.spin.js"></script>
</head>
<body>

<script>
/**
 * Wake on Lan application
 *
 * @copyright  Copyright (c) 2010-2015 Yunhui Fu <yhfudev@gmail.com>
 * @license    GNU GPL 2.0 and later
 */

/*
state machine for each host angent:
Idle:
    show wake/sleep buttons; if it's remote host, show both, otherwise show it according to the status of the host
    return error on any timeout/msg
Wake:
    disable both wake/sleep buttons;
    try to get next status, and ping it, until is waken or timeout.
Sleep:
    disable both wake/sleep buttons;
    try to get next status, and ping it, until is sleep or timeout.

*/

var ST_IDLE = 0;
var ST_WAKE = 1;
var ST_SLEEP = 2;

var EV_PING = 1;
var EV_STATUS = 2;
var EV_WOL = 3;
var EV_SOL = 4;

var EV_BTN_WAKE = 5;
var EV_BTN_SLEEP = 6;

function state_val2cstr (stateval) {
    if (stateval == ST_IDLE) {
        return "ST_IDLE";
    } else if (stateval == ST_WAKE) {
        return "ST_WAKE";
    } else if (stateval == ST_SLEEP) {
        return "ST_SLEEP";
    }
}


// event for state machine:
// "event" = EV_XXX
// "nexttime" = EV_STATUS,
// "message" = EV_XXX
// "return" = EV_XXX, 0 - success, 1 - task error, -1 - fatal error

function show_buttons_wake (id_comp, enables) {
    if ( "1" == $("#cav" + id_comp).attr( "isremote" ) ) {
        enables = true;
    }
    if (enables) {
        $('#btn-wake-' + id_comp).show();
        $('#btn-wake-' + id_comp).prop("disabled", false);
    } else {
        $('#btn-wake-' + id_comp).hide();
    }
}

function show_buttons_sleep (id_comp, enables) {
    if ( "1" == $("#cav" + id_comp).attr( "isremote" ) ) {
        enables = true;
    }
    if (enables) {
        $('#btn-sleep-' + id_comp).show();
        $('#btn-sleep-' + id_comp).prop("disabled", false);
    } else {
        $('#btn-sleep-' + id_comp).hide();
    }
}

function backto_idle (id_comp) {
    console.log( "return to IDLE from " + state_val2cstr($("#cav" + id_comp).attr( "state" )) + ":" + $("#cav" + id_comp).attr( "state" ) );
    $('#spin' + id_comp).hide();
    $("#cav" + id_comp).attr( "state", ST_IDLE );
    setTimeoutCheckPings (id_comp, 1, 500);
}

function process_event (id_comp, event_in) {

    if ($("#cav" + id_comp).attr( "state" ) == ST_IDLE) {
        $('#msg' + id_comp).show();

        if (event_in["event"] == EV_PING) {
            if (event_in["return"] == 1) {
                // error
                var num = 2;
                show_buttons_wake (id_comp, true);
                show_buttons_sleep (id_comp, false);
                //$('#spin' + id_comp).hide();
                $('#msg' + id_comp).html( fmt(RET_POS, event_in["message"]) );
                //$('#msg' + id_comp).html( fmt(RET_POS, 'Trying to ping the host for ' + num + ' time(s) ...'));
            } else if (event_in["return"] == 0) {
                show_buttons_wake (id_comp, false);
                show_buttons_sleep (id_comp, true);
                //$('#spin' + id_comp).hide();
                $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
            } else { // if (event_in["return"] == -1) {
                $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
                console.log("internal error");
            }
            return 0;

        } else if (event_in["event"] == EV_BTN_WAKE) {
            $("#cav" + id_comp).attr( "state", ST_WAKE );
            $('#spin' + id_comp).show();
            resetVariables(id_comp);
            show_buttons_wake (id_comp, false);
            processWol (id_comp);
            $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
            return 0;

        } else if (event_in["event"] == EV_BTN_SLEEP) {
            $("#cav" + id_comp).attr( "state", ST_SLEEP );
            $('#spin' + id_comp).show();
            resetVariables(id_comp);
            show_buttons_sleep (id_comp, false);
            processSol (id_comp);
            $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
            return 0;
        } else {
            console.log( "ignore event " + event_in["event"] + "at ST_IDLE:" + $("#cav" + id_comp).attr( "state" ) );
        }

    } else if ($("#cav" + id_comp).attr( "state" ) == ST_WAKE) {

        if (event_in["event"] == EV_PING) {
            if ($("#cav" + id_comp).attr( "status" ) == -1) {
                var pingnum = $("#cav" + id_comp).attr( "pingnum" );
                if (event_in["return"] == 1) {
                    if (pingnum > 0) {
                        console.log('got:' + event_in["message"] + '. Trying to ping the host for ' + pingnum + ' time(s) ...');
                        $("#cav" + id_comp).attr( "pingnum", pingnum - 1 );
                        setTimeoutCheckPings (id_comp, 2, 1000);
                        return 0;
                    } else {
                        // unable to detect if it is wake
                        // return to idle
                        backto_idle(id_comp);
                    }
                    return 0;
                } else if (event_in["return"] == -1) {
                    $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
                    console.log("internal error");
                    // return to idle
                    //backto_idle(id_comp);
                    //return 0;
                }
                // detected it works
                // return to idle
                backto_idle(id_comp);
                return 0;

            } else if ($("#cav" + id_comp).attr( "status" ) == 0) {
                show_buttons_wake (id_comp, false);
                // start another ping
                var curtime = new Date().getTime();
                var nexttime = $("#cav" + id_comp).attr( "statusnexttime" );
                if (curtime >= nexttime) {
                    setTimeoutCheckPings (id_comp, 2, 1000);
                } else {
                    setTimeoutCheckPings (id_comp, 2, nexttime - curtime);
                }
                return 0;

            } else if ($("#cav" + id_comp).attr( "status" ) == 1) {
                // got last status message
                // set buttons
                show_buttons_wake (id_comp, false);
                if (event_in["return"] != 0) {
                    show_buttons_wake (id_comp, true);
                }
                $('#msg' + id_comp).html( fmt(RET_POS, event_in["message"]) );
                backto_idle(id_comp);
                return 0;
            }
            return 0;

        } else if (event_in["event"] == EV_STATUS) {
            if (event_in["return"] == 0) {
                // get the status successfully
                $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
                if (event_in["islast"] == 0) {
                    $("#cav" + id_comp).attr( "status", 0);
                    var nexttime = event_in["nexttime"] - event_in["elasp"];
                    setTimeoutCheckStatus (id_comp, "wakestatus", nexttime * 1000);
                    nexttime = new Date().getTime() + nexttime * 1000;
                    $("#cav" + id_comp).attr( "statusnexttime", nexttime )
                } else {
                    // the last one, do nothing
                    $("#cav" + id_comp).attr( "status", 1);
                }
            } else if (event_in["return"] == -1) {
                $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
                $("#cav" + id_comp).attr( "status", -1);
            } else {
                $("#cav" + id_comp).attr( "status", -1);
            }
            //$('#spin' + id_comp).hide();
            $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
            return 0;

        } else if (event_in["event"] == EV_SOL) {
            if (event_in["return"] == 0) {
                //$('#spin' + id_comp).show();
                resetVariables(id_comp);
                checkStatus (id_comp, "wakestatus");
                checkPing (id_comp, 2);
                $('#msg' + id_comp).html( fmt(RET_POS, "Sent WoL, " + event_in["message"]));
            } else if (event_in["return"] == 1) {
                show_buttons_sleep (id_comp, true);
                $('#msg' + id_comp).html( fmt(RET_NEG, 'Error: ' + event_in["message"]));
            } else { // if (event_in["return"] == -1) {
                $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
                console.log("internal error");
                // return to idle
                backto_idle(id_comp);
            }
        } else if (event_in["event"] == EV_WOL) {
            if (event_in["return"] == 0) {
                //$('#spin' + id_comp).show();
                resetVariables(id_comp);
                checkStatus (id_comp, "wakestatus");
                checkPing (id_comp, 2);
                $('#msg' + id_comp).html( fmt(RET_POS, "Sent WoL, " + event_in["message"]));
            } else if (event_in["return"] == 1) {
                show_buttons_wake (id_comp, true);
                $('#msg' + id_comp).html( fmt(RET_NEG, 'Error: ' + event_in["message"]));
            } else { // if (event_in["return"] == -1) {
                $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
                console.log("internal error");
                // return to idle
                backto_idle(id_comp);
            }
        } else {
            console.log( "ignore event " + event_in["event"] + " at state " + state_val2cstr($("#cav" + id_comp).attr( "state" )) + ":" + $("#cav" + id_comp).attr( "state" ) );
        }

    } else if ($("#cav" + id_comp).attr( "state" ) == ST_SLEEP) {

        if (event_in["event"] == EV_PING) {
            if ($("#cav" + id_comp).attr( "status" ) == -1) {
                var pingnum = $("#cav" + id_comp).attr( "pingnum" );
                if (event_in["return"] == 0) {
                    if (pingnum > 0) {
                        console.log('got:' + event_in["message"] + '. Trying to ping the host for ' + pingnum + ' time(s) ...');
                        $("#cav" + id_comp).attr( "pingnum", pingnum - 1 );
                        setTimeoutCheckPings (id_comp, 2, 1000);
                        return 0;
                    } else {
                        // unable to detect if it is sleep
                        // return to idle
                        backto_idle(id_comp);
                    }
                    return 0;
                } else if (event_in["return"] == -1) {
                    $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
                    console.log("internal error");
                    // return to idle
                    //backto_idle(id_comp);
                    //return 0;
                }
                // detected that it works
                // return to idle
                backto_idle(id_comp);
                return 0;

            } else if ($("#cav" + id_comp).attr( "status" ) == 0) {
                show_buttons_sleep (id_comp, false);
                // start another ping
                var curtime = new Date().getTime();
                var nexttime = $("#cav" + id_comp).attr( "statusnexttime" );
                if (curtime >= nexttime) {
                    setTimeoutCheckPings (id_comp, 2, 1000);
                } else {
                    setTimeoutCheckPings (id_comp, 2, nexttime - curtime);
                }
                return 0;

            } else if ($("#cav" + id_comp).attr( "status" ) == 1) {
                // got last status message
                // set buttons
                show_buttons_sleep (id_comp, false);
                if (event_in["return"] == 1) {
                    show_buttons_sleep (id_comp, true);
                } else if (event_in["return"] == -1) {
                    $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
                    console.log("internal error");
                    // return to idle
                    //backto_idle(id_comp);
                    //return 0;
                }
                $('#msg' + id_comp).html( fmt(RET_POS, event_in["message"]) );
                backto_idle(id_comp);
                return 0;
            }
            return 0;

        } else if (event_in["event"] == EV_STATUS) {
            if (event_in["return"] == 0) {
                // get the status successfully
                $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
                if (event_in["islast"] == 0) {
                    $("#cav" + id_comp).attr( "status", 0);
                    var nexttime = event_in["nexttime"] - event_in["elasp"];
                    setTimeoutCheckStatus (id_comp, "sleepstatus", nexttime * 1000);
                    nexttime = new Date().getTime() + nexttime * 1000;
                    $("#cav" + id_comp).attr( "statusnexttime", nexttime )
                } else if (event_in["return"] == -1) {
                    $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
                    $("#cav" + id_comp).attr( "status", 1);
                } else {
                    // the last one, do nothing
                    $("#cav" + id_comp).attr( "status", 1);
                }
            } else {
                $("#cav" + id_comp).attr( "status", -1);
            }
            //$('#spin' + id_comp).hide();
            $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
            return 0;

        } else if (event_in["event"] == EV_SOL) {
            if (event_in["return"] == 0) {
                //$('#spin' + id_comp).show();
                resetVariables(id_comp);
                checkStatus (id_comp, "sleepstatus");
                checkPing (id_comp, 2);
                $('#msg' + id_comp).html( fmt(RET_POS, "Sent WoL, " + event_in["message"]));
            } else if (event_in["return"] == 1) {
                show_buttons_sleep (id_comp, true);
                $('#msg' + id_comp).html( fmt(RET_NEG, 'Error: ' + event_in["message"]));
            } else { //if (event_in["return"] == -1) {
                $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
                console.log("internal error");
                // return to idle
                backto_idle(id_comp);
                return 0;
            }
        } else if (event_in["event"] == EV_WOL) {
            if (event_in["return"] == 0) {
                //$('#spin' + id_comp).show();
                resetVariables(id_comp);
                checkStatus (id_comp, "sleepstatus");
                checkPing (id_comp, 2);
                $('#msg' + id_comp).html( fmt(RET_POS, "Sent WoL, " + event_in["message"]));
            } else if (event_in["return"] == 1) {
                show_buttons_wake (id_comp, true);
                $('#msg' + id_comp).html( fmt(RET_NEG, 'Error: ' + event_in["message"]));
            } else { //if (event_in["return"] == -1) {
                $('#msg' + id_comp).html( fmt(RET_NEG, event_in["message"]) );
                console.log("internal error");
                // return to idle
                backto_idle(id_comp);
                return 0;
            }
        } else {
            console.log( "ignore event " + event_in["event"] + " at state " + state_val2cstr($("#cav" + id_comp).attr( "state" )) + ":" + $("#cav" + id_comp).attr( "state" ) );
        }

    }
    return -1;
}

var RET_POS = 1;
var RET_NEG = 2;

function fmt (type, msg) {
    if (RET_POS == type) {
        return '<font color="blue">' + msg + '</font>';
    }
    return '<font color="red">' + msg + '</font>';
}

function resetStates (id_comp) {
    $("#cav" + id_comp).attr( "state", ST_IDLE );
    show_buttons_wake (id_comp, true);
    show_buttons_sleep (id_comp, true);
}

function resetVariables (id_comp) {
    $("#cav" + id_comp).attr( "starttime", 0 );
    $("#cav" + id_comp).attr( "statustimes", 0 );
    $("#cav" + id_comp).attr( "status", -1 );
    $("#cav" + id_comp).attr( "statusnexttime", 0 );
    $("#cav" + id_comp).attr( "pingnum", 8 );
}

function setTimeoutCheckPings (id_comp, num, nexttime) {
    var off = Math.random() * 500;
    setTimeout('checkPing(' + id_comp + ', ' + num + ')', nexttime + off);
}

function setTimeoutCheckStatus (id_comp, type, nexttime) {
    var off = Math.random() * 500;
    //alert("setup timer: " + "id=" + id_comp + ", nexttime=" + nexttime);
    setTimeout('checkStatus(' + id_comp + ', "' + type + '")', nexttime + off);
}

function checkStatus(id_comp, type) {
    $('#msg' + id_comp).html( fmt(RET_POS, 'Trying to get the status for host ...'));
    var lasttime = 0;
    var waitedtime = new Date().getTime() / 1000;

    lasttime = $("#cav" + id_comp).attr( "starttime" );
    if (0 == lasttime) {
        lasttime = waitedtime;
        $("#cav" + id_comp).attr( "starttime", lasttime );
    }
    waitedtime = waitedtime - lasttime;
    var requestedtimes = $("#cav" + id_comp).attr( "statustimes" );
    requestedtimes = 10000; // debug

    $.ajax({
        timeout : 2000,     // 2000: ajax请求超时时间2秒
        url : 'wolapi.php',
        type : 'POST',
        dataType : 'json', data : {"cmd": "status", "idx": id_comp, "type": type, "waitedtime": waitedtime, "requestedtimes": requestedtimes },
        //dataType : 'html', data : 'cmd=status&idx=' + id_comp,
        success : function(data) {
            //$('#msg' + id_comp).html(data);
            var retevt = {};
            retevt["event"] = EV_STATUS;
            retevt["message"] = data.message;
            retevt["islast"] = data.islast;
            retevt["elasp"] = data.elasp;
            retevt["nexttime"] = data.nexttime;
            if (data.success == "0") {
                retevt["return"] = 0;
            } else if (data.success == "-1") {
                retevt["return"] = -1;
            } else {
                retevt["return"] = 1;
            }
            process_event (id_comp, retevt);
        },
        error : function(xhr, status) {
            alert('ping, Sorry, there was a problem! status=' + status);
            var retevt = {};
            retevt["event"] = EV_STATUS;
            retevt["message"] = "error in ajax";
            retevt["islast"] = 1;
            retevt["return"] = -1;
            process_event (id_comp, retevt);
        },
        complete : function(xhr, status) {
            //alert('ping The request is complete!');
        }
    });
    $('#msg' + id_comp).show();
}

function checkPing(id_comp, num) {
    if (num > 0) {
        num --;
    } else {
        return;
    }
    $.ajax({
        timeout : 80000,     // 80000: ajax请求超时时间80秒
        url : 'wolapi.php',
        type : 'POST',
        dataType : 'json', data : {"cmd": "ping", "idx": id_comp},
        //dataType : 'html', data : 'cmd=ping&idx=' + id_comp,
        success : function(data) {
            //$('#msg' + id_comp).html(data);
            var show_wake = false;
            var show_btn = false;
            if (data.success == "0") {
                var retevt = {};
                retevt["event"] = EV_PING;
                retevt["message"] = data.message;
                retevt["return"] = 0;
                process_event (id_comp, retevt);
            } else if (data.success == "1") {
                if (num > 0) {
                    checkPing(id_comp, num);
                } else {
                    var retevt = {};
                    retevt["event"] = EV_PING;
                    retevt["message"] = data.message;
                    retevt["return"] = 1;
                    process_event (id_comp, retevt);
                }
            } else {
                var retevt = {};
                retevt["event"] = EV_PING;
                retevt["message"] = data.message;
                retevt["return"] = -1;
                process_event (id_comp, retevt);
            }
        },
        error : function(xhr, status) {
            var retevt = {};
            retevt["event"] = EV_PING;
            retevt["return"] = -1;
            retevt["message"] = "error in ajax";
            process_event (id_comp, retevt);
            alert('ping, Sorry, there was a problem! status=' + status);
        },
        complete : function(xhr, status) {
            //alert('ping The request is complete!');
        }
    });
}

// sleep on lan
function submitSol(id_comp) {
    var retevt = {};
    retevt["event"] = EV_BTN_SLEEP;
    retevt["message"] = "Sleep button pressed";
    process_event (id_comp, retevt);
}

function processSol(id_comp) {
    //alert('Sorry, sleep not be implemented yet!');
    $('#btn-sleep-' + id_comp).prop("disabled", true);
    $.ajax({
        timeout : 80000,     // 80000: ajax请求超时时间80秒
        url : 'wolapi.php',
        type : 'POST',
        dataType : 'json', data : {"cmd": "shutdown", "idx": id_comp},
        //dataType : 'html', data : 'cmd=shutdown&idx=' + id_comp,
        success : function(data) {
            var retevt = {};
            retevt["event"] = EV_WOL;
            retevt["message"] = data.message;
            if (data.success == "0") {
                retevt["return"] = 0;
            } else if (data.success == "1") {
                retevt["return"] = 1;
            } else {
                retevt["return"] = -1;
            }
            process_event (id_comp, retevt);
        },
        error : function(xhr, status) {
            var retevt = {};
            retevt["return"] = -1;
            retevt["message"] = "error in ajax";
            process_event (id_comp, retevt);
            alert('Sorry, there was a problem! status=' + status);
        },
        complete : function(xhr, status) {
            //alert('The request is complete!');
        }
    });
}

// wake on lan
function submitWol(id_comp) {
    var retevt = {};
    retevt["event"] = EV_BTN_WAKE;
    retevt["message"] = "Wake button pressed";
    process_event (id_comp, retevt);
}

function processWol(id_comp) {
    //alert ("here 001 id=" + id_comp);
    $('#btn-wake-' + id_comp).prop("disabled", true);
    //$.post("wolapi.php", {cmd: "wol", idx: id_comp},
    $.ajax({
        timeout : 80000,     // 80000: ajax请求超时时间80秒
        url : 'wolapi.php',
        type : 'POST',
        dataType : 'json', data : {"cmd": "wol", "idx": id_comp},
        //dataType : 'html', data : 'cmd=wol&idx=' + id_comp,
        success : function(data) {
            //成功之后调用该函数
            //data:服务器返回的数据，
            //如果服务器返回的是一个xml文档
            //需要调用$(data),将xml转换成一个jQuery对象
            // $(data).find('desc').text()
            //$('#cav' + id_comp).after("<div id='tips" + id_comp + "'></div>");
            //$('#tips' + id_comp).html(data);
            var retevt = {};
            retevt["event"] = EV_WOL;
            retevt["message"] = data.message;
            if (data.success == "0") {
                retevt["return"] = 0;
            } else if (data.success == "1") {
                retevt["return"] = 1;
            } else {
                retevt["return"] = -1;
            }
            process_event (id_comp, retevt);
        },
        // 如果 Ajax 执行失败；
        // 将返回原始错误信息以及状态码
        // 传入这个回调函数中
        error : function(xhr, status) {
            var retevt = {};
            retevt["event"] = EV_WOL;
            retevt["message"] = "error in ajax";
            retevt["return"] = -1;
            process_event (id_comp, retevt);
            alert('Sorry, there was a problem! status=' + status);
        },

        // 这里是无论 Ajax 是否成功执行都会触发的回调函数
        complete : function(xhr, status) {
            //alert('The request is complete!');
        }
    });
    //alert ("here 002");
}

function checkPingsAll() {
    $( "li" ).each( function( index, element ){
        var id_comp = $(this).attr('idx');
        //var id_canv = $(this).attr('id');
        checkPing (id_comp, 2);
    });
}

// Set aspect ratio of #btn-*
var aspect_ratio = 0.2;
function reset_btn_size () {
    $( "li" ).each( function( index, element ){
        var id_comp = $(this).attr('idx');
        var width = $(this).width();
        $('#btn-wake-' + id_comp).width( width * aspect_ratio );
        $('#btn-sleep-' + id_comp).width( width * aspect_ratio );
    });
}

function init() {
    reset_btn_size ();
    $( "li" ).each( function( index, element ){
        var id_comp = $(this).attr('idx');
        //var id_canv = $(this).attr('id');
        var spinner = new Spinner().spin(); document.getElementById('spin' + id_comp).appendChild(spinner.el);
        //new Spinner().spin($('#spin' + id_comp)[0]);
        $('#spin' + id_comp).hide();
        $('#btn-wake-' + id_comp).hide();
        $('#btn-sleep-' + id_comp).hide();
        resetVariables (id_comp);
        resetStates (id_comp);
    });
    checkPingsAll();
    window.setInterval(checkPingsAll, 120000);
}

$( document ).ready(function() {
    //console.log( "ready!" );
    //console.log( "the fist: " + $( "li" )[ 0 ] );

    init();
});

// Resize #btn-* on browser resize
jQuery(window).resize(function() {
    reset_btn_size ();
});

</script>

<div data-role="page" id="pageone">
  <div data-role="content">

<h2>Wake/Sleep on Lan</h2>

<ul data-role="listview" data-filter="true" >
    <!-- li data-role="list-divider">Servers List<span class="ui-li-count"><?php echo count($LIST_COMPUTERS); ?></span></li -->

<?php
// TODO: 2 send 'simulated' status of booting, for reference only.

require_once 'netaddr.php';

for ($i = 0; $i < count($LIST_COMPUTERS); $i ++) {
    $isremote = 1;
    if ( isInNetwork($LIST_COMPUTERS[$i]["cidr"], $_SERVER["SERVER_ADDR"])) {
        $isremote = 0;
    }
    // we define the state IDLE as 0!!
    print '<li id="cav'.$i.'" idx='.$i.' isremote='.$isremote.' state=0 starttime="0" statustimes="0" ><img src="icons/' . $LIST_COMPUTERS[$i]["icon"]
        . '" height="100" width="100" /><h3><a href="#'.$i.'">' . $LIST_COMPUTERS[$i]["name"]
        . '</a></h3>'
        //. '<p class="ui-li-aside">' . $LIST_COMPUTERS[$i]["mac"] . '</p>'
        . '<p>' . $LIST_COMPUTERS[$i]["cidr"]
        . '<button id="btn-wake-'.$i.'" onclick="submitWol('.$i.')">Wake</button>'
        . '<button id="btn-sleep-'.$i.'" onclick="submitSol('.$i.')">Sleep</button>'
        . '</p><p><div id="msg'.$i.'" /><div id="spin'.$i.'" />'
        . '</p></li>' . "\n";
}
?>
</ul>


  </div>
</div> 
</body>
</html>
