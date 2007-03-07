<?php

/**
 * Data file for Pacific/Saipan timezone, compiled from the olson data.
 *
 * Auto-generated by the phing olson task on 03/06/2007 23:48:48
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
      'rawOffset' => 34980,
      'dstOffset' => 0,
      'name' => 'LMT',
    ),
    1 => 
    array (
      'rawOffset' => 32400,
      'dstOffset' => 0,
      'name' => 'MPT',
    ),
    2 => 
    array (
      'rawOffset' => 36000,
      'dstOffset' => 0,
      'name' => 'MPT',
    ),
    3 => 
    array (
      'rawOffset' => 36000,
      'dstOffset' => 0,
      'name' => 'ChST',
    ),
  ),
  'rules' => 
  array (
    0 => 
    array (
      'time' => -3944626980,
      'type' => 0,
    ),
    1 => 
    array (
      'time' => -2177487780,
      'type' => 1,
    ),
    2 => 
    array (
      'time' => -7981200,
      'type' => 2,
    ),
    3 => 
    array (
      'time' => 977493600,
      'type' => 3,
    ),
  ),
  'finalRule' => 
  array (
    'type' => 'static',
    'name' => 'ChST',
    'offset' => 36000,
    'startYear' => 2001,
  ),
  'name' => 'Pacific/Saipan',
);

?>