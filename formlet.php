<?php

/*
 * (c) 2016 Maximilian Strauch
 * Creative Commons BY-SA 4.0 (http://creativecommons.org/licenses/by-sa/4.0/)
 *
 * https://github.com/maxstrauch/formlet
 * 
 * This program is distributed WITHOUT ANY WARRANTY; without even the implied 
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
 *
 */

class Formlet {

	/**
	 * Constants for the different XML tag names processed by
	 * this class
	 */
	const TAG_FORM 			= 'ui:form';
	const TAG_MESSAGES 		= 'ui:messages';
	const TAG_TEXT 			= 'ui:text';
	const TAG_HIDDEN 		= 'ui:hidden';
	const TAG_TEXT_AREA 	= 'ui:textArea';
	const TAG_SECRET 		= 'ui:secret';
	const TAG_SINGLE_SELECT = 'ui:singleSelect';
	const TAG_MULTI_SELECT	= 'ui:multiSelect';
	const TAG_SUBMIT 		= 'ui:submit';
	const TAG_LABEL			= 'ui:label';
	const TAG_IFERROR		= 'ui:iferror';
	const TAG_RENDERED		= 'ui:rendered';

	/**
	 * Prefix of the XML tag names processed by this class
	 */
	const TAG_PREFIX		= 'ui';

	/**
	 * Tags which can be rendered as bachelor tags, if its content
	 * is empty. Tags which are not in this array but have empty
	 * content will be rendered normally, e.g. <span></span>
	 */
	const BACHELOR_TAGS	= array('hr', 'br', 'link', 'meta');

	/**
	 * Contains the DOM tree for the XML file loaded by the
	 * load function of this class
	 */
	var $dom;

	/**
	 * This boolean values are cached so that subsequent calls
	 * of isSubmitted() and isValid() return only the previously
	 * computed values
	 */
	var $cachedIsSubmitted = null;
	var $cachedIsValid = null;

	/**
	 * All messages generated by the validation process
	 */
	var $msgs = array();

	/**
	 * Contains all data bindings for single- and multi-select
	 * input fields. This array is a map assigning an array of
	 * values to a "virtual variable"
	 */
	var $dataBindings = array();

	/**
	 * All input elements of the loaded form. This array is popu-
	 * lated by the load() function
	 */
	var $elements = array();

	/**
	 * Value of the form action field or <code>NULL</code> if not
	 * set and default behaviour is expected.
	 */
	var $action = NULL;

	/**
	 * Contains predefined values which sould be inserted into the
	 * form in the beginning and can be then edited by the user.
	 */
	var $predefValues = array();

	/**
	 * Constructs a new object of this class and sets relevant
	 * attribute values
	 */
	function __construct() {
		// Create a new DOM instance to load the XML file
		$this->dom = new DOMDocument();
	}

	/**
	 * Registers a new array as data binding to be used by an
	 * sinle- or multi-select input in the form. The value should
	 * be an array of values to choose from
	 *
	 * @param $name Name of the data binding, the same as the
	 * XML "bind" attribute used in the XML file
	 * @param $value An array of values
	 */
	function registerDataBinding($name, $value) { //: void
		if ($name === NULL || $value === NULL) {
			return;
		}
		$this->dataBindings[$name] = $value;
	}

	/**
	 * Sets the value of the action parameter of the generated 
	 * form
	 *
	 * @param $action The form action
	 */
	function setAction($action) { //: void
		$this->action = $action;
	}

	/**
	 * Sets a predefined value for a form field.
	 *
	 * @param $name The name of the field
	 * @param $value The value to display
	 */
	function setPredefValue($name, $value) { //: void
		$this->predefValues[$name] = $value;
	}

