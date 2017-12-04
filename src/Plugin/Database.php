<?php 
namespace Drupal\chmi\Plugin;


final class Database{
	const HYDRO_MAIN_PAGE_ROOT = "http://hydro.chmi.cz";
	const HYDRO_MAIN_PAGE = "http://hydro.chmi.cz/hpps";
	const HYDRO_DB_MAIN_TABLE_NAME = 'hydro_main';
	const HYDRO_DB_STATION_TABLE_NAME = 'hydro_station';

	const METEO_MAIN_PAGE_ROOT = "http://www.in-pocasi.cz/meteostanice";
	const METEO_MAIN_PAGE = "http://www.in-pocasi.cz/meteostanice";
	const METEO_DB_MAIN_TABLE_NAME = 'meteo_main';
	const METEO_DB_STATION_TABLE_NAME = 'meteo_station';
	
	const METEO_SPEC_DB_MAIN = array(
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

	const METEO_SPEC_DB_STATION = array(
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
              'temperature' => array(
                'description' => 'Altitude of water',
                'type' => 'float',
              ),
              'pressure' => array(
                'description' => 'Watter flow',
                'type' => 'float',
              ),
              'rain' => array(
                'description' => 'Water temperature',
                'type' => 'float',
              ),
              'dev_point' => array(
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

const HYDRO_SPEC_DB_MAIN = array(
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

const HYDRO_SPEC_DB_STATION = array(
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
};