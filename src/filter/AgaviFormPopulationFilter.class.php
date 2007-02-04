<?php

// +---------------------------------------------------------------------------+
// | This file is part of the Agavi package.                                   |
// | Copyright (c) 2003-2006 the Agavi Project.                                |
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
 * AgaviFormPopulationFilter automatically populates a form that is re-posted,
 * which usually happens when a View::INPUT is returned again after a POST 
 * request because an error occured during validation.
 * That means that developers don't have to fill in request parameters into
 * form elements in their templates anymore. Text inputs, selects, radios, they
 * all get set to the value the user selected before submitting the form.
 * If you would like to set default values, you still have to do that in your
 * template. The filter will recognize this situation and automatically remove
 * the default value you assigned after receiving a POST request.
 * This filter only works with POST requests, and compares the form's URL and
 * the requested URL to decide if it's appropriate to fill in a specific form
 * it encounters while processing the output document sent back to the browser.
 * Since this form is executed very late in the process, it works independently
 * of any template language.
 *
 * <b>Optional parameters:</b>
 *

 * # <b>cdata_fix</b> - [true] - Fix generated CDATA delimiters in script and 
 *                               style blocks.
 * # <b>error_class</b> - "error" - The class name that is assigned to form 
 *                                  elements which didn't pass validation and 
 *                                  their labels.
 * # <b>force_output_mode</b> - [false] - If false, the output mode (XHTML or 
 *                                        HTML) will be auto-detected using the 
 *                                        document's DOCTYPE declaration. Set 
 *                                        this to "html" or "xhtml" to force 
 *                                        one of these output modes explicitly.
 * # <b>include_hidden_inputs</b> - [false] - If hidden input fields should be 
 *                                            re-populated.
 * # <b>include_password_inputs</b> - [false] - If password input fields should 
 *                                              be re-populated.
 * # <b>remove_xml_prolog</b> - [true] - If the XML prolog generated by DOM 
 *                                       should be removed (existing ones will 
 *                                       remain untouched).
 *
 * @package    agavi
 * @subpackage filter
 *
 * @author     David Zuelke <dz@bitxtender.com>
 * @copyright  (c) Authors
 * @since      0.11.0
 *
 * @version    $Id$
 */
