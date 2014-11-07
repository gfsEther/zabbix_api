<?php
/**
 * @file        index.php
 *
 * @brief       Exemple of using ZabbixApi by getting interface informaction.
 *
 */

// load ZabbixApi
require 'ZabbixApiAbstract.class.php';
require 'ZabbixApi.php';



try {
    
    function setParmets($parm) {
        global $ip, $port;
        $ip = $parm['ip'];
        $port = $parm['port'];
    }
    
    $api = new ZabbixApi('api', 'zabbix');

    echo "Connect\n";
  
    $ip;
    $port;
    $funcType=0; // packets monitoring
    $time=15; // 15 minutes before
    

    $bras = array( 
        'ip' => '91.197.175.194',
        'port' => '2/1'
    );
    
    $extrem = array(
        'ip' => '91.197.175.181',
        'port' => '10'
    );
    
    $cisco = array(
        'ip' => '91.197.175.42',
        'port' => 'gi0/3'
    );
    
    $juniper = array(
        'ip' => '91.197.175.184',
        'port' => 'ge-1/0/0.41'
    );
    
    setParmets($juniper);

    $his = $api->historyIntInOut($ip, $port, $funcType, $time);

    print_r($his);
    
    
} catch (Exception $e) {

    // Exception in ZabbixApi catched
    echo $e->getMessage();
}