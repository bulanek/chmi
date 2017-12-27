<?php
namespace Drupal\chmi\Form;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\SettingsCommand;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Database\DatabaseException;
use Drupal\chmi\Plugin\Database;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\UpdateBuildIdCommand;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\AppendCommand;

const TABLES = [[Database::HYDRO_DB_MAIN_TABLE_NAME, Database::HYDRO_DB_STATION_TABLE_NAME],
            [Database::METEO_DB_MAIN_TABLE_NAME, Database::METEO_DB_STATION_TABLE_NAME]];

function assert_access_denied($file, $line, $code, $desc = null ) {
    printf("Error occured, $file ($line), $code\n");
    throw new AccessDeniedHttpException("Assert failed");
}

assert_options(ASSERT_CALLBACK, 'assert_access_denied');

/**
 * Controller for Hooks example description page.
 *
 * This class uses the DescriptionTemplateTrait to display text we put in the
 * templates/description.html.twig file.
 */
class JavascriptForm extends ConfigFormBase {
    use StringTranslationTrait;

    protected $database;
    protected $tables;
    // Current station id
    protected $station_id;
    protected $last_stations_data;

    function __construct() {
        $this->database = \Drupal::database();
        $this->station_id = 1;
        $this->tables = new \stdClass();
        $this->tables->main_db = TABLES[0][0];
        $this->tables->station_db = TABLES[0][1];
        $this->last_stations_data = array();
        $schema = $this->database->schema();

        assert(($schema->tableExists(TABLES[0][0]) == true) 
            && ($schema->tableExists(TABLES[0][1]) == true), 
            sprintf("Tables %s or %s not in database!", TABLES[0][0], TABLES[0][1]));
        assert(($schema->tableExists(TABLES[1][0]) == true) 
            && ($schema->tableExists(TABLES[1][1]) == true), 
            sprintf("Tables %s or %s not in database!", TABLES[1][0], TABLES[1][1]));
        $this->last_stations_data[$this->tables->main_db] = $this->getLastStationsData();
    }

    /**
     *
     * {@inheritdoc}
     */
    public function getFormId() { return 'chmi_js'; }

