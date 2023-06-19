<?php

namespace Tots\CloudTask\Services;

use Google\Cloud\Tasks\V2\AppEngineHttpRequest;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\Task;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Protobuf\Timestamp;

class TaskService
{
    /**
     *
     * @var array
     */
    protected $config = [];
    /**
     *
     * @var string
     */
    protected $projectId = '';
    /**
     *
     * @var string
     */
    protected $locationId = '';
    /**
     *
     * @var string
     */
    protected $queueId = '';
    /**
     * 
     * @var string
     */
    protected $secretKey = '';
    /**
     *
     * @var boolean
     */
    protected $isActive = false;
    /**
     * @var CloudTasksClient
     */
    protected $client;

    public function __construct($config)
    {
        $this->config = $config;
        $this->processConfig();

        try {
            $this->client = new CloudTasksClient();
        } catch (\Throwable $th) { }
    }

    protected function executeTaskInSameThread($taskClassName, $params)
    {
        $task = new $taskClassName();
        return $task->run($params);
    }

    public function executeTask($taskClassName, $params, $path = '/task/handler', $queueId = null)
    {
        if(!$this->isActive){   
            $this->executeTaskInSameThread($taskClassName, $params);
            return;
        }

        try {
            $params['tots_task_name'] = $taskClassName;
            $this->addTask($queueId ?? $this->queueId, $path, $params);
        } catch (\Throwable $th) {
            $this->executeTaskInSameThread($taskClassName, $params);
        }
    }

    public function addTaskHttp($queueId, $url, $params, \DateTime $scheduleTime = null)
    {
        // Create an App Engine Http Request Object.
        $httpRequest = new HttpRequest();
        $httpRequest->setUrl($url);
        // POST is the default HTTP method, but any HTTP method can be used.
        $httpRequest->setHttpMethod(HttpMethod::POST);
        // Setting a body value is only compatible with HTTP POST and PUT requests.
        $httpRequest->setHeaders(['Content-Type' => 'application/json']);
        $httpRequest->setBody(json_encode($params));
        // Create a Cloud Task object.
        $task = new Task();
        $task->setHttpRequest($httpRequest);

        if($scheduleTime != null){
            $timestamp = new Timestamp();
            $timestamp->fromDateTime($scheduleTime);
            $task->setScheduleTime($timestamp);
        }

        // Create Queue
        $queueName = $this->client->queueName($this->projectId, $this->locationId, $queueId);

        // Send request and print the task name.
        return $this->client->createTask($queueName, $task);
    }

    public function addTask($queueId, $path, $params, $service = null, \DateTime $scheduleTime = null)
    {
        // Create an App Engine Http Request Object.
        $httpRequest = new AppEngineHttpRequest();
        // The path of the HTTP request to the App Engine service.
        $httpRequest->setRelativeUri($path);

        if($service !== null){
            $routing = new \Google\Cloud\Tasks\V2\AppEngineRouting();
            $routing->setService($service);

            $httpRequest->setAppEngineRouting($routing);
        }

        // POST is the default HTTP method, but any HTTP method can be used.
        $httpRequest->setHttpMethod(HttpMethod::POST);
        // Add Secret Key
        $params['secret_key'] = $this->secretKey;
        // Setting a body value is only compatible with HTTP POST and PUT requests.
        $httpRequest->setHeaders(['Content-Type' => 'application/json']);
        $httpRequest->setBody(json_encode($params));

        // Create a Cloud Task object.
        $task = new Task();
        $task->setAppEngineHttpRequest($httpRequest);

        if($scheduleTime != null){
            $timestamp = new Timestamp();
            $timestamp->fromDateTime($scheduleTime);
            $task->setScheduleTime($timestamp);
        }

        // Create Queue
        $queueName = $this->client->queueName($this->projectId, $this->locationId, $queueId);

        // Send request and print the task name.
        return $this->client->createTask($queueName, $task);
    }

    /**
     * Verify if secret key is valid
     */
    public function isValidSecretKey($key) : bool
    {
        if($this->secretKey == $key){
            return true;
        }

        return false;
    }

    /**
     * Remove task
     *
     * @param string $taskId
     */
    public function removeTask($taskId)
    {
        return $this->client->deleteTask($taskId);
    }

    protected function processConfig()
    {
        if(array_key_exists('project_id', $this->config)){
            $this->projectId = $this->config['project_id'];
        }
        if(array_key_exists('location_id', $this->config)){
            $this->locationId = $this->config['location_id'];
        }
        if(array_key_exists('queue_id', $this->config)){
            $this->queueId = $this->config['queue_id'];
        }
        if(array_key_exists('secret_key', $this->config)){
            $this->secretKey = $this->config['secret_key'];
        }
        if(array_key_exists('is_active', $this->config) && $this->config['is_active'] == 1){
            $this->isActive = true;
        }
    }
}