	/**
	 * Loads a layout from an XML file into this class and 
	 * parses it to get the input fields
	 */
	function load($file) { //: void
		// Load the file
		$this->dom->load($file);

		// Check for correct root element
		if (!($this->dom->documentElement->tagName === self::TAG_FORM)) {
			trigger_error('Root element must be ' . self::TAG_FORM . '.', E_USER_ERROR);
			return '';
		}

		// Discovers all input fields (namespace "ui") in the
		// layout file. The order of the fields is reversed
		$root = $this->dom->documentElement;
		$pending = array($root);

		while (count($pending) > 0) {
			$current = array_pop($pending);

			if (!($current === $root) && $current->nodeType === 1 
					&& $current->prefix === self::TAG_PREFIX) {

				// Get the essentials of the element
				$attr = Formlet::domAttrMap2Array($current);
				if (array_key_exists('name', $attr)) {
					$this->elements[] = array(
						'type' => $current->tagName,
						'name' => $attr['name'],
						'@attrs' => $attr
					);

				} else {
					$elementName = $current->prefix . ':' . $current->localName;
					if (!($elementName === self::TAG_MESSAGES) &&
						!($elementName === self::TAG_LABEL) &&
						!($elementName === self::TAG_IFERROR) &&
						!($elementName === self::TAG_RENDERED)) {
						trigger_error('Element ' .  $elementName . 
							' needs non-emtpy attribute "name".', E_USER_ERROR);
						return;
					}
				}
			}

			// Discover all child nodes
			if ($current->childNodes) {
				for ($i = 0; $i < $current->childNodes->length; $i++) {
					$pending[] = $current->childNodes[$i];		
				}	
			}
		}
	}

	/**
	 * Tests whether the form has been submitted or not. If the form
	 * has been submitted the submit button is send and is contained
	 * in $_REQUEST. No further checking is done
	 *
	 * @return Returns <code>true</code> if the form is submitted
	 * (input values are available) or not (then <code>false</code>)
	 */
	function isSubmitted() { //: Boolean
		// Return a cached value if available
		if ($this->cachedIsSubmitted != null) {
			return $this->cachedIsSubmitted;
		}

		// Find the next submit button
		for ($i = 0; $i < count($this->elements); $i++) {
			if ($this->elements[$i]['type'] === 'ui:submit') {
				$name = $this->elements[$i]['name'];
				return ($this->cachedIsSubmitted = isset($_REQUEST[$name]));
			}
		}

		return false; // Nothing found
	}

