<?php
/**
 * @file        createHost.php
 *
 * @brief       Exemple of using ZabbixApi to create a host.
 *
 */

// load ZabbixApi
require 'ZabbixApiAbstract.class.php';
require 'ZabbixApi.php';

try {
    $zabbix = new ZabbixApi('api', 'zabbix');
    
    $ip = '10.111.111.1';
    $name = 'teste';
    $templateid = '10189';
    $groupid = '9';
    
    // createHost(ip, name, templateid, groupid)
    // Switch_template is the default group for the request.
    $host = $zabbix->createHost($ip, $name, $templateid, $groupid);
    
    var_dump($host->hostids);
    
} catch (Exception $e) {
    // Exception in ZabbixApi catched
    echo $e->getMessage();
}
