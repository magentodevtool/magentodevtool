<?php

namespace TimeEstimation;

Class Storage
{

    public function save($name, $te)
    {

        $return = $this->request(
            'save',
            [
                'name' => $name,
                'te' => $te,
                'user' => LDAP_USER,
            ]
        );

        if (!isset($return->id) || empty($return->id)) {
            error('Invalid response received (id not found), see return: ' . json_encode($return));
        }

        return static::getLinkById($return->id);
    }

    static public function getLinkById($id)
    {
        return CENTR_HOST . '?view=timeEstimation/details2&id=' . $id;
    }

    public function load($id)
    {
        $teJson = $this->request(
            'load',
            ['id' => $id,]
        );
        $te = @json_decode($teJson);
        if (!$te) {
            error("Invalid json received: $teJson");
        }
        return $te;
    }

    protected function request($action, $ARG)
    {
        $url = CENTR_HOST . "?action=timeEstimation/api/$action";
        $headers = [
            'API-Key: ' . TE_API_KEY,
            'User-Agent: Chrome',
            "Referer: " . CENTR_HOST,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'ARG' => json_encode($ARG),
            'underground' => 1,
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $output = curl_exec($ch);
        if ($output === false) {
            error('CURL error: ' . curl_error($ch));
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode !== 200) {
            error("CURL error: HTTPS response status \"$httpCode\"");
        }
        curl_close($ch);

        $result = @json_decode($output);
        if (!$result) {
            error('Invalid json received, see output: ' . $output);
        }

        if (isset($result->exception)) {
            error('Exception received: ' . $result->exception->message);
        }

        return $result->return;
    }
}