	/**
	 * Validates all submitted form data. The form must be submitted before
	 * this function will do anything.
	 *
	 * @return Returns <code>true</code> if the submitted form data is
	 * valid according to the defined restriction. Otherwise <code>false</code>
	 * is returned
	 */
	function isValid() { //: Boolean
		// Check if there is a cached result available
		if (!($this->cachedIsValid === null)) {
			return $this->cachedIsValid;
		}

		// If the form hasn't been submitted do nothing
		if (!$this->isSubmitted()) {
			return false;
		}

		// Initially everything is valid
		$isValid = true;

		// Check for every component if the inputs are valid
		// and run the validator function (if any)
		for ($i = 0; $i < count($this->elements); $i++) {
			$current = $this->elements[$i];
			$attrs = $current['@attrs'];

			// Go through the different types of input fields
			switch ($current['type']) {

				// This text input components are validated the same way, 
				// but the "number" attribute is only available for TAG_TEXT
				case self::TAG_SECRET:
				case self::TAG_TEXT_AREA:
				case self::TAG_TEXT:

					// Perform only checks if value is required
					if (isset($attrs['required'])) {
						// Get the value
						$value = $this->_get($current['name']);

						// Do the requested check
						if (isset($attrs['validator'])) {

							// Call validator function
							$funcName = $attrs['validator'];
							if (!(call_user_func($funcName, $value) === true)) {
								$isValid = $isValid && false;
								
								// Append error message
								if (isset($attrs['message'])) {
									$this->msgs[] = $attrs['message'];
								} else {
									$this->msgs[] = 'Field "' . $attrs['name'] . 
										'" is not valid.';
								}
							}	

						} else if ($current['type'] == self::TAG_TEXT && isset($attrs['number'])) {

							// Check if only an integer is contained
							if (!(((string) ((int) $value)) == $value)) {
								$isValid = $isValid && false;

								// Append error message
								if (isset($attrs['message'])) {
									$this->msgs[] = $attrs['message'];
								} else {
									$this->msgs[] = 'Field "' . $attrs['name'] . 
										'" may only contain a number.';
								}
							}

						} else if (isset($attrs['minlength'])) {

							// Check if input is long enough
							if (strlen($value) < ((int) $attrs['minlength'])) {
								$isValid = $isValid && false;

								// Append error message
								if (isset($attrs['message'])) {
									$this->msgs[] = $attrs['message'];
								} else {
									$this->msgs[] = 'Field "' . $attrs['name'] . 
										'" requires at least ' . ((int) $attrs['minlength']) . 
										' characters.';
								}
							}

						} else {

							// Default condition: at least one character
							if (strlen($value) < 1) {
								$isValid = $isValid && false;

								// Append error message
								if (isset($attrs['message'])) {
									$this->msgs[] = $attrs['message'];
								} else {
									$this->msgs[] = 'Field "' . $attrs['name'] . 
										'" may not be empty.';
								}
							}

						}
					} else if ($current['type'] == self::TAG_TEXT && isset($attrs['number']) && strlen($this->_get($current['name'])) > 0) {
						// Get the value
						$value = $this->_get($current['name']);

						// Check if only an integer is contained
						if (!(((string) ((int) $value)) == $value)) {
							$isValid = $isValid && false;

							// Append error message
							if (isset($attrs['message'])) {
								$this->msgs[] = $attrs['message'];
							} else {
								$this->msgs[] = 'Field "' . $attrs['name'] . 
									'" may only contain a number.';
							}
						}
					}
					break;

				case self::TAG_SINGLE_SELECT:
					// Perform only checks if value is required
					if (isset($attrs['required'])) {
						// Get the value
						if (($value = $this->_get($current['name'])) == NULL) {
							// Field not submitted, but is required!
							$isValid = $isValid && false;

							// ... so append error message
							if (isset($attrs['message'])) {
								$this->msgs[] = $attrs['message'];
							} else {
								$this->msgs[] = 'Field "' . $attrs['name'] . 
									'" is not valid.';
							}

							break;
						}
						$value = (int) $value;

						// Do the requested check
						if (isset($attrs['validator'])) {

							// Call validator function
							$funcName = $attrs['validator'];

							// Get the selected value from the data binding
							$data = NULL;
							if ($value > -1) {
								$data = $this->dataBindings[$attrs['bind']][$value];
							}

							// Call validator function
							if (!(call_user_func($funcName, $data, $value) === true)) {
								$isValid = $isValid && false;
								
								// Append error message
								if (isset($attrs['message'])) {
									$this->msgs[] = $attrs['message'];
								} else {
									$this->msgs[] = 'Field "' . $attrs['name'] . 
										'" is not valid.';
								}
								break;
							}

						} else {

							// If no validator specified, one option
							// must be selected
							if ($value == -1) {
								$isValid = $isValid && false;

								// Append error message
								if (isset($attrs['message'])) {
									$this->msgs[] = $attrs['message'];
								} else {
									$this->msgs[] = 'Select one option from "' . 
										$attrs['name'] . '".';
								}
								break;
							}

						}
					}
					break;

				case self::TAG_MULTI_SELECT:
					// Perform only checks if value is required
					if (isset($attrs['required'])) {
						// Get all indicies
						$indices = $this->_get($current['name']);
						if ($indices == NULL || count($indices) < 1) {
							$isValid = $isValid && false;

							// Append error message
							if (isset($attrs['message'])) {
								$this->msgs[] = $attrs['message'];
							} else {
								$this->msgs[] = 'For field "' . $attrs['name'] . 
									'" at least one element must be selected.';
							}
							break;
						}

						// Call validator if requested
						if (isset($attrs['validator'])) {

							// Call validator function
							$funcName = $attrs['validator'];

							// Get the selected value from the data binding
							$data = array();
							for ($i = 0; $i < count($indices); $i++) {
								$data[] = $this->dataBindings[$attrs['bind']][$i];
							}

							// Call validator function
							if (!(call_user_func($funcName, $data, $indices) === true)) {
								$isValid = $isValid && false;
								
								// Append error message
								if (isset($attrs['message'])) {
									$this->msgs[] = $attrs['message'];
								} else {
									$this->msgs[] = 'Field "' . $attrs['name'] . 
										'" is not valid.';
								}
								break;
							}
						}
					}
					break;

					// Components with no input are valid at every
					// point of time
					case self::TAG_LABEL:
					case self::TAG_SUBMIT:
					case self::TAG_FORM:
					case self::TAG_MESSAGES:
					case self::TAG_HIDDEN:
					case self::TAG_IFERROR:
					case self::TAG_RENDERED:
					default:
						break;
				}

		}

		// Cache the result for the next call
		return ($this->cachedIsValid = $isValid);
	}