    /**
     *
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['#theme'] = 'graph';
        $form['db_change_wrapper'] = [
            '#type' => 'container',
            '#attributes' => [
                'id' => 'db_change-wrapper'
            ]
        ];
        $form['db_change_wrapper']['db_graph_wrapper'] = [
            '#type' => 'container',
            '#attributes' => [
                'id' => 'graph_chmi'],
        ];

        $this->buildSelection($form, $form_state);
        $this->buildTable($form, $form_state);
        $this->buildGraph($form, $form_state);
        return $form;
    }

    private function buildSelection(array & $form, FormStateInterface & $form_state) {

        $form['db_select'] = array(
            '#type' => 'select',
            '#title' => $this->t("Select hydro or meteo"),
            '#options' => array(
                'hydro',
                'meteo',
            ),
            '#ajax' => array(
                'callback' => '::change_db',
                'progress' => array(
                    'type' => 'throbber',
                    'message' => 'Update'
                )
            )
        );

		$db_select = intval ( $form_state->getValue('db_select'));
        $db_name = $form['db_select']['#options'][$db_select];
        $options_observable = array();

		switch($db_name) {
			case 'hydro':
				$this->tables->main_db = TABLES[0][0];
				$this->tables->station_db = TABLES[0][1];
                $options_observable = array( 'temperature', 'altitude', 'flow');

				break;
			case 'meteo':
				$this->tables->main_db = TABLES[1][0];
				$this->tables->station_db = TABLES[1][1];
                $options_observable = array('temperature', 'pressure');

				break;
		}

        if (!array_key_exists($this->tables->main_db, $this->last_stations_data)) {
            $this->last_stations_data[$this->tables->main_db] = $this->getLastStationsData();
        }

        $tableId = array_map(function ($stationData) {
        return $stationData->station_id;
        }, $this->last_stations_data[$this->tables->main_db]);
        $form['db_change_wrapper']['station_select'] = array(
            '#type' => 'select',
            '#title' => $this->t("Select station"),
            '#id' => 'station_select_id',
            '#options' => $tableId,
            '#ajax' => array(
                'callback' => '::change_graph',
                'event' => 'change',
                'progress' => array(
                    'type' => 'throbber',
                    'message' => 'Update'
                )
            )
        );

        $form['db_change_wrapper']['observable_select'] = array(
            '#type' => 'select',
            '#title' => $this->t("Select observable"),
            '#id' => 'observable_select_id',
            '#options' => $options_observable,
            '#ajax' => array(
                'callback' => '::change_graph',
                'wrapper' => 'graph_chmi',
                'event' => 'change',
                'progress' => array(
                    'type' => 'throbber',
                    'message' => 'Update'
                )
            )
        );
    }

    private function buildTable(array& $form, FormStateInterface& $form_state) {
        $header = array(
            array( 'data' => $this->t('Id')),
            array( 'data' => $this->t('Name')),
            array( 'data' => $this->t('Time')),
            array( 'data' => $this->t('Temperature')));
        
        $form['db_change_wrapper']['station_table'] = array(
            '#type' => 'table',
            '#theme' => 'table',
            '#attributes' => array('class' => array('tablesorter'), 'id' => array('station_table')),  
            '#caption' => $this->t("Station table with the last data"),
            '#header' => $header
        );
        $counter = 0;
        foreach ($this->last_stations_data[$this->tables->main_db] as $stationData) {
            $form['db_change_wrapper']['station_table'][$counter]['Id'] = array(
                '#plain_text' => $stationData->station_id
            );
            $form['db_change_wrapper']['station_table'][$counter]['Name'] = array(
                '#plain_text' => $stationData->station
            );
            $form['db_change_wrapper']['station_table'][$counter]['Date and time'] = array(
                '#plain_text' => strftime("%d.%m.%Y %H:%m", $stationData->time)
            );
            $form['db_change_wrapper']['station_table'][$counter]['Temperature'] = array(
                '#plain_text' => $stationData->temperature
            );
            ++$counter;
        }
        $form['#attached']['library'][] = 'chmi/tablesorter';
    }
    
    private function buildGraph(array& $form, FormStateInterface& $form_state)
    {
        $station_id = 1;
        $observable = 'temperature';
        if (! empty($station_select_id)) {
            $station_id = intval($form['db_change_wrapper']['station_select']['#options'][intval($station_select_id)]);
        }
        if (! empty($observable_select_id)) {
            $observable = $form['db_change_wrapper']['observable_select']['#options'][$observable_select_id];
        }
        $resultData = $this->getStationData($station_id, $observable);
        if (count($resultData->time) == 0) return;
        $form['#attached']['drupalSettings']['graph_chmi']['station'] = $this->getStationName($station_id);
        $form['#attached']['drupalSettings']['graph_chmi']['observable'] = $observable;
        $form['#attached']['drupalSettings']['graph_chmi']['time'] = $resultData->time;
        $form['#attached']['drupalSettings']['graph_chmi']['values'] = $resultData->values;
        
        $form['#attached']['library'][] = 'chmi/graph_chmi';
        $form['#attached']['library'][] = 'jqplot/jqplot.canvasAxisLabelRenderer';
        $form['#attached']['library'][] = 'jqplot/jqplot.canvasAxisTicksRenderer';
        $form['#attached']['library'][] = 'jqplot/jqplot.canvasTextRenderer';
        $form['#attached']['library'][] = 'jqplot/jqplot.dateAxisRenderer';
    }
    
    /**
     * Get last recorded data for all stations.
     * @note  Zero size array is returned in case of DatabaseException (e.g. no db table) as well.
     *
     * @return array Array of stdClass data for particular station.
     */
    private function getLastStationsData(): array {
        $stationId = 1;
        $stationData = NULL;
        
        $stations = array();
        try {
            $query = $this->database->select($this->tables->main_db);
            $query->addField($this->tables->main_db, "station_id");
            $station_ids_query = $query->execute();
            assert($station_ids_query != NULL);

            foreach ($station_ids_query as $station_id) {
                $stationData = $this->getLastStationData($station_id->station_id);
                if ($stationData->temperature == 0) {
                    ++$stationId;
                    continue;
                }
                $stations[] = $stationData;
                ++$stationId;
            }
        } catch (DatabaseException $e) {
        }
        return $stations;
    }

  private function getStationName($stationId): string {
      $station = "";
      $query = $this->database->select($this->tables->main_db);
      $query->addField($this->tables->main_db, "station");
      $query->condition ( 'station_id', $stationId );
      $result = $query->execute();
      assert($result != NULL);
      $station = $result->fetchField();

      return $station;     
  }
  
  /**
   * Get last data entry for particular station.
   * 
   * @param int $stationId		
   * @return \stdClass		
   */
  private function getLastStationData(int $stationId): \stdClass {
		$stationTable = $this->tables->station_db;
        $query = $this->database->select($stationTable);
        $query->addExpression('MAX(time)');
        $query->condition('station_id', $stationId);
        $result = $query->execute();
        assert($result != NULL);
        $time = $result->fetchField();
        if ( $time == NULL)
        {
            $resultData = new \stdClass();
            $resultData->time = 0;
            $resultData->temperature = 0;
            return $resultData;
        }

        $query = $this->database->select($stationTable);
            
        $query->addField($stationTable, 'temperature');
        $query->addField($stationTable, 'time');
        $query->addField($stationTable, 'station_id');
        $query->condition('station_id', $stationId);
        $query->condition('time', $time);

        $resultData = NULL;       
        $result = $query->execute();
        assert($result != NULL);
        $resultData = $result->fetch();
        assert($resultData != NULL, sprintf("Failed to get the last data for station with id %i", $stationId));
        $resultData->station = $this->getStationName($stationId);
        return $resultData;     
  }

