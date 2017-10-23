<?php

/**
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2017 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter\Plugins\Litedown\Parser\Passes;

class Strikethrough extends AbstractPass
{
	/**
	* {@inheritdoc}
	*/
	public function parse()
	{
		$pos = $this->text->indexOf('~~');
		if ($pos === false)
		{
			return;
		}

		preg_match_all(
			'/~~[^\\x17]+?~~/',
			$this->text,
			$matches,
			PREG_OFFSET_CAPTURE,
			$pos
		);
		foreach ($matches[0] as list($match, $matchPos))
		{
			$matchLen = strlen($match);

			$this->parser->addTagPair('DEL', $matchPos, 2, $matchPos + $matchLen - 2, 2);
		}
	}
}