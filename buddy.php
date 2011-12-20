<?php
/**
 * Loads the buddy gadget xml and replaces known tokens
 */

require_once(dirname(__FILE__) . '/code/gadget.php');

$xml = file_get_contents(dirname(__FILE__) . '/buddy/buddy.xml');

$output = gadget::replace_tokens($xml);

gadget::output($output);
