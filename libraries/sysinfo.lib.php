<?php
function getSysInfo() {
    $supported = array('Linux','WINNT');

    $sysinfo = array();

    if(in_array(PHP_OS, $supported)) {
        return eval("return new ".PHP_OS."();");
    }
    
    return $sysinfo;
}


class WINNT {
    private $_wmi;
    
    public $os = 'WINNT';

    public function __construct() {
        // initialize the wmi object
        $objLocator = new COM('WbemScripting.SWbemLocator');
        $this->_wmi = $objLocator->ConnectServer();
    }

    function loadavg() {
        $loadavg = "";
        $sum = 0;
        $buffer = $this->_getWMI('Win32_Processor', array('LoadPercentage'));
        
        foreach ($buffer as $load) {
            $value = $load['LoadPercentage'];
            $loadavg .= $value.' ';
            $sum += $value;
        }
        
        return array('loadavg' => $sum / count($buffer));
    }
    
    private function _getWMI($strClass, $strValue = array()) {
        $arrData = array();
        $value = "";
        
        $objWEBM = $this->_wmi->Get($strClass);
        $arrProp = $objWEBM->Properties_;
        $arrWEBMCol = $objWEBM->Instances_();
        foreach ($arrWEBMCol as $objItem) {
            if (is_array($arrProp)) {
                reset($arrProp);
            }
            $arrInstance = array();
            foreach ($arrProp as $propItem) {
                if ( empty($strValue)) {
                    eval("\$value = \$objItem->".$propItem->Name.";");
                    $arrInstance[$propItem->Name] = trim($value);
                } else {
                    if (in_array($propItem->Name, $strValue)) {
                        eval("\$value = \$objItem->".$propItem->Name.";");
                        $arrInstance[$propItem->Name] = trim($value);
                    }
                }
            }
            $arrData[] = $arrInstance;
        }
        return $arrData;
    }

    
    function memory() {
        $buffer = $this->_getWMI("Win32_OperatingSystem", array('TotalVisibleMemorySize', 'FreePhysicalMemory'));
        $mem = Array();
        $mem['MemTotal'] = $buffer[0]['TotalVisibleMemorySize'];
        $mem['MemFree'] = $buffer[0]['FreePhysicalMemory'];
        $mem['MemUsed'] = $mem['MemTotal'] - $mem['MemFree'];
        
        $buffer = $this->_getWMI('Win32_PageFileUsage');
        
        $mem['SwapTotal'] = 0;
        $mem['SwapUsed'] = 0;
        $mem['SwapPeak'] = 0;
        
        foreach ($buffer as $swapdevice) {
            $mem['SwapTotal'] += $swapdevice['AllocatedBaseSize'] * 1024;
            $mem['SwapUsed'] += $swapdevice['CurrentUsage'] * 1024;
            $mem['SwapPeak'] += $swapdevice['PeakUsage'] * 1024;
        }
        
        return $mem;
    }
}

class Linux {
    public $os = 'Linux';
    
    function loadavg() {
        $buf = file_get_contents('/proc/stat');
        $nums=preg_split("/\s+/", substr($buf,0,strpos($buf,"\n")));
        return Array('busy' => $nums[1]+$nums[2]+$num[3], 'idle' => $nums[4]);
    }
    
    function memory() {
        preg_match_all('/^(MemTotal|MemFree|Cached|Buffers|SwapCached|SwapTotal|SwapFree):\s+(.*)\s*kB/im', file_get_contents('/proc/meminfo'), $matches);
        
        $mem['MemUsed'] = $mem['MemTotal'] - $mem['MemFree'] - $mem['Cached'] - $mem['Buffers'];
        $mem['SwapUsed'] = $mem['SwapTotal'] - $mem['SwapFree'] - $mem['SwapCached'];
        
        $mem = array_combine( $matches[1], $matches[2] );
        return $mem;
    }
}