	/**
	 * Returns the value entered by the user for the form input
	 * identified by the given name
	 *
	 * @param $name Name of the form input to return the value for
	 * @param $indexOnly By default set to <code>false</code>. If 
	 * <code>true</code> is given only the return values for select
	 * (single and multi) inputs will change: instead of the selected
	 * values the selected indices are returned
	 * @return Returns either a string or an array of strings
	 * (for multi-select inputs). <code>NULL</code> is returned
	 * for multiple reasons:
	 * <ul>
	 * <li>the given input field does not exist</li>
	 * <li>the form/input field is not yet submitted (no data)</li>
	 * <li>no value is available (e.g. submit button, label)</li>
	 * <li>there is no variable available for the data binding</li>
	 * </ul>
	 */
	function getValue($name, $indexOnly = false) { //: mixed
		// Find the element in the form
		$element = NULL;
		for ($i = 0; $i < count($this->elements); $i++) {
			if ($this->elements[$i]['name'] == $name) {
				$element = $this->elements[$i];
			}
		}

		// No element with this name
		if ($element == NULL) {
			return NULL;
		}

		// Check if form data is already submitted
		if (!array_key_exists($element['name'], $_REQUEST)) {
			return NULL;
		}

		// Switch through the different cases
		switch ($element['type']) {
			// Very simple lookups
			case self::TAG_TEXT:
			case self::TAG_TEXT_AREA:
			case self::TAG_SECRET:
			case self::TAG_HIDDEN:
				$value = $_REQUEST[$element['name']];
				if (strlen($value) < 1) {
					return NULL;
				}
				return $value;


			case self::TAG_SINGLE_SELECT:
				// Get the selected index
				$index = @intval($_REQUEST[$element['name']]);
				if ($indexOnly) {
					return $index;
				}

				// Get the data binding
				if (!array_key_exists($element['@attrs']['bind'], $this->dataBindings)) {
					return NULL;
				}

				// Return the value directly
				return $this->dataBindings[$element['@attrs']['bind']][$index];

			case self::TAG_MULTI_SELECT:
				// Get selected indices (and convert them to int)
				$indices = $_REQUEST[$element['name']];
				for ($i = 0; $i < count($indices); $i++) {
					$indices[$i] = @intval($indices[$i]);
				}

				if ($indexOnly) {
					return $indices;
				}

				if (!isset($element['@attrs']['bind'])) {
					return isset($_REQUEST[$element['@attrs']['name']]);
				}

				// Get the data binding
				if (!array_key_exists($element['@attrs']['bind'], $this->dataBindings)) {
					return NULL;
				}

				// Copy the selected entries
				$data = $this->dataBindings[$element['@attrs']['bind']];
				$result = array();
				for ($i = 0; $i < count($indices); $i++) {
					$result[] = $data[$indices[$i]];
				}

				return $result; // Return the result

			case self::TAG_SUBMIT:
			case self::TAG_LABEL:
			case self::TAG_IFERROR:
			case self::TAG_RENDERED:
				return NULL; // No value to return
		}
	}

