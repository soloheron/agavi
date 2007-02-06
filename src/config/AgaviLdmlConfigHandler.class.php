<?php

// +---------------------------------------------------------------------------+
// | This file is part of the Agavi package.                                   |
// | Copyright (c) 2003-2007 the Agavi Project.                                |
// |                                                                           |
// | For the full copyright and license information, please view the LICENSE   |
// | file that was distributed with this source code. You can also view the    |
// | LICENSE file online at http://www.agavi.org/LICENSE.txt                   |
// |   vi: set noexpandtab:                                                    |
// |   Local Variables:                                                        |
// |   indent-tabs-mode: t                                                     |
// |   End:                                                                    |
// +---------------------------------------------------------------------------+

/**
 * AgaviLdmlConfigHandler allows you to parse ldml files into an array.
 *
 * @package    agavi
 * @subpackage config
 *
 * @author     Dominik del Bondio <ddb@bitxtender.com>
 * @copyright  Authors
 * @copyright  The Agavi Project
 * @since      0.11.0
 *
 * @version    $Id$
 */
class AgaviLdmlConfigHandler extends AgaviConfigHandler
{
	protected $nodeRefs = array();

	/**
	 * Execute this configuration handler.
	 *
	 * @param      string An absolute filesystem path to a configuration file.
	 * @param      string An optional context in which we are currently running.
	 *
	 * @return     string Data to be written to a cache file.
	 *
	 * @throws     <b>AgaviUnreadableException</b> If a requested configuration
	 *                                             file does not exist or is not
	 *                                             readable.
	 * @throws     <b>AgaviParseException</b> If a requested configuration file is
	 *                                        improperly formatted.
	 *
	 * @author     Dominik del Bondio <ddb@bitxtender.com>
	 * @since      0.11.0
	 */
	public function execute($config, $context = null)
	{
		$pathParts = pathinfo($config);
		$lookupPaths = AgaviLocale::getLookupPath(substr($pathParts['basename'], 0, -strlen($pathParts['extension'])-1));
		$lookupPaths[] = 'root';

		$data = array(
			'layout' => array('orientation' => array('lines' => 'top-to-bottom', 'characters' => 'left-to-right')),
		);

		foreach(array_reverse($lookupPaths) as $basename) {
			$filePath = $pathParts['dirname'] . '/' . $basename . '.' . $pathParts['extension'];
			if(is_readable($filePath)) {
				$ldmlTree = AgaviConfigCache::parseConfig($filePath, false, $this->getValidationFile(), $this->parser);
				$this->prepareParentInformation($ldmlTree);
				$this->parseLdmlTree($ldmlTree->ldml, $data);
			}
		}

		$dayMap = array(
										'sun' => AgaviDateDefinitions::SUNDAY,
										'mon' => AgaviDateDefinitions::MONDAY,
										'tue' => AgaviDateDefinitions::TUESDAY,
										'wed' => AgaviDateDefinitions::WEDNESDAY,
										'thu' => AgaviDateDefinitions::THURSDAY,
										'fri' => AgaviDateDefinitions::FRIDAY,
										'sat' => AgaviDateDefinitions::SATURDAY,
		);

		// fix the day indices for all day fields
		foreach($data['calendars'] as $calKey => &$calValue) {
			// skip the 'default' => '' key => value pair
			if(is_array($calValue)) {
				if(isset($calValue['days']['format'])) {
					foreach($calValue['days']['format'] as $formatKey => &$formatValue) {
						if(is_array($formatValue)) {
							$newData = array();
							foreach($formatValue as $day => $value) {
								$newData[$dayMap[$day]] = $value;
							}
							$formatValue = $newData;
						}
					}
				}

				if(isset($calValue['days']['stand-alone'])) {
					foreach($calValue['days']['stand-alone'] as $formatKey => &$formatValue) {
						if(is_array($formatValue)) {
							$newData = array();
							foreach($formatValue as $day => $value) {
								$newData[$dayMap[$day]] = $value;
							}
							$formatValue = $newData;
						}
					}
				}
			}
		}

		$code = array();
		$code[] = 'return ' . var_export($data, true) . ';';

		// compile data
		$retval = "<?php\n" .
				  "// auto-generated by ".__CLASS__."\n" .
				  "// date: %s GMT\n%s\n?>";
		$retval = sprintf($retval, gmdate('m/d/Y H:i:s'), join("\n", $code));

		return $retval;
	}

