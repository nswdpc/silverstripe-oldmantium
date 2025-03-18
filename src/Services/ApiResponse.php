<?php

namespace NSWDPC\Utilities\Cloudflare;

use GuzzleHttp\ClientInterface;

class ApiResponse {

    protected $results = [];

    public function __construct() {
        $this->results = [];
    }

    /**
     * Add an API result to this response
     */
    public function addResult(ApiResult $result) : self {
        $this->results[] = $result;
        return $this;
    }

    public function getResults() : array {
        return $this->results;
    }

    public function getResultCount() : int {
        return count($this->results);
    }

    public function allSuccess() : bool {
        $result = array_filter(
            $this->results,
            function($r) {
                return $r->isSuccess();
            }
        );
        return count($result) == count($this->results);
    }

    public function hasErrors() : bool {
        $result = array_filter(
            $this->results,
            function($r) {
                return count($r->getErrors()) > 0;
            }
        );
        return count($result) > 0;
    }

    public function getErrors() : array {
        $errors = [];
        foreach($this->results as $result) {
            $errors = array_merge($errors, $result->getErrors());
        }
        return $errors;
    }

    public function getSuccesses() : array {
        $successes = [];
        foreach($this->results as $result) {
            if($result->isSuccess() && ($id = $result->getId()) ) {
                $successes[] = $id;
            }
        }
        return $successes;
    }

    /**
     * Get all the exceptions thrown, if any
     */
    public function getExceptions() : array {
        $exceptions = [];
        foreach($this->results as $result) {
            if($exception = $result->getException()) {
                $exceptions[] = $exception;
            }
        }
        return $exceptions;
    }

}
