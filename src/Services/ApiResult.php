<?php

namespace NSWDPC\Utilities\Cloudflare;

class ApiResult {

    protected $errors = [];
    protected $messages = [];
    protected $result = null;
    protected $success = false;

    protected $body = [];

    public function __construct(object $result, array $body = []) {
        $this->errors = $result->errors ?? [];
        $this->messages = $result->messages ?? [];
        $this->result = $result->result ?? null;
        $this->success = isset($result->success) && $result->success;
        $this->body = $body;
    }

    public function getErrors() : array {
        return $this->errors;
    }

    public function getResult() {
        return $this->result;
    }

    public function getBody() {
        return $this->body;
    }

    public function getId() : ?string {
        return $this->result->id ?? null;
    }

    public function isSuccess() : bool {
        return $this->success;
    }

    public function getMessages() : array {
        return $this->messages;
    }

}
