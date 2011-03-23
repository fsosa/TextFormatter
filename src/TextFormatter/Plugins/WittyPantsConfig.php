<?php

/**
* @package   s9e\Toolkit
* @copyright Copyright (c) 2010 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Toolkit\TextFormatter\Plugins;

use s9e\Toolkit\TextFormatter\ConfigBuilder,
    s9e\Toolkit\TextFormatter\PluginConfig;

/**
* This plugin combines some of the functionalities found in SmartyPants and Textile.
*
* @link http://daringfireball.net/projects/smartypants/
* @link http://textile.thresholdstate.com/
*/
class WittyPantsConfig extends PluginConfig
{
	/**
	* @var string Name of the tag used to mark the text to replace
	*/
	protected $tagName = 'WP';

	/**
	* @var string Name of attribute used to for the replacement
	*/
	protected $attrName = 'char';

	public function setUp()
	{
		$this->cb->addTag($this->tagName);
		$this->cb->addTagAttribute($this->tagName, $this->attrName, 'text');
		$this->cb->setTagTemplate($this->tagName, '<xsl:value-of select="@' . $this->attrName . '"/>');
	}

	public function getConfig()
	{
		return array(
			'tagName'  => $this->tagName,
			'attrName' => $this->attrName,

			'regexp' => array(
				'singletons' => "#(?:---?|\\.\\.\\.)#S",
				'quotation'  => '#(?<!=)(["\'])(?:.+?)(?<!=)\\1#S',
				'symbols'    => '#\\((?:tm|r|c)\\)#i'
			),

			'replacements' => array(
				'singletons' => array(
					'--'  => "\xE2\x80\x93",
					'---' => "\xE2\x80\x94",
					'...' => "\xE2\x80\xA6"
				),
				'quotation' => array(
					"'" => array("\xE2\x80\x98", "\xE2\x80\x99"),
					'"' => array("\xE2\x80\x9C", "\xE2\x80\x9D")
				),
				'symbols' => array(
					'(tm)' => "\xE2\x84\xA2",
					'(r)'  => "\xC2\xAE",
					'(c)'  => "\xC2\xA9"
				)
			)
		);
	}
}