  public function change_graph(array &$form, FormStateInterface& $form_state) {
        $station_select_id = intval($form_state->getValue('station_select'));
        $station_id = intval($form['db_change_wrapper']['station_select']['#options'][$station_select_id]);

        $observable_select_id = intval($form_state->getValue('observable_select'));
        $observable = $form['db_change_wrapper']['observable_select']['#options'][$observable_select_id];
        $resultData = $this->getStationData($station_id, $observable);   
        
        $settings = array(
            'graph_chmi' => array(
                'station' => $this->getStationName($station_id),
                'time' => $resultData->time,
                'values' => $resultData->values,
						'observable' => $observable 
				) 
		);
		$response = new AjaxResponse ();
		$response->addCommand ( new SettingsCommand ( $settings ) );
		return $response;
	}

	public function change_db(array &$form, FormStateInterface & $form_state) {
		$db_select = intval ( $form_state->getValue('db_select'));
        $db_name = $form['db_select']['#options'][$db_select];
		switch($db_name)
		{
			case 'hydro':
				$this->tables->main_db = TABLES[0][0];
				$this->tables->station_db = TABLES[0][1];
				break;
			case 'meteo':
				$this->tables->main_db = TABLES[1][0];
				$this->tables->station_db = TABLES[1][1];
				break;
		}

        $schema = $this->database->schema();

        assert(($schema->tableExists($this->tables->main_db) == true) 
            && ($schema->tableExists($this->tables->station_db) == true), 
            sprintf("Tables %s or %s not in database!", $this->tables->main_db, $this->tables->station_db));
        if (!array_key_exists($this->tables->main_db, $this->last_stations_data)) {
            $this->last_stations_data[$this->tables->main_db] = $this->getLastStationsData();
        }

        
        $station_select_id = intval($form_state->getValue('station_select'));
        $station_id = intval($form['db_change_wrapper']['station_select']['#options'][$station_select_id]);

        $observable_select_id = intval($form_state->getValue('observable_select'));
        $observable = $form['db_change_wrapper']['observable_select']['#options'][$observable_select_id];
        $resultData = $this->getStationData($station_id, $observable);   
        
        $settings = array(
            'graph_chmi' => array(
                'station' => $this->getStationName($station_id),
                'time' => $resultData->time,
                'values' => $resultData->values,
                'observable' => $observable
            ),
            'stationsId' => $form['db_change_wrapper']['station_select']['#options'],
        );
        $response = new AjaxResponse();
        $response->addCommand(new SettingsCommand($settings));
        $response->addCommand(new ReplaceCommand('#station_table', $form['db_change_wrapper']['station_table']));
// //      NOTE BB: BUG https://www.drupal.org/project/drupal/issues/736066

        $response->addCommand(new RemoveCommand("select[id='observable_select_id'] > option"));
        $options = $form['db_change_wrapper']['observable_select']["#options"];
        $length = count($options);
        for($i=0;$i<count($options);++$i)
         {
             $response->addCommand(new AppendCommand("select[id='observable_select_id']","<option value=$i>$options[$i]</option>"));
         }
        $response->addCommand(new UpdateBuildIdCommand($form["#build_id_old"], $form["#build_id"]), TRUE);
		return $response;
  }

    /**
     * 
     * @param int $stationId
     *            Id of chmi station.
     * @param string $observable
     *            Data to check (temperature, altitude, flow).
     * @return \stdClass stdClass of array data (->time, ->{$observable}).
     * @retval  Zero size array in case of $see DatabaseException.
     */
    private function getStationData(int $stationId, string& $observable): \stdClass {
        $resultData = NULL;
        $stationTableName = $this->tables->station_db;
        $outData = new \stdClass();
        $outData->time = array();
        $outData->values = array();
        try {
            $query = $this->database->select($stationTableName);
            $query->addField($stationTableName, 'time');
            $query->addField($stationTableName, $observable);
            $query->condition('station_id', $stationId);
            $result = $query->execute();
            assert($result != NULL);
            $outData->time = array();
            $outData->values = array();
            foreach ($result as $tempTime) {
                $outData->time[] = $tempTime->time;
                $outData->values[] = $tempTime->{$observable};
            }
        } catch (DatabaseException $e) {}
        return $outData;
    }

   /**
   * {@inheritdoc}
   */
   protected function getEditableConfigNames() {}

   /**
   * {@inheritdoc}
   */
   protected function getModuleName()
    {return "chmi";}
}
