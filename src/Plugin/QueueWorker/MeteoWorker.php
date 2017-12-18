<?php 
namespace Drupal\chmi\Plugin\QueueWorker;
header('Content-type: text/plain; charset=utf-8');
use Drupal\Component\Utility\Html;
use Drupal\chmi\Plugin\Database;


/**
 * A report worker.
 *
 * @QueueWorker(
 *   id = "meteo_queue",
 *   title = @Translation("Worker in meteo"),
 *   cron = {"time" = 24}
 * )
 *
 * QueueWorkers are new in Drupal 8. They define a queue, which in this case
 * is identified as chmi_queue and contain a process that operates on
 * all the data given to the queue.
 *
 * @see queue_example.module
 */
class MeteoWorker extends ReportWorkerBase  {

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
        $rawData = file_get_contents(Database::METEO_MAIN_PAGE, true);       
        if ($rawData != false)
        {
            $this->processData($rawData);
        }
    }
    
    private function createTables()
    {
        $schema = $this->database->schema();
        if ( $schema->tableExists(Database::METEO_DB_MAIN_TABLE_NAME) == false) {
            $schema->createTable(Database::METEO_DB_MAIN_TABLE_NAME, Database::METEO_SPEC_DB_MAIN);
        }
        if ($schema->tableExists(Database::METEO_DB_STATION_TABLE_NAME) == false) {
            $schema->createTable(Database::METEO_DB_STATION_TABLE_NAME, Database::METEO_SPEC_DB_STATION);
        }
    }

    private function processData($rawData)
    {
        $rawData = mb_convert_encoding($rawData, 'html-entities', 'utf-8');
        $domData = Html::load($rawData);
        $domList = $domData->getElementsByTagName("div");
        foreach ($domList as $domStation) {
            if ($domStation->getAttribute('class') != 'meteostation') continue;
            $stationHref = $domStation->getElementsByTagName('a')->item(0)->getAttribute('href');
            $stationName = "no_name";
            foreach ($domStation->getElementsByTagName('span') as $stationNameNode)
            {
                if ($stationNameNode->getAttribute('class') == 'station_name_right') {
                    $stationName = $stationNameNode->nodeValue;
                }
            }
            $matches = array();
            preg_match("/^\s*?([\p{L}\p{P}-]+.*?)\s*?$/u", $stationName, $matches, PREG_OFFSET_CAPTURE);
            $stationName = $matches[1][0];
            
            $query = $this->database->upsert(Database::METEO_DB_MAIN_TABLE_NAME);
            $query->fields([ 'station', 'href' ]);
            $query->key('station_id');
            $query->values([ $stationName, $stationHref ]);
            $query->execute();
            
            $query = $this->database->select(Database::METEO_DB_MAIN_TABLE_NAME);
            $query->addField(NULL, 'station_id');
            $query->condition('station', $stationName);
            $result = $query->execute();
            assert($result != NULL);
            $stationId = $result->fetch();
            $rawDataStation = file_get_contents(sprintf("%s/%s", Database::METEO_MAIN_PAGE_ROOT, $stationHref));
            if ($rawDataStation == false) continue;
            $this->processStationData($stationId->station_id, $rawDataStation);
        }
    }


    private function processStationData($stationId, $rawDataStation): bool {
        $domData = Html::load($rawDataStation);
        $domList = $domData->getElementsByTagName("table");

        $stationDataDb = $this->getDataFromDom($domList);
        $record_id = $this->getRecordIdFromDb($stationId, $stationDataDb['time']);
        $stationDataDb['station_id'] = $stationId;
        $stationDataDb['record_id'] = $record_id;

        $query = $this->database->upsert(Database::METEO_DB_STATION_TABLE_NAME);
        $keys = array_keys($stationDataDb);

        $query->fields($keys, array_values($stationDataDb));
        $query->key(['station_id','record_id']);
        $query->execute();
        return True;
    }
    
    /**
     * 
     * @param \DOMNodeList $domList List of found <table> DOM elements.
     * @return array Final data for database with key eq. db keyword already.
     */
    private function getDataFromDom(\DOMNodeList& $domList) {
        $stationDbData = array();
        for($i = 0; $i < $domList->length; ++$i) {
            if ($domList->item($i)->getAttribute('class') == 'oblastits') {
                for ($j = 0; $j < 2; ++$j) {
                    $header_data = $domList->item($i + $j)
                                    ->getElementsByTagName('tr')->item(0)->getElementsByTagName('td');
                    $station_data = $domList->item($i + $j)
                                    ->getElementsByTagName('tr')->item(1)->getElementsByTagName('td'); 
                    assert($header_data->length == $station_data->length);
                    $this->fillStationData($header_data, $station_data, $stationDbData);
                }
                break;
            }
        }
        return $stationDbData;
    }
    
    /**
     * 
     * @param \DOMNodeList $header_data     Raw string of header table data from html site.
     * @param \DOMNodeList $station_data    Raw string of values of station data from html site.
     * @param array $stationRawData         Created array with key eq. db keyword already. @note mi
     */
    private function fillStationData(\DOMNodeList $header_data, \DOMNodeList $station_data, array& $stationRawData) {
        $matches = array();
        for ($j = 0; $j < $header_data->length; ++$j) {
            preg_match("(\s*(.*)\s*)", $header_data->item($j)->nodeValue,
                                                            $matches, PREG_OFFSET_CAPTURE);
            if (array_key_exists($matches[1][0], MeteoDataParser::NAME_TO_DB)) {
                $header = $matches[1][0];
                $key = MeteoDataParser::NAME_TO_DB[$header][0];
                $value = $station_data->item($j)->nodeValue;
                $stationRawData[$key] = call_user_func(array( 'Drupal\chmi\Plugin\QueueWorker\MeteoDataParser',
                        MeteoDataParser::NAME_TO_DB[$header][1]), $value );
            }
        }
    }
    
    private function getRecordIdFromDb($stationId, $time): int
    {
        $record_id = 1;
        $query = $this->database->select(Database::METEO_DB_STATION_TABLE_NAME);
        $query->condition('station_id', $stationId);
        $query->addExpression('MAX(record_id)');
        $result = $query->execute();
        if ($result != NULL) {
            $fetch = $result->fetchField();
            if ($fetch != null) $record_id = $fetch + 1;
        }
        // In case of existing time
        $query->condition('time', $time);
        $result = $query->execute();
        if ($result != NULL) {
            $fetch = $result->fetchField();
            if ($fetch != null) $record_id = $fetch;
        }
        return $record_id;
    }


}

