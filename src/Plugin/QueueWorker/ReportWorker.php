<?php 
namespace Drupal\chmi\Plugin\QueueWorker;
use Drupal\Component\Utility\Html;

const MAIN_PAGE_ROOT = "http://hydro.chmi.cz";
const MAIN_PAGE =  "http://hydro.chmi.cz/hpps";

const DB_MAIN_TABLE_NAME = 'hydro_main';
const DB_STATION_TABLE_NAME = 'hydro_station';
const SPEC_DB_MAIN = array(
            'description' => 'Main table of meteo data',
            'fields' => array(
              'station' => array(
                'description' => 'Name of the station',
                'type' => 'varchar',
                'length' => 155,
                'not null' => TRUE,
                'default' => '',
              ),
              'station_id' => array(
                'description' => 'Stations id',
                'type' => 'serial',
                'not null' => TRUE,
              ),
              'href' => array(
                'description' => 'Link to the station',
                'type' => 'varchar',
                'length' => 155,
                'not null' => TRUE,
                'default' => '',
              ),
            ),
            'primary key' => array('station_id'),
            'unique keys' => array('station' => array('station')),
          ); 

const SPEC_DB_STATION = array(
            'description' => 'Station table of hydro data',
            'fields' => array(
              'station_id' => array(
                'description' => 'Station identification',
                'type' => 'int',
                'not null' => TRUE,
              ),
              'record_id' => array(
                'description' => 'Station record identification',
                'type' => 'int',
                'not null' => TRUE,
              ),
              'time' => array(
                'description' => 'Time of measurement',
                'type' => 'int',
                'not null' => TRUE,
                'default' => 1,
              ),
              'altitude' => array(
                'description' => 'Altitude of water',
                'type' => 'float',
              ),
              'flow' => array(
                'description' => 'Watter flow',
                'type' => 'float',
              ),
              'temperature' => array(
                'description' => 'Water temperature',
                'type' => 'float',
              ),
            ),
            'primary key' => array('station_id','record_id'),
            'indexes' => array(
                'primary_key' => array('station_id','record_id'),
            ),
            'unique keys' => array('time_station' => array('time','station_id')),
          ); 


/**
 * A report worker.
 *
 * @QueueWorker(
 *   id = "chmi_queue",
 *   title = @Translation("Worker in chmi"),
 *   cron = {"time" = 24}
 * )
 *
 * QueueWorkers are new in Drupal 8. They define a queue, which in this case
 * is identified as chmi_queue and contain a process that operates on
 * all the data given to the queue.
 *
 * @see queue_example.module
 */
class ReportWorker extends ReportWorkerBase  {

      /**
       * The Database Connection.
       *
       * @var \Drupal\Core\Database\Connection
       */
      protected $database;

    /**
     *
     * {@inheritdoc}
     */

    function __construct()
    {
        $this->database = \Drupal::database();
        date_default_timezone_set('Europe/Prague');
    }

  /**
   * {@inheritdoc}
   */
    public function processItem($data)
    {
        // TODO BB: $data will be removed or the address
        $this->createTables();
        $rawData = file_get_contents(MAIN_PAGE, true);       
        if ($rawData != false)
        {
            $this->processData($rawData);
        }
    }
    
    private function createTables()
    {
        $schema = $this->database->schema();
        if ( $schema->tableExists(DB_MAIN_TABLE_NAME) == false) {
            $schema->createTable(DB_MAIN_TABLE_NAME, SPEC_DB_MAIN);
        }
        if ($schema->tableExists(DB_STATION_TABLE_NAME) == false) {
            $schema->createTable(DB_STATION_TABLE_NAME, SPEC_DB_STATION);
        }
    }

    private function processData($rawData)
    {
        $domData = Html::load($rawData);
        $domList = $domData->getElementsByTagName("area");
        foreach ($domList as $domStation) {

            $stationHref = $domStation->getAttribute("href");
            $stationName = $domStation->getAttribute("title");
            
            $query = $this->database->upsert(DB_MAIN_TABLE_NAME);
            $query->fields([ 'station', 'href' ]);
            $query->key('station_id');
            $query->values([ $stationName, $stationHref ]);
            $query->execute();
            
            $query = $this->database->select(DB_MAIN_TABLE_NAME);
            $query->addField(NULL, 'station_id');
            $query->condition('station', $stationName);
            $result = $query->execute();
            assert($result != NULL);
            $stationId = $result->fetch();
            $rawDataStation = file_get_contents(sprintf("%s/%s", MAIN_PAGE_ROOT, $stationHref));
            if ($rawDataStation == false) continue;
            $this->processStationData($stationId->station_id, $rawDataStation);
        }
    }

    private function processStationData($stationId, $rawDataStation): bool
    {
        $domData = Html::load($rawDataStation);
        $domList = $domData->getElementsByTagName("tr");
        
        foreach ($domList as $domStationData) {
            $domStationData = $domStationData->getElementsByTagName("td");
            $length = $domStationData->length;
            if (($domStationData->length != 4)) continue;
            if ($domStationData->item(0)->getAttribute('style') != "text-align:center;") continue;

            $stationData = $this->processStationDataDom($domStationData);
            $record_id = $this->getRecordIdFromDb($stationId, $stationData['time']);
            $query = $this->database->upsert(DB_STATION_TABLE_NAME);
            
            $query->fields([ "station_id", "record_id", "time", 'altitude', 'flow', 'temperature' ]); 
            $values = array_merge(array( $stationId, $record_id,), $stationData);
            $query->values($values);
            $query->key(['station_id','record_id']);
            if ($query->execute() == NULL) return False;
        }
        return True;
    }

      /**
       *
       * @return array
       */
    private function processStationDataDom($domStationData): array
    {
        $results = array();
        $dateTime = $domStationData->item(0)->nodeValue;
        $dateTimeObj = \DateTime::createFromFormat("d.m.Y H:i", $dateTime);
        $dateTimeUnix = date_timestamp_get($dateTimeObj);
        $results += array('time' => $dateTimeUnix);
        $results += array('altitude' => floatval($domStationData->item(1)->nodeValue));
        $results += array('flow' => floatval($domStationData->item(2)->nodeValue));
        $results += array('temperature' => floatval($domStationData->item(3)->nodeValue));
        return $results;
    }
    
    private function getRecordIdFromDb($stationId, $time): int
    {
        $record_id = 1;
        $query = $this->database->select(DB_STATION_TABLE_NAME);
        $query->condition('station_id', $stationId);
        $query->addExpression('MAX(record_id)');
        $result = $query->execute();
        if ($result != NULL)
        {
            $fetch = $result->fetchField();
            if ($fetch != null)
            {
                $record_id = $fetch + 1;
            }
        }
        // In case of existing time
        $query->condition('time', $time);
        $result = $query->execute();
        if ($result != NULL)
        {
            $fetch = $result->fetchField();
            if ($fetch != null)
            {
                $record_id = $fetch;
            }
        }
        return $record_id;
    }
};
