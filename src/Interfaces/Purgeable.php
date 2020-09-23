<?php

namespace NSWDPC\Utilities\Cloudflare;

/**
 * An interface with methods a purgeable dataobject must implement
 * @author James
 */
interface Purgeable {
    public function getPurgeValues();
    public function getPurgeTypes();
    public function getPurgeTypeValues($type);
}