	/**
	 * Renders the form represented by this class and
	 * the corresponding layout file to HTML code
	 *
	 * @return Returns the rendered HTML code
	 */
	function toHtml() { //: String
		// If the form is submitted, run the validator method to
		// generate output for messages and others
		if ($this->isSubmitted()) {
			$this->isValid();
		}

		// Get root element to start rendering at
		$root = $this->dom->documentElement;

		// Render all child nodes of this root node recursively
		$buf = '';
		for ($i = 0; $i < $root->childNodes->length; $i++) {
			$buf .= $this->_render($root->childNodes[$i]);
		}

		return $buf; // Return HTML buffer
	}

	/**
	 * Internal element render function. This funktion walks
	 * recursively through the XML tree and generates HTML
	 *
	 * @return The generated HTML code for an element and all
	 * its child elements
	 */
	function _render($root, $level = 0) { //: String
		$buf = '';
		$closingTag = '';
		$space = '';
		for ($i = 0; $i < $level; $i++)
			$space .= '  ';

		// Text node encountered
		if ($root->nodeType == 3) {
			$content = trim($root->data);
			if (strlen($content) < 1) {
				return '';
			} else {
				return $space . trim($root->data) . "\n";
			}
		}

		// Ignore "other" nodes
		if ($root->nodeType != 1) {
			return '';
		}

		$renderChildren = true;
		if ($root->prefix === self::TAG_PREFIX) {
			// Get all attributes
			$attrMap = Formlet::domAttrMap2Array($root);

			// This is a kind of directive so don't pass it on to
			// the rendering method since it decides only if child
			// nodes are going to be rendered
			if ((self::TAG_PREFIX . ':' . $root->localName) 
					=== self::TAG_IFERROR) {
				$renderChildren = $this->isSubmitted() && !$this->isValid();
			} else if ((self::TAG_PREFIX . ':' . $root->localName) 
					=== self::TAG_RENDERED) {
				$renderChildren = isset($this->dataBindings[$attrMap['var']]) && 
						$this->dataBindings[$attrMap['var']];
			} else {
				// Render the element
				$result = $this->_renderUIElement(
					self::TAG_PREFIX . ':' . $root->localName, 
					$attrMap
				);
				$buf .= $space . $result . "\n";
			}
		} else {
			// Ordinary HTML node: "pass through"
			// Render element start with attributes
			$buf .= $space . '<' . $root->tagName;

			// Manipulte the action attribute for forms
			$attrs = Formlet::domAttrMap2Array($root);
			if ($root->tagName == 'form') {
				if ($this->action != NULL) {
					$attrs['action'] = $this->action; // Overwrite
				}

				if (!isset($attrs['action'])) {
					// If no action attribute is set, use
					// the current script as action
					$attrs['action'] = $_SERVER['PHP_SELF'];
				}

				if (!isset($attrs['method'])) {
					// Set HTTP POST method if not defined
					$attrs['method'] = 'post';
				}
			}

			if ($root->attributes->length > 0) {
				$buf .= ' ' . Formlet::join4XMLAttributes($attrs);
			} 

			// An empty tag is a bachelor tag, but only if allowed!
			if ($root->childNodes->length < 1 &&
				in_array($root->tagName, self::BACHELOR_TAGS)) {
				return $buf . ' />' . "\n";
			}

			// Otherwise close start tag
			$buf .= '>' . "\n";

			// Create closing tag
			$closingTag = '</' . $root->tagName . '>';
		}

		if ($renderChildren) {
			// Render all child nodes recursively
			for ($i = 0; $i < $root->childNodes->length; $i++) {
				$buf .= $this->_render($root->childNodes[$i], $level+1);
			}
		}

		// Append closing tag
		if (strlen($closingTag) > 0) {
			$buf .= $space . $closingTag . "\n";
		}

		return $buf;
	}