class AgaviFormPopulationFilter extends AgaviFilter implements AgaviIGlobalFilter, AgaviIActionFilter
{
	/**
	 * Execute this filter.
	 *
	 * @param      AgaviFilterChain        The filter chain.
	 * @param      AgaviExecutionContainer The current execution container.
	 *
	 * @throws     <b>AgaviFilterException</b> If an error occurs during execution.
	 *
	 * @author     David Zuelke <dz@bitxtender.com>
	 * @since      0.11.0
	 */
	public function executeOnce(AgaviFilterChain $filterChain, AgaviExecutionContainer $container)
	{
		$filterChain->execute($container);
		
		$response = $container->getResponse();
		
		$output = $response->getContent();
		
		if(!$output) {
			return;
		}
		
		$req = $this->getContext()->getRequest();
		
		$vm = null;
		
		$cfg = array_merge(array('populate' => null, 'skip' => null), $this->getParameters(), $req->getAttributes('org.agavi.filter.FormPopulationFilter'));
		
		if(is_array($cfg['output_types']) && !in_array($container->getOutputType()->getName(), $cfg['output_types'])) {
			return;
		}
		
		if(is_array($cfg['populate']) || $cfg['populate'] instanceof AgaviParameterHolder) {
			$populate = $cfg['populate'];
		} elseif(in_array($req->getMethod(), $cfg['methods']) && $cfg['populate'] !== false) {
			$populate = $req->getRequestData();
		} else {
			return;
		}
		
		$skip = null;
		if($cfg['skip'] instanceof AgaviParameterHolder) {
			$cfg['skip'] = $cfg['skip']->getParameters();
		} elseif($cfg['skip'] !== null && !is_array($cfg['skip'])) {
			$cfg['skip'] = null;
		}
		if($cfg['skip'] !== null && count($cfg['skip'])) {
			$skip = '/(\A' . str_replace('\[\]', '\[[^\]]*\]', implode('|\A', array_map('preg_quote', $cfg['skip']))) . ')/';
		}
		
		$luie = libxml_use_internal_errors(true);
		libxml_clear_errors();
		
		$doc = new DOMDocument();
		
		$doc->substituteEntities = $cfg['dom_substitute_entities'];
		$doc->resolveExternals   = $cfg['dom_resolve_externals'];
		$doc->validateOnParse    = $cfg['dom_validate_on_parse'];
		$doc->preserveWhiteSpace = $cfg['dom_preserve_white_space'];
		$doc->formatOutput       = $cfg['dom_format_output'];
		
		$hasXmlProlog = false;
		if(preg_match('/^<\?xml[^\?]*\?>/', $output)) {
			$hasXmlProlog = true;
		}
		
		$xhtml = (preg_match('/<!DOCTYPE[^>]+XHTML[^>]+/', $output) > 0 && strtolower($cfg['force_output_mode']) != 'html') || strtolower($cfg['force_output_mode']) == 'xhtml';
		if($xhtml && $cfg['parse_xhtml_as_xml']) {
			$doc->loadXML($output);
			$xpath = new DomXPath($doc);
			if($doc->documentElement && $doc->documentElement->namespaceURI) {
				$xpath->registerNamespace('html', $doc->documentElement->namespaceURI);
				$ns = 'html:';
			} else {
				$ns = '';
			}
		} else {
			$doc->loadHTML($output);
			$xpath = new DomXPath($doc);
			$ns = '';
		}
		
		if(libxml_get_last_error() !== false) {
			$errors = array();
			foreach(libxml_get_errors() as $error) {
				$errors[] = sprintf("Line %d: %s", $error->line, $error->message);
			}
			libxml_clear_errors();
			libxml_use_internal_errors($luie);
			throw new AgaviParseException(
				sprintf(
					'Form Population Filter could not parse the document due to the following error%s: ' . "\n\n%s", 
					count($errors) > 1 ? 's' : '', 
					implode("\n", $errors)
				)
			);
		}
		
		libxml_clear_errors();
		libxml_use_internal_errors($luie);
		
		$properXhtml = false;
		foreach($xpath->query('//' . $ns . 'head/' . $ns . 'meta') as $meta) {
			if(strtolower($meta->getAttribute('http-equiv')) == 'content-type') {
				if($doc->encoding === null) {
					if(preg_match('/charset=(.+)\s*$/i', $meta->getAttribute('content'), $matches)) {
						$doc->encoding = $matches[1];
					} else {
						$doc->encoding = "utf-8";
					}
				}
				if(strpos($meta->getAttribute('content'), 'application/xhtml+xml') !== false) {
					$properXhtml = true;
				}
				break;
			}
		}
		
		if(($encoding = $this->getParameter('force_encoding')) === false) {
			if($doc->actualEncoding) {
				$encoding = $doc->actualEncoding;
			} elseif($doc->encoding) {
				$encoding = $doc->encoding;
			} else {
				$encoding = $doc->encoding = 'utf-8';
			}
		} else {
			$doc->encoding = $encoding;
		}
		$encoding = strtolower($encoding);
		$utf8 = $encoding == 'utf-8';
		if(!$utf8 && $encoding != 'iso-8859-1' && !function_exists('iconv')) {
			throw new AgaviException('No iconv module available, input encoding "' . $encoding . '" cannot be handled.');
		}
		
		$base = $xpath->query('//' . $ns . 'head/' . $ns . 'base[@href]');
		if($base->length) {
			$baseHref = $base->item(0)->getAttribute('href');
		} else {
			$baseHref = $req->getUrl();
		}
		$baseHref = substr($baseHref, 0, strrpos($baseHref, '/') + 1);
		if(is_array($populate)) {
			foreach(array_keys($populate) as $id) {
				$query[] = '@id="' . $id . '"';
			}
			$query = '//' . $ns . 'form[' . implode(' or ', $query) . ']';
		} else {
			$query = '//' . $ns . 'form[@action]';
		}
		foreach($xpath->query($query) as $form) {
			if($populate instanceof AgaviParameterHolder) {
				$action = trim($form->getAttribute('action'));
				$ruri = $req->getRequestUri();
				$rurl = $req->getUrl();
				if(!(
					$action == $rurl || 
					(strpos($action, '/') === 0 && preg_replace(array('#/\./#', '#/\.$#', '#[^\./]+/\.\.(/|\z)#', '#/{2,}#'), array('/', '/', '', '/'), $action) == $ruri) ||
					$baseHref . preg_replace(array('#/\./#', '#/\.$#', '#[^\./]+/\.\.(/|\z)#', '#/{2,}#'), array('/', '/', '', '/'), $action) == $rurl
				)) {
					continue;
				}
				$p = $populate;
			} else {
				if(isset($populate[$form->getAttribute('id')]) && (($p = $populate[$form->getAttribute('id')]) instanceof AgaviParameterHolder)) {
					$p = $populate[$form->getAttribute('id')];
				} else {
					continue;
				}
			}
			
			// no validation manager set yet? let's do that. the later, the better.
			if($vm === null) {
				$vm = $container->getValidationManager();
			}
			
			// our array for remembering foo[] field's indices
			$remember = array();
			
			// build the XPath query
			$query = 'descendant::' . $ns . 'textarea[@name] | descendant::' . $ns . 'select[@name] | descendant::' . $ns . 'input[@name and (not(@type) or @type="text" or (@type="checkbox" and not(contains(@name, "[]"))) or (@type="checkbox" and contains(@name, "[]") and @value) or @type="radio" or @type="password" or @type="file"';
			if($cfg['include_hidden_inputs']) {
				$query .= ' or @type="hidden"';
			}
			$query .= ')]';
			foreach($xpath->query($query, $form) as $element) {
				
				$pname = $name = $element->getAttribute('name');
				
				$multiple = $element->nodeName == 'select' && $element->hasAttribute('multiple');
				
				$checkValue = false;
				if($element->getAttribute('type') == 'checkbox' || $element->getAttribute('type') == 'radio') {
					if(($pos = strpos($pname, '[]')) && ($pos + 2 != strlen($pname))) {
						// foo[][3] checkboxes etc not possible, [] must occur only once and at the end
						continue;
					} elseif($pos !== false) {
						$checkValue = true;
						$pname = substr($pname, 0, $pos);
					}
				}
				if(preg_match_all('/([^\[]+)?(?:\[([^\]]*)\])/', $pname, $matches)) {
					$pname = $matches[1][0];
					
					if($multiple) {
						$count = count($matches[2]) - 1;
					} else {
						$count = count($matches[2]);
					}
					for($i = 0; $i < $count; $i++) {
						$val = $matches[2][$i];
						if((string)$matches[2][$i] === (string)(int)$matches[2][$i]) {
							$val = (int)$val;
						}
						if(!isset($remember[$pname])) {
							$add = ($val !== "" ? $val : 0);
							if(is_int($add)) {
								$remember[$pname] = $add;
							}
						} else {
							if($val !== "") {
								$add = $val;
								if(is_int($val) && $add > $remember[$pname]) {
									$remember[$pname] = $add;
								}
							} else {
								$add = ++$remember[$pname];
							}
						}
						$pname .= '[' . $add . ']';
					}
				}
				
				if(!$utf8) {
					if($encoding == 'iso-8859-1') {
						$pname = utf8_decode($pname);
					} else {
						$pname = iconv('UTF-8', $encoding, $pname);
					}
				}
				
				if($skip !== null && preg_match($skip . ($utf8 ? 'u' : ''), $pname . ($checkValue ? '[]' : ''))) {
					// skip field
					continue;
				}
				
				// there's an error with the element's name in the request? good. let's give the baby a class!
				if($vm->hasError($pname)) {
					$element->setAttribute('class', preg_replace('/\s*$/', ' ' . $cfg['error_class'], $element->getAttribute('class')));
					// assign the class to all implicit labels
					foreach($xpath->query('ancestor::' . $ns . 'label[not(@for)]', $element) as $label) {
						$label->setAttribute('class', preg_replace('/\s*$/', ' ' . $cfg['error_class'], $label->getAttribute('class')));
					}
					if(($id = $element->getAttribute('id')) != '') {
						// assign the class to all explicit labels
						foreach($xpath->query('descendant::' . $ns . 'label[@for="' . $id . '"]', $form) as $label) {
							$label->setAttribute('class', preg_replace('/\s*$/', ' ' . $cfg['error_class'], $label->getAttribute('class')));
						}
					}
				}
				
				$value = $p->getParameter($pname);
				
				if(is_array($value) && !($element->nodeName == 'select' || $checkValue)) {
					// name didn't match exactly. skip.
					continue;
				}
				
				if(!$utf8) {
					if($encoding == 'iso-8859-1') {
						if(is_array($value)) {
							$value = array_map('utf8_encode', $value);
						} else {
							$value = utf8_encode($value);
						}
					} else {
						if(is_array($value)) {
							foreach($value as &$val) {
								$val = iconv($encoding, 'UTF-8', $val);
							}
						} else {
							$value = iconv($encoding, 'UTF-8', $value);
						}
					}
				} else {
					if(is_array($value)) {
						$value = array_map('strval', $value);
					} else {
						$value = (string) $value;
					}
				}
				
				if($element->nodeName == 'input') {
					
					if(!$element->hasAttribute('type') || $element->getAttribute('type') == 'text' || $element->getAttribute('type') == 'hidden') {
						
						// text inputs
						$element->removeAttribute('value');
						if($p->hasParameter($pname)) {
							$element->setAttribute('value', $value);
						}
						
					} elseif($element->getAttribute('type') == 'checkbox' || $element->getAttribute('type') == 'radio') {
						
						// checkboxes and radios
						$element->removeAttribute('checked');
						
						if($checkValue && is_array($value)) {
							$eValue = $element->getAttribute('value');
							if(!$utf8) {
								if($encoding == 'iso-8859-1') {
									$eValue = utf8_decode($eValue);
								} else {
									$eValue = iconv('UTF-8', $encoding, $eValue);
								}
							}
							if(!in_array($eValue, $value)) {
								continue;
							} else {
								$element->setAttribute('checked', 'checked');
							}
						} elseif($p->hasParameter($pname) && (($element->hasAttribute('value') && $element->getAttribute('value') == $value) || (!$element->hasAttribute('value') && $p->getParameter($pname)))) {
							$element->setAttribute('checked', 'checked');
						}
						
					} elseif($element->getAttribute('type') == 'password') {
						
						// passwords
						$element->removeAttribute('value');
						if($cfg['include_password_inputs'] && $p->hasParameter($pname)) {
							$element->setAttribute('value', $value);
						}
					}
					
				} elseif($element->nodeName == 'select') {
					// select elements
					// yes, we still use XPath because there could be OPTGROUPs
					foreach($xpath->query('descendant::' . $ns . 'option', $element) as $option) {
						$option->removeAttribute('selected');
						if($p->hasParameter($pname) && ($option->getAttribute('value') === $value || ($multiple && is_array($value) && in_array($option->getAttribute('value'), $value)))) {
							$option->setAttribute('selected', 'selected');
						}
					}
					
				} elseif($element->nodeName == 'textarea') {
					
					// textareas
					foreach($element->childNodes as $cn) {
						// remove all child nodes (= text nodes)
						$element->removeChild($cn);
					}
					// append a new text node
					if($xhtml && $properXhtml) {
						$element->appendChild($doc->createCDATASection($value));
					} else {
						$element->appendChild($doc->createTextNode($value));
					}
				}
				
			}
		}
		if($xhtml) {
			if(!$cfg['parse_xhtml_as_xml']) {
				// workaround for a bug in dom or something that results in two xmlns attributes being generated for the <html> element
				foreach($xpath->query('//html') as $html) {
					$html->removeAttribute('xmlns');
				}
			}
			$out = $doc->saveXML();
			if((!$cfg['parse_xhtml_as_xml'] || !$properXhtml) && $cfg['cdata_fix']) {
				// these are ugly fixes so inline style and script blocks still work. better don't use them with XHTML to avoid trouble
				$out = preg_replace('/<style([^>]*)>\s*<!\[CDATA\[/iU' . ($utf8 ? 'u' : ''), '<style$1><!--/*--><![CDATA[/*><!--*/', $out);
				$out = preg_replace('/\]\]><\/style>/iU' . ($utf8 ? 'u' : ''), '/*]]>*/--></style>', $out);
				$out = preg_replace('/<script([^>]*)>\s*<!\[CDATA\[/iU' . ($utf8 ? 'u' : ''), '<script$1><!--//--><![CDATA[//><!--', $out);
				$out = preg_replace('/\]\]><\/script>/iU' . ($utf8 ? 'u' : ''), '//--><!]]></script>', $out);
			}
			if($cfg['remove_auto_xml_prolog'] && !$hasXmlProlog) {
				// there was no xml prolog in the document before, so we remove the one generated by DOM now
				$out = preg_replace('/<\?xml.*?\?>\s+/iU' . ($utf8 ? 'u' : ''), '', $out);
			} elseif(!$cfg['parse_xhtml_as_xml']) {
				// yes, DOM sucks and inserts another XML prolog _after_ the DOCTYPE... and it has two question marks at the end, not one, don't ask me why
				$out = preg_replace('/<\?xml.*?\?\?>\s+/iU' . ($utf8 ? 'u' : ''), '', $out);
			}
			$response->setContent($out);
		} else {
			$response->setContent($doc->saveHTML());
		}
		unset($xpath);
		unset($doc);
	}