	/**
	 * Prepares the parent information for the given ldml tree.
	 *
	 * @param      AgaviConfigValueHolder The ldml tree.
	 *
	 * @author     Dominik del Bondio <ddb@bitxtender.com>
	 * @since      0.11.0
	 */
	protected function prepareParentInformation($ldmlTree)
	{
		$this->nodeRefs = array();
		$i = 0;
		$ldmlTree->setAttribute('__agavi_node_id', $i);
		$ldmlTree->setAttribute('__agavi_parent_id', null);
		$this->nodeRefs[$i] = $ldmlTree;
		++$i;
		if($ldmlTree->hasChildren()) {
			$this->generateParentInformation($ldmlTree->getChildren(), $i, 0);
		}
	}

	/**
	 * Generates the parent information for the given ldml subtree.
	 *
	 * @param      AgaviConfigValueHolder The ldml node.
	 *
	 * @author     Dominik del Bondio <ddb@bitxtender.com>
	 * @since      0.11.0
	 */
	protected function generateParentInformation($childList, &$nextId, $parentId)
	{
		foreach($childList as $child) {
			$child->setAttribute('__agavi_node_id', $nextId);
			$child->setAttribute('__agavi_parent_id', $parentId);
			$this->nodeRefs[$nextId] = $child;
			++$nextId;
			if($child->hasChildren()) {
				$this->generateParentInformation($child->getChildren(), $nextId, $child->getAttribute('__agavi_node_id'));
			}
		}
	}

/*


array data format


 locale                  =
   language              = de|en|fr|..
   territory             = DE|AT|CH|..
   script                = Latn|...
   variant               = NYNORSK|...


 display Names           =
   languages             =
     [lId]               = localized language 
   scripts               =
     [sId]               = localized script name
   territories           = 
     [tId]               = localized territory name
   variants              = 
     [vId]               = localized variant name
   keys                  = 
     [key]               = localized key name
   measurementSystemNames=
     [mId]               = localized measurement system name
 

 layout                  =
   orientation           =
     lines               = top-to-bottom|bottom-to-top
     characters          = left-to-right|right-to-left

 delimiters              =
   quotationStart        = The quotation start symbol
   quotationEnd          = The quotation end symbol
   altQuotationStart     = The alternative quotation start symbol
   altQuotationEnd       = The alternative quotation end symbol


 calendars               =
   default               = The default calendar
   [cId]                 =
     months              =
       default           = format|stand-alone
       format            =
         default         = wide|abbreviated|narrow
         wide            =
           1|2|3|...     = The wide month name
         abbreviated     = 
           1|2|3|...     = The abbreviated month name
         narrow          = 
           1|2|3|...     = The narrow month name
       stand-alone       =
         default         = wide|abbreviated|narrow
         wide            =
           1|2|3|...     = The wide month name
         abbreviated     = 
           1|2|3|...     = The abbreviated month name
         narrow          = 
           1|2|3|...     = The narrow month name
     days                =
       default           = format|stand-alone
       format            =
         default         = wide|abbreviated|narrow
         wide            =
           mon|tue|...   = The wide day name
         abbreviated     = 
           ...
     quarters            =
       default           = format|stand-alone
       format            =
         default         = wide|abbreviated|narrow
         wide            =
           1|2|3|4       = The wide quarter name
         abbreviated     = 
           ...
     am                  = The locale string for am
     pm                  = The locale string for pm
     eras                =
       wide              =
         1|2|3|...       = The wide era name
       abbreviated       = 
         1|2|3|...       = The abbreviated era name
       narrow            = 
         1|2|3|...       = The narrow era name
     dateFormats         =
       default           = full|long|medium|short
       [dfId]            =
         pattern         = The date pattern
         displayName     = An optional format name
     timeFormats         =
       default           = full|long|medium|short
       [tfId]            =
         pattern         = The time pattern
         displayName     = An optional format name

     dateTimeFormats     =
       default           = full|long|medium|short
       formats           =
         [fId]           = pattern
       availableFormats  =
         [afId]          = The datetime pattern
       appendItems       =
         [aiId]          = pattern

     fields              =
       [fId]             =
         displayName     = The localized name for this field
         relatives       =
           [rId]         = The localized relative of this field

 timeZoneNames
   hourFormat            = 
   hoursFormat           = 
   gmtFormat             = 
   regionFormat          = 
   fallbackFormat        = 
   abbreviationFallback  = standard|...?
   singleCountries       =
     [id]                = timezone
   zones                 =
     [tzId]              =
       long              =
         generic         = 
         standard        = 
         daylight        = 
       short
         generic         = 
         standard        = 
         daylight        = 
       exemplarCity      = 

 numbers                 =
   symbols               =
     decimal             = .
     group               = ,
     list                = ;
     percentSign         = %
     nativeZeroDigit     = 0
     patternDigit        = #
     plusSign            = +
     minusSign           = -
     exponential         = E
     perMille            = ‰
     infinity            = ∞
     nan                 = ☹
   decimalFormats        =
     [dfId]              = pattern
   scientificFormats     =
     [sfId]              = pattern
   percentFormats        =
     [pfId]              = pattern
   currencyFormats       =
     [cfId]              = pattern
   currencySpacing       =
     beforeCurrency      =
       currencyMatch     = 
       surroundingMatch  = 
       insertBetween     = 
     afterCurrency       =
       currencyMatch     = 
       surroundingMatch  = 
       insertBetween     = 
   currencies            =
     [cId]               =
       displayName       = The locale display name
       symbol            = The symbol (or array when its a choice)

			
*/