	/**
	 * Internal function to render a form input field inserted
	 * in the layout file.
	 * 
	 * @param $tagName Name of element to render
	 * @param $attrMap Attributes of this element to support rendering
	 * @return The HTML code of the rendered form input element
	 */
	function _renderUIElement($tagName, $attrMap) { //: String
		// Render the requested tag
		switch ($tagName) {

			case self::TAG_LABEL: // Simple label element
				// Error handling ...
				if (!function_exists('lang') || !array_key_exists('key', $attrMap)) {
					return '<em style="color: red;">???' . $attrMap['key'] . '???</em>';
				}

				// Clean up attributes
				$innerText = lang($attrMap['key']);
				unset($attrMap['key']);

				// Add appender
				if (isset($attrMap['append'])) {
					$innerText .= $attrMap['append'];
					unset($attrMap['append']);
				}

				// Render element
				if (array_key_exists('plain', $attrMap)) {
					return $innerText; // Plain result
				} else {
					$out = '<label';
					if (count($attrMap) > 0) {
						$out .= ' ' . Formlet::join4XMLAttributes($attrMap);
					}
					return $out . '>' . $innerText . '</label>';
				}

			// Both share most of the properties ...
			case self::TAG_SECRET: // A password input field
			case self::TAG_TEXT: // Text field
			case self::TAG_HIDDEN:
				// Remove "meta" attributes
				unset($attrMap['required']);
				unset($attrMap['validator']);
				unset($attrMap['message']);
				unset($attrMap['number']);
				unset($attrMap['minlength']);

				// Overlay the submitted form value
				if (isset($attrMap['valuemap'])) {
					// The REQUEST parameters needed are encoded in an array;
					// therefore also an index is needed to get the right
					// value
					$value = $this->_get(str_replace("[]", "", $attrMap['name']), true);
					
					$value = $value[(int) $attrMap['valuemap']];
					if (strlen($value) > 0) {
						$attrMap['value'] = htmlspecialchars($value);
					}
					unset($attrMap['valuemap']);
				} else {
					// Normal behaviour
					$value = $this->_get($attrMap['name']);
					if (($value == "0" || $value != NULL) && 
							array_key_exists('name', $attrMap)) {
						$attrMap['value'] = htmlspecialchars($value);
					}
				}

				// Generate input HTML
				$out = '<input type="';
				if ($tagName == self::TAG_HIDDEN) {
					$out .= 'hidden';
				} else if ($tagName == self::TAG_SECRET) {
					$out .= 'password';
				} else {
					$out .= 'text';
				}
				$out .= '" ' . Formlet::join4XMLAttributes($attrMap) . ' />';
				return $out;

			case self::TAG_TEXT_AREA: // The text area element
				// Remove "meta" attributes
				unset($attrMap['required']);
				unset($attrMap['validator']);
				unset($attrMap['message']);
				unset($attrMap['number']);
				unset($attrMap['minlength']);
				unset($attrMap['value']);

				$value = $this->_get($attrMap['name']);

				// Return the renered HTML
				return '<textarea ' . Formlet::join4XMLAttributes($attrMap) . '>' .
						($value != NULL ? htmlspecialchars($value) : '') . '</textarea>';

			case self::TAG_SUBMIT: // The form submit button
				// Check if language key used for value
				if (isset($attrMap['value']) && strpos($attrMap['value'], ':') === 0) {
					$attrMap['value'] = lang(substr($attrMap['value'], 1));
				}

				return '<input type="submit" ' . Formlet::join4XMLAttributes($attrMap) . ' />';

			case self::TAG_MESSAGES:
				if (isset($attrMap['errorClass'])) {
					$clazz = $attrMap['errorClass'];
				} else {
					$clazz = NULL;
				}
				unset($attrMap['errorClass']);

				// Render the error messages
				$out = '<p class="form-messages"><ul ';
				$out .= Formlet::join4XMLAttributes($attrMap) . '>' . "\n";

				for ($i = 0; $i < count($this->msgs); $i++) {
					$out .= '<li';
					if ($clazz != NULL) {
						$out .= ' class="' . $clazz . '"';
					}
					$out .= '>';  

					if (strpos($this->msgs[$i], ':') === 0) {
						$out .= lang(substr($this->msgs[$i], 1));
					} else {
						$out .= $this->msgs[$i];
					}

					$out .= '</li>' . "\n";

				}

				return $out . '</ul></p>' . "\n";

			case self::TAG_SINGLE_SELECT:
				// Get the data to inject into the select field
				$data = array();
				if (isset($this->dataBindings[$attrMap['bind']])) {
					$data = $this->dataBindings[$attrMap['bind']];
				}
				unset($attrMap['bind']);
				unset($attrMap['required']);
				unset($attrMap['validator']);
				unset($attrMap['message']);

				if (isset($attrMap['style']) && $attrMap['style'] === 'radio') {
					unset($attrMap['style']);
					unset($attrMap['empty']);
					$out = '';
					// Render the single select input as
					// distinct radio boxes

					for ($i = 0; $i < count($data); $i++) {
						// Create a generated id for the labels
						$attrMap['id'] = $attrMap['name'] . '_labelfor_' . $i;

						// Writ element
						$out .= '<input type="radio" ' . 
									Formlet::join4XMLAttributes($attrMap) . 
									' value="' . $i . '"';

						// Test if the current element is selected
						$value = $this->_get($attrMap['name']);
						if ($value != NULL && $i == ((int) $value)) {
							$out .= ' checked';
						}

						$out .= '>&nbsp;<label for="' . $attrMap['id'] . '">' . 
									$data[$i] . "</label>\n";
					}

					return $out;
				} else {
					// If requested, render the empty option (assigned to -1)
					$emptyValue = '';
					if (isset($attrMap['empty'])) {
						$emptyValue .= '<option value="-1"';

						$value = $this->_get($attrMap['name']);
						if ($value != NULL && -1 == ((int) $value)) {
							$emptyValue .= ' selected';
						}

						$emptyValue .='>' . $this->_lang($attrMap['empty']) . '</option>' . "\n";
					}

					// Render as select input field
					unset($attrMap['style']);
					unset($attrMap['empty']);

					// Render the HTML select field
					$out = '<select ' . Formlet::join4XMLAttributes($attrMap) . '>' . "\n";
					$out .= $emptyValue;					
					for ($i = 0; $i < count($data); $i++) {
						$out .= '<option value="' . $i . '"';

						// Manage element selection on submit
						$value = $this->_get($attrMap['name']);
						if ($value != NULL && $i == ((int) $value)) {
							$out .= ' selected';
						}

						$out .='>' . $data[$i] . '</option>' . "\n";
					}
					return $out . '</select>';
				}

				
			case self::TAG_MULTI_SELECT:
				// Get the data to display
				$data = array();
				if (isset($attrMap['bind']) && isset($this->dataBindings[$attrMap['bind']])) {
					$data = $this->dataBindings[$attrMap['bind']];
				}

				// Unset special attributes
				unset($attrMap['bind']);
				unset($attrMap['required']);
				unset($attrMap['validator']);
				unset($attrMap['message']);

				// Tweak the name of the component since multi selections
				// require array brackets ("[]") after the name
				$finalAttrMap = $attrMap;
				$finalAttrMap['name'] = $finalAttrMap['name'] . '[]';

				// Get the already submitted fields
				$sendData = $this->_get($attrMap['name']);
				if ($sendData == NULL) {
					$sendData = array(); // Nothing submitted
				}

				if (isset($attrMap['style']) && $attrMap['style'] === 'check') {
					unset($attrMap['style']);
					$out = '';

					if (count($data) == 0) {


						return '<input type="checkbox" ' . 
									Formlet::join4XMLAttributes($finalAttrMap) . 
									' value="on"' .
									($this->_get($attrMap['name']) != NULL ? ' checked' : '') .
									'>&nbsp;' . "\n";

					}

					// Render checkboxes with labels
					for ($i = 0; $i < count($data); $i++) {
						// Set the id for the label
						$finalAttrMap['id'] = $attrMap['name'] . '_labelfor_' . $i;

						// Render input element and label
						$out .= '<input type="checkbox" ' . 
									Formlet::join4XMLAttributes($finalAttrMap) . 
									' value="' . $i . '"' .
									(in_array($i, $sendData) ? ' checked' : '') .
									'>&nbsp;<label for="' . $finalAttrMap['id'] . '">' .
									$data[$i] . '</label>' . "\n";
					}

					return $out;
				} else {
					unset($attrMap['style']);

					// Render as (multi) select form input element
					$out = '<select ' . Formlet::join4XMLAttributes($finalAttrMap) 
								. ' multiple>' . "\n";
					for ($i = 0; $i < count($data); $i++) {
						$out .= '<option value="' . $i . '"' .
									(in_array($i, $sendData) ? ' selected' : '') .
									'>' . $data[$i] . '</option>' . "\n";
					}

					return $out . '</select>';
				}

			default:
				return '<em style="color: red;">Can\'t render "' . $tagName . '".</em>';
 		}
	}

