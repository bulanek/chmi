<?php

namespace Drupal\chmi\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\CronInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Form 
 */
class ChmiCronForm extends ConfigFormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The cron service.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected $cron;

  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queue;

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountInterface $current_user, 
                                        CronInterface $cron, QueueFactory $queue, StateInterface $state) {
    parent::__construct($config_factory);
    $this->currentUser = $current_user;
    $this->cron = $cron;
    $this->queue = $queue;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('cron'),
      $container->get('queue'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'chmi_cron';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
  	$form['#theme'] = 'cron';

    $form['status'] = [
      '#type' => 'details',
      '#title' => $this->t('Cron status information'),
      '#open' => TRUE,
    ];
    $next_execution = \Drupal::state()->get('chmi.next_execution');
    $next_execution = !empty($next_execution) ? $next_execution : \Drupal::time()->getRequestTime();

    $args = [
      '%time' => date_iso8601(\Drupal::state()->get('chmi.next_execution')),
      '%seconds' => $next_execution - \Drupal::time()->getRequestTime(),
    ];
    $form['status']['last'] = [
      '#type' => 'item',
      '#markup' => $this->t("Hydro and meteo update cron job  will execute at %time (%seconds seconds from now)", $args),
    ];

    if ($this->currentUser->hasPermission('administer site configuration')) {
      $form['cron_run'] = [
        '#type' => 'details',
        '#title' => $this->t('Run cron manually'),
        '#open' => TRUE,
      ];
		  $form['cron_run']['cron_reset'] = [
			'#type' => 'checkbox',
			'#title' => $this->t("Run chmi's cron regardless of whether interval has expired."),
			'#default_value' => FALSE
				];
          $form['cron_run']['cron_trigger']['actions'] = [
                '#type' => 'actions'
            ];
          $form['cron_run']['cron_trigger']['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Run cron now'),
        '#submit' => [[$this, 'cronRun']],
      ];
    }
    $form['link'] = [
    '#type' => 'details',
    '#title' => $this->t('Output data'),
    '#open' => TRUE,
    ];
   
    $form['link']['js_link'] = [
      '#title' => $this->t('Link to output data'),
      '#type' => 'link',
      '#url' => Url::fromRoute('graph_chmi'),
    ];

    $form['cron_queue_setup'] = [
      '#type' => 'details',
      '#title' => $this->t('Cron queue setup (for hook_cron_queue_info(), etc.)'),
      '#open' => TRUE,
    ];

    $queue_1 = $this->queue->get('hydro_queue');
    $queue_2 = $this->queue->get('meteo_queue');

    $args = [
      '%queue_1' => $queue_1->numberOfItems(),
      '%queue_2' => $queue_2->numberOfItems(),
    ];

    $form['cron_queue_setup']['current_cron_queue_status'] = [
      '#type' => 'item',
      '#markup' => $this->t("Queues: Hydro: %queue_1 Meteo: %queue_2", $args),
    ];
    $form['cron_queue_setup']['num_items'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of items to add to queue'),
      '#options' => array_combine([1, 5, 10, 100, 1000], [1, 5, 10, 100, 1000]),
      '#default_value' => 5,
    ];
    $form['cron_queue_setup']['queue'] = [
      '#type' => 'radios',
      '#title' => $this->t('Queue to add items to'),
      '#options' => [
        'hydro_queue' => $this->t('Hydro'),
        'meteo_queue' => $this->t('Meteo'),
      ],
      '#default_value' => 'hydro_queue',
    ];
    $form['cron_queue_setup']['actions'] = ['#type' => 'actions'];
    $form['cron_queue_setup']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add jobs to queue'),
      '#submit' => [[$this, 'addItems']],
    ];
    $form['cron_queue_setup']['actions']['remove'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove jobs from queue'),
      '#submit' => [[$this, 'removeItems']],
    ];

    $form['configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuration of chmi_cron()'),
      '#open' => TRUE,
    ];

    $config = $this->configFactory->get('chmi.settings');
    $form['configuration']['chmi_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Cron interval'),
      '#description' => $this->t('Time after which chmi_cron will respond to a processing request.'),
      '#default_value' => $config->get('interval'),
      '#options' => [
        3600 => $this->t('1 hour'),
        86400 => $this->t('1 day'),
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Allow user to directly execute cron, optionally forcing it.
   */
  public function cronRun(array &$form, FormStateInterface &$form_state) {
    $config = $this->configFactory->getEditable('chmi.settings');

    $cron_reset = $form_state->getValue('cron_reset');
    if (!empty($cron_reset)) {
      \Drupal::state()->set('chmi.next_execution', 0);
    }

    // Use a state variable to signal that cron was run manually from this form.
    $this->state->set('chmi_show_status_message', TRUE);
    if ($this->cron->run()) {
      drupal_set_message($this->t('Cron ran successfully.'));
    }
    else {
      drupal_set_message($this->t('Cron run failed.'), 'error');
    }
  }

  /**
   * Add the items to the queue when signaled by the form.
   */
  public function addItems(array &$form, FormStateInterface &$form_state) {
    $values = $form_state->getValues();
    $num_items = $form_state->getValue('num_items');
    // Queues are defined by a QueueWorker Plugin which are selected by their
    // id attribute.
    $queue = $this->queue->get($values['queue']);

    for ($i = 1; $i <= $num_items; $i++) {
      // Create a new item, a new data object, which is passed to the
      // QueueWorker's processItem() method.
      $item = new \stdClass();
      $item->created = \Drupal::time()->getRequestTime();
      $item->sequence = $i;
      $queue->createItem($item);
    }
    $queue_name = $form['cron_queue_setup']['queue'][$values['queue']]['#title'];
    $args = [
      '%num' => $num_items,
      '%queue' => $queue_name,
    ];
    drupal_set_message($this->t('Added %num items to %queue', $args));
  }

  /**
   * Add the items to the queue when signaled by the form.
   */
  public function removeItems(array &$form, FormStateInterface &$form_state) {
    $value = $form_state->getValue('queue');
    $queue = $this->queue->get($value);
    $queue->deleteQueue();
    drupal_set_message($this->t('Removed items from %queue', 
        ['%queue' => $form['cron_queue_setup']['queue'][$value]['#title']]));
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Update the interval as stored in configuration. This will be read when
    // this modules hook_cron function fires and will be used to ensure that
    // action is taken only after the appropiate time has elapsed.
    $this->configFactory->getEditable('chmi.settings')
      ->set('interval', $form_state->getValue('chmi_interval'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['chmi.settings'];
  }
}