	/**
	 * Generates the array used by AgaviLocale from an LDML tree.
	 *
	 * @param      AgaviConfigValueHolder The ldml tree.
	 * @param      array The array to store the parsed data to.
	 *
	 * @return     array The array with the data.
	 *
	 * @author     Dominik del Bondio <ddb@bitxtender.com>
	 * @since      0.11.0
	 */
	public function parseLdmlTree($ldmlTree, &$data)
	{

		if(isset($ldmlTree->identity)) {
			$data['locale']['language'] = $ldmlTree->identity->language->getAttribute('type');
			if(isset($ldmlTree->identity->territory)) {
				$data['locale']['territory'] = $ldmlTree->identity->territory->getAttribute('type');
			}
			if(isset($ldmlTree->identity->script)) {
				$data['locale']['script'] = $ldmlTree->identity->script->getAttribute('type');
			}
			if(isset($ldmlTree->identity->variant)) {
				$data['locale']['variant'] = $ldmlTree->identity->variant->getAttribute('type');
			}
		}

		if(isset($ldmlTree->localeDisplayNames)) {
			$ldn = $ldmlTree->localeDisplayNames;

			if(isset($ldn->languages)) {
				$data['displayNames']['languages'] = isset($data['displayNames']['languages']) ? $data['displayNames']['languages'] : array();
				$this->getTypeList($ldn->languages, $data['displayNames']['languages']);
			}

			if(isset($ldn->scripts)) {
				$data['displayNames']['scripts'] = isset($data['displayNames']['scripts']) ? $data['displayNames']['scripts'] : array();
				$this->getTypeList($ldn->scripts, $data['displayNames']['scripts']);
			}

			if(isset($ldn->territories)) {
				$data['displayNames']['territories'] = isset($data['displayNames']['territories']) ? $data['displayNames']['territories'] : array();
				$this->getTypeList($ldn->territories, $data['displayNames']['territories'], false);
			}

			if(isset($ldn->variants)) {
				$data['displayNames']['variants'] = isset($data['displayNames']['variants']) ? $data['displayNames']['variants'] : array();
				$this->getTypeList($ldn->variants, $data['displayNames']['variants']);
			}

			if(isset($ldn->keys)) {
				$data['displayNames']['keys'] = isset($data['displayNames']['keys']) ? $data['displayNames']['keys'] : array();
				$this->getTypeList($ldn->keys, $data['displayNames']['keys']);
			}

			/*
			// not needed right now
			if(isset($ldn->types)) {
			}
			*/

			if(isset($ldn->measurementSystemNames)) {
				$data['displayNames']['measurementSystemNames'] = isset($data['displayNames']['measurementSystemNames']) ? $data['displayNames']['measurementSystemNames'] : array();
				$this->getTypeList($ldn->measurementSystemNames, $data['displayNames']['measurementSystemNames']);
			}
		}

		if(isset($ldmlTree->layout->orientation)) {
			$ori = $ldmlTree->layout->orientation;

			$data['layout']['orientation']['lines'] = $ori->getAttribute('lines', $data['layout']['orientation']['lines']);
			$data['layout']['orientation']['characters'] = $ori->getAttribute('characters', $data['layout']['orientation']['characters']);
		}

		if(isset($ldmlTree->delimiters)) {
			$delims = $ldmlTree->delimiters;

			if(isset($delims->quotationStart)) {
				$data['delimiters']['quotationStart'] = $delims->quotationStart->getValue();
			}
			if(isset($delims->quotationEnd)) {
				$data['delimiters']['quotationEnd'] = $delims->quotationEnd->getValue();
			}
			if(isset($delims->alternateQuotationStart)) {
				$data['delimiters']['alternateQuotationStart'] = $delims->alternateQuotationStart->getValue();
			}
			if(isset($delims->alternateQuotationEnd)) {
				$data['delimiters']['alternateQuotationEnd'] = $delims->alternateQuotationEnd->getValue();
			}
		}


		if(isset($ldmlTree->dates)) {
			$dates = $ldmlTree->dates;

			if(isset($dates->calendars)) {
				$cals = $dates->calendars;

				foreach($cals as $calendar) {

					if($calendar->getName() == 'default') {
						$data['calendars']['default'] = $calendar->getAttribute('choice');
					} elseif($calendar->getName() == 'calendar') {
						$calendarName = $calendar->getAttribute('type');

						if(!isset($data['calendars'][$calendarName])) {
							$data['calendars'][$calendarName] = array();
						}

						if(isset($calendar->months)) {
							$this->getCalendarWidth($calendar->months, 'month', $data['calendars'][$calendarName]);
						}

						if(isset($calendar->days)) {
							$this->getCalendarWidth($calendar->days, 'day', $data['calendars'][$calendarName]);
						}

						if(isset($calendar->quarters)) {
							$this->getCalendarWidth($calendar->quarters, 'quarter', $data['calendars'][$calendarName]);
						}

						if(isset($calendar->am)) {
							$data['calendars'][$calendarName]['am'] = $calendar->am->getValue();
						}
						if(isset($calendar->pm)) {
							$data['calendars'][$calendarName]['pm'] = $calendar->pm->getValue();
						}

						if(isset($calendar->eras)) {
							if(isset($calendar->eras->eraNames)) {
								foreach($this->getChildsOrAlias($calendar->eras->eraNames) as $era) {
									$data['calendars'][$calendarName]['eras']['wide'][$era->getAttribute('type')] = $era->getValue();
								}
							}
							if(isset($calendar->eras->eraAbbr)) {
								foreach($this->getChildsOrAlias($calendar->eras->eraAbbr) as $era) {
									$data['calendars'][$calendarName]['eras']['abbreviated'][$era->getAttribute('type')] = $era->getValue();
								}
							}
							if(isset($calendar->eras->eraNarrow)) {
								foreach($this->getChildsOrAlias($calendar->eras->eraNarrow) as $era) {
									$data['calendars'][$calendarName]['eras']['narrow'][$era->getAttribute('type')] = $era->getValue();
								}
							}
						}


						if(isset($calendar->dateFormats)) {
							$this->getDateOrTimeFormats($calendar->dateFormats, 'dateFormat', $data['calendars'][$calendarName]);
						}
						if(isset($calendar->timeFormats)) {
							$this->getDateOrTimeFormats($calendar->timeFormats, 'timeFormat', $data['calendars'][$calendarName]);
						}

						if(isset($calendar->dateTimeFormats)) {
							$dtf = $calendar->dateTimeFormats;
							$data['calendars'][$calendarName]['dateTimeFormats']['default'] = isset($dtf->default) ? $dtf->default->getAttribute('choice') : '__default';

							$dtfItems = $this->getChildsOrAlias($dtf);
							foreach($dtfItems as $item) {
								if($item->getName() == 'dateTimeFormatLength') {
									if(isset($item->dateTimeFormat->pattern)) {
										$data['calendars'][$calendarName]['dateTimeFormats']['formats'][$item->getAttribute('type', '__default')] = $item->dateTimeFormat->pattern->getValue();
									} else {
										throw new AgaviException('unknown child content in dateTimeFormatLength tag');
									}
								} elseif($item->getName() == 'availableFormats') {
									foreach($item as $dateFormatItem) {
										if($dateFormatItem->getName() != 'dateFormatItem') {
											throw new AgaviException('unknown childtag "' . $dateFormatItem->getName() . '" in availableFormats tag');
										}
										$data['calendars'][$calendarName]['dateTimeFormats']['availableFormats'][$dateFormatItem->getAttribute('id')] = $dateFormatItem->getValue();
									}
								} elseif($item->getName() == 'appendItems') {
									foreach($item as $appendItem) {
										if($appendItem->getName() != 'appendItem') {
											throw new AgaviException('unknown childtag "' . $appendItem->getName() . '" in appendItems tag');
										}
										$data['calendars'][$calendarName]['dateTimeFormats']['appendItems'][$appendItem->getAttribute('request')] = $appendItem->getValue();
									}
								} elseif($item->getName() != 'default') {
									throw new AgaviException('unknown childtag "' . $item->getName() . '" in dateTimeFormats tag');
								}
							}
						}

						if(isset($calendar->fields)) {
							foreach($this->getChildsOrAlias($calendar->fields) as $field) {
								$type = $field->getAttribute('type');
								if(isset($field->displayName)) {
									$data['calendars'][$calendarName]['fields'][$type]['displayName'] = $field->displayName->getValue();
								}
								if(isset($field->relative)) {
									foreach($field as $relative) {
										if($relative->getName() == 'relative') {
											$data['calendars'][$calendarName]['fields'][$type]['relatives'][$relative->getAttribute('type')] = $relative->getValue();
										}
									}
								}
							}
						}
					} else {
						throw new Exception('unknown childtag "' . $calendar->getName() . '" in calendars tag');
					}
				}
			}
			
			if(isset($dates->timeZoneNames)) {
				$tzn = $dates->timeZoneNames;
				if(isset($tzn->hourFormat)) {
					$data['timeZoneNames']['hourFormat'] = $tzn->hourFormat->getValue();
				}
				if(isset($tzn->hoursFormat)) {
					$data['timeZoneNames']['hoursFormat'] = $tzn->hoursFormat->getValue();
				}
				if(isset($tzn->gmtFormat)) {
					$data['timeZoneNames']['gmtFormat'] = $tzn->gmtFormat->getValue();
				}
				if(isset($tzn->regionFormat)) {
					$data['timeZoneNames']['regionFormat'] = $tzn->regionFormat->getValue();
				}
				if(isset($tzn->fallbackFormat)) {
					$data['timeZoneNames']['fallbackFormat'] = $tzn->fallbackFormat->getValue();
				}
				if(isset($tzn->abbreviationFallback)) {
					$data['timeZoneNames']['abbreviationFallback'] = $tzn->abbreviationFallback->getAttribute('choice');
				}
				if(isset($tzn->singleCountries)) {
					$data['timeZoneNames']['singleCountries'] = explode(' ', $tzn->singleCountries->getAttribute('list'));
				}

				foreach($tzn as $zone) {
					$zoneName = $zone->getAttribute('type');
					if($zone->getName() == 'zone') {
						if(isset($zone->long->generic)) {
							$data['timeZoneNames']['zones'][$zoneName]['long']['generic'] = $zone->long->generic->getValue();
						}
						if(isset($zone->long->standard)) {
							$data['timeZoneNames']['zones'][$zoneName]['long']['standard'] = $zone->long->standard->getValue();
						}
						if(isset($zone->long->daylight)) {
							$data['timeZoneNames']['zones'][$zoneName]['long']['daylight'] = $zone->long->daylight->getValue();
						}
						if(isset($zone->short->generic)) {
							$data['timeZoneNames']['zones'][$zoneName]['short']['generic'] = $zone->short->generic->getValue();
						}
						if(isset($zone->short->standard)) {
							$data['timeZoneNames']['zones'][$zoneName]['short']['standard'] = $zone->short->standard->getValue();
						}
						if(isset($zone->short->daylight)) {
							$data['timeZoneNames']['zones'][$zoneName]['short']['daylight'] = $zone->short->daylight->getValue();
						}
						if(isset($zone->exemplarCity)) {
							$data['timeZoneNames']['zones'][$zoneName]['exemplarCity'] = $zone->exemplarCity->getValue();
						}

					}
				}
			}
		}

		if(isset($ldmlTree->numbers)) {
			$nums = $ldmlTree->numbers;
			if(!isset($data['numbers'])) {
				$data['numbers'] = array();
			}

			if(isset($nums->symbols)) {
				$syms = $nums->symbols;
				if(isset($syms->decimal)) {
					$data['numbers']['symbols']['decimal'] = $syms->decimal->getValue();
				}
				if(isset($syms->group)) {
					$data['numbers']['symbols']['group'] = $syms->group->getValue();
				}
				if(isset($syms->list)) {
					$data['numbers']['symbols']['list'] = $syms->list->getValue();
				}
				if(isset($syms->percentSign)) {
					$data['numbers']['symbols']['percentSign'] = $syms->percentSign->getValue();
				}
				if(isset($syms->nativeZeroDigit)) {
					$data['numbers']['symbols']['nativeZeroDigit'] = $syms->nativeZeroDigit->getValue();
				}
				if(isset($syms->patternDigit)) {
					$data['numbers']['symbols']['patternDigit'] = $syms->patternDigit->getValue();
				}
				if(isset($syms->plusSign)) {
					$data['numbers']['symbols']['plusSign'] = $syms->plusSign->getValue();
				}
				if(isset($syms->exponential)) {
					$data['numbers']['symbols']['exponential'] = $syms->exponential->getValue();
				}
				if(isset($syms->perMille)) {
					$data['numbers']['symbols']['perMille'] = $syms->perMille->getValue();
				}
				if(isset($syms->infinity)) {
					$data['numbers']['symbols']['infinity'] = $syms->infinity->getValue();
				}
				if(isset($syms->nan)) {
					$data['numbers']['symbols']['nan'] = $syms->nan->getValue();
				}
			}
			if(isset($nums->decimalFormats)) {
				$this->getNumberFormats($nums->decimalFormats, 'decimalFormat', $data['numbers']);
			}
			if(isset($nums->scientificFormats)) {
				$this->getNumberFormats($nums->scientificFormats, 'scientificFormat', $data['numbers']);
			}
			if(isset($nums->percentFormats)) {
				$this->getNumberFormats($nums->percentFormats, 'percentFormat', $data['numbers']);
			}
			if(isset($nums->currencyFormats)) {
				$cf = $nums->currencyFormats;


				foreach($this->getChildsOrAlias($cf) as $itemLength) {
					if($itemLength->getName() == 'default') {
						$data['numbers']['currencyFormats']['default'] = $itemLength->getAttribute('choice');
					} elseif($itemLength->getName() == 'currencyFormatLength') {
						$itemLengthName = $itemLength->getAttribute('type', '__default');

						foreach($this->getChildsOrAlias($itemLength) as $itemFormat) {
							if($itemFormat->getName() == 'currencyFormat') {
								if(isset($itemFormat->pattern)) {
									$data['numbers']['currencyFormats'][$itemLengthName] = $itemFormat->pattern->getValue();
								}
							} else {
								throw new Exception('unknown childtag "' . $itemFormat->getName() . '" in currencyFormatLength tag');
							}

						}
					} elseif($itemLength->getName() == 'currencySpacing') {

						if(isset($itemLength->beforeCurrency->currencyMatch)) {
							$data['numbers']['currencySpacing']['beforeCurrency']['currencyMatch'] = $itemLength->beforeCurrency->currencyMatch->getValue();
						}
						if(isset($itemLength->beforeCurrency->surroundingMatch)) {
							$data['numbers']['currencySpacing']['beforeCurrency']['surroundingMatch'] = $itemLength->beforeCurrency->surroundingMatch->getValue();
						}
						if(isset($itemLength->beforeCurrency->insertBetween)) {
							$data['numbers']['currencySpacing']['beforeCurrency']['insertBetween'] = $itemLength->beforeCurrency->insertBetween->getValue();
						}
						if(isset($itemLength->afterCurrency->currencyMatch)) {
							$data['numbers']['currencySpacing']['afterCurrency']['currencyMatch'] = $itemLength->afterCurrency->currencyMatch->getValue();
						}
						if(isset($itemLength->afterCurrency->surroundingMatch)) {
							$data['numbers']['currencySpacing']['afterCurrency']['surroundingMatch'] = $itemLength->afterCurrency->surroundingMatch->getValue();
						}
						if(isset($itemLength->afterCurrency->insertBetween)) {
							$data['numbers']['currencySpacing']['afterCurrency']['insertBetween'] = $itemLength->afterCurrency->insertBetween->getValue();
						}



					} else {
						throw new Exception('unknown childtag "' . $itemLength->getName() . '" in currencyFormats tag');
					}
				}
			}
			if(isset($nums->currencies)) {
				foreach($nums->currencies as $currency) {
					$name = $currency->getAttribute('type');
					if(isset($currency->displayName)) {
						$data['numbers']['currencies'][$name]['displayName'] = $currency->displayName->getValue();
					}
					if(isset($currency->symbol)) {
						$symbolValue = $currency->symbol->getValue();
						if($currency->symbol->getAttribute('choice') == 'true') {
							$symbolValue = explode('|', $symbolValue);
						}
						$data['numbers']['currencies'][$name]['symbol'] = $symbolValue;
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Gets the value of each node with a type attribute.
	 *
	 * @param      array List of AgaivConfigValueHolder items.
	 * @param      array The array to store the parsed data to.
	 *
	 * @return     array The array with the data.
	 *
	 * @author     Dominik del Bondio <ddb@bitxtender.com>
	 * @since      0.11.0
	 */
	protected function getTypeList($list, &$data)
	{
		// debug stuff to check if we missed any tags (lc = loop count)
		$lc = 0;
		foreach($list as $listItem) {
			$type = $listItem->getAttribute('type');

			if(!$listItem->hasAttribute('alt')) {
				$data[$type] = $listItem->getValue();
			}

			++$lc;
		}

		if($lc != count($list->getChildren())) {
			throw new AgaviException('wrong tagcount');
		}

		return $data;
	}

	/**
	 * Gets the calendar widths for the given item.
	 *
	 * @param      AgaivConfigValueHolder The item.
	 * @param      string The name of item.
	 * @param      array The array to store the parsed data to.
	 *
	 * @return     array The array with the data.
	 *
	 * @author     Dominik del Bondio <ddb@bitxtender.com>
	 * @since      0.11.0
	 */
	protected function getCalendarWidth($item, $name, &$data)
	{
		$dataIdxName = $name . 's';

		$items = $this->getChildsOrAlias($item);
		foreach($items as $itemContext) {
			if($itemContext->getName() == 'default') {
				$data[$dataIdxName]['default'] = $itemContext->getAttribute('choice');
			} elseif($itemContext->getName() == $name . 'Context') {
				$itemContextName = $itemContext->getAttribute('type');

				foreach($itemContext as $itemWidths) {
					if($itemWidths->getName() == 'default') {
						$data[$dataIdxName][$itemContextName]['default'] = $itemWidths->getAttribute('choice');
					} elseif($itemWidths->getName() == $name . 'Width') {
						$itemWidthName = $itemWidths->getAttribute('type');

						$widthChildItems = $this->getChildsOrAlias($itemWidths);
						foreach($widthChildItems as $item) {
							if($item->getName() != $name) {
								throw new Exception('unknown childtag "' . $item->getName() . '" in ' . $name . 'Widths tag');
							}

							if(!$item->hasAttribute('alt')) {
								$itemName = $item->getAttribute('type');
								$data[$dataIdxName][$itemContextName][$itemWidthName][$itemName] = $item->getValue();
							}
						}
					} else {
						throw new Exception('unknown childtag "' . $itemWidths->getName() . '" in ' . $name . 'Context tag');
					}

				}
			} else {
				throw new Exception('unknown childtag "' . $itemContext->getName() . '" in ' . $name . 's tag');
			}
		}
	}

	/**
	 * Gets the date or time formats the given item.
	 *
	 * @param      AgaivConfigValueHolder The item.
	 * @param      string The name of item.
	 * @param      array The array to store the parsed data to.
	 *
	 * @return     array The array with the data.
	 *
	 * @author     Dominik del Bondio <ddb@bitxtender.com>
	 * @since      0.11.0
	 */
	protected function getDateOrTimeFormats($item, $name, &$data)
	{
		$dataIdxName = $name . 's';

		$items = $this->getChildsOrAlias($item);
		foreach($items as $itemLength) {
			if($itemLength->getName() == 'default') {
				$data[$dataIdxName]['default'] = $itemLength->getAttribute('choice');
			} elseif($itemLength->getName() == $name . 'Length') {
				$itemLengthName = $itemLength->getAttribute('type', '__default');

				$aliasedItemLength = $this->getChildsOrAlias($itemLength);
				foreach($aliasedItemLength as $itemFormat) {
					if($itemFormat->getName() == $name) {
						if(isset($itemFormat->pattern)) {
							$data[$dataIdxName][$itemLengthName]['pattern'] = $itemFormat->pattern->getValue();
						}
						if(isset($itemFormat->displayName)) {
							$data[$dataIdxName][$itemLengthName]['displayName'] = $itemFormat->displayName->getValue();
						}
					} else {
						throw new Exception('unknown childtag "' . $itemFormat->getName() . '" in ' . $name . 'Length tag');
					}

				}
			} else {
				throw new Exception('unknown childtag "' . $itemLength->getName() . '" in ' . $name . 's tag');
			}
		}
	}

	/**
	 * Gets the number formats the given item.
	 *
	 * @param      AgaivConfigValueHolder The item.
	 * @param      string The name of item.
	 * @param      array The array to store the parsed data to.
	 *
	 * @return     array The array with the data.
	 *
	 * @author     Dominik del Bondio <ddb@bitxtender.com>
	 * @since      0.11.0
	 */
	protected function getNumberFormats($item, $name, &$data)
	{
		$dataIdxName = $name . 's';

		$items = $this->getChildsOrAlias($item);
		foreach($items as $itemLength) {
			if($itemLength->getName() == 'default') {
				$data[$dataIdxName]['default'] = $itemLength->getAttribute('choice');
			} elseif($itemLength->getName() == $name . 'Length') {
				$itemLengthName = $itemLength->getAttribute('type', '__default');

				foreach($this->getChildsOrAlias($itemLength) as $itemFormat) {
					if($itemFormat->getName() == $name) {
						if(isset($itemFormat->pattern)) {
							$data[$dataIdxName][$itemLengthName] = $itemFormat->pattern->getValue();
						}
					} else {
						throw new Exception('unknown childtag "' . $itemFormat->getName() . '" in ' . $name . 'Length tag');
					}

				}
			} else {
				throw new Exception('unknown childtag "' . $itemLength->getName() . '" in ' . $name . 's tag');
			}
		}
	}

	/**
	 * Resolves the alias LDML tag.
	 *
	 * @param      AgaivConfigValueHolder The item.
	 *
	 * @return     mixed Either the item if there is no alias or the resolved 
	 *                   alias.
	 *
	 * @author     Dominik del Bondio <ddb@bitxtender.com>
	 * @since      0.11.0
	 */
	protected function getChildsOrAlias($item)
	{
		if(isset($item->alias)) {
			$alias = $item->alias;
			if($alias->getAttribute('source') != 'locale') {
				throw new AgaviException('The alias handling doesn\'t support any source except locale (' . $alias->getAttribute('source') . ' was given)');
			}


			$pathParts = explode('/', $alias->getAttribute('path'));

			$currentNodeId = $item->getAttribute('__agavi_node_id');
			
			foreach($pathParts as $part) {
				// select the parent node
				if($part == '..') {
					$currentNodeId = $this->nodeRefs[$currentNodeId]->getAttribute('__agavi_parent_id');
				} else {
					$predicates = array();
					if(preg_match('#([^\[]+)\[([^\]]+)\]#', $part, $match)) {
						if(!preg_match('#@([^=]+)=\'([^\']+)\'#', $match[2], $predMatch)) {
							throw new AgaviException('Unknown predicate ' . $match[2] . ' in alias xpath spec');
						}
						$tagName = $match[1];
						$predicates[$predMatch[1]] = $predMatch[2];
					} else {
						$tagName = $part;
					}
					foreach($this->nodeRefs[$currentNodeId]->getChildren() as $childNode) {
						$isSearchedNode = false;
						if($childNode->getName() == $tagName) {
							$predMatches = 0;
							foreach($predicates as $attrib => $value) {
								if($childNode->getAttribute($attrib) == $value) {
									++$predMatches;
								}
							}
							if($predMatches == count($predicates)) {
								$isSearchedNode = true;
							}
						}

						if($isSearchedNode) {
							$currentNodeId = $childNode->getAttribute('__agavi_node_id');
						}
					}
				}
			}

			return $this->nodeRefs[$currentNodeId]->getChildren();
		} else {
			return $item;
		}
	}
}

?>