<?php
namespace Drupal\chmi\Plugin\QueueWorker;
use Drupal\Component\Datetime\TimeInterface;


final class MeteoDataParser {
    
    const NAME_TO_DB = array(
        'Čas' => ['time', 'getTimeStamp'], 
        'Teplota' => ['temperature', 'getTemperatureDevPoint'],
        'Tlak' => ['pressure', 'getPressure'],
        'Srážky (dnes)' => ['rain', 'getRain'],
        'Rosný bod' => ['dev_point', 'getTemperatureDevPoint'],
    );
    

    static public function getTimeStamp($timeStr): int
    {
        list ($hour, $minute) = explode(':', $timeStr);
        $dateTime = new \DateTime();
        $dateTime->setTime($hour, $minute);
        return $dateTime->getTimestamp();
    }
    
    static public function getTemperatureDevPoint($tempStr)
    {
        $matches = array();
        $reg_return = preg_match("((\d+|\d+.\d+)\s+Â°C)", $tempStr, $matches, PREG_OFFSET_CAPTURE);
        return (float) $matches[1][0];
    }

    static public function getPressure($pressureStr)
    {
        $matches = array();
        preg_match("((\d+|\d+.\d+)\s+hPa)", $tempStr, $matches, PREG_OFFSET_CAPTURE);
        return (float) $matches[1][0];
    }

    static public function getRain($rainStr)
    {
        $matches = array();
        preg_match("((\d+|\d+.\d+)\s+mm)", $tempStr, $matches, PREG_OFFSET_CAPTURE);
        return (float) $matches[1][0];
    }

    static public function getHumidity($humidityStr)
    {
        $matches = array();
        preg_match("((\d+)\s*%)", $tempStr, $matches, PREG_OFFSET_CAPTURE);
        return (float) $matches[1][0];       
    }
}