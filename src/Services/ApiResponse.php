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

    /**
     * Get all results from this response in an array format
     * Possible keys are success, error, exception
     */
    public function getAllResults(): array {
        try {
            $results = [];
            $successes = $this->getSuccesses();
            $errors = $this->getErrors();
            $exceptions = $this->getExceptions();
            if($exceptions !== []) {
                // no response from API
                $exceptionHeader = [];
                array_walk(
                    $exceptions,
                    function($exception, $key) use (&$exceptionHeader) {
                        $exceptionHeader[] = "(" . $exception->getCode() . ") " . get_class($exception);
                    }
                );
                $results['exception'] = $this->sanitiseResultValue(implode(", ", $exceptionHeader));
            }
            if($successes != []) {
                // has some success, the values are the ids from the response
                $results['success'] = $this->sanitiseResultValue(implode(", ", $successes));
            }
            if($errors != []) {
                // has some error
                $errorHeader = [];
                array_walk(
                    $errors,
                    function($error, $key) use (&$errorHeader) {
                        $code = isset($error->code) ? $error->code : "?";
                        $message = isset($error->message) ? $error->message : "?";
                        $errorHeader[] = "({$code}) {$message}";
                    }
                );
                $results['error'] = $this->sanitiseResultValue(implode(", ", $errorHeader));
            }
        } catch (\Exception $exception) {
        }
        return $results;
    }

    /**
     * Remove all non-ASCII chrs from the header value
     */
    private function sanitiseResultValue(string $value): string {
        return preg_replace("/[[:^ascii:]]+/", " ", $value);
    }

}
