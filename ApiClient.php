<?php
require "vendor/autoload.php";
use GuzzleHttp\Client;

class ApiClient {
    protected static function _getSpec($host) {
        try {
            $client = new GuzzleHttp\Client(["base_uri" => $host]);

            return json_decode($client->request("GET", "/.json")->getBody());
        } catch (Exception $e) {
            throw new Exception("Cannot get spec file.");
        }
    }

    protected static function _call($request) {
        try {
            $client = new GuzzleHttp\Client(["base_uri" => $request->host]);

            $options = [
                "headers" => $request->headers,
            ];

            if (property_exists($request, "data")) {
                $options["json"] = $request->data;
            }

            $response = $client->request(
                $request->method,
                $request->query,
                $options
            );
            
            return json_decode($response->getBody());
        } catch (Exception $e) {
            if ($e->hasResponse()) {
                return json_decode($e->getResponse()->getBody()->getContents());
            } else {
                throw new Exception($e->getMessage());
            }
        }
    }

    public static function run($settings) {
        $settings = json_decode(json_encode($settings));
        $request = new stdClass();

        if (!property_exists($settings, "host")) {
            throw new Exception("API host is required.");
        }

        if (!property_exists($settings, "operationId")) {
            throw new Exception("Operation ID is required.");
        }                  

        $spec = self::_getSpec($settings->host);

        foreach ($spec->servers as $name => $server) {
            if ($server->protocol == "http") {
                $request->host = $server->path;
            }
        }

        if (!property_exists($request, "host")) {
            throw new Exception("No HTTP hosts specified in spec file.");
        }

        $request->query = $spec->operations->{$settings->operationId}->request->http->uri;
        $request->method = $spec->operations->{$settings->operationId}->request->http->method;
        $request->headers = [];

        if (property_exists($settings, "token")) {
            $request->headers = [
                "Authorization" => "Bearer " . $settings->token
            ];
        }     
        
        if (property_exists($settings, "data")) {
            $request->data = $settings->data;
        }         

        if (!property_exists($request, "query") || !property_exists($request, "method")) {
            throw new Exception("Unable to get HTTP route from spec file.");
        }

        preg_match_all("/{([^}]+)}/", $request->query, $params);

        if (count($params) > 1) {
            foreach ($params[1] as $param) {
                if (!property_exists($settings->params, $param)) {
                    throw new Exception("Parameter missing in request: " . $param);
                }

                $request->query = str_replace("{" . $param . "}", $settings->params->$param, $request->query);
            }
        }

        return self::_call($request);
    }
}