	/**
	 * Fetches a parameter from the HTTP request
	 *
	 * @param $key The key of the value for the request parameters
	 * @param $returnArray Determines if the raw REQUEST array value
	 * should be returned or not
	 * @return Returns either a string from the request parameters
	 * or an array of integers (for single-/multi-select)
	 */
	function _get($key, $returnArray = false) { //: mixed
		// Overlay predefined values only if not submitted
		if (!$this->isSubmitted()) {
			if (isset($this->predefValues[$key])) {
				return $this->predefValues[$key];
			}
		}

		if (!isset($_REQUEST[$key])) {
			return NULL;
		}

		if ($returnArray && is_array($_REQUEST[$key])) {
			return $_REQUEST[$key];
		}

		if (is_array($_REQUEST[$key])) {
			$arr = array();
			for ($i = 0; $i < count($_REQUEST[$key]); $i++) {
				$arr[] = (int) $_REQUEST[$key][$i];
			}
			return $arr;
		}

		return $_REQUEST[$key];
	}

	/**
	 * Retrieves a language string. Therefore the function 
	 * <code>lang($key)</code> must exist. It takes a key and
	 * returns the language key. This function checks whether the
	 * given string is a language key (indicated by a ':' at the 
	 * first character) and looks it up if so. Otherwise the
	 * key is directly returned.
	 *
	 * @param $key The key for a language string
	 * @return The language string retrieved or the key
	 */
	function _lang($key) { //: mixed 
		$key = trim($key);
		if (strpos($key, ':') == 0 
				&& function_exists('lang')) {
			return lang(substr($key, 1));
		}
		return $key;
	}

	/**
	 * Extracts all attributes from a DOM element and returns
	 * them as an array
	 * 
	 * @param $e A DOM element object
	 * @return An array of mixed values
	 */
	static function domAttrMap2Array($e) { //: mixed[]
		$attrMap = array();
		for ($i = 0; $i < $e->attributes->length; $i++) {
			$attrMap[$e->attributes[$i]->name] = $e->attributes[$i]->value;
		}
		return $attrMap;
	}

	/**
 	 * Joins attributes, gatherd with e.g. domAttrMap2Array(...)
 	 * to a string of the XML attribute notation
 	 *
 	 * @param $arr An associative array of attributes
 	 * @return The joined attributes as string
	 */
	static function join4XMLAttributes($arr) { //: String
		$buf = '';
		foreach ($arr as $key => $value) {
			$buf .= $key . '="' . $value . '" ';
		}
		return trim($buf);
	}

	/**
	 * Creates a new instance of this class
	 *
	 * @param $file The layout file to load
	 * @return The Formlet object created
	 */
	static function create($file) { //: Formlet
		$f = new Formlet;
		$f->load($file);
		return $f;
	}

}

?>