	/**
	 * Initialize this filter.
	 *
	 * @param      AgaviContext The current application context.
	 * @param      array        An associative array of initialization parameters.
	 *
	 * @throws     <b>AgaviFilterException</b> If an error occurs during 
	 *                                         initialization
	 *
	 * @author     David Zuelke <dz@bitxtender.com>
	 * @since      0.11.0
	 */
	public function initialize(AgaviContext $context, array $parameters = array())
	{
		// set defaults
		$this->setParameter('cdata_fix', true);
		$this->setParameter('error_class', 'error');
		$this->setParameter('force_output_mode', false);
		$this->setParameter('force_encoding', false);
		$this->setParameter('parse_xhtml_as_xml', true);
		$this->setParameter('include_password_inputs', false);
		$this->setParameter('include_hidden_inputs', true);
		$this->setParameter('remove_auto_xml_prolog', true);
		$this->setParameter('methods', array());
		$this->setParameter('output_types', null);
		$this->setParameter('dom_substitute_entities', false);
		$this->setParameter('dom_resolve_externals', false);
		$this->setParameter('dom_validate_on_parse', false);
		$this->setParameter('dom_preserve_white_space', true);
		$this->setParameter('dom_format_output', false);
		
		// initialize parent
		parent::initialize($context, $parameters);
		
		$this->setParameter('methods', (array) $this->getParameter('methods'));
		if($ot = $this->getParameter('output_types')) {
			$this->setParameter('output_types', (array) $ot);
		}
	}
}

?>