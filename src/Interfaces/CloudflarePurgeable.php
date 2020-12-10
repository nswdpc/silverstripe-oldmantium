<?php

namespace NSWDPC\Utilities\Cloudflare;

/**
 * An interface with methods a purgeable dataobject must implement
 * @author James
 */
interface CloudflarePurgeable {
    public function getPurgeValues() : array;
    public function getPurgeTypes() : array;
    public function getPurgeRecordName() : string;
    public function getPurgeTypeValues($type) : array;
}
