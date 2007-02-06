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
 * AgaviRotatingFileLoggerAppender extends AgaviFileLoggerAppender by enabling 
 * per-day log files and removing unwanted old log files.
 *
 * <b>Required parameters:</b>
 *
 * # <b>dir</b>    - [none]              - Log directory
 *
 * <b>Optional parameters:</b>
 *
 * # <b>cycle</b>  - [7]                 - Number of log files to keep.
 * # <b>prefix</b> - [core.app_name-]    - Log filename prefix.
 * # <b>suffix</b> - [.log]              - Log filename suffix.
 *
 * @package    agavi
 * @subpackage logging
 *
 * @author     Veikko Mäkinen <mail@veikkomakinen.com>
 * @copyright  Authors
 * @copyright  The Agavi Project
 * @since      0.11.0
 *
 * @version    $Id$
 */
class AgaviRotatingFileLoggerAppender extends AgaviFileLoggerAppender
{
	/**
	 * Initialize the object.
	 *
	 * @param      AgaviContext An AgaviContext instance.
	 * @param      array        An associative array of initialization parameters.
	 *
	 * @author     Veikko Mäkinen <mail@veikkomakinen.com>
	 * @since      0.11.0
	 */
	public function initialize(AgaviContext $context, array $parameters = array())
	{
		$cycle = 7;
		$prefix = str_replace(' ', '_', AgaviConfig::get('core.app_name')) . '-';
		$suffix = '.log';

		if(!isset($parameters['dir'])) {
			throw new AgaviLoggingException('No directory defined for rotating logging.');
		}

		$dir = $parameters['dir'];

		if(isset($parameters['cycle'])) {
			$cycle = $parameters['cycle'];
		}

		if(isset($parameters['prefix'])) {
			$prefix = $parameters['prefix'];
		}

		if(isset($parameters['suffix'])) {
			$suffix = $parameters['suffix'];
		}

		$logfile = $dir . $prefix . date('Y-m-d') . $suffix;

		if(!file_exists($logfile)) {

			// todays log file didn't exist so we need to create it
			// and at the same time we'll remove all unwanted history files

			$files = array();
			foreach(glob($dir . $prefix . '*-*-*' . $suffix) as $filename) {
				$files[] = $filename;
			}

			while(count($files) > $cycle-1) { //-1 because todays file is not yet created
				unlink(array_shift($files));
			}
		}

		//it's all up to the parent after this
		$parameters['file'] = $logfile;
		parent::initialize($context, $parameters);
	}
}

?>