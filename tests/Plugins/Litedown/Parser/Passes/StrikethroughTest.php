<?php

namespace s9e\TextFormatter\Tests\Plugins\Litedown\Parser\Passes;

/**
* @covers s9e\TextFormatter\Plugins\Litedown\Parser
* @covers s9e\TextFormatter\Plugins\Litedown\Parser\ParsedText;
* @covers s9e\TextFormatter\Plugins\Litedown\Parser\Passes\AbstractPass
* @covers s9e\TextFormatter\Plugins\Litedown\Parser\Passes\Strikethrough
*/
class StrikethroughTest extends AbstractTest
{
	public function getParsingTests()
	{
		return self::fixTests([
		]);
	}

	public function getRenderingTests()
	{
		return self::fixTests([
		]);
	}
}