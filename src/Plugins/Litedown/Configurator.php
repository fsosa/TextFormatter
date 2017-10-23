<?php

/**
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2017 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter\Plugins\Litedown;

use s9e\TextFormatter\Plugins\ConfiguratorBase;

class Configurator extends ConfiguratorBase
{
	/**
	* @var bool Whether to decode HTML entities in attribute values
	*/
	public $decodeHtmlEntities = false;

	/**
	* @var array Default tags
	*/
	protected $tags = [
		'C'      => '<code><xsl:apply-templates /></code>',
		'CODE'   => [
			'attributes' => [
				'lang' => [
					'filterChain' => ['#simpletext'],
					'required'    => false
				]
			],
			'template' =>
				'<pre>
					<code>
						<xsl:if test="@lang">
							<xsl:attribute name="class">
								<xsl:text>language-</xsl:text>
								<xsl:value-of select="@lang"/>
							</xsl:attribute>
						</xsl:if>
						<xsl:apply-templates />
					</code>
				</pre>'
		],
		'DEL'    => '<del><xsl:apply-templates/></del>',
		'EM'     => '<em><xsl:apply-templates/></em>',
		'H1'     => '<h1><xsl:apply-templates/></h1>',
		'H2'     => '<h2><xsl:apply-templates/></h2>',
		'H3'     => '<h3><xsl:apply-templates/></h3>',
		'H4'     => '<h4><xsl:apply-templates/></h4>',
		'H5'     => '<h5><xsl:apply-templates/></h5>',
		'H6'     => '<h6><xsl:apply-templates/></h6>',
		'HR'     => '<hr/>',
		'IMG'    => [
			'attributes' => [
				'alt'   => ['required' => false],
				'src'   => ['filterChain' => ['#url']],
				'title' => ['required' => false]
			],
			'template' => '<img src="{@src}"><xsl:copy-of select="@alt"/><xsl:copy-of select="@title"/></img>'
		],
		'LI'     => '<li><xsl:apply-templates/></li>',
		'LIST'   => [
			'attributes' => [
				'start' => [
					'filterChain' => ['#uint'],
					'required'    => false
				],
				'type' => [
					'filterChain' => ['#simpletext'],
					'required'    => false
				]
			],
			'template' =>
				'<xsl:choose>
					<xsl:when test="not(@type)">
						<ul><xsl:apply-templates/></ul>
					</xsl:when>
					<xsl:otherwise>
						<ol><xsl:copy-of select="@start"/><xsl:apply-templates/></ol>
					</xsl:otherwise>
				</xsl:choose>'
		],
		'QUOTE'  => '<blockquote><xsl:apply-templates/></blockquote>',
		'STRONG' => '<strong><xsl:apply-templates/></strong>',
		'SUP'    => '<sup><xsl:apply-templates/></sup>',
		'URL'    => [
			'attributes' => [
				'title' => [
					'required' => false
				],
				'url'   => [
					'filterChain' => ['#url']
				]
			],
			'template' => '<a href="{@url}"><xsl:copy-of select="@title"/><xsl:apply-templates/></a>'
		]
	];

	/**
	* {@inheritdoc}
	*/
	protected function setUp()
	{
		$this->configurator->rulesGenerator->append('ManageParagraphs');

		foreach ($this->tags as $tagName => $tagConfig)
		{
			// Skip this tag if it already exists
			if (isset($this->configurator->tags[$tagName]))
			{
				continue;
			}

			// If the tag's config is a single string, it's really its default template
			if (is_string($tagConfig))
			{
				$tagConfig = ['template' => $tagConfig];
			}

			// Replace default filters in the definition
			if (isset($tagConfig['attributes']))
			{
				foreach ($tagConfig['attributes'] as &$attributeConfig)
				{
					if (isset($attributeConfig['filterChain']))
					{
						foreach ($attributeConfig['filterChain'] as &$filter)
						{
							if (is_string($filter) && $filter[0] === '#')
							{
								$filter = $this->configurator->attributeFilters[$filter];
							}
						}
						unset($filter);
					}
				}
				unset($attributeConfig);
			}

			// Add this tag
			$this->configurator->tags->add($tagName, $tagConfig);
		}
	}

	/**
	* {@inheritdoc}
	*/
	public function asConfig()
	{
		return ['decodeHtmlEntities' => (bool) $this->decodeHtmlEntities];
	}

	/**
	* {@inheritdoc}
	*/
	public function getJSHints()
	{
		return ['LITEDOWN_DECODE_HTML_ENTITIES' => (int) $this->decodeHtmlEntities];
	}

	/**
	* {@inheritdoc}
	*/
	public function getJSParser()
	{
		$js = file_get_contents(__DIR__ . '/Parser/ParsedText.js') . "\n"
		    . file_get_contents(__DIR__ . '/Parser/LinkAttributesSetter.js');

		$passes = [
			'Blocks',
			'LinkReferences',
			'InlineCode',
			'Images',
			'Links',
			'Strikethrough',
			'Superscript',
			'Emphasis',
			'ForcedLineBreaks'
		];
		foreach ($passes as $pass)
		{
			$js .= "\n(function(){\n"
			     . file_get_contents(__DIR__ . '/Parser/Passes/' . $pass . '.js') . "\n"
			     . "parse();\n"
			     . "})();";
		}

		return $js;
	}
}