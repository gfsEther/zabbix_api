<?php
/**
 * @file    ZabbixApi.php
 * @brief   Class file for the implementation of the class ZabbixApi.
 *
 * Customizations in this file.
 *
 * This file is part of PhpZabbixApi.
 *
 * @version     $Id: ZabbixApi.php 2 2014-11-07 15:00:00 Gustavo $
 */


class ZabbixApi extends ZabbixApiAbstract {
	
    /**
     * @brief   API URL.
     */ 
    
    private $url = 'http://91.197.172.70/zabbix/api_jsonrpc.php';

    /**
     * @brief   IP address of the device.
     */
    
    private $ipAddress;
    
    /**
     * @brief   PORT number of the device.
     */
    
    private $port;
    
    /**
     * @brief   UNIX time stamp.
     */
    
    private $timeStamp;
    
    /**
     * @brief   INTERFACE parameters.
     */
    
    private $defaultInterface = [
        'type' => '2',
        'main' => '1',
        'useip' => '1',
        'ip' => '',
        'dns' => '',
        'port' => '161'
        ];
    

    /**
     * @brief   Class constructor.
     *
     * @param   $user       Username.
     * @param   $pass   Password.
     */
    
    public function __construct($user, $pass) {
        parent::__construct($this->url, $user, $pass);
        $this->timeStamp = time();
    }

    /**
     * @brief   Ip and Port verification.
     *
     * @param   $ip             IP address.
     * @param   $portNumber     Port number.
     * 
     * @throws  Custom exception
     */
    
    public function verIpPort($ip, $portNumber) {
        // Ip address verification.	
        (isset($ip) && filter_var($ip, FILTER_VALIDATE_IP)) ?  
            $this->ipAddress = $ip : 
            $this->excep("IP not valid/not set.");

        // Port verification.
        (isset($portNumber)) ? 
            $this->port = $portNumber : $this->excep("Port not set.");	
        (is_numeric($this->port)) && ($this->port = sprintf("%02d", $portNumber));
    }

    /**
     * @brief   Custom exception.
     *
     * @param   $string     The information of what has happened.     
     * @param   $type       Which kind of problem occurred.
     */
    
    public function excep($string, $type='Error') {
        throw new Exception("{$type}: {$string}\n");
    }

    /**
    * @brief   Retrieve the history values of a interface.
    *
    * The $port has four differents formats. Ex: 
    * 	- 'Gi0/1' is the cisco format.
    * 	- '1/1' is the RedBack format.
    * 	- '1' is the DLINK format.
    *   - 'ge-1/1/9' is the Juniper format. 
    * 
    * The $funcType should be '1' for traffic monitoring. Values that are not
    * igual to '1' will be treated as a 'packets' monitoring.
    *
    * The $time will be set to 1440 minutes(24h) if no value be specified.
    *
    * @param   $ip          Ip address of the device.
    * @param   $port        Port number of device.
    * @param   $funcType    Type of monitoring.
    * @param   $time        The period of the time(minutes).
    * 
    * @return	array $info { 	
    * 
    * 	Retrieve a associative array with the name of the item and the values
    *   and times of each item.
    * 
    * 	'itemname' {
    *       'clock' => @type unixtime,
    *       'value' => @type integer
    * 	}
    * }
    * 
    * @throws  Custom exception
    */
    
