<?php

/**
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2017 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter\Renderers;

use DOMNode;
use DOMXPath;
use RuntimeException;
use s9e\TextFormatter\Renderer;

abstract class PHP extends Renderer
{
	/**
	* @var array[] List of dictionaries used by the Quick renderer [[attrName => attrValue]]
	*/
	protected $attributes;

	/**
	* @var array Dictionary of replacements used by the Quick renderer [id => [match, replace]]
	*/
	protected $dynamic;

	/**
	* @var bool Whether to enable the Quick renderer
	*/
	public $enableQuickRenderer = false;

	/**
	* @var string Renderer's output
	*/
	protected $out;

	/**
	* @var array Branching table used by the Quick renderer [id => index]
	*/
	protected $quickBranches;

	/**
	* @var string Regexp that matches XML elements to be rendered by the quick renderer
	*/
	protected $quickRegexp = '((?!))';

	/**
	* @var string Regexp that matches nodes that SHOULD NOT be rendered by the quick renderer
	*/
	protected $quickRenderingTest = '(<[!?])';

	/**
	* @var array Dictionary of static replacements used by the Quick renderer [id => replacement]
	*/
	protected $static;

	/**
	* @var DOMXPath XPath object used to query the document being rendered
	*/
	protected $xpath;

	/**
	* Render given DOMNode
	*
	* @param  DOMNode $node
	* @return void
	*/
	abstract protected function renderNode(DOMNode $node);

	public function __sleep()
	{
		$this->reset();
		$props = get_object_vars($this);
		unset($props['dynamic']);
		unset($props['metaElementsRegexp']);
		unset($props['quickBranches']);
		unset($props['quickRegexp']);
		unset($props['quickRenderingTest']);
		unset($props['static']);

		return array_keys($props);
	}

	/**
	* Render the content of given node
	*
	* Matches the behaviour of an xsl:apply-templates element
	*
	* @param  DOMNode $root  Context node
	* @param  string  $query XPath query used to filter which child nodes to render
	* @return void
	*/
	protected function at(DOMNode $root, $query = null)
	{
		if ($root->nodeType === XML_TEXT_NODE)
		{
			// Text nodes are outputted directly
			$this->out .= htmlspecialchars($root->textContent,0);
		}
		else
		{
			$nodes = (isset($query)) ? $this->xpath->query($query, $root) : $root->childNodes;
			foreach ($nodes as $node)
			{
				$this->renderNode($node);
			}
		}
	}

	/**
	* Test whether given XML can be rendered with the Quick renderer
	*
	* @param  string $xml
	* @return bool
	*/
	protected function canQuickRender($xml)
	{
		return ($this->enableQuickRenderer && !preg_match($this->quickRenderingTest, $xml));
	}

	/**
	* Extract the text content from given XML
	*
	* NOTE: numeric character entities are decoded beforehand, we don't need to decode them here
	*
	* @param  string $xml Original XML
	* @return string      Text content, with special characters decoded
	*/
	protected function getQuickTextContent($xml)
	{
		return htmlspecialchars_decode(strip_tags($xml));
	}

	/**
	* Test whether given array has any non-null values
	*
	* @param  array $array
	* @return bool
	*/
	protected function hasNonNullValues(array $array)
	{
		foreach ($array as $v)
		{
			if (isset($v))
			{
				return true;
			}
		}

		return false;
	}

	/**
	* Capture and return the attributes of an XML element
	*
	* NOTE: XML character entities are left as-is
	*
	* @param  string $xml Element in XML form
	* @return array       Dictionary of [attrName => attrValue]
	*/
	protected function matchAttributes($xml)
	{
		if (strpos($xml, '="') === false)
		{
			return [];
		}

		// Match all name-value pairs until the first right bracket
		preg_match_all('(([^ =]++)="([^"]*))S', substr($xml, 0, strpos($xml, '>')), $m);

		return array_combine($m[1], $m[2]);
	}

	/**
	* Render an intermediate representation using the Quick renderer
	*
	* @param  string $xml Intermediate representation
	* @return void
	*/
	protected function renderQuick($xml)
	{
		$this->attributes = [];
		$xml = $this->decodeSMP($xml);
		$html = preg_replace_callback(
			$this->quickRegexp,
			[$this, 'renderQuickCallback'],
			preg_replace(
				'(<[eis]>[^<]*</[eis]>)',
				'',
				substr($xml, 1 + strpos($xml, '>'), -4)
			)
		);

		return str_replace('<br/>', '<br>', $html);
	}

	/**
	* Render a string matched by the Quick renderer
	*
	* This stub should be overwritten by generated renderers
	*
	* @param  string[] $m
	* @return void
	*/
	protected function renderQuickCallback(array $m)
	{
		if (isset($m[3]))
		{
			return $this->renderQuickSelfClosingTag($m);
		}

		$id = (isset($m[2])) ? $m[2] : $m[1];
		if (isset($this->static[$id]))
		{
			return $this->static[$id];
		}
		if (isset($this->dynamic[$id]))
		{
			return preg_replace($this->dynamic[$id][0], $this->dynamic[$id][1], $m[0], 1);
		}
		if (isset($this->quickBranches[$id]))
		{
			return $this->renderQuickTemplate($this->quickBranches[$id], $m[0]);
		}

		return '';
	}

	/**
	* Render a self-closing tag using the Quick renderer
	*
	* @param  string[] $m
	* @return string
	*/
	protected function renderQuickSelfClosingTag(array $m)
	{
		unset($m[3]);

		$m[0] = substr($m[0], 0, -2) . '>';
		$html = $this->renderQuickCallback($m);

		$m[0] = '</' . $m[2] . '>';
		$m[2] = '/' . $m[2];
		$html .= $this->renderQuickCallback($m);

		return $html;
	}

	/**
	* Render a string matched by the Quick renderer using a generated PHP template
	*
	* This stub should be overwritten by generated renderers
	*
	* @param  integer $qb  Template's index in the quick branch table
	* @param  string  $xml
	* @return string
	*/
	protected function renderQuickTemplate($qb, $xml)
	{
		throw new RuntimeException('Not implemented');
	}

	/**
	* {@inheritdoc}
	*/
	protected function renderRichText($xml)
	{
		try
		{
			if ($this->canQuickRender($xml))
			{
				return $this->renderQuick($xml);
			}
		}
		catch (RuntimeException $e)
		{
		}

		$dom         = $this->loadXML($xml);
		$this->out   = '';
		$this->xpath = new DOMXPath($dom);
		$this->at($dom->documentElement);
		$html        = $this->out;
		$this->reset();

		return $html;
	}

	/**
	* Reset object properties that are populated during rendering
	*
	* @return void
	*/
	protected function reset()
	{
		unset($this->attributes);
		unset($this->out);
		unset($this->xpath);
	}
}