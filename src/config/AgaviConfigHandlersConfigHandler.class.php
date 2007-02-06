<?php

// +---------------------------------------------------------------------------+
// | This file is part of the Agavi package.                                   |
// | Copyright (c) 2003-2007 the Agavi Project.                                |
// | Based on the Mojavi3 MVC Framework, Copyright (c) 2003-2005 Sean Kerr.    |
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
 * AgaviConfigHandlersConfigHandler allows you to specify configuration handlers
 * for the application or on a module level.
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
class AgaviConfigHandlersConfigHandler extends AgaviConfigHandler
{

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
		// parse the config file
		$configurations = $this->orderConfigurations(AgaviConfigCache::parseConfig($config, false, $this->getValidationFile(), $this->parser)->configurations, AgaviConfig::get('core.environment'));

		// init our data arrays
		$data     = array();

		foreach($configurations as $cfg) {
			// let's do our fancy work
			foreach($cfg->handlers as $handler) {
				$pattern = $handler->getAttribute('pattern');

				$category = var_export(AgaviToolkit::normalizePath($this->replaceConstants($pattern)), true);

				$class = $handler->getAttribute('class');

				$parameters = $this->getItemParameters($handler);

				// append new data
				$tmp    = "self::\$handlers[%s] = new %s();";
				$data[] = sprintf($tmp, $category, $class);

				$tmp    = "self::\$handlers[%s]->initialize(%s, %s, %s);";
				$data[] = sprintf($tmp, $category, var_export($this->literalize($handler->getAttribute('validate')), true), var_export($handler->getAttribute('parser'), true), var_export($parameters, true));
			}
		}

		// compile data
		$retval = "<?php\n" .
				  "// auto-generated by ".__CLASS__."\n" .
				  "// date: %s GMT\n%s\n?>";

		$retval = sprintf($retval, gmdate('m/d/Y H:i:s'), implode("\n", $data));

		return $retval;

	}

}

?>