    public function historyIntInOut($ip, $port, $funcType, $time='1440') {

        // set output to be extend as by default requests.				
        $this->setDefaultParams(['output' => 'extend']); 

        $this->timeStamp = $this->timeStamp - ($time * 60);

        // set which type of monitoring will be handled. 
        $funcType = (isset($funcType) && $funcType == '1' ) ? 
            'traffic HC (64)' : 'packets HC (64)'; 

        // verify if ip and port are valid.
        $this->verIpPort($ip, $port); 

        // get the hostid.
        $hostid = $this->hostGet(['filter' => ['ip'=> "{$this->ipAddress}"]]); 

        // verify if the hostid exists.
        (empty($hostid)) && $this->excep("The host \"{$this->ipAddress}\" is not configured in zabbix"); 

        // get the items from the host.
        $items = $this->itemGet(['hostids' => "{$hostid[0]->hostid}"]); 

        // verify if the host has items.
        (empty($items)) && $this->excep("There is no items for this host \"{$this->ipAddress}\""); 

        $pattern = preg_quote("{$funcType} on interface {$this->port}", '/'); 

        // build an array with the informations of traffic/packets of a interface.
        for ($i=0,$j=0,$info=[]; $i < count($items) ; $i++) { 
            if (!(preg_match('/'.$pattern.'$/i', $items[$i]->name))) {	
                continue;
            } else {
                $info += $this->builtHistory($items[$i]);
                
                if ($j < 1) { $j++; } else break;
            }
        }

        // Verify if there is monitoring item for this interface in zabbix.
        (empty($info)) && $this->excep("There is no traffic/packets monitoring for this host: \"{$hostid[0]->name}\"");
        
        // return associative array.
        return $info;        
    }
    
    
    /**
    * @brief   Build the historic array of a item.
    *
    * @param stdClass   $item    Item object.
    * @param int        $limit   Number of values that will be returned.
    * 
    * @return	array $info 	
    * 
    * 	Retrieve a associative array with the name of the item and the values
    *   and times of each item.
    * 
    * 	'itemname' {
    *       'clock' => @type unixtime,
    *       'value' => @type integer
    * 	}
    * 
    */
    
    public function builtHistory($item,$limit=0) {
        $time = $this->timeStamp - (5*60);
        
        $history = $this->historyGet([
            'itemids' => "{$item->itemid}",
            'history' => '3',
            'sortfield' => 'clock',
            'sortorder' => 'DESC',
            'time_from' => "{$time}",
            'limit' => "{$limit}"    
            ]); 

        if (!empty($history)) {

                for ($i=0; $i < count($history) ; $i++) {
                        $value[$i] = array(
                                'clock' => $history[$i]->clock,
                                'value' => (int)$history[$i]->value
                        );  
                }

                $info[$item->name] = $value; 

        } else { $info[$item->name] = 'no data'; }
        
        return $info;
    }
    

    /**
    * @brief   Create a host and assign a template to it.
    *
    * The $templateid should be a valid template id.
    * 
    * The $ip should be a ip address that is not being used in the zabbix.
    *
    * The $name should be a name that  is not being used in the zabbix.
    *
    * @param   $ip          Ip address of the device.
    * @param   $name        Name of the host.
    * @param   $templateid  Template id that exists in the zabbix.
    * 
    * @retval  stdClass { 	 
    * 
    * 	Retrieve the hostid of the host created.
    * 
    * }
    * 
    * @throws  Custom exception
    */
    
    public function createHost($ip, $name, $templateid, $groupid='9') {
        //Verify if host already exists.
        $this->hostExists(['host' => $name]) && 
                $this->excep("Host \"{$name}\" already exists.");
        $this->hostinterfaceExists(['ip' => "{$ip}"]) &&
                $this->excep("Host with IP address \"{$ip}\" already exists.");
                
        //Verify ip address.
        $this->verIpPort($ip, 1);
        
        //create the paramters to make the hostCreate request.
        $params = $this->intParams($name, $templateid, $groupid);
        print_r($params);
        
        return $this->hostCreate($params);       
    }
    
    
    /**
    * @brief   Built the paramters to make the createHost request.
    *
    * The $templateid should be a valid template id.
    * 
    * The $groupid as default will be '9' (Switch_templates).
    *
    * The $name should be a name that  is not being used in the zabbix.
    *
    * @param   $name            Name of the host.
    * @param   $templateid      Template id that exists in the zabbix.
    * @param   $groupid         Groupid id to assign to the host.
    * 
    * @retval  array $params { 	 
    * 
    * 	Retrieve params array to create a host.
    * 
    * }
    * 
    * @throws  Custom exception
    */
    
    public function intParams($name, $templateid, $groupid) {
        
        if (is_array($templateid)) {
            for($i = 0; $i < count($templateid); $i++) {
                $template[$i] = ['templateid' => "{$templateid[$i]}"];
            }
        } else $template['templateid'] = $templateid;
        
        if (is_array($groupid)) {
            for($i = 0; $i < count($groupid); $i++) {
                $group[$i] = ['groupid' => "{$groupid[$i]}"];
            }
        } else $group['groupid'] = $groupid;
        
        $this->defaultInterface['ip'] = $this->ipAddress;
        
        return $params = [
            'host' => "{$name}",
            'interfaces' => $this->defaultInterface,
            'groups' => $group,
            'templates' => $template
        ];
    }
}