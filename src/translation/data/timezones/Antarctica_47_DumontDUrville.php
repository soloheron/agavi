<?php

/**
 * Data file for Antarctica/DumontDUrville timezone, compiled from the olson data.
 *
 * Auto-generated by the phing olson task on 01/01/2008 04:45:33
 *
 * @package    agavi
 * @subpackage translation
 *
 * @copyright  Authors
 * @copyright  The Agavi Project
 *
 * @since      0.11.0
 *
 * @version    $Id$
 */

return array (
  'types' => 
  array (
    0 => 
    array (
      'rawOffset' => 36000,
      'dstOffset' => 0,
      'name' => 'PMT',
    ),
    1 => 
    array (
      'rawOffset' => 0,
      'dstOffset' => 0,
      'name' => 'zzz',
    ),
    2 => 
    array (
      'rawOffset' => 36000,
      'dstOffset' => 0,
      'name' => 'DDUT',
    ),
  ),
  'rules' => 
  array (
    0 => 
    array (
      'time' => -725846400,
      'type' => 0,
    ),
    1 => 
    array (
      'time' => -566992800,
      'type' => 1,
    ),
    2 => 
    array (
      'time' => -415497600,
      'type' => 2,
    ),
  ),
  'finalRule' => 
  array (
    'type' => 'static',
    'name' => 'DDUT',
    'offset' => 36000,
    'startYear' => 1957,
  ),
  'name' => 'Antarctica/DumontDUrville',
);

?>