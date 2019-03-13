<?php

namespace Project\Installation;

class Newrelic
{
    /**
     * @var \Project\Installation $inst
     */
    protected $inst;

    public function __construct($inst)
    {
        $this->inst = $inst;
    }

    /**
     * Retrieve NewRelic x-api-key.
     *
     * @return string x-api-key.
     */
    protected function getXApiKey()
    {
        if ($apiKey = $this->inst->project->getNode('newRelic/apiKey')) {
            return $apiKey;
        }

        return \Config::getNode('newRelic/apiKey');
    }

    /**
     * Parse NewRelic server deployment response.
     *
     * @param string $input String for parsing.
     *
     * @return array Parse result array.
     */
    protected function parseResponse($input)
    {
        $result = array();
        $xmlPart = strstr($input, '<?xml');
        $sxml = simplexml_load_string($xmlPart);
        if ($sxml === false) {
            return false;
        }
        if (isset($sxml->error)) {
            $result['error'] = $sxml->error;
        }
        if (isset($sxml->description)) {
            $result['Description'] = $sxml->description;
            $result['User'] = isset($sxml->user) ? $sxml->user : '';
            $result['Timestamp'] = isset($sxml->timestamp) ? date('Y-m-d H:i:s', strtotime($sxml->timestamp)) : '';
        }
        return $result;
    }

    public function getConfig()
    {

        static $config = array();
        if ($config) {
            return $config;
        }

        if (!$config = $this->inst->execRaiScriptbyUrl('newrelic/getConfig.php')) {
            error('Can\'t get new relic config. Check write rights on docroot and if project docroot is accessible by ' . $this->inst->domain . ' and there is no http auth.');
        }

        $config = (array)$config;
        $config['appname'] = explode(';', $config['appname'])[0];

        return $config;

    }

    /**
     * Send deployment event into New Relic server.
     *
     * @param string $tagName Git tag name.
     * @param string $description Deployment description.
     *
     * @return object If error occurs with New Relic success property will be set to false and error will contain message.
     */
    public function sendDeployment($tagName, $description)
    {
        $response = array('success' => false, 'error' => '');
        if ($this->inst->type === 'local') {
            $response['newrelicOutput'] = 'New Relic is not available on local installation.';
            return (object)$response;
        }

        $config = $this->getConfig();
        if (!$config['enabled']) {
            $response['newrelicOutput'] = 'New Relic is not available.';
            return (object)$response;
        }
        $deploymentEvent = array(
            'deployment' => array(
                'revision' => $tagName,
                'description' => $description,
                'user' => $this->inst->login,
                'environment' => $this->inst->name,
                'app_name' => $config['appname']
            ),
            'x-api-key' => $this->getXApiKey()
        );
        $spfResponse = $this->inst->spf('newrelic/sendDeployment', $deploymentEvent);
        if (!$spfResponse) {
            $response['newrelicOutput'] = '';
            $response['error'] = 'not correct arguments send to new relic SPF';
            return (object)$response;
        }

        if (!$spfResponse->success) {
            return $spfResponse;
        }

        $response['newrelicOutput'] = "Deployment event was sent to New Relic AppName " . $config['appname'] . PHP_EOL;
        $parsedResp = $this->parseResponse($spfResponse->response);
        if ($parsedResp === false) {
            $response['newrelicOutput'] .= '. Server response is not valid xml.';
            $response['error'] = 'server response is not valid xml.';
            return (object)$response;
        }

        if (is_array($parsedResp)) {
            $response['newrelicOutput'] .= isset($parsedResp['error']) ?
                'Error response: ' . PHP_EOL . $parsedResp['error'] : 'Successful response: ' . PHP_EOL;
            if (isset($parsedResp['error'])) {
                $response['error'] = $parsedResp['error'];
            } else {
                foreach ($parsedResp as $key => $value) {
                    $response['newrelicOutput'] .= $key . ' ' . $value . PHP_EOL;
                }
                $response['success'] = true;
            }
        }

        return (object)$response;
    }

    /**
     * Retrieve deployment description based on deployment info.
     *
     * @param $deployment
     */
    public function getDeploymentDescription($deployment)
    {
        $desc = array();
        $desc['tag'] = '';
        if ($deployment->type == 'staging') {
            $branches = implode(',', $deployment->branchesToDeploy);
            $desc['text'] = "Deployment to {$deployment->currentRemoteBranch} from branches {$branches} was done.";
        } else {
            // production
            $desc['tag'] = $deployment->newTagName;
            $desc['text'] = "Deployment for version {$deployment->newTagName} was done. Version was commented with {$deployment->newTagComment}.";
        }

        return (object)$desc;
